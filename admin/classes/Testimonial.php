<?php
	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Configuration\Registry;
	use Core\Attributes\Data;
	use Core\Attributes\ImageValue;
	use Core\Columns\PropertyColumn;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Elements\Textarea;
	use Core\Generator;
	use Files\Image;
	
	/**
	 * Basic class for accessing Testimonial records
	 */
	class Testimonial extends Generator implements AdminNavItemGenerator
	{
		/** @var	string	The name of the table that stores the testimonial data */
		const TABLE = "testimonials";

		/** @var	string	The database name for the primary key of the database data */
		const ID_FIELD = "testimonial_id";

		const SINGULAR = 'Testimonial';
		const PLURAL = 'Testimonials';

		/** @var	bool	Whether Testimonials use positioning */
		const HAS_POSITION = true;
		const HAS_ACTIVE = true;
		const LABEL_PROPERTY = 'witness';
		
		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/testimonial/";
		const IMAGE_WIDTH = 120;
		const IMAGE_HEIGHT = 0;
		const IMAGE_SCALE_TYPE = ImageValue::SCALE;

		const DO_IMAGE = false;

		#[Data("witness")]
		public string $witness = "";
		
		#[Data("testimony")]
		public string $testimony = "";
		
		#[ImageValue("image", self::IMAGE_LOCATION, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, self::IMAGE_SCALE_TYPE)]
		public Image|null $image = null;
		
		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn('witness', 'Witness'));

			parent::columns();
		}

		/**
		 * Gets all the active testimonials
		 * @return	static[]	Said testimonials
		 */
		public static function loadAllActive()
		{
			return static::loadAllFor("active", true, ["position" => true]);
		}
		
		/**
		 * Gets a random Testimonial
		 * @return	Testimonial		A random Testimonial
		 */
		public static function loadRandom()
		{
			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "ORDER BY RAND() "
				   . "LIMIT 1";

			return static::makeOne($query);
		}
		
		/**
		 * Sets the Form Elements for this object
		 */
		 protected function elements()

		{
			parent::elements();

			$this->addElement(new Text('witness', 'Customer name'));
			$this->addElement(new Textarea('testimony', 'Testimonial'));

			if (static::DO_IMAGE)
			{
				$this->addElement(new ImageElement("image", 'Image', $this->image));
			}
		}
		
		//region AdminNavItemGenerator
		
		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(static::getAdminNavLink(), "Testimonials", [static::class], Registry::TESTIMONIALS);
		}
		
		//endregion
	}
