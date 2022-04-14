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
		//region PageSection
		
		public function getTemplateLocation(): string
		{
			return "pages/sections/extra-content-embed-code.twig";
		}
	}