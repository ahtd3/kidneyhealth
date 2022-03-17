<?php
	namespace Payments\PaymentExpress;

	use Controller\RedirectController;
	use Exception;
	use MifMessage;
	use Payments\PaymentGateway;
	use Payments\PaymentResult;
	use PxPay_Curl;
	use PxPayRequest;
	
	require_once(__DIR__ . "/PxPay_Curl.inc.php");

	/**
	 * PaymentExpress gateway class
	 */
	class PaymentExpress extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging
		
		protected const API = "https://sec.paymentexpress.com/pxpay/pxaccess.aspx";
		protected const CURRENCY = "NZD";
		protected const TRANSACTION_METHOD = "Purchase"; // Purchase or Auth

		// Activate Design developer account or Client's live details
		protected const API_KEY = self::TEST_MODE ? '9ab6069948263952afc303d0c0a5ce0a589db63e1713136d5002719dbdc99b3e' : '';
		protected const MERCHANT_ID = self::TEST_MODE ? 'ActivateDesignPXP2_Dev' : '';

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "Payment Express";
		}
		
		/**
		 * Gets the messenger that will send messages to pxpay
		 * @return	PxPay_Curl	The messenger
		 */
		protected static function getMessenger()
		{
			return new PxPay_Curl(static::API, static::MERCHANT_ID, static::API_KEY);
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

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    RedirectController    The controller that will handle the user
		 * @throws    Exception    If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$request = new PxPayRequest();
			$request->setMerchantReference($this->payment->localReference);
			$request->setAmountInput($this->payment->amount);
			$request->setTxnType(static::TRANSACTION_METHOD);
			$request->setCurrencyInput(static::CURRENCY);
			$request->setEmailAddress($this->payment->email);
			$request->setUrlFail($this->getFailureUrl());
			$request->setUrlSuccess($this->getSuccessUrl());

			$pxPay = static::getMessenger();
			$requestString = $pxPay->makeRequest($request);
			$response = new MifMessage($requestString);

			return new RedirectController($response->get_element_text("URI"));
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			$pxPay = static::getMessenger();
			$encryptedString = $_REQUEST["result"] ?? "";
			$result = $pxPay->getResponse($encryptedString);

			if($result->getSuccess() === "1")
			{
				return new PaymentResult(true);
			}
			else
			{
				return new PaymentResult(false, "Transaction unsuccessful");
			}
		}
	}