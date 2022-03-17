<?php
	namespace Pages\PageSections\BigSlideshow;
	
	use Core\Attributes\Data;
	use Core\Attributes\ImageValue;
	use Core\Attributes\LinkTo;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Elements\Editor;
	use Core\Generator;
	use Files\Image;
	
	/**
	 * A slide in a big slideshow content block
	 */
	class BigSlideshowSlide extends Generator
	{
		const TABLE = "big_slideshow_slides";
		const ID_FIELD = "big_slideshow_slide_id";
		const SINGULAR = "Slide";
		const PLURAL = "Slides";
		const LABEL_PROPERTY = "image";
		const PARENT_PROPERTY = "bigSlideshow";
		const HAS_POSITION = true;
		
		#[Data("alt_text")]
		public string $altText = "";
		
		#[ImageValue("image", DOC_ROOT . "/resources/images/page/", 1173, 625, ImageValue::SCALE)]
		public ?Image $image = null;
		
		#[LinkTo("big_slideshow_id")]
		public BigSlideshow $bigSlideshow;
		
		#[Data("content", "html")]
		public string $content = "";

		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new ImageElement("image", "Image"));
			$this->addElement(new Text("altText", "Alt Text"));
			$this->addElement(new Editor("content", "Content"));
		}
	}