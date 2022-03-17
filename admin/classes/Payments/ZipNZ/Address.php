<?php
	namespace Payments\ZipNZ;
	
	use JsonSerializable;
	
	/**
	 * Contains address data for an order
	 * @author	Callum Muir <callum@activatedesign.co.nz>
	 */
	class Address implements JsonSerializable
	{
		public $addressLine1;
		public $addressLine2;
		public $suburb;
		public $city;
		public $postcode;
		public $state;
		
		/**
		 * Creates a new Address object
		 * @param	string	$addressLine1	The first line of the address
		 * @param	string	$addressLine2	The second line of the address
		 * @param	string	$suburb			The suburb
		 * @param	string	$city			The city
		 * @param	string	$postcode		The postcode for the address
		 * @param	string	$state			The state of the address
		 */
		public function __construct($addressLine1 = null, $addressLine2 = null, $suburb = null, $city = null, $postcode = null, $state = null)
		{
			$this->addressLine1 = $addressLine1;
			$this->addressLine2 = $addressLine2;
			$this->suburb = $suburb;
			$this->city = $city;
			$this->postcode = $postcode;
			$this->state = $state;
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