<?php
	namespace Products;

	use Core\Elements\BasicEditor;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	
	/**
	 * The content of an information tab for a product
	 */
	class ProductTab extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = "product_tabs";
		const ID_FIELD = "product_tab_id";
		const SINGULAR = 'Tab';
		const PLURAL = 'Tabs';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = "name";
		const PARENT_PROPERTY = "product";
		
		/** @var string */
		public $name = '';
		public $content = '';
		
		/** @var Product */
		public $product;
		
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			static::addProperty(new Property('content', 'content', 'html'));
			static::addProperty(new Property('name', 'name', 'string'));
			static::addProperty(new LinkToProperty("product", "product_id", Product::class));
		}
		
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text('name', 'Name'));
			$this->addElement(new BasicEditor('content', 'Content'));
		}
	}
