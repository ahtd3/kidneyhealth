<?php
	namespace Menus;

	use Core\Elements\Select;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	
	/**
	 * A Menu Item Attribute is a Menu Attribute attached to a specific Menu Item
	 */
	class MenuItemAttribute extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = "menu_item_attributes";
		const ID_FIELD = "menu_item_attribute_id";
		const PARENT_PROPERTY = "menuItem";
		const SINGULAR = "Attribute";
		const PLURAL = "Attributes";
		const LABEL_PROPERTY = "menuAttribute";
		const HAS_POSITION = true;

		// MenuItemAttribute
		/** @var string */
		public $label = '';

		/** @var MenuItem */
		public $menuItem;

		/** @var MenuAttribute */
		public $menuAttribute;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new LinkToProperty("menuItem", "menu_item_id", MenuItem::class));
			static::addProperty(new LinkToProperty("menuAttribute", "menu_attribute_id", MenuAttribute::class));
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

			$this->addElement(new Select("menuAttribute", "Attribute", MenuAttribute::loadOptions()));
		}
		
		/**
		 * Gets the dynamic label script to use for this generator
		 * @return	string	The dynamic label
		 */
		public function getDynamicLabelScript()
		{
			return "return menuAttribute;";
		}

		/**
		* A little cheat to keep the position of Attributes the same over all menus
		*/
		public function set_position()
		{
			$this->setValue('position', $this->menuAttribute->position);
		}
	}
