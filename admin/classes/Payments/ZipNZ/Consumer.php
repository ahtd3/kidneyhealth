<?php
	namespace Payments\ZipNZ;
	
	use JsonSerializable;
	
	/**
	 * Contains consumer data for an order
	 * @author	Callum Muir <callum@activatedesign.co.nz>
	 */
	class Consumer implements JsonSerializable
	{
		public $phoneNumber;
		public $givenNames;
		public $surname;
		public $email;
		
		/**
		 * Creates a new Consumer object
		 * @param	string	$email			The consumer's email address
		 * @param	string	$givenNames		The consumer's first names
		 * @param	string	$surname		The consumer's last name
		 * @param	string	$phoneNumber	The consumer's phone number
		 */
		public function __construct($email = null, $givenNames = null, $surname = null, $phoneNumber = null)
		{
			$this->phoneNumber = $phoneNumber;
			$this->givenNames = $givenNames;
			$this->surname = $surname;
			$this->email = $email;
		}
		
		//region JsonSerializable
		
		/**
		 * Specify data which should be serialized to JSON
		 * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
		 * @return mixed data which can be serialized by <b>json_encode</b>,
		 * which is a value of any type other than a resource.
		 * @since 5.4
		 */
		public function jsonSerialize()
		{
			$data = [];
			
			foreach($this as $key => $value)
			{
				if($value !== null)
				{
					$data[$key] = $value;
				}
			}
			
			return $data;
		}
		
		//endregion
	}