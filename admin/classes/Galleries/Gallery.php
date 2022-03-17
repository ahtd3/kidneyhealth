<?php
	namespace Galleries;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Configuration\Registry;
	use Controller\Twig;
	use Core\Attributes\Data;
	use Core\Attributes\Dynamic;
	use Core\Attributes\LinkFromMultiple;
	use Core\Columns\CustomColumn;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Base\ResultElement;
	use Core\Elements\GridElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\Property;
	use Template\ShortCodes\ShortCode;
	use Template\ShortCodes\ShortCodeSupport;
	use Twig\Error\Error;
	
	/**
	 * Basic Gallery module
	 */
	class Gallery extends Generator implements ShortCodeSupport, AdminNavItemGenerator
	{
		const TABLE = 'galleries';
		const ID_FIELD = 'gallery_id';
		const SINGULAR = 'Gallery';
		const PLURAL = 'Galleries';
		const LABEL_PROPERTY = "title";

		#[Data("title")]
		public string $title = '';

		/** @var GalleryItem[] */
		#[LinkFromMultiple(GalleryItem::class, "gallery")]
		public array $galleryItems;

		/** @var  GalleryItem[] */
		#[Dynamic]
		public array $_activeImages;

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn('title', 'Gallery'));

			static::addColumn(new CustomColumn("includeCode", "Include code", function(Gallery $gallery)
			{
				return ShortCode::generate($gallery);
			}));

			parent::columns();
			static::removeColumn('Name');
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$this->removeElement("active");
			$this->removeElement("name");

			$this->addElement((new Text('title', 'Gallery title'))->addValidation(ResultElement::REQUIRED));
			$this->addElement(new GridElement('galleryItems'));
		}

		/**
		 * lazy-loading only active images
		 *
		 * @return GalleryItem[]
		 */
		public function get_activeImages()
		{
			if(!isset($this->_activeImages))
			{
				$this->_activeImages = GalleryItem::loadActiveImagesForGallery($this);
			}

			return $this->_activeImages;
		}

		/**
		 * turn the Introduction into a short description suitable for gallery list page
		 *
		 * @return string
		 */
		public function get_description()
		{
			$str = strip_tags($this->getValue('content'));
			return substr($str,0,100)
				. ((strlen($str) > 100) ?  '&hellip;' : '')
				;
		}

		/*****
		 * html generators
		 **/

		/*****
		 * Publishes interface methods
		 **/
		/**
		 * is this public? Method intended to be queried by other classes which may want to
		 * 	pull in this content before calling output()
		 *
		 * @return bool
		 */
		public function canShow()
		{
			return $this->active;
		}

		//region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(static::getAdminNavLink(), "Galleries", [static::class], Registry::GALLERIES);
		}

		//endregion

		//region ShortCodeSupport

		/**
		 * Gets a unique identifier for this class
		 * @return    string    An identifier that uniquely identifies this class
		 */
		public static function getClassShortcodeIdentifier()
		{
			return "Gallery";
		}
		
		/**
		 * Format and output properties as a coherent string of HTML
		 * @return	string	The HTML for the gallery
		 * @throws	Error	If something goes wrong while rendering the gallery
		 */
		public function getShortcodeContent()
		{
			return Twig::render("galleries/gallery.twig",
			[
				"gallery" => $this
			]);
		}
		
		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getShortcodeIdentifier()
		{
			return $this->id;
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param    string $identifier The identifier to load from
		 * @return    ShortCodeSupport   An object that can be outputted to the page, or null if the correct one cannot be found
		 */
		public static function loadForShortcodeIdentifier($identifier)
		{
			$gallery = static::loadForMultiple(
			[
				"id" => $identifier
			]);

			return $gallery->isNull() ? null : $gallery;
		}

		//endregion

	}
