<?php
	namespace Menus;


	use Core\Columns\PropertyColumn;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	
	/**
	 * A single item (food or drink or whatever) in the menu
	 */
	class MenuItem extends Generator
	{
		const TABLE = 'menu_items';
		const ID_FIELD = 'menu_item_id';
		const SINGULAR = 'Menu item';
		const PLURAL = 'Menu items';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = 'name';
		const PARENT_PROPERTY = "menu";

		public bool $active = true;
		public string $name = '';
		public Menu $menu;
		public MenuGroup $group;

		/** @var MenuItemAttribute[] */
		public array $itemAttributes;

		/** @var MenuItemPrice */
		public MenuItemPrice $menuItemPrice; // An item will always have at least one price

		/** @var MenuItemPrice[] */
		public array $prices; // Optional additional prices.

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new Property('name', 'name', 'string'));
			static::addProperty(new LinkToProperty("menu", "menu_id", Menu::class));
			static::addProperty(new LinkToProperty("menuGroup", "menu_group_id", MenuGroup::class));
			static::addProperty((new LinkFromMultipleProperty('itemAttributes', MenuItemAttribute::class, 'menuItem')));
			static::addProperty((new LinkToProperty('menuItemPrice', 'menu_item_price_id', MenuItemPrice::class))->setAutoDelete(true));
			static::addProperty((new LinkFromMultipleProperty('prices', MenuItemPrice::class, 'menuItem')));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		public static function columns()
		{
			static::addColumn(new PropertyColumn('Name', 'name'));
			
			parent::columns();
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text('name', 'Name'));
			$this->addElement(new GeneratorElement('menuItemPrice'));
			$this->addElement(new GeneratorElement('prices'));
			$this->addElement(new GeneratorElement('itemAttributes'));
		}
		
		/**
		 * @return MenuItemPrice[] just the prices for display
		 */
		public function getPrices()
		{		
			return filterActive($this->prices);
		}
	}
