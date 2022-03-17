<?php
	namespace Payments;

	use Controller\Controller;
	use Controller\RedirectController;
	use Exception;
	
	use Users\User;
	
	/**
	 * On Account gateway class
	 */
	class OnAccount extends PaymentGateway
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
		 * some payment gateways (e.g. On Account) may only be available to particular user types or users with a particular flag set
		 * (putting this here ready for modification) 
		 *
		 * @param User $user the user associated with the payment
		 * @return bool  is this gateway available to this user?
		 */
		public static function availableForUser(User $user)
		{
			return true;
		}
		
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "On Account";
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return "On Account";
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
			return new RedirectController(PaymentController::BASE_PATH . "invoice/" . $this->payment->localReference . "/");
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			// An account order should never trigger this method
			return null;
		}
	}
