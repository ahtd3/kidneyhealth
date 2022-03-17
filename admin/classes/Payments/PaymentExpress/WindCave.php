<?php
	namespace Payments\PaymentExpress;

	use Payments\PaymentResult;

	require_once(__DIR__ . "/PxPay_Curl.inc.php");

	/**
	 * PaymentExpress "WindCave" branded payment gateway class
	 */
	class WindCave extends PaymentExpress
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging

		protected const API = "https://sec.windcave.com/pxaccess/pxpay.aspx";
		protected const CURRENCY = "NZD";
		protected const TRANSACTION_METHOD = "Purchase"; // Purchase or Auth

		// Activate Design developer account or Client's live details
		protected const API_KEY = self::TEST_MODE ? '9ab6069948263952afc303d0c0a5ce0a589db63e1713136d5002719dbdc99b3e' : '';
		protected const MERCHANT_ID = self::TEST_MODE ? 'ActivateDesignPXP2_Dev' : '';

		// Per integration doc v 3.4, plus Zip which is the only one we've had to integrate so far
		// Note: Commented out methods are available from the API, but have not been developed for or tested in this system, so further development may be necessary
		public const METHODS = [
			//'Account2Account' = false,
			//'Alipay' = false,
			//'AmexExpressWallet' = false,
			'Card'  => true,
			//'GECard' = false,
			//'MasterPass' = false,
			//'MonerisIOP' = false,
			//'Oxipay' = false,
			//'PayPal' = false,
			//'UPOP5' = false,
			//'UPOP' = false,
			//'VisaCheckout' = false,
			//'WeChat' = false,
			'Zip' => true
		];

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "WindCave";
		}

		/**
		 * Checks if this payment gateway supports processing a particular payment type
		 * @param	string					$gatewayIdentifier	The identifier for the gateway
		 * @return	bool					Does this gateway support this payment type
		 */
		public static function isResponsibleFor($gatewayIdentifier)
		{
			$identifier = static::getClassIdentifier();
			$identifiers = [$identifier];
			foreach(static::METHODS as $method => $enabled)
			{
				if($enabled)
				{
					$identifiers[] = "{$identifier}-{$method}";
				}
			}

			return in_array($gatewayIdentifier, $identifiers);
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
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
				$paymentMethod = $result->getPaymentMethod();
				// the only thing I am concerned about here is that __construct() sets the Payment::paymentType to the default for the PaymentGateway so there is the potential for it to be overwritten later.
				// but I am pretty sure that by the time we are calling getResult() (from PaymentController) there are no further instances of the payment being handled by the PaymentGateway that do not also
				// include calling getResult() i.e. subsequent triggers of PaymentGateway::paymentNotification() or PaymentGateway::successfulPayment()
				$this->payment->paymentType = static::getClassIdentifier(). ' - ' .$paymentMethod;

				return new PaymentResult(true);
			}
			else
			{
				return new PaymentResult(false, "Transaction unsuccessful");
			}
		}
	}