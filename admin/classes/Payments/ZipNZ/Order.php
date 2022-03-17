<?php
	namespace Payments\ZipNZ;
	
	use JsonSerializable;
	
	/**
	 * An order being made to Zip
	 */
	class Order implements JsonSerializable
	{
		// Zip would like a lot more information tahn we are sending them; commented out properties may be needed at a later date if they start enforcing these
		// public $productType = 'classic';
		public $amount;
		public $consumer;
		//public $billing;
		//public $shipping;
		public $description = '';
		//public $items = null;
		public $merchant;
		public $merchantReference = '';
		// public $taxAmount = null;
		// public $shippingAmount = null;
		// public $token = '';
		// public $paymentFlow = 'payment';
		
		/**
		 * Creates a new Order object
		 */
		public function __construct($amount = 0.00, $merchantReference = '')
		{
			$this->amount = $amount;
			$this->merchantReference = $merchantReference;
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
				if(is_object($value))
				{
					if($value instanceof JsonSerializable && count($value->jsonSerialize()) > 0)
					{
						$data[$key] = $value;
					}
				}
				else if($value !== null && $value !== "")
				{
					$data[$key] = $value;
				}
			}
			
			return $data;
		}
		
		//endregion
	}