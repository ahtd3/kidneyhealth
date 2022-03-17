<?php
	namespace Configuration;

	use Core\Elements\Text;
	use Core\Generator;
	use Core\Elements\Editor;
	use Configuration\Regional;
	use Core\Attributes\Data;
	use Core\Attributes\LinkFromMultiple;
	use Core\Properties\LinkToProperty;
	use Core\Elements\ImageElement;
	use Core\Elements\GeneratorElement;
	use Core\Properties\ImageProperty;
	use Configuration\Configuration;
	/**
	 * A slideshow that stretches partway across the page, with a content section to the side
	 */
	class RegionalGroup extends Generator
	{
		const TABLE = "regional_group";
		const ID_FIELD = "regional_group_id";
		const LABEL_PROPERTY = "label";

		#[Data("content", "html")]
		public string $content = "";

		#[Data("label")]
		public string $label = "";
		
		/** @var Configuration */
		public $parent;

		/** @var Link[] */
		#[LinkFromMultiple(Regional::class, "regionalGroup")]
		public array $regional;

		public $icon = null;

		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("label", "Label"));
			$this->addElement(new ImageElement("icon", "Icon"));
			$this->addElement(new GeneratorElement("regional", "Location"));
		}
		
		protected static function properties()
		{
			parent::properties();
			static::addProperty(new ImageProperty("icon", "icon", DOC_ROOT . "/resources/images/regional/", 40, 40, ImageProperty::CROP));
			static::addProperty(new LinkToProperty("parent", "parent_id", Configuration::class));
		}
	}