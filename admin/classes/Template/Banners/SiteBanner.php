<?php
	namespace Template\Banners;

	use Controller\Twig;
	use Core\Elements\Group;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Elements\Textarea;
	use Core\Elements\Editor;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Files\Image;
	use Pages\Page;
	
	/**
	 * Class file for the SiteBanner class
	 *
	 * Parent to DefaultBanner, FooterBanner, Pages\PageBanner and the banner classes for anything else for
	 *	which design requires the "page" banner to be customised (eg categories, products, blog posts)
	 * Keeps image sizes and banner/slide captions consistent unless specifically overridden
	 */
	class SiteBanner extends Generator implements Banner
	{
		/*~~~~~
		 * setup
		 **/

		// Entity / Generator

		/** @var bool */
		// if you are setting up a table for object-specific banners HAS_AUTOCAST can be false and you won't need a `class` field
		// @see page_banners in database (unused by default) as an example
		const HAS_AUTOCAST = true;

		/** @var string */
		// because we're autocasting all banners /can/ be stored in this table, but you may wish to use a module-specific table.
		// properties() is set up to support just changing this constant rather than the normal table inheritance.
		const TABLE = 'template_banners';
		const ID_FIELD = 'id';

		/** @var string */
		const LABEL_PROPERTY = 'image';
		const PARENT_PROPERTY = 'parent';
		const PLURAL = 'Banners';
		const SINGULAR = 'Banner';

		// SiteBanner
		/* @var bool ONE_OF_MANY does the parent object use LinkTo or LinkFrom property? */
		// if your parent object uses a LinkToProperty and stores the banner id in its own table this can be false
		// and a module-specific table won't need a `parent_id` field
		// otherwise this is true and engages the `parent_id` field (@see properties())
		const ONE_OF_MANY = true;

		/** @var string PARENT_CLASS class of the object linked through $this->[PARENT_PROPERTY] @see properties() */
		const PARENT_CLASS = '';

		/** @var string PARENT_DB_FIELD database field to go with PARENT_PROPERTY @see properties() */
		const PARENT_DB_FIELD = 'parent_id';

		/// Images
		/** @var bool */
		const CONTAINS_MULTIPLE_IMAGES = true;

		/** @var string */
		const IMAGE_LOCATION = Page::IMAGE_LOCATION;

		/** @var int */
		const DESKTOP_IMAGE_WIDTH = 2560;
		const DESKTOP_IMAGE_HEIGHT = 450; // 2560 / [design canvas width] * [design banner height]
		const DESKTOP_IMAGE_RESIZE_TYPE = ImageProperty::SCALE;

		// these are only relevant if CONTAINS_MULTIPLE_IMAGES = true
		const RESPONSIVE_IMAGE_WIDTH = 720; // screen width at which design changes, usually $TABLET_MIN
		const RESPONSIVE_IMAGE_HEIGHT = 280; // [design responsive banner fixed height]
		const RESPONSIVE_IMAGE_RESIZE_TYPE = ImageProperty::SCALE;

		/// caption
		/* @var string */
		// For when eg the page content title needs to be displayed in the banner
		// PARENT_TITLE_PROPERTY can be used without DO_TITLE being set to true; this means the title
		// will always be drawn from the parent object and can't be set/overridden on the banner.
		const PARENT_TITLE_PROPERTY = '';

		/** @var bool */
		const DO_CAPTION = true;

		// following have no effect unless DO_CAPTION is true
		// if there is only one format of text on the banner use title, if there is a second format
		// (it's very uncommon for there to be more than two) use text as well.
		// You may need to change the associated Elements depending on the design / need for client-specified line breaks.
		const DO_TITLE = true;
		const DO_TEXT = true;
		const DO_LINK = true;

		// following have no effect unless DO_LINK is true
		const DO_BUTTON = false;

		/** @var string */
		const DEFAULT_BUTTON_TEXT = 'View';

		/** @var bool */
		public bool $active = true;

		/** @var string */
		public $button = '';
		public $link = '';
		public $text = '';
		public $title = '';

		/** @var Image */
		public $image = null;
		public $responsiveImage = null;

		/** @var Generator */
		public $parent = null;

		// custom field
		public $content_below = '';

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Sets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			// explicitly use static::TABLE as second parameter to addProperty() so child classes can use another table just by changing the constant

			// It's a graphic banner - at minimum we have an image
			static::addProperty(new ImageProperty('image', 'image', static::IMAGE_LOCATION, static::DESKTOP_IMAGE_WIDTH, static::DESKTOP_IMAGE_HEIGHT, static::DESKTOP_IMAGE_RESIZE_TYPE), static::TABLE);

			// subsequently we check flags before adding properties so that if an alternative table is being used it does not require unnecessary columns
			if(static::CONTAINS_MULTIPLE_IMAGES)
			{
				static::addProperty(new ImageProperty('responsiveImage', 'responsive_image', static::IMAGE_LOCATION, static::RESPONSIVE_IMAGE_WIDTH, static::RESPONSIVE_IMAGE_HEIGHT, static::RESPONSIVE_IMAGE_RESIZE_TYPE), static::TABLE);
			}

			if(static::DO_CAPTION)
			{
				static::addProperty(new Property('text', 'text', 'string'), static::TABLE);
				static::addProperty(new Property('title', 'title', 'string'), static::TABLE);

				if(static::DO_LINK)
				{
					static::addProperty(new Property('button', 'button', 'string'), static::TABLE);
					static::addProperty(new Property('link', 'link', 'string'), static::TABLE);
				}
			}

			if(static::ONE_OF_MANY || static::PARENT_CLASS !== '')
			{
				// note: if you want to enforce database-level constraints on `parent_id` you will need to set up a separate banners table for the module
				// instead of storing the records in the template_banners table
				static::addProperty(new LinkToProperty(static::PARENT_PROPERTY, static::PARENT_DB_FIELD, static::PARENT_CLASS), static::TABLE);
			}
			else
			{
				// will always be null and won't result in queries getting muddled which is what happens if we omit the property altogether
				static::addProperty(new Property('parent'), static::TABLE);
			}

			static::addProperty(new Property('content_below', 'content_below', 'html'), static::TABLE);
		}

		/**
		 * Loads all the Objects that have particular values
		 * @param    array  $values  Key/value pairs of property name => property value
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]                Array of Objects
		 */
		public static function loadAllForMultiple(array $values, array $orderBy = [])
		{
			// autoclassing fix
			// @TODO test if this has been fixed in Alice
			if(!isset($values['class']))
			{
				$values['class'] = get_called_class();
			}

			return parent::loadAllForMultiple($values, $orderBy);
		}

		/**
		 * Loads the object that have particular values
		 * @param    array $values Key/value pairs of property name => property value
		 * @return    static                The requested Object
		 */
		public static function loadForMultiple(array $values)
		{
			// autoclassing fix
			// @TODO test if this has been fixed in Alice
			if(!isset($values['class']))
			{
				$values['class'] = get_called_class();
			}

			return parent::loadForMultiple($values);
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

			$images = (new Group('images'))->addClass('columns');
			$this->addElement($images);
				$images->add(new ImageElement('image', 'Image'));

				if(static::CONTAINS_MULTIPLE_IMAGES)
				{
					$images->add(
						(new ImageElement('responsiveImage', 'Responsive Image'))
							->setHint("Displays on phones and tablets, optional")
					);
				}

			if(static::DO_CAPTION)
			{
				if (static::DO_TITLE)
				{
					$this->addElement((new Text('title', 'Title'))->setHint("Optional"));
				}

				if (static::DO_TEXT)
				{
					$this->addElement((new Textarea('text', 'Text'))->setHint("Optional"));
				}

				if(static::DO_LINK)
				{

					$element = (new Text('link', 'Link'))->setHint("Optional");
					$this->addElement($element);

					if(static::DO_BUTTON)
					{
						$element->addClasses(['half', 'first']);
						$this->addElement(
							(new Text('button', 'Button text'))
								->addClasses(['half'])
								->setHint('Optional, defaults to "' . static::DEFAULT_BUTTON_TEXT . '"')
						);
					}
				}

				$this->addElement(new Editor('content_below', 'Content Below'));
			}
		}

		/*~~~~~
		 * Interface methods
		 **/
		//region Banner

		/**
		 * Gets the caption for this slide
		 * TODO this needs to go in a twig file
		 * @return    string    The caption for this slide
		 */
		public function getCaption(): string
		{
			if($this->button === '')
			{
				$this->button = static::DEFAULT_BUTTON_TEXT;
			}

			return Twig::render('template/sections/banner-caption.twig',
			[
				'banner' => $this
			]);
		}

		/**
		 * Gets the image for this slide
		 * @return    Image    The image for this slide
		 */
		public function getLargeImage(): ?Image
		{
			return $this->image;
		}

		/**
		 * Gets the responsive image for this slide
		 *
		 * Can always return null. Template uses this to just output non-responsive code without caring
		 * why there is only one image (not enabled or not uploaded)
		 *
		 * @return    Image    The smaller image for this slide
		 */
		public function getSmallImage(): ?Image
		{
			return static::CONTAINS_MULTIPLE_IMAGES ? $this->responsiveImage : null;
		}

		/**
		 * @return string the title for this slide
		 */
		public function getTitle()
		{
			$title = $this->title;

			if($title === '' && static::PARENT_TITLE_PROPERTY !== '' && $this->parent != null)
			{
				$title = $this->parent->{static::PARENT_TITLE_PROPERTY};
			}

			return $title;
		}

		//endregion
	}
