<?php
	namespace Payments\ZipNZ;
	
	use Exception;
	use stdClass;
	
	/**
	 * Thrown when something goes wrong with Zip
	 */
	class ZipException extends Exception
	{
		public $isValid;
		public $errors;
		
		/**
		 * Creates a new ZipException
		 * @param	string		$message	The error message
		 * @param	bool		$isValid	Whether the request was valid
		 * @param	stdClass[]	$errors		All generated errors
		 * @param	string		$errorCode	The code of the triggered error
		 */
		public function __construct($message, $errorCode, $isValid, array $errors)
		{
			parent::__construct($message);
			
			$this->code = $errorCode;
			$this->isValid = $isValid;
			$this->errors = $errors;
		}
	}