<?php
	namespace Payments;

	use Configuration\Configuration;
	use Controller\Controller;
	use Controller\RedirectController;
	use Controller\Twig;
	use Exception;
	
	/**
	 * Bank Deposit gateway class
	 */
	class BankDeposit extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		/** whether this gateway actually does a monitary transaction. Ie, not bank deposit or on account*/
		const DOES_PAYMENT = false;

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "Bank Deposit";
		}

		/**
		 * Gets some HTML to output on the customer-facing invoice page regarding this payment gateway
		 * @param Payment $payment the paymnet that the invoice is for so we can access details as needed
		 * @return	string|null	Some confirm page HTML, or null for no extra HTML
		 */
		public static function getInvoiceHtml(Payment $payment = null)
		{
			if( $payment->status === Payment::SUCCESS )
			{
				return null;
			}
			//else

			return Twig::render('orders/sections/bank-deposit-details.twig', ['config' => Configuration::acquire(), 'payment' => $payment]);
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return "Bank Deposit";
		}

		/*~~~~~
		 * non-static
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    Controller    The controller that will handle the user
		 * @throws    Exception    If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			return new RedirectController(PaymentController::BASE_PATH . "invoice/" . $this->payment->localReference . "/");
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			// A Bank Deposit should never trigger this method
			return null;
		}
	}
