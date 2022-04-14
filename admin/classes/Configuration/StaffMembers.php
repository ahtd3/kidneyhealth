<?php
	namespace Configuration;
	

	use Core\Elements\Editor;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\Property;
	use Configuration\StaffGroup;
	use Core\Attributes\LinkTo;
	use Core\Elements\ImageElement;
	use Core\Properties\ImageProperty;

	use JsonSerializable;

	class StaffMembers extends Generator implements JsonSerializable
	{
		const TABLE = "staff_members";
		const ID_FIELD = "id";
		const LABEL_PROPERTY = "full_name";
		const PARENT_PROPERTY = "staffGroup";
		const HAS_POSITION = true;
		const IMAGE_DEFAULT = "/resources/images/staff/default.png";
		public $first_name = "";
		public $full_name = "";
		public $description= "";
		public $title = "";
		public $image = null;
		// public $link_type  = "";
		
		#[LinkTo("group_id")]
		public StaffGroup $staffGroup;
		
		protected static function properties()
		{
			parent::properties();
			static::addProperty(new ImageProperty("image", "image", DOC_ROOT . "/resources/images/staff/", 650, 650, ImageProperty::CROP));
			static::addProperty(new Property("first_name", "first_name", "string"));
			static::addProperty(new Property("full_name", "full_name", "string"));
			static::addProperty(new Property("title", "title", "string"));
			static::addProperty(new Property("description", "description", "html"));
		}

		protected function elements()
		{
			parent::elements();
			$this->addElement(new ImageElement("image", "Profile Image"));
			$this->addElement(new Text("first_name", "first_name"));
			$this->addElement(new Text("full_name", "full_name"));
			$this->addElement(new Text("title", "title"));
			$this->addElement(new Editor("description", "More Information"));
		}
		public function getDefault() {
			return self::IMAGE_DEFAULT;
		}
		public function jsonSerialize(): array
		{
			return
			[
				"first_name" => $this->first_name,
				"title" => $this->title,
				"full_name" => $this->full_name,
				"description" => $this->description,
			];
		}
	}