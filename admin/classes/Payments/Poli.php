<?php
	namespace Payments;

	use Controller\Controller;
	use Controller\JsonController;
	use Controller\RedirectController;
	use Controller\Twig;
	use Exception;
	
	/**
	 * POLi gateway class
	 */
	class Poli extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging

		private const CURRENCY = "NZD";
		private const REQUEST_URL = "https://poliapi.apac.paywithpoli.com/api/Transaction/Initiate";
		private const LOOKUP_URL = "https://poliapi.apac.paywithpoli.com/api/Transaction/GetTransaction";

		// Client's test details or live details
		// Test details here are Activate Design account credentials, switch for client's credentials if available (and comment appropriately)
		private const MERCHANT_CODE = self::TEST_MODE ? 'SS64000565' : '';
		private const AUTHENTICATION_CODE = self::TEST_MODE ? 'L9O!q2aHV9z@H' : '';

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "POLi";
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return Twig::render('cart/sections/poli-block.twig', ['merchantCode' => self::MERCHANT_CODE]);
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    Controller    The controller that will handle the user
		 * @throws    Exception    If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$jsonBuilder =
			[
				"Amount" => $this->payment->amount,
				"CurrencyCode" => self::CURRENCY,
				"MerchantReference" => $this->payment->localReference,
				"MerchantHomepageURL" => PROTOCOL . SITE_ROOT,
				"SuccessURL" => $this->getSuccessUrl(),
				"FailureURL" => $this->getFailureUrl(),
				"CancellationURL" => $this->getFailureUrl(),
				"NotificationURL" => $this->getNotificationUrl()
			];

			$auth = base64_encode(self::MERCHANT_CODE . ":" . self::AUTHENTICATION_CODE);

			$header =
			[
				"Content-Type: application/json",
				"Authorization: Basic " . $auth
			];

			$curl = curl_init(self::REQUEST_URL);

			curl_setopt_array($curl,
			[
				CURLOPT_CAINFO => DOC_ROOT . "/admin/ca-bundle.crt",
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTPHEADER => $header,
				CURLOPT_HEADER => 0,
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => json_encode($jsonBuilder, JsonController::getStandardEncodeOptions()),
				CURLOPT_FOLLOWLOCATION => 0,
				CURLOPT_RETURNTRANSFER => 1
			]);

			$response = curl_exec($curl);
			$data = json_decode($response, true);

			if(!isset($data["Success"]))
			{
				throw new Exception("Error: " . $data["Message"]);
			}
			elseif(!$data["Success"])
			{
				throw new Exception("Error: " . $data["ErrorCode"] . ": " . $data["ErrorMessage"]);
			}
			else
			{
				$this->payment->remoteReference = $data["TransactionRefNo"];
				$this->payment->save();

				return new RedirectController($data["NavigateURL"]);
			}
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			$auth = base64_encode(self::MERCHANT_CODE. ":" . self::AUTHENTICATION_CODE);
			$header = ["Authorization: Basic " . $auth];
			$token = $_GET["token"];

			$curl = curl_init(self::LOOKUP_URL . "?token=" . rawurlencode($token));

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
			curl_close($curl);

			$data = json_decode($response, true);

			if($data["TransactionStatusCode"] === "Completed")
			{
				return new PaymentResult(true);
			}
			else
			{
				return new PaymentResult(false, $data["ErrorMessage"]);
			}
		}
	}