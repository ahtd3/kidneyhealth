<?php
	namespace Pages\Linking;

	use Core\Elements\Text;
	use Core\Generator;
	use Core\Elements\Editor;
	use Pages\Linking\Link;
	use Core\Attributes\Data;
	use Core\Attributes\LinkFromMultiple;
	use Pages\Linking\LinkingPage;
	use Core\Properties\LinkToProperty;
	use Core\Elements\GeneratorElement;

	
	class GroupLinks extends Generator
	{
		const TABLE = "group_links";
		const ID_FIELD = "group_link_id";
		const LABEL_PROPERTY = "label";
		
		#[Data("content", "html")]
		public string $content = "";

		#[Data("label")]
		public string $label = "";
		
		/** @var LinkingPage */
		public $page;

		/** @var Link[] */
		#[LinkFromMultiple(Link::class, "groupLink")]
		public array $links;

		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("label", "Label"));
			$this->addElement(new Editor("content", "Content"));
			$this->addElement(new GeneratorElement("links", "Links"));
		}
		
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new LinkToProperty("page", "page_id", LinkingPage::class));
		}
	}