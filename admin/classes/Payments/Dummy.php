<?php
	namespace Payments;

	use Controller\Controller;
	use Controller\RedirectController;
	use Exception;
	
	/**
	 * A Dummy Payment, for testing purposes only. Even dollar amounts will be successful, odd dollar amounts will be a failure.
	 */
	class Dummy extends PaymentGateway
	{
		/*~~~~~
		 * static methods
		 **/
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "Dummy";
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return "Test Transaction (Even dollar value for success, odd for failure)";
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
			if(((int) $this->payment->amount) % 2 === 0)
			{
				return new RedirectController($this->getSuccessUrl());
			}
			else
			{
				return new RedirectController($this->getFailureUrl());
			}
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			return new PaymentResult(true);
		}
	}