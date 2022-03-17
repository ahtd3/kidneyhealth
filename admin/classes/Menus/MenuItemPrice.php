<?php

	namespace Menus;

	use Core\Columns\PropertyColumn;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	
	/**
	 * Class so that menu items can have one or many prices, with some useful text about that price
	 */
	class MenuItemPrice extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = 'menu_item_prices';
		const ID_FIELD = 'menu_item_price_id';
		const SINGULAR = 'Price';
		const PLURAL = 'Prices';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = 'price';
		const PARENT_PROPERTY = 'menuItem';
		
		// MenuItemPrice
		/** @var bool */
		public bool $active = true;

		/** @var float */
		public $price = 0;
		
		/** @var string */
		public $label = '';
		
		/** @var MenuItem */
		public $menuItem;
		
		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		public static function properties()
		{
			parent::properties();
			static::addProperty(new Property('price', 'price', 'float'));
			static::addProperty(new Property('label', 'label', 'string'));
			static::addProperty((new LinkToProperty('menuItem', 'menu_item_id', MenuItem::class))->setAllowNull(true)); // This is just used by the automated tests to prevent issues relating to the fact that prices are both a LinkTo and a LinkFromMultiple relationship
		}
		
		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		public static function columns()
		{
			static::addColumn(new PropertyColumn('Name', 'name'));
			parent::columns();
		}
		
		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		public function elements()
		{
			parent::elements();
			$this->addElement((new Text('price', 'Price'))->addClasses(['half', 'first', 'currency']));
			$this->addElement((new Text('label', 'Price label'))->setHint("Optional")->addClass('half'));
		}
		
		/**
		 * Gets the dynamic label script to use for this generator
		 * @return	string	The dynamic label
		 */
		public function getDynamicLabelScript()
		{
			return "return '\$' + Number(price).toFixed(2) + ( label !== '' ? ' - ' + label : '');";
		}
	}
