<?php
	namespace Configuration;

	use Template\Links\ExternalLink;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Core\Elements\ImageElement;
	

	use Files\Image;
	/**
	 * A link to an external website (social media account), with an icon (HAS_IMAGE true by default) and always opens in a new tab
	 */
	class SocialMediaLink extends ExternalLink
	{
		/*~~~~~
		 * setup
		 **/

		// AbstractLink

		/** @var string */
		const PARENT_CLASS = Configuration::class;

		/** @var int */
		const IMAGE_WIDTH = 40;
		const IMAGE_HEIGHT = 40;

		/** @var Image */
		public $image_dark = null;

		protected static function properties()
		{
			parent::properties();
			static::addProperty(new ImageProperty('image_dark', 'image_dark', static::IMAGE_LOCATION, static::IMAGE_WIDTH, static::IMAGE_HEIGHT, static::IMAGE_RESIZE_TYPE), static::TABLE);
		}

		protected function elements()
		{
			parent::elements();
			$this->addElement(new ImageElement('image_dark', 'Icon Contrast'));
		}
	}
