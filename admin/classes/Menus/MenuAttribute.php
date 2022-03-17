<?php
	namespace Menus;

	use Core\Columns\CustomColumn;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\Property;
	use Files\Image;
	
	/**
	 * A Menu Attribute is an optional label that can be attached to any Menu Item
	 */
	class MenuAttribute extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = "menu_attributes";
		const ID_FIELD = "menu_attribute_id";
		const SINGULAR = "Attribute";
		const PLURAL = "Attributes";
		const LABEL_PROPERTY = "name";
		const HAS_POSITION = true;

		// MenuAttribute
		const ICON_LOCATION = DOC_ROOT . "/resources/images/menus/";
		const ICON_HEIGHT = 25;

		/** @var string */
		public $name = '';

		/** @var Image */
		public $icon = null;

		/** @var MenuItemAttribute[] */
		public $menuItemAttributes;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new ImageProperty("icon", "icon", static::ICON_LOCATION, null, static::ICON_HEIGHT));
			static::addProperty((new LinkFromMultipleProperty("menuItemAttributes", MenuItemAttribute::class, "menuAttribute")));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("name", "Name", function(MenuAttribute $menuAttribute)
			{
				if($menuAttribute->icon === null)
				{
					return $menuAttribute->name;
				}

				return $menuAttribute->icon->tag() . " " . $menuAttribute->name;
			}));

			parent::columns();
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

			$this->addElement(new Text("name", "Name"));
			$this->addElement(new ImageElement("icon", "Icon"));
		}
	}
