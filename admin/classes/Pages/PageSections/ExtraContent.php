<?php
	namespace Pages\PageSections;
	
	use Core\Attributes\Data;
	use Core\Elements\Editor;
	use Core\Generator;
	use Core\Elements\Text;
	use Core\Elements\ImageElement;
	use Core\Attributes\ImageValue;
	use Files\Image;
	use Core\Properties\ImageProperty;

	use Core\Elements\Select;
	use Core\Elements\FormOption;
	/**
	 * An extra piece of content to be displayed after some other content
	 */
	class ExtraContent extends Generator implements PageSection
	{
		const TABLE = "extra_contents";
		const ID_FIELD = "extra_content_id";
		
		#[Data("content", "html")]
		public string $content = "";
		
		#[Data("code")]
		public string $code = "";

		#[ImageValue("image", DOC_ROOT . "/resources/images/page/", null, null, ImageValue::CROP)]
		public ?Image $image = null;

		#[Data("type")]
		public $type = "";

		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Editor("content", "Content"));
			$this->addElement(new Text("code", "Class"));
			$this->addElement(new Select("type", "Type", static::outputAllTypes()));
			$this->addElement(new ImageElement("image", "Image"));
		}
		
		//region PageSection
		
		public function getTemplateLocation(): string
		{
			return "pages/sections/extra-content.twig";
		}
		
		public static function outputAllTypes()
		{
			return
			[
				new FormOption("Normal", ''),
				new FormOption("Image right content", 'image_right'),
				new FormOption("Image left and content above image", 'content_above_image_left'),
				new FormOption("Image right and content above image", 'content_above_image_right'),
			];
		}
		//endregion
	}