<?php
	namespace Orders;
	
	use Core\Attributes\Data;
	use Core\Elements\Text;
	use Core\Elements\Textarea;
	use Core\Generator;
	
	/**
	 * An address contains address details for a user, cart or order
	 */
	class Address extends Generator
	{
		const TABLE = "addresses";
		const ID_FIELD = "address_id";
		const SINGULAR = "Address";
		const PLURAL = "Addresses";
		
		const REQUIRED_SHIPPING_ADDRESS_FIELDS = ["name", "address", "city", "postCode", "country"];
		const REQUIRED_BILLING_ADDRESS_FIELDS = ["name", "email", "phone", "address", "city", "postCode", "country"];
		
		#[Data("name")]
		public string $name = "";
		
		#[Data("email")]
		public string $email = "";
		
		#[Data("phone")]
		public string $phone = "";
		
		#[Data("address")]
		public string $address = "";
		
		#[Data("suburb")]
		public string $suburb = "";
		
		#[Data("city")]
		public string $city = "";
		
		#[Data("post_code")]
		public string $postCode = "";
		
		#[Data("country")]
		public string $country = "";
		
		#[Data("delivery_instructions")]
		public string $deliveryInstructions = "";
		
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("name", "Name"));
			$this->addElement(new Text("email", "Email"));
			$this->addElement(new Text("phone", "Phone number"));
			$this->addElement(new Text("address", "Address"));
			$this->addElement(new Text("suburb", "Suburb"));
			$this->addElement(new Text("city", "City"));
			$this->addElement(new Text("postCode", "Postal code"));
			$this->addElement(new Text("country", "Country"));
			$this->addElement(new Textarea("deliveryInstructions", "Delivery instructions"));
		}
		
		/**
		 * Outputs the full address (with new lines, not <br />s)
		 * @return	string	The full address
		 */
		public function outputAddress()
		{
			$html = "";
			$html .= $this->address . "\n";
			
			if($this->suburb !== "")
			{
				$html .= $this->suburb . "\n";
			}
			
			$html .= $this->city . " " . $this->postCode . "\n";
			$html .= $this->country;
			
			return $html;
		}
		
		/**
		 * Verifies the fields in this address
		 * @param	string[]	$fields		The fields to verify for this address
		 * @return	string[]				An array of error messages. An empty array means there are no errors
		 */
		public function verifyFields(array $fields)
		{
			$messages = [];
			
			if($this->name === "" && in_array("name", $fields))
			{
				$messages[] = "Please enter a name";
			}
			
			if(in_array("email", $fields))
			{
				if($this->email === "")
				{
					$messages[] = "Please enter an email address";
				}
				else if(!isEmail($this->email))
				{
					$messages[] = "This email address is invalid, please enter a valid email address";
				}
			}
			
			if($this->phone === "" && in_array("phone", $fields))
			{
				$messages[] = "Please enter a phone number";
			}
			
			if($this->address === "" && in_array("address", $fields))
			{
				$messages[] = "Please enter a street address";
			}
			
			if($this->suburb === "" && in_array("suburb", $fields))
			{
				$messages[] = "Please enter a suburb";
			}
			
			if($this->city === "" && in_array("city", $fields))
			{
				$messages[] = "Please enter a town or city";
			}
			
			if($this->postCode === "" && in_array("postCode", $fields))
			{
				$messages[] = "Please enter a postal code";
			}
			
			if($this->country === "" && in_array("country", $fields))
			{
				$messages[] = "Please enter a country";
			}
			
			if($this->deliveryInstructions === "" && in_array("deliveryInstructions", $fields))
			{
				$messages[] = "Please enter any delivery instructions";
			}
			
			return $messages;
		}
	}