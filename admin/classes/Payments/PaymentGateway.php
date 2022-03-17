<?php
	namespace Payments;

	use Controller\RedirectController;
	use Exception;
	use Payments\PaymentExpress\PaymentExpress;
	use Payments\PaymentExpress\WindCave;
	use Payments\ZipNZ\ZipNZ;
	
	use Users\User;
	
	/**
	 * Interface for all Payment Gateway classes
	 */
	abstract class PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		/** @var bool is the site in full on test-payment-gateways mode. Checked/changed as part of the go-live checklist */
		public const TEST_MODE = true;

		/** @var string by default the customer return to somewhere near here from the paymnet gateway */
		public const BASE_RETURN_URL = PROTOCOL . SITE_ROOT . PaymentController::BASE_PATH;

		/** @var bool[] all the payment gateway classes and if they are enabled for this site  */
		public const CLASSES =
		[
			BankDeposit::class => false,
			Dummy::class => true,
			Eway::class => false,
			GenoaPay::class => false, // not tested with Bill Payments, limited live testing
			Laybuy::class => false, // not tested with Bill Payments, limited live testing
			OnAccount::class => false,
			PaymentExpress::class => false,
			Paypal::class => false,
			Paystation::class => false,
			Poli::class => false, // next implementation gets to check the POLi block at checkout is displaying correctly (Poli::getUserlabel()) and remove this comment from core
			Stripe::class => false,
			WindCave::class => false, // limited live testing
			ZipMoney::class => false, // (AU) // requires additional modifications to template, see class docblock // Not tested
			ZipNZ::class => false // requires addititional modifications to template, see class docblock // previously PartPay // Not tested with bill payments, not live tested this version of code
		];

		/** @var bool whether this gateway actually does a monetary transaction. Ie, not bank deposit or on account*/
		public const DOES_PAYMENT = true;

		/** @var Payment $payment */
		protected $payment;

		/*~~~~~
		 * static methods
		 **/
		/**
		 * some payment gateways set minimum or maximum limits on the value of order which can be paid through them
		 * These may vary between different merchant accounts and may require the merchant /not/ to present the gateway as an option if the order total falls outside these limits
		 * @see AfterPay, GenoaPay
		 * @param float $orderValue value of the order
		 * @return bool  is this gateway available given this order value?
		 */
		public static function availableForOrderValue($orderValue)
		{
			return true;
		}
		
		/**
		 * some payment gateways (e.g. On Account) may only be available to particular user types or users with a particular flag set
		 *
		 * @param User $user the user associated with the payment
		 * @return bool  is this gateway available to this user?
		 */
		public static function availableForUser(User $user)
		{
			return true;
		}

		/**
		 * Gets the active payment gateway classes
		 * @return	array<class-string<PaymentGateway>>		The active payment gateway classes
		 */
		public static function getActivePaymentGatewayClasses()
		{
			$activeClasses = [];

			foreach(static::CLASSES as $paymentGatewayClass => $enabled)
			{
				if($enabled)
				{
					$activeClasses[] = $paymentGatewayClass;
				}
			}

			return $activeClasses;
		}

		/**
		 * Gets the class identifier for this class
		 * @return	string	A string that uniquely identifies this class among all payment gateway classes
		 */
		abstract public static function getClassIdentifier();

		/**
		 * Gets some HTML to output on the confirm page regarding this payment gateway
		 * @return	string	Some confirm page HTML, or null for no extra HTML
		 */
		public static function getConfirmHtml()
		{
			return null;
		}

		/**
		 * Gets a PaymentGateway class matching a specific identifier
		 * @param	string								$gatewayIdentifier	The identifier for the gateway
		 * @return	class-string<PaymentGateway>|null						The class name for that gateway, or null if it's disabled or non-existent
		 */
		public static function getGatewayClassForIdentifier($gatewayIdentifier)
		{
			/** @var class-string<PaymentGateway>|PaymentGateway $type */
			foreach(static::CLASSES as $type => $enabled)
			{
				if($enabled && $type::isResponsibleFor($gatewayIdentifier))
				{
					return $type;
				}
			}

			return null;
		}

		/**
		 * Gets a PaymentGateway for a specific Payment
		 * @param	Payment			$payment	The payment being made to the gateway
		 * @return	PaymentGateway				The gateway for that payment, or null if one can't be found
		 */
		public static function getGatewayForPayment(Payment $payment)
		{
			$gatewayType = static::getGatewayClassForIdentifier($payment->paymentMethod);

			if($gatewayType === null)
			{
				return null;
			}

			return new $gatewayType($payment);
		}

		/**
		 * Gets some HTML to output on the customer-facing invoice page regarding this payment gateway
		 * @param Payment $payment the paymnet that the invoice is for so we can access details as needed
		 * @return	string	Some confirm page HTML, or null for no extra HTML
		 */
		public static function getInvoiceHtml(Payment $payment = null)
		{
			return null;
		}

		/**
		 * utility function for debugging
		 * @return string the location of a log file which can be written to with error_log or the logging mechanism of your choice
		 */
		public static function getLogDestination()
		{
			// splitting the logs by date makes long-term monitoring /much/ easier
			return __DIR__ . '/' . static::getClassIdentifier() . '.' . date('Y-m-d') . '.log';
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
			return static::getUserLabel($total);
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * Gateways with installments like to include information about their service
		 * and the amount of the installment in the label.
		 * @see AfterPay, GenoaPay
		 *
		 * @param 	float 	$total 	Rather than assuming the global cart is the correct
		 * 		one at the time this method is called better to pass in the total
		 * @return	string			A label to describe this payment gateway
		 */
		abstract public static function getUserLabel($total = null);
		
		/**
		 * Checks if this payment gateway supports processing a particular payment type
		 * @param	string					$gatewayIdentifier	The identifier for the gateway
		 * @return	bool					Does this gateway support this payment type
		 */
		public static function isResponsibleFor($gatewayIdentifier)
		{
			 return $gatewayIdentifier === static::getClassIdentifier();
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Creates a new payment for a specific Payment Action
		 * @param	Payment $payment The payment to create
		 */
		public function __construct(Payment $payment)
		{
			$this->payment = $payment;
			$this->payment->paymentMethod = static::getClassIdentifier();
		}

		/**
		 * Gets the return failure URL
		 * @return	string	The return URL
		 */
		final protected function getFailureUrl()
		{
			return static::BASE_RETURN_URL . $this->payment->localReference . "/failure/";
		}

		/**
		 * Gets the notification URL
		 * @return	string	The notification URL
		 */
		final protected function getNotificationUrl()
		{
			return static::BASE_RETURN_URL . $this->payment->localReference . "/notification/";
		}

		/**
		 * Gets the return successful URL
		 * @return	string	The return URL
		 */
		final protected function getSuccessUrl()
		{
			return static::BASE_RETURN_URL . $this->payment->localReference . "/success/";
		}

		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return	RedirectController	The controller that will handle the user
		 * @throws	Exception			If something goes wrong setting up the request
		 */
		abstract public function getRedirectController();

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		abstract public function getResult();
	}
