<?php
	namespace Payments\ZipNZ;
	
	use JsonSerializable;
	
	/**
	 * A single item in an order
	 * @author	Callum Muir <callum@activatedesign.co.nz>
	 */
	class Item implements JsonSerializable
	{
		public $description;
		public $name;
		public $sku;
		public $quantity;
		public $price;
		
		/**
		 * Creates a new Item object
		 * @param	string	$description	The description of the item
		 * @param	string	$name			The name of the item
		 * @param	string	$sku			The SKU for the item
		 * @param	int		$quantity		The quantity of the item
		 * @param	float	$price			The price of the item
		 */
		public function __construct($description = null, $name = null, $sku = null, $quantity = null, $price = null)
		{
			$this->description = $description;
			$this->name = $name;
			$this->sku = $sku;
			$this->quantity = $quantity;
			$this->price = $price;
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