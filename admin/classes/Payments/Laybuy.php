<?php
	namespace Payments;

	use Controller\RedirectController;
	use Error;
	use Exception;
	use Orders\OrderPayment;
	use Payments\BillPayments\BillPayment;
	
	/**
	 * Laybuy gateway class
	 *
	 * @see https://afterpay-online.readme.io/reference
	 */
	class Laybuy extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging

		private const API = self::TEST_MODE ? 'https://sandbox-api.laybuy.com/' : 'https://api.laybuy.com/';
		// Our sandbox or client's live details
		private const API_KEY = self::TEST_MODE ? 'NA2pg4Gag3G24hnB0God1AaVcSDeGdzTvsyU61S9BnBh0ptvH1C1a7Sj8mnFyGl7' : '';
		private const MERCHANT_ID = self::TEST_MODE ? 100755 : '';

		private const CURRENCY = 'NZD';

		/*~~~~~
		 * static methods
		 **/
		/**
		 * some payment gateways (eg AfterPay, GenoaPay) set minimum or maximum limits on the value of order which can be paid through them
		 * These may vary between different merchant accounts and may require the merchant /not/ to present the gateway as an option if the order total falls outside these limits
		 * @param float $orderValue value of the order
		 * @return bool  is this gateway available given this order value?
		 */
		public static function availableForOrderValue($orderValue)
		{
			// there are limits, we're just not sure what they are or if they are API accessible yet
			return true;

			// return $orderValue >= static::getMinimumValue() && $orderValue <= static::getMaximumValue();
		}

		/**
		 * place a GET request to an API endpoint
		 * @param string $endpoint
		 * @return array the decoded response
		 * @throw Exception if the connection fails
		 */
		protected static function get($endpoint)
		{
			$auth = base64_encode( self::MERCHANT_ID . ":" . self::API_KEY );

			$header = [
				"Authorization: Basic " . $auth,
				"Content-Type: application/json",
				"Accept: application/json"
			];

			$curl = curl_init( self::API . $endpoint );

			curl_setopt_array($curl,
			[
				CURLOPT_CAINFO => DOC_ROOT . "/admin/ca-bundle.crt",
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTPHEADER => $header,
				CURLOPT_HEADER => 0,
				CURLOPT_POST => 0,
				CURLOPT_FOLLOWLOCATION => 0,
				CURLOPT_RETURNTRANSFER => 1
			]);

			$response = curl_exec($curl);

			return json_decode($response, true);
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "Laybuy";
		}

		/**
		 * Laybuy doesn't expose their installment calcuation, so we're approximating it
		 *
		 * @param float $total
		 * @return float probabaly slightly under 1/6th of the value
		 */
		public static function getInstallment($total)
		{
			// always rounding to the lowest cent;
			return formatPrice(round(($total / 6) - 0.005 ,2));
		}

		/**
		 * @return float|string the maximum order value which the merchant is allowed to submit to Laybuy
		 */
		public static function getMaximumValue()
		{
			// Laybuy "currently don't have a minimum or maximum limit" (Laybuy Support)
			// but based on other part-payment gateways they may well implement one in the future
			// so support for conditional display of Laybuy option at checkout
			// returning very large value to always pass
			return 10000;
		}

		/**
		 * @return float|string the minimum order value which the merchant is allowed to submit to Laybuy
		 */
		public static function getMinimumValue()
		{
			// Laybuy "currently don't have a minimum or maximum limit" (Laybuy Support)
			// but based on other part-payment gateways they may well implement one in the future
			// so support for conditional display of Laybuy option at checkout
			// returning 0 to always pass
			return 0;
		}

		/**
		 * Gets a label to describe this payment gateway to the user after purchase.
		 * For gateways which do installments the amount and period of installments
		 * should be included in this label, without all the signup information
		 * typically displayed at checkout.
		 * Payments should store a snapshot of this value
		 *
		 * @param 	float 	$total 	Order total
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getPaymentLabel($total = null)
		{
			return ' Laybuy - Six weekly payments from ' . static::getInstallment($total);
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	Order total to generate installments from
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return '<span class="laybuy-logo">Laybuy</span> <div class="payment-gateway-text layby-text"><p><b>Six easy weekly payments from ' . self::getInstallment($total) . ' each.</b></p></div>';
		}

		/**
		 * place a POST request to an API endpoint
		 * @param string $endpoint
		 * @param string[] $data request payload
		 * @return array the decoded response
		 * @throw Exception if the connection fails
		 */
		protected static function post($endpoint, array $data)
		{
			$auth = base64_encode( self::MERCHANT_ID . ":" . self::API_KEY );

			$header = [
				"Content-Type: application/json",
				"Authorization: Basic " . $auth,
				"Accept: application/json"
			];

			$curl = curl_init( self::API . $endpoint );

			curl_setopt_array($curl,
			[
				CURLOPT_CAINFO => DOC_ROOT . "/admin/ca-bundle.crt",
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTPHEADER => $header,
				CURLOPT_HEADER => 0,
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => json_encode($data),
				CURLOPT_FOLLOWLOCATION => 0,
				CURLOPT_RETURNTRANSFER => 1
			]);

			$response = curl_exec($curl);

			return json_decode($response, true);
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 *
		 * @return	RedirectController	The controller that will handle the user
		 * @throws	Exception			If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$payment = $this->payment;

			if($payment instanceof OrderPayment)
			{
				$order = $payment->order;

				// prior validation should see name being longer than an empty string
				$names = explode(' ', $order->billingAddress->name);

				if(count($names) > 1)
				{
					$surname = array_pop($names);
					$givenNames = implode(' ', $names);
				}
				else
				{
					$surname = '[Not supplied]';
					$givenNames = $names[0];
				}

				// only including required fields; sharing as little information as possible
				$parameters =
				[
					'amount' => number_format($order->getOrderDetailsTotal(), 2)
					, 'currency' => self::CURRENCY
					, 'returnUrl' => $this->getSuccessUrl() // we only get one return url but success or failure is checked at the success url anyway
					, 'merchantReference' => $payment->localReference
					// , 'tax' => $order->getTax() // documentation says required, API is happy without
					, 'customer' => [
						'firstName' => $givenNames
						, 'lastName' => $surname
						, 'email' => $order->billingAddress->email
						, 'phone' => $order->billingAddress->phone
					]
					, 'items' => []
				];

				foreach($order->lineItems as $item)
				{
					$generator = $item->getGenerator();

					$id = $generator->getLineItemGeneratorIdentifier() || $item->title;
					$parameters['items'][] = [
						'id' => $id
						, 'description' => $item->title
						, 'quantity' => $item->quantity
						, 'price' => $item->price
					];
				}
			}
			elseif($payment instanceof BillPayment)
			{
				// untested as we haven't had a site which combines Bill Payments and LayBuy
				throw new Error(get_called_class() . ' requires different handling for Bill Payments and this has not been tested. Congratulations Developer, you get to remove this Error and do that.');

				// prior validation should see name being longer than an empty string
				/** @noinspection PhpUnreachableStatementInspection */
				$names = explode(' ', $payment->name);

				if(count($names) > 1)
				{
					$surname = array_pop($names);
					$givenNames = implode(' ', $names);
				}
				else
				{
					$surname = '[Not supplied]';
					$givenNames = $names[0];
				}

				// only including required fields; sharing as little information as possible
				$parameters =
				[
					'amount' => number_format($payment->amount, 2)
					, 'currency' => self::CURRENCY
					, 'returnUrl' => $this->getSuccessUrl() // we only get one return url but success or failure is checked at the success url anyway
					, 'merchantReference' => $payment->localReference
					// , 'tax' => $order->getTax() // documentation says required, API is happy without
					, 'customer' => [
						'firstName' => $givenNames
						, 'lastName' => $surname
						, 'email' => $payment->email
						, 'phone' => $payment->phone
					]
					, 'items' => [
						[
							'id' => $payment->localReference
							, 'description' => $payment->label
							, 'quantity' => 1
							, 'price' => $payment->amount
						]
					]
				];
			}
			else
			{
				throw new Exception(self::getClassIdentifier() . ' gateway implementation does not support ' . get_class($this->payment));
			}

			$response = self::post('order/create', $parameters);

			if(isset($response['token']))
			{
				$this->payment->remoteReference = $response['token'];
				$this->payment->save();

				return new RedirectController($response['paymentUrl']);
			}
			elseif(isset($response['error']))
			{
				throw new Exception('Error: ' . $response['error']);
			}
			else
			{
				throw new Exception('Error: ' . print_r($response, true));
			}
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			$data = static::post('order/confirm', ['token' => $this->payment->remoteReference]);

			if(isset($data["result"]) && $data['result'] === 'SUCCESS')
			{
				return new PaymentResult(true);
			}
			elseif(isset($data['error']))
			{
				// error from API
				return new PaymentResult(false, $data["error"]);
			}
			else
			{
				return new PaymentResult(false, "Error: " . $data["Message"]);
			}
		}
	}