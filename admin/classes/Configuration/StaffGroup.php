<?php
	namespace Configuration;

	use Core\Elements\Text;
	use Core\Generator;
	use Core\Elements\Editor;
	use Core\Attributes\Data;
	use Core\Attributes\LinkFromMultiple;
	use Core\Properties\LinkToProperty;
	use Core\Elements\ImageElement;
	use Core\Elements\GeneratorElement;
	use Core\Properties\ImageProperty;
	use Configuration\Configuration;

	use Configuration\StaffMembers;
	/**
	 * A slideshow that stretches partway across the page, with a content section to the side
	 */
	class StaffGroup extends Generator
	{
		const TABLE = "staff_group";
		const ID_FIELD = "id";
		const LABEL_PROPERTY = "label";
		const HAS_POSITION = true;
		#[Data("label")]
		public string $label = "";
		
		/** @var Configuration */
		public $parent;

		/** @var Link[] */
		#[LinkFromMultiple(StaffMembers::class, "staffGroup")]
		public array $staffGroup;


		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("label", "Label"));
			$this->addElement(new GeneratorElement("staffGroup", "Members"));
		}
		
		protected static function properties()
		{
			parent::properties();
			static::addProperty(new LinkToProperty("parent", "parent_id", Configuration::class));
		}
	}