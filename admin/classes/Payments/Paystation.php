<?php
	namespace Payments;

	use Controller\RedirectController;
	use Exception;
	use SimpleXMLElement;
	
	/**
	 * Handles payments using Paystation
	 */
	class Paystation extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging
		
		private const GATEWAY_ID = 'paystation';
		private const LOOKUP_URL = "https://payments.paystation.co.nz/lookup/";
		private const REQUEST_URL = "https://www.paystation.co.nz/direct/paystation.dll";

		// Client's test details or live details
		// Test details here are Activate Design account credentials, switch for client's credentials if available (and comment appropriately)
		private const PAYSTATION_ID = self::TEST_MODE ? '615252' : '';
		private const HMAC_KEY = self::TEST_MODE ? 'JUVvqq6P8LGUE7xA' : '';


		/*~~~~~
		 * static methods
		 **/
		/**
		 * Generates the HMAC string, for security reasons
		 * @param	string		$postBody	The data to generate the HMAC string from
		 * @return	string[]	The HMAC parameters
		 */
		private static function generateHmacParameters($postBody)
		{
			$authenticationKey = self::HMAC_KEY;
			$timestamp = time();
			$body = pack("a*", $timestamp) . pack("a*", "paystation") . pack("a*", $postBody);
			$hash = hash_hmac("sha512", $body, $authenticationKey);

			return
			[
				"pstn_HMACTimestamp" => $timestamp,
				"pstn_HMAC" => $hash
			];
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "Paystation";
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return "Credit Card";
		}

		/**
		 * Makes a request to the Paystation API
		 * @param	string		$url			The URL to send the request to
		 * @param	string[]	$parameters		The parameters to pass to Paystation
		 * @return	string						The response from Paystation
		 */
		private static function sendRequest($url, array $parameters)
		{
			$parameterString = http_build_query($parameters);
			$url .= '?' . http_build_query(self::generateHmacParameters($parameterString));

			$curl_handler = curl_init($url);
            curl_setopt($curl_handler, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl_handler, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($curl_handler, CURLOPT_POST, TRUE);
            curl_setopt($curl_handler, CURLOPT_POSTFIELDS, $parameterString);
            curl_setopt($curl_handler, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($curl_handler, CURLOPT_HEADER, FALSE);
            curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, TRUE);
            $output = curl_exec($curl_handler);
            curl_close($curl_handler);

            return $output;
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Creates the parameters for this transaction
		 * @return	string[]	The parameters
		 */
		private function getParameters()
		{
			$parameters =
			[
				"paystation" => "_empty",
				"pstn_pi" => self::PAYSTATION_ID,
				"pstn_gi" => self::GATEWAY_ID,
				"pstn_ms" => $this->payment->localReference,
				"pstn_mr" => $this->payment->localReference,
				"pstn_am" => $this->payment->amount * 100,
				"pstn_du" => $this->getSuccessUrl(),
				"pstn_dp" => $this->getNotificationUrl(),
				"pstn_nr" => "t"
			];

			if(self::TEST_MODE)
			{
				$parameters["pstn_tm"] = "t";
			}

			return $parameters;
		}

		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    RedirectController    The controller that will handle the user
		 * @throws    Exception            If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$data = self::sendRequest(self::REQUEST_URL, $this->getParameters());
			$xmlData = new SimpleXMLElement($data);

			if(isset($xmlData->DigitalOrder) && isset($xmlData->PaystationTransactionID))
			{
				$this->payment->remoteReference = (String) $xmlData->PaystationTransactionID;
				return new RedirectController($xmlData->DigitalOrder);
			}
			elseif(isset($xmlData->em))
			{
				throw new Exception($xmlData->em);
			}
			else
			{
				throw new Exception("An unknown error has occurred");
			}
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			$parameters =
			[
				"pi" => self::PAYSTATION_ID,
				"ti" => $this->payment->remoteReference
			];

			$data = self::sendRequest(self::LOOKUP_URL, $parameters);
			$xmlData = new SimpleXMLElement($data);

			if((int) ($xmlData->LookupResponse->PaystationErrorCode ?? -1) === 0)
			{
				return new PaymentResult(true);
			}
			elseif(isset($xmlData->LookupResponse->PaystationErrorMessage))
			{
				return new PaymentResult(false, $xmlData->LookupResponse->PaystationErrorMessage);
			}
			elseif(isset($xmlData->LookupMessage))
			{
				return new PaymentResult(false, $xmlData->LookupMessage);
			}
			else
			{
				return new PaymentResult(false, "An unknown error has occurred");
			}
		}
	}
