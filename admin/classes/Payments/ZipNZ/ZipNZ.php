<?php
	namespace Payments\ZipNZ;

	use Controller\RedirectController;
	use Error;
	use Exception;
	use JsonSerializable;
	use Orders\OrderPayment;
	use Payments\BillPayments\BillPayment;
	use Payments\PaymentGateway;
	use Payments\PaymentResult;
	use stdClass;
	
	/**
	 * Handles requests to Zip NZ (was PartPay)
	 *
	 * Zip integrations require sign-off by Zip before going live
	 * @see https://zip.co/nz/integration-best-practice-requirements/ for additional requirements for site
	 * @see https://zip.co/nz/custom-via-api/ for certification checklist
	 * @see https://docs-nz.zip.co/merchant-api/ for the API docs
	 * @see https://docs-nz.zip.co/merchant-api/testing for how to use the sandbox for testing
	 */
	class ZipNZ extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging
		/** @var string API endpoint to use */
		private const API_ENDPOINT = self::TEST_MODE ? 'https://sandbox.zip.co/nz/api/' : 'https://zip.co/nz/api/';

		// need to get these from client or client's Zip contact
		/** @var string API authentication details */
		const CLIENT_ID = self::TEST_MODE ? '' : '';
		const CLIENT_SECRET = self::TEST_MODE ?  '' : '';
		const AUTHENTICATION_ENDPOINT = self::TEST_MODE ?  'https://merchant-auth-nz.sandbox.zip.co/oauth/token' : 'https://merchant-auth-nz.zip.co/oauth/token';
		const AUTHENTICATION_AUDIENCE = self::TEST_MODE ? 'https://auth-dev.partpay.co.nz' : 'https://auth.partpay.co.nz';

		/** @var string location of the local cache for authentication details */
		const TOKEN_PATH = __DIR__ . '/' . (self::TEST_MODE ? 'test-' : '') . 'zip-token.json';

		/** @var stdClass|null */
		private static $accessToken = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Retrieves an access token from Zip
		 * @see		https://docs-nz.zip.co/#authentication
		 * @return	stdClass	The access token object
		 */
		private static function authenticate()
		{
			$token = self::request(static::AUTHENTICATION_ENDPOINT, 'POST',
			[
				'client_id' => static::CLIENT_ID,
				'client_secret' => static:: CLIENT_SECRET,
				'audience' => static::AUTHENTICATION_AUDIENCE,
				'grant_type' => 'client_credentials'
			]);

			$token->expires_at = time() + $token->expires_in;

			file_put_contents(static::TOKEN_PATH, json_encode($token, JSON_FORCE_OBJECT));
			return $token;
		}

		/**
		 * Zip has method for getting the merchant details which include these limits but it has been deprecated
		 * @see https://docs-nz.zip.co/merchant-api/api-reference/configuration/merchantconfigurationv1-getasync
		 * @param float $orderValue value of the order
		 * @return bool  is this gateway available given this order value?
		 */
		public static function availableForOrderValue($orderValue)
		{
			return true;
		}

		/**
		 * Captures the payment for a Authorised order (untested as we only create 'payment' orders at this stage)
		 * @see	https://docs-nz.zip.co/merchant-api/api-reference/order/capturepayment
		 * @param	string		$orderId	The identifier for the order
		 * @return	stdClass	Order details
		 */
		private static function capturePayment($orderId)
		{
			return self::request('order/' . $orderId . '/capture', 'POST');
		}

		/**
		 * Creates an order
		 * @see		https://docs-nz.zip.co/merchant-api/api-reference/order/createorder
		 * @param	Order	$order	The order to create
		 * @return	stdClass	Order details
		 */
		private static function createOrder(Order $order)
		{
			return self::request('order', 'POST', $order);
		}

		/**
		 * Retrieves an order from the server
		 * @see		https://docs-nz.zip.co/#get-order
		 * @param	string		$orderId	The identifier for the order
		 * @return	stdClass	Order details
		 */
		public static function getOrder($orderId)
		{
			return self::request('order/' . $orderId, 'GET');
		}

		/**
		 * Gets a current Bearer token for API calls
		 * @return string the token
		 */
		private static function getToken()
		{
			if(self::$accessToken === null)
			{
				if(is_file(static::TOKEN_PATH))
				{
					$token = json_decode(file_get_contents(static::TOKEN_PATH));
					if($token === null) // not valid json
					{
						$token = self::authenticate();
					}
				}
				else
				{
					$token = self::authenticate();
				}

				self::$accessToken = $token;
			}

			if(self::$accessToken->expires_at <= time())
			{
				self::$accessToken = self::authenticate();
			}

			return self::$accessToken->access_token;
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return 'Zip';
		}

		/**
		 * Zip doesn't expose their installment calcuation, so we're approximating it
		 *
		 * @param float $total
		 * @return float probabaly slightly under 1/4th of the value
		 */
		public static function getInstallment($total)
		{
			// always rounding to the lowest cent;
			return formatPrice(round(($total / 4) - 0.005 ,2));
		}

		/**
		 * Gets a label to describe this payment gateway to the user after purchase.
		 * @param 	float 	$total 	Order total
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getPaymentLabel($total = null)
		{
			return 'Zip | four payments from ' . static::getInstallment($total);
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	Rather than assuming the global cart is the correct
		 * 		one at the time this method is called better to pass in the total
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return '<img src="https://static.zipmoney.com.au/assets/default/footer-tile/footer-tile-new.png" style="height:30px;" alt="Zip /> | four payments from ' . static::getInstallment($total);
		}

		/**
		 * Refunds an order (untested as our admin panel doesn't incorporate refund functionality)
		 * @see		https://docs-nz.zip.co/merchant-api/api-reference/order/createrefund
		 * @param	string	$orderId	The identifier for the order
		 */
		private static function refund($orderId)
		{
			return self::request('order/' . $orderId . '/refund', 'POST');
		}

		/**
		 * Sends a request to the URL
		 * @param	string					$path			The endpoint to make the request to
		 * @param	string					$type			The type of request to make
		 * @param	JsonSerializable		$data			The data to send to that URL
		 * @return	stdClass								The response
		 * @throws	ZipException							If something goes wrong with the request
		 */
		private static function request($path, $type = 'GET', $data = null)
		{
			$headers = [];
			$responseHeaders = [];
			$query = '';
			$postFields = '';

			if($path !== static::AUTHENTICATION_ENDPOINT)
			{
				$headers[] = 'Accept: application/json';
				$headers[] = 'Authorization: Bearer ' . self::getToken();
				$path = self::API_ENDPOINT . trim($path, '/');
			}
			else
			{
				$path = trim($path, '/');
			}

			if($type === 'GET' && !empty($data))
			{
				$query = '?' . http_build_query($data, null, '&', PHP_QUERY_RFC3986);
			}

			$curl = curl_init($path . $query);

			if($type !== 'GET' && !empty($data))
			{
				$postFields = json_encode($data, JSON_FORCE_OBJECT);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
				$headers[] = 'Content-Type: application/json';
			}

			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

			$result = curl_exec($curl);
			$responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
			$resultData = json_decode($result);

			// Zip certification requires logging all requests at our end
			file_put_contents(static::getLogDestination(), [date('Y-m-d H:i:s'), "Endpoint: {$path}{$query}\\n", "POST: {$postFields}\n", " Response: {$responseCode} {$result}\n\n"], FILE_APPEND);

			// $responseCode is the http response code - anything 300+ is not OK
			if($responseCode > 299)
			{
				if(!is_object($resultData))
				{
					$resultData = (object) [];
				}

				if(isset($resultData->error->message))
				{
					$message = $resultData->error->message;
				}
				else if(isset($resultData->error->error_description))
				{
					$message = $resultData->error->error_description;
				}
				else
				{
					$message = 'An unknown error has occurred';
				}

				if(isset($resultData->errorCode))
				{
					$errorCode = $resultData->errorCode;
				}
				else if(isset($resultData->error))
				{
					$errorCode = $resultData->error;
				}
				else
				{
					$errorCode = 'GenericError';
				}

				$isValid = isset($resultData->isValid) ? $resultData->isValid : false;
				$errors = isset($resultData->errors) ? $resultData->errors : [];

				throw new ZipException($message, $errorCode, $isValid, $errors);
			}

			return $resultData;
		}

		/**
		 * Updates the merchant reference in an order (untested)
		 * @see		https://docs-nz.zip.co/merchant-api/api-reference/order/changemerchantorderreference
		 * @param	string	$orderId			The identifier for the order
		 * @param	string	$merchantReference	The new merchant reference
		 */
		public static function updateMerchantReference($orderId, $merchantReference)
		{
			return self::request('order/' . $orderId . '/merchantReference', 'PUT', ['merchantReference' => $merchantReference]);
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    RedirectController    The controller that will handle the user
		 * @throws    Exception            If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$payment = $this->payment;
			// Zip would also like us to pass along more information on the user including billing and delivery addresses but until they actually start enforcing that we send the minimum necessary.
			if($payment instanceof OrderPayment)
			{
				$order = new Order($payment->amount, $payment->order->reference);
				$order->consumer = new Consumer($payment->order->getOrderDetailsBillingAddress()->email);
				$order->description = $payment->order->getDescription();
			}
			elseif($payment instanceof BillPayment)
			{
				// untested as we haven't had a site which combines Bill Payments and Zip
				throw new Error(get_called_class() . ' support for for Bill Payments has not been tested. Congratulations Developer, you get to remove this Error and do that.');
				/** @noinspection PhpUnreachableStatementInspection */
				$order = new Order($payment->amount, $payment->reference);
				$order->consumer = new Consumer($payment->email);
			}
			else
			{
				throw new Exception(self::getClassIdentifier() . ' gateway implementation does not support ' . get_class($payment));
			}

			$order->merchant = new Merchant(
									$this->getSuccessUrl(),
									$this->getSuccessUrl(), // failure url, but we need to make sure is passed to getResult() to handle Zip status messages
									$this->getNotificationUrl()
								);

			$createdOrder = self::createOrder($order);
			$this->payment->remoteReference = $createdOrder->orderId; // saves with order in CartController

			return new RedirectController($createdOrder->redirectUrl);
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			$order = static::getOrder($this->payment->remoteReference);

			// @see https://docs-nz.zip.co/merchant-api/api-reference/order/getorderbyid for list of statuses
			switch($order->orderStatus)
			{
				case 'Approved':
					return new PaymentResult(true);

				case 'Authorised': // untested
					$this->capturePayment($this->payment->remoteReference);
					return new PaymentResult(true);

				case 'Abandoned':
				case 'Created': // untested
				case 'Expired':
					return new PaymentResult(false, 'Payment was cancelled or timed out. Please try again or select a different method of payment.');

				case 'Declined':
					return new PaymentResult(false, 'Zip declined your application. Please select a different method of payment.');
					// possible enhancement: some sort of flag remove zip from the list of available payment gateways for the duration of the order

				default:
					return new PaymentResult(false, 'Something went wrong, we\'re not sure what. Please try again.');
			}
		}
	}