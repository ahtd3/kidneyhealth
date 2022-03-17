<?php
	namespace Configuration;
	

	use Core\Elements\Editor;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\Property;
	use Configuration\RegionalGroup;
	use Core\Attributes\LinkTo;

	use JsonSerializable;

	class Regional extends Generator implements JsonSerializable
	{
		const TABLE = "regional";
		const ID_FIELD = "regional_id";
		const LABEL_PROPERTY = "label";
		const PARENT_PROPERTY = "regionalGroup";
		
		public $label = "";
		public $phone = "";
		public $email = "";
		public $address = "";
		public $detail = "";
		
		// public $link_type  = "";
		
		#[LinkTo("regional_group_id")]
		public RegionalGroup $regionalGroup;

		
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new Property("label", "label", "string"));
			// static::addProperty(new Property("phone", "phone", "string"));
			// static::addProperty(new Property("email", "email", "string"));
			// static::addProperty(new Property("address", "address", "string"));
			static::addProperty(new Property("detail", "detail", "html"));
		}
		
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("label", "Name"));
			// $this->addElement(new Text("phone", "Phone"));
			// $this->addElement(new Text("email", "Email"));
			// $this->addElement(new Textarea("address", "Address"));
			$this->addElement(new Editor("detail", "Detail"));
		}

		public function jsonSerialize(): array
		{
			return
			[
				"label" => $this->label,
			];
		}
	}