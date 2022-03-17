<?php
	namespace Menus;

	use Core\Elements\GeneratorElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	
	/**
	 * A Menu Group contains a group of Menu Items
	 */
	class MenuGroup extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = "menu_groups";
		const ID_FIELD = "menu_group_id";
		const PARENT_PROPERTY = "menu";
		const SINGULAR = "Item Group";
		const PLURAL = "Item Groups";
		const LABEL_PROPERTY = "heading";
		const HAS_POSITION = true;
		const HAS_ACTIVE = true;

		// MenuGroup
		public bool $active = true;
		
		/** @var string */
		public $heading = '';

		/** @var Menu */
		public $menu;

		/** @var GroupedMenuItem[] */
		public $items;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("heading", "heading", "string"));
			static::addProperty(new LinkToProperty("menu", "menu_id", Menu::class));
			static::addProperty((new LinkFromMultipleProperty("items", GroupedMenuItem::class, "menuGroup")));
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

			$this->addElement(new Text("heading", "Heading"));
			$this->addElement(new GeneratorElement("items"));
		}
		
		/**
		 * @return GroupedMenuItem[] just the menu items for display
		 */
		public function getVisibleMenuItems()
		{
			return filterActive($this->items);
		}
	}
