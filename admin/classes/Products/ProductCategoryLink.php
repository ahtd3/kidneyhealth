<?php
	namespace Products;

	use Core\Columns\Column;
	use Core\Columns\ColumnCell;
	use Core\EntityLink;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;

	use Users\User;

	/**
	 * Linking class between Products and Categories
	 */
	class ProductCategoryLink extends EntityLink
	{
		/*~~~~~
		 * setup
		 **/
		// Entity/Generator
		const ID_FIELD = "product_category_id";
		const TABLE = "product_category_links";
		const SINGULAR = 'Product';
		const PLURAL = 'Products';
		const HAS_POSITION = true;
		const PARENT_PROPERTY = 'category';

		// EntityLink
		const LINK_PROPERTIES = ["product", "category"];

		/** @var Product */
		public $product;
		private $_product = null;

		/** @var ProductCategory */
		public $category;


		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Product Category interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("position", "position", "int"));
			static::addProperty(new LinkToProperty("product", "product_id", Product::class));
			static::addProperty(new LinkToProperty("category", "category_id", ProductCategory::class));
		}

		/**
		 * Gets the array of Columns that are displayed to the user for this object type
		 * @return    Column[]        The Columns to display
		 */
		public static function getColumns(): array
		{
			return Product::getColumns();
		}

		/**
		 * Gets name => id pairs for all the categories inside of a category
		 * @param	ProductCategory $root The root category
		 * @return	ProductCategory[]			All the categories inside that category
		 */
		public static function getProductCategoryArray(ProductCategory $root)
		{
			$productCategories = [];

			foreach(ProductCategoryLink::loadAllFor("category", $root) as $productCategory)
			{
				$productCategories[$productCategory->product->name] = $productCategory->id;
			}

			return $productCategories;
		}

		/**
		 * Gets the value for a particular column
		 * @param	Column $column The Column to get the value for
		 * @return	ColumnCell	The value for that Column
		 */
		public function getValueForColumn(Column $column): ColumnCell
		{
			return $this->product->getValueForColumn($column);
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Gets the Product belonging to this ProductCategoryLink
		 * @return	Product		The Product
		 */
		public function get_product()
		{
			if($this->_product === null)
			{
				$this->_product = $this->getValue("product");
				$this->_product->category = $this->category;
			}

			return $this->_product;
		}

		/**
		 * get the path to edit this Generator in the admin paneliss
		 * @param	string	$class	The class this is an example of
		 * @return	string			The edit link
		 */
		public function getEditLink(string $class = null)
		{
			return $this->product->getEditLink();
		}

		/**
		 * Delete process triggered by the user
		 * @param	User	$user	The user who triggered the delete process
		 * @param	int		$id		The id that was passed in (in cases where we want to delete something that doesn't exist in the database)
		 */
		public function removeForUser(User $user, int $id)
		{
			if($this->id !== null)
			{
				$this->product->removeForUser($user, $this->product->id);
			}
			else
			{
				Product::load(-$id)->removeForUser($user, -$id);
			}
		}
	}
