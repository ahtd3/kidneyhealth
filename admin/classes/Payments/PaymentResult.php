<?php
	namespace Payments;
	
	use Error;
	
	/**
	 * Keeps track of payment results
	 */
	class PaymentResult
	{
		public $success;
		public $error;
		
		/**
		 * Creates a new Payment Result
		 * @param	bool	$success	Whether the payment was successful
		 * @param	string	$error		An error for the payment being unsuccessful
		 */
		public function __construct($success, $error = null)
		{
			$this->success = $success;
			$this->error = $error;
			
			if(!$success && $error === null)
			{
				throw new Error("Failed transactions must include an error message");
			}
		}
	}