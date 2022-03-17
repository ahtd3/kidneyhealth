<?php
	namespace Products\Options;

	use Core\Columns\PropertyColumn;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	
	/**
	 * A single option in a group of options
	 */
	class ProductOption extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = 'product_options';
		const ID_FIELD = 'product_option_id';
		const SINGULAR = 'Option';
		const PLURAL = 'Options';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = 'name';
		const PARENT_PROPERTY = 'group';

		// ProductOption
		// To make priced products simple
		const PARENT_CLASS_TYPE = OptionGroup::class;

		/** @var bool */
		public bool $active = true;

		/** @var string */
		public $name = '';

		/** @var OptionGroup */
		public $group;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property('name', 'name', 'string'));
			static::addProperty(new LinkToProperty('group', 'group_id', static::PARENT_CLASS_TYPE));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn('name', 'Name'));

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

			$this->addElement((new Text('name', 'Name')));
		}
	}
