<?php
	namespace Template\Links;

	use Controller\Twig;

	use Core\Elements\Checkbox;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;

	use Core\Generator;

	use Core\Properties\ImageProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;

	use Files\Image;

	/**
	 * An html link with image or icon
	 * This genreator should not be used directly - extend it and set PARENT_CLASS for your module
	 */
	class AbstractLink extends Generator
	{
		/*~~~~~
		 * setup
		 **/

		// Entity / Generator

		/** @var bool */
		const HAS_ACTIVE = true;
		const HAS_AUTOCAST = true;
		const HAS_POSITION = true;

		/** @var string */
		const LABEL_PROPERTY = 'text';
		const PARENT_PROPERTY = 'parent';
		const PLURAL = 'Links';
		const SINGULAR = 'Link';

		// because we're autocasting all links /can/ be stored in this table, but you may wish to use a module-specific table.
		// properties() is set up to support just changing this constant rather than the normal table inheritance.
		const TABLE = 'template_links';
		const ID_FIELD = 'id';

		// AbstractLink

		/** @var string */
		const PARENT_CLASS = '';
		const PARENT_DB_FIELD = 'parent_id';

		/** @var bool */
		const CUSTOMISABLE_OPEN = true;
		const HAS_IMAGE = true;

		/** @var int */
		const IMAGE_WIDTH = 24;
		const IMAGE_HEIGHT = 24;
		const IMAGE_RESIZE_TYPE = ImageProperty::SCALE;

		/** @var string */
		const IMAGE_LOCATION = DOC_ROOT . '/resources/images/template/';

		/** @var bool */
		public bool $active = true;
		public $newTab = false;

		/** @var string */
		public $text = '';
		public $url = '';

		/** @var Generator $parent */
		public $parent = null;

		/** @var Image */
		public $image = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			// explicitly use static::TABLE as second parameter to addProperty() so child classes can use another table just by changing the constant

			static::addProperty(new Property('text', 'text', 'html'), static::TABLE);
			static::addProperty(new Property('url', 'url', 'string'), static::TABLE);

			// checks so we don't need to create an unneeded database field if using a different table
			
			if(static::CUSTOMISABLE_OPEN)
			{
				static::addProperty(new Property('newTab', 'open_new_tab', 'bool'), static::TABLE);
			}
			
			if(static::HAS_IMAGE)
			{
				static::addProperty(new ImageProperty('image', 'image', static::IMAGE_LOCATION, static::IMAGE_WIDTH, static::IMAGE_HEIGHT, static::IMAGE_RESIZE_TYPE), static::TABLE);
			}
			
			if(static::PARENT_CLASS !== '')
			{
				static::addProperty(new LinkToProperty(static::PARENT_PROPERTY, static::PARENT_DB_FIELD, static::PARENT_CLASS), static::TABLE);
			}
			else
			{
				// will always be null and won't result in queries getting muddled which is what happens if we omit the property altogether
				static::addProperty(new Property('parent'));
			}
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement((new Text('text', 'Link description'))->addValidation(Text::REQUIRED));
			$this->addElement((new Text('url', 'Link'))->addValidation(Text::REQUIRED));

			if(static::HAS_IMAGE)
			{
				$this->addElement(new ImageElement('image', 'Icon or image'));
			}


			if(static::CUSTOMISABLE_OPEN)
			{
				$this->addElement(new Checkbox('newTab', 'Open in new tab'));
			}
		}

		/*
		 * although the following include css classes for targeting no attempt
		 * has been made at creating default css to go with them.
		 */

		/**
		 * @param string $classes any extra classes to apply to the link
		 * @param string $attributes any extra attributes to apply to the link
		 * @return string the html for a link with the image as a CSS background-image and the description as text
		 */
		public function backgroundImageLink(string $classes = '', string $attributes = ''): string
		{
			return Twig::render('widgets/links/link-with-background.twig',
				[
					'url' => $this->url,
					'text' => $this->text,
					'image' => $this->image,
					'attributes' => $attributes,
					'classes' => 'background-image-link ' . $classes,
					'newTab' => $this->newTab
				]
			);
		}

		/**
		 * @param string $classes any extra classes to apply to the link
		 * @param string $attributes any extra attributes to apply to the link
		 * @return string the html for a link with the image inline and the description as alt-text
		 */
		public function imageLink(string $classes = '', string $attributes = ''): string
		{
			return Twig::render('widgets/links/image-link.twig',
				[
					'url' => $this->url,
					'text' => $this->text,
					'image' => $this->image,
					'attributes' => $attributes,
					'classes' => 'image-link ' . $classes,
					'newTab' => $this->newTab
				]
			);
		}

		/**
		 * @param string $classes any extra classes to apply to the link
		 * @param string $attributes any extra attributes to apply to the link
		 * @return string simple text link with no image
		 */
		public function textLink(string $classes = '', string $attributes = ''): string
		{
			return Twig::render('widgets/links/text-link.twig',
				[
					'url' => $this->url,
					'text' => $this->text,
					'attributes' => $attributes,
					'classes' => 'text-link ' . $classes,
					'newTab' => $this->newTab
				]
			);
		}
	}
