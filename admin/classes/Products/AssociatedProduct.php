<?php
	namespace Products;

	use Core\Elements\Select;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	
	/**
	 * Associates a Product with another Product
	 */
	class AssociatedProduct extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = "associated_products";
		const ID_FIELD = "associated_product_id";
		const PARENT_PROPERTY = "from";
		const SINGULAR = "Associated Product";
		const PLURAL = "Associated Products";
		const HAS_POSITION = true;
		const LABEL_PROPERTY = "to";

		/** @var Product */
		public $from;
		
		/** @var ProductCategoryLink */
		public $to;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * @inheritdoc
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new LinkToProperty("from", "from_id", Product::class));
			static::addProperty(new LinkToProperty("to", "to_id", ProductCategoryLink::class));
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * @inheritdoc
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement(new Select("to", "Product", $this->from->getAssociatedOptions()));
		}
	}
