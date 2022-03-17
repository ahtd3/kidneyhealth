<?php
	namespace Products\Options;

	use Core\Columns\PropertyColumn;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Products\Product;

	/**
	 * A group of options
	 */
	class OptionGroup extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = 'option_groups';
		const ID_FIELD = 'option_group_id';
		const SINGULAR = 'Group';
		const PLURAL = 'Groups';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = 'name';
		const PARENT_PROPERTY = 'product';

		// OptionGroup
		// To make priced products simple
		const CHILD_CLASS_TYPE = ProductOption::class;

		/** @var bool */
		public bool $active = true;

		/** @var string */
		public $name = '';

		/** @var Product */
		public $product = null;

		/** @var ProductOption[] */
		public $options;

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
			static::addProperty(new LinkToProperty('product', 'product_id', Product::class));
			static::addProperty((new LinkFromMultipleProperty('options', static::CHILD_CLASS_TYPE, 'group'))->setAutoDelete(true));
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

			$this->addElement(new Text('name', 'Name'));
			$this->addElement(new GeneratorElement('options'));
		}

		/**
		 * @return ProductOption[] just the options which can be displayed
		 */
		public function getOptions()
		{
			return filterActive($this->options);
		}
	}
