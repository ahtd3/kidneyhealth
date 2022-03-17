<?php
	namespace Pages\PageSections\BigSlideshow;
	
	use Core\Attributes\Data;
	use Core\Attributes\LinkFromMultiple;
	use Core\Elements\Text;
	use Core\Elements\Editor;
	use Core\Elements\GridElement;
	use Core\Generator;
	use Pages\PageSections\PageSection;
	
	/**
	 * A slideshow that stretches partway across the page, with a content section to the side
	 */
	class BigSlideshow extends Generator implements PageSection
	{
		const TABLE = "big_slideshows";
		const ID_FIELD = "big_slideshow_id";
		
		#[Data("content", "html")]
		public string $content = "";

		#[Data("code")]
		public string $code = "";
		
		/** @var BigSlideshowSlide[] */
		#[LinkFromMultiple(BigSlideshowSlide::class, "bigSlideshow")]
		public array $slides;
		
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("code", "Class"));
			$this->addElement(new Editor("content", "Side Content"));
			$this->addElement(new GridElement("slides", "Slides"));
		}
		
		//region PageSection
		
		/**
		 * @inheritDoc
		 */
		function getTemplateLocation(): string
		{
			return "pages/sections/big-slideshow.twig";
		}
		
		//endregion
	}