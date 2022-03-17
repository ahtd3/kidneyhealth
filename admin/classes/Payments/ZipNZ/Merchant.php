<?php
	namespace Payments\ZipNZ;
	
	use JsonSerializable;
	
	/**
	 * Redirection details for Zip
	 * @author	Callum Muir <callum@activatedesign.co.nz>
	 */
	class Merchant implements JsonSerializable
	{		
		public $redirectConfirmUrl;
		public $redirectCancelUrl;
		public $statusCallbackUrl;
		
		/**
		 * Creates a new Merchant object
		 * @param	string	$redirectConfirmUrl		URL to redirect successful transactions to
		 * @param	string	$redirectCancelUrl		URL to redirect failed or cancelled transactions to
		 * @param	string	$statusCallbackUrl		URL for instant payment notification
		 */
		public function __construct($redirectConfirmUrl = '', $redirectCancelUrl = '', $statusCallbackUrl = '')
		{
			$this->redirectConfirmUrl = $redirectConfirmUrl;
			$this->redirectCancelUrl = $redirectCancelUrl;
			$this->statusCallbackUrl = $statusCallbackUrl;
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