<?php
	namespace Payments;

	use Controller\RedirectController;
	use DateTimeImmutable;
	use Error;
	use Exception;
	use Orders\OrderPayment;
	use Payments\BillPayments\BillPayment;
	
	/**
	 * GenoaPay gateway class
	 *
	 * @see https://www.genoapay.com/api-doc-v3/
	 */
	class GenoaPay extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging

		private const API = self::TEST_MODE ? 'https://api.uat.genoapay.com/v3/' : 'https://api.genoapay.com/v3/';
		// Client's sandbox & live details
		private const API_KEY = self::TEST_MODE ? '' : '';
		private const API_SECRET = self::TEST_MODE ? '' : '';

		private const CONTENT_TYPE = 'application/com.genoapay.ecom-v3.0+json';
		private const CURRENCY = "NZD";

		protected static $configuration = null;

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Recursively converts arrays into HMAC string format
		 *
		 * @return string
		 */
		private static function arrayToHmacString(array $array)
		{
			$string = '';
			foreach($array as $key => $value)
			{
				if(is_array($value))
				{
					$value = self::arrayToHmacString($value);
				}
				elseif(is_bool($value))
				{
					$value = $value ? 'true' : 'false';
				}

				$string .= (is_string($key) ? $key : '') . (is_array($value) ?  : (string) $value);
			}

			return $string;
		}

		/**
		 * some payment gateways (eg AfterPay, GenoaPay) set minimum or maximum limits on the value of order which can be paid through them
		 * These may vary between different merchant accounts and may require the merchant /not/ to present the gateway as an option if the order total falls outside these limits
		 * @param float $orderValue value of the order
		 * @return bool  is this gateway available given this order value?
		 */
		public static function availableForOrderValue($orderValue)
		{
			return $orderValue >= static::getMinimumValue() && $orderValue <= static::getMaximumValue();
		}

		/**
		 * check that the HMAC signature matches the rest of the data in the query string
		 * @see https://s3-ap-southeast-2.amazonaws.com/genoapay-public-assets/Online+API+Signing+Mechanisms.pdf
		 *
		 * @return bool do the two match
		 */
		protected static function confirmHmacSignature()
		{
			//san check
			if(!isset($_GET['signature']))
			{
				return false;
			}

			$signature = null;
			$string = '';
			// "Unescape the parameters and remove query parameter delimiters"
			// easiest just to create the string from $_GET
			foreach($_GET as $param => $value)
			{
				if($param === 'signature')
				{
					$signature = $value;
					// should always be the last one, but just in case
					continue;
				}
				// else
				// pretty sure values are already url_decoded
				$string .= $param . $value;
			}

			// "Strip any white space from the resulting string"
			// "Base 64 encode the result"
			$string = base64_encode(preg_replace('/\s/', '', $string));

			// "Compute the HMAC using SHA256 digest algorithm"
			$result = (hash_hmac("sha256", $string, self::API_SECRET) === $signature);

			return $result;
		}

		/**
		 * Generates the HMAC string, for security reasons
		 * @see https://s3-ap-southeast-2.amazonaws.com/genoapay-public-assets/Online+API+Signing+Mechanisms.pdf
		 *
		 * @param	string[]	$data	The data to generate the HMAC string from
		 * @return	string		The HMAC parameters
		 */
		protected static function generateHmacParameter(array $data, $secret = null)
		{
			$string = static::stripJson(stripslashes(json_encode($data, JSON_UNESCAPED_SLASHES)));
			return hash_hmac("sha256", base64_encode($string), $secret ?? self::API_SECRET);
		}

		/**
		 * place a GET request to an API endpoint
		 * @param string $endpoint
		 * @param string[] $headers any custom headers which are required for a particular call
		 * @return array the decoded response
		 * @throw Exception if the connection fails
		 */
		protected static function get($endpoint, $headers = [])
		{
			$logFile = static::getLogDestination();
			$allHeaders = self::getHeaders($headers);

			$curl = curl_init( self::API . $endpoint );

			curl_setopt_array($curl,
			[
				CURLOPT_CAINFO => DOC_ROOT . "/admin/ca-bundle.crt",
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTPHEADER => $allHeaders,
				CURLOPT_HEADER => 0,
				CURLOPT_POST => 0,
				CURLOPT_FOLLOWLOCATION => 0,
				CURLOPT_RETURNTRANSFER => 1
			]);

			$response = curl_exec($curl);

			if($response === false)
			{
				throw new Exception(print_r(curl_getinfo($curl, true)));
			}
			else
			{
				return json_decode($response, true);
			}
		}

		/**
		 * Get an Auth token which is used as identification for subsequent calls
		 *
		 * @return string the token
		 * @throws Exception if a token cannot be retrieved
		 */
		private static function getAuthToken()
		{
			$filename = self::getAuthTokenStore();

			// json_decode() will return null if parsing the file fails
			$fileContents = file_exists($filename) ? json_decode(file_get_contents($filename), true) : null;

			if($fileContents === null)
			{
				$auth = base64_encode(self::API_KEY . ":" . self::API_SECRET);
				$response = self::post('token', [], ['Authorization: Basic ' . $auth]);
				if(isset($response['authToken']) && isset($response['expiryDate']))
				{
					$filePlaced = file_put_contents($filename, json_encode($response));
					// now that we have a file, try again
					return self::getAuthToken();
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
			// still here?

			$expiry = new DateTimeImmutable($fileContents['expiryDate']);
			$renew = new DateTimeImmutable('+10 second');

			if($expiry < $renew || $fileContents['authToken'] === '')
			{
				// time to get a new token
				unlink($filename);
				return self::getAuthToken();
			}
			else
			{
				return $fileContents['authToken'];
			}
		}

		/**
		 * gets the location at which the AuthToken is cached locally
		 * @return string a file path
		 */
		private static function getAuthTokenStore()
		{
			// including the API key in here distinguishes between Sandbox and Live stores
			return __DIR__ . '/GenoaPayAuth-' . self::API_KEY . '.json';
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return 'GenoaPay';
		}

		/**
		 * @return string[] the configuration model from the API, which contains information on minimum and maximum values
		 * @throw Exception if connection to the API fails
		 */
		public static function getConfiguration($totalAmount = null)
		{
			if(static::$configuration === null)
			{
				$filename = self::getConfigurationStore();

				// we don't want to be getting the configuration from the API every time availableForOrderValue() is called
				// json_decode() will return null if parsing the file fails
				$configuration = file_exists($filename) && filemtime($filename) > strtotime('-1 day') ? json_decode(file_get_contents($filename), true) : null;

				if($configuration === null)
				{
					$configuration = self::get('configuration');

					if(isset($configuration['name']))
					{
						file_put_contents($filename, json_encode($configuration));
					}
					elseif(isset($configuration['error']))
					{
						throw new Exception('Error: ' . $configuration['error']);
					}
					else
					{
						throw new Exception('Error: ' . print_r($configuration, true));
					}
				}

				static::$configuration = $configuration;
			}

			return static::$configuration;
		}

		/**
		 * gets the location at which the configuration is cached locally
		 * @return string a file path
		 */
		private static function getConfigurationStore()
		{
			// including the API key in here distinguishes between Sandbox and Live stores
			return __DIR__ . '/GenoaPayConfig-' . self::API_KEY . '.json';
		}

		/**
		 * add the required headers for every API call to any custom headers
		 * @param string[] $headers any custom headers which are required for a particular call
		 *		- practically this is only used when getting the auth token @see getAuthToken()
		 * @return string[] all required headers
		 */
		private static function getHeaders($headers = [])
		{
			if(empty($headers))
			{
				$headers = ["Authorization: Bearer " . self::getAuthToken()];
			}

			$headers[] = "Content-Type: " . self::CONTENT_TYPE;
			$headers[] = "Accept: " . self::CONTENT_TYPE;

			return $headers;
		}

		/**
		 * @return float|string the maximum order value which the merchant is allowed to submit to GenoaPay
		 */
		public static function getMaximumValue()
		{
			return self::getConfiguration()['maximumAmount'];
		}

		/**
		 * @return float|string the minimum order value which the merchant is allowed to submit to GenoaPay
		 */
		public static function getMinimumValue()
		{
			return self::getConfiguration()['minimumAmount'];
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
			$text = self::getPayments($total);
			$matches = [];
			preg_match('/\$.*?weeks/', $text, $matches);

			return 'GenoaPay - ' . $matches[0];
		}

		/**
		 * "If the totalAmount parameter is provided, the description field will contain a customized response detailing
		 * the weekly instalment amount, which can be displayed on the cart page."
		 *
		 * @param float $total invoice total
		 *
		 * @return string text to display
		 */
		public static function getPayments($total)
		{
			return self::get('configuration?totalAmount='.$total)['description'];
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	Order total to generate installments from
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return '<span class="genoapay-logo">GenoaPay</span> <div class="payment-gateway-text genoapay-text">' . self::getPayments($total) . '</div>';
		}

		/**
		 * place a POST request to an API endpoint
		 * @param string $endpoint
		 * @param string[] $data request payload
		 * @param string[] $headers any custom headers which are required for a particular call
		 *		- practically this is only used when getting the auth token @see getAuthToken()
		 * @return array the decoded response
		 * @throw Exception if the connection fails
		 */
		protected static function post($endpoint, array $data = [], array $headers = [])
		{
			$allHeaders = self::getHeaders($headers);
			$hmac = self::generateHmacParameter($data);
			$payload = json_encode($data, JSON_UNESCAPED_SLASHES);

			$curl = curl_init( self::API . $endpoint . '?signature=' . $hmac	);

			// logging curl errors by default
			$logFile = static::getLogDestination();
			$fh = fopen($logFile, 'a');

			curl_setopt_array($curl,
			[
				CURLOPT_CAINFO => DOC_ROOT . "/admin/ca-bundle.crt",
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTPHEADER => $allHeaders,
				CURLOPT_HEADER => 0,
				//  CURLOPT_HEADER_OUT  => 1, // debugging
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => $payload,
				CURLOPT_FOLLOWLOCATION => 0,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_VERBOSE => 1,
				CURLOPT_STDERR => $fh,
				// CURLOPT_WRITEHEADER => $fh // debugging
			]);

			$response = curl_exec($curl);

			fclose($fh);

			if($response === false)
			{
				throw new Exception(print_r(curl_getinfo($curl, true)));
			}
			// still here?

			$decoded = json_decode($response, true);

			// sometimes they just send straight up error text ....
			if($decoded === null)
			{
				throw new Exception($response);
			}
			// still here?

			return $decoded;
		}

		/**
		 * process json into hmac string
		 * copied from https://jsfiddle.net/xpvt214o/681907/
 		 * @see https://s3-ap-southeast-2.amazonaws.com/genoapay-public-assets/Online+API+Signing+Mechanisms.pdf
		 *
		 */
		protected static function stripJson($json)
		{
			$progress = '';
			$len = strlen($json);

			// inc for every non escaped quote

			$counter = 0;

			for ($i = 0; $i < $len; $i++)
			{
				$c = substr($json, $i, 1);

				// inc the counter for each non escaped quote. When counter is even, we're processing JSON characters, when odd, we're processing a key or value.
				// With the exception of ints and floats, where the counter will be even
				if ($c == '"' && $i > 0 && substr($json, $i-1, 1) != '\\')
				{
					$counter++;
				}

				// unconditionally remove spaces
				if (preg_match('/\s/', $c) )
				{
					continue;
				}

				// if processing JSON, and it's a JSON char, don't include the char
				if (($counter % 2 == 0 && in_array($c, ['"', '{', '}', ':', "\n", "\r", ',', '[', ']'])))
				{
					continue;
				}

				// append the character as long as it is not a double quote, or if it is an escaped double quote
				if ($c != '"' || substr($json, $i-1, 1) == '\\')
				{
					$progress .= $c;
				}
			}

			return $progress;
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

				// sharing as little information as possible
				// unfortunately API documentation doesn't indicate what fields if any are required
				$parameters = [
					'customer' => [
						'firstName' => $givenNames
						, 'surname' => $surname
						, 'email' => $order->billingAddress->email
						, 'mobileNumber' => $order->billingAddress->phone
					]
					, 'reference' => $payment->localReference
					, 'products' =>  [
						[
							'name' => $order->getDescription()
							, 'price' => [
								'amount' => $order->getTotal()
								, 'currency' => self::CURRENCY
								]
							, 'quantity' => 1
						]
					]
					, 'totalAmount' => [
						'amount' => $order->getOrderDetailsTotal()
						, 'currency' => self::CURRENCY
					]
				];
			}
			elseif($payment instanceof BillPayment)
			{
				// untested as we haven't had a site which combines Bill Payments and GenoaPay
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

				$parameters = [
					'customer' => [
						'firstName' => $givenNames
						, 'surname' => $surname
						, 'email' => $payment->email
						, 'mobileNumber' => $payment->phone
					]
					, 'reference' => $payment->localReference
					, 'products' =>  [
						[
							'name' => $payment->label
							, 'price' => [
								'amount' => $payment->amount
								, 'currency' => self::CURRENCY
								]
							, 'quantity' => 1
						]
					]
					, 'totalAmount' => [
						'amount' => $payment->amount
						, 'currency' => self::CURRENCY
					]
				];
			}
			else
			{
				throw new Exception(self::getClassIdentifier() . ' gateway implementation does not support ' . get_class($this->payment));
			}

			$parameters['returnUrls'] = [
						'successUrl' => $this->getSuccessUrl()
						, 'failUrl' => $this->getFailureUrl()
						, 'callbackUrl' => $this->getNotificationUrl()
					];

			$response = self::post('sale/online', $parameters);

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
			if(!self::confirmHmacSignature())
			{
				return new PaymentResult(false, 'API response failed integrity check');
			}
			elseif(!isset($_GET['result']))
			{
				return new PaymentResult(false, 'Request does not include all parameters');
			}
			elseif($_GET['result'] !== 'COMPLETED')
			{
				return new PaymentResult(false, $_GET['message'] ?? 'Transaction did not complete');
			}
			else
			{
				return new PaymentResult(true);
			}
		}
	}