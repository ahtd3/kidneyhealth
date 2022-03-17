<?php
	namespace Pages\PageSections;
	
	use Pages\PageSections\ExtraContent;

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
	class EmbedCode extends ExtraContent
	{

		#[Data("embed_code", "html")]
		public string $embed_code = "";

		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Editor("embed_code", "Embed code"));
		}
		
		//region PageSection
		
		public function getTemplateLocation(): string
		{
			return "pages/sections/extra-content.twig";
		}
	}