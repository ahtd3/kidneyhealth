<?php
	namespace Products;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;

	use Configuration\Registry;

	use Core\Columns\CustomColumn;
	use Core\Columns\ToggleColumn;

	use Core\Elements\BasicEditor;
	use Core\Elements\FormOption;
	use Core\Elements\FormOptionGroup;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Group;
	use Core\Elements\ImageElement;
	use Core\Elements\Select;
	use Core\Elements\Text;
	use Core\Elements\Textarea;

	use Core\Entity;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkFromProperty;
	use Core\Properties\LinkManyManyProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Files\Image;

	use Pages\Page;
	use Pages\PageType;

	use Search\SearchResult;
	use Search\SearchResultGenerator;

	use SitemapItem;

	use Template\Banners\BannerSource;
	use Template\NavItem;
	use Template\PageItem;

	use Users\User;

	/**
	 * Handles categories using the Database Object system
	 */
	class ProductCategory extends Generator implements AdminNavItemGenerator, BannerSource, NavItem, PageItem, SearchResultGenerator, SitemapItem
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = "product_categories";
		const ID_FIELD = "category_id";
		const SINGULAR = 'Category';
		const PLURAL = 'Categories';
		const HAS_POSITION = true;
		const HAS_ACTIVE = true;
		const LABEL_PROPERTY = "name";
		const PATH_PARENT = 'pathParent';
		const PARENT_PROPERTY = "parent";
		const SLUG_PROPERTY = 'name';
		const SLUG_TAB = "Content";

		const SUBITEM_PROPERTY = 'children';
		//Must be defined because CategoryChild doesn't have a static::PLURAL etc that can be used
		const SUBITEM_SINGULAR = 'item';
		const SUBITEM_PLURAL = 'items';

		// Product
		const DO_BANNER = Page::DO_BANNER;
		const DO_HTML = false;
		const DO_IMAGE = false;
		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/category/";
		// we usually want these to be consistent
		const THUMBNAIL_WIDTH = ProductImage::THUMBNAIL_WIDTH;
		const THUMBNAIL_HEIGHT = ProductImage::THUMBNAIL_HEIGHT;
		const THUMBNAIL_RESIZE_TYPE = ProductImage::THUMBNAIL_RESIZE_TYPE;

		/** @var bool */
		public $onNav = false;

		/** @var string */
		public $content = "";
		public $description = "";
		public $metaDescription = "";
		public $name = "";
		public $pageTitle = "";
		public $path = "";
		public $mainHeading = "";

		/** @var CategoryBanner */
		public $banner = null;

		/** @var Image null */
		public $image = null;

		/** @var Page|ProductCategory */
		public $pathParent;
		private $_pathParent = null;

		/** @var Product[] */
		public $allProducts;
		private $_allProducts = null;
		private $allActiveProducts = null;
		public $products;

		/** @var array<Product|ProductCategory> */
		private $_children = null;

		/** @var ProductCategory */
		public $parent = null;

		/** @var ProductCategoryLink[] */
		public $productLinks;
		public $children = null;

		/** @var ProductCategory[] */
		public $subcategories;

		/** @var bool|null */
		private $isSelected = null;

		/** @var string */
		private $_path = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Category interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new Property("mainHeading", "main_heading", "string"));
			static::addProperty(new Property("description", "description", "string"));
			static::addProperty(new Property("content", "content", "html"));
			static::addProperty(new Property("active", "active", "bool"));
			static::addProperty(new Property("onNav", "on_nav", "bool"));
			static::addProperty(new Property("position", "position", "int"));
			static::addProperty(new LinkToProperty("parent", "parent_id", ProductCategory::class));
			static::addProperty(new LinkFromMultipleProperty("subcategories", ProductCategory::class, "parent", ["position" => true]));
			static::addProperty(new Property("allProducts"));
			static::addProperty(new LinkFromMultipleProperty("children", CategoryChild::class, 'parent'));
			static::addProperty(new ImageProperty("image", "image", static::IMAGE_LOCATION, static::THUMBNAIL_WIDTH, static::THUMBNAIL_HEIGHT, static::THUMBNAIL_RESIZE_TYPE));
			static::addProperty(new LinkFromProperty('banner', CategoryBanner::class, 'parent'));
			static::addProperty(new Property("pathParent"));
			static::addProperty(new Property("path"));
			static::addProperty(new Property('metaDescription', 'meta_description', 'string'));
			static::addProperty(new Property('pageTitle', 'page_title', 'string'));
			static::addProperty(new LinkFromMultipleProperty("productLinks", ProductCategoryLink::class, "category"));
			static::addProperty(new LinkManyManyProperty("products", ProductCategoryLink::class, "category"));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("name", "Name", function(ProductCategory $category)
			{
				if ($category->pathParent->isNull())
				{
					return $category->name;
				}
				else
				{
					return "<a href='" . $category->path . "' target='_blank'>" . $category->name . "</a>";
				}
			}));
			
			parent::columns();
			
			static::removeColumn("active");
			static::addColumn(new ToggleColumn("active", "Active", fn(ProductCategory $category) => $category->id === null), "edit");
		}

		/**
		 * Gets the singular subitem name
		 * @param	string|Generator|null	$type	The class name for the type of subitem to get the name for
		 * @return	string|null						The singular subitem name, or null if there's no subitems
		 */
		public static function getSubitemSingular($type): ?string
		{
			if(is_a($type, ProductCategory::class, true))
			{
				return ProductCategory::SINGULAR;
			}
			else if(is_a($type, Product::class, true))
			{
				return Product::SINGULAR;
			}
			else
			{
				return parent::getSubitemSingular($type);
			}
		}

		/**
		 * Gets the top level categories
		 * @return	static[]	The top level categories
		 */
		public static function getTopLevelCats()
		{
			return static::loadAllForMultiple(['parent' => null, 'active' => true]);
		}

		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int			$page	The page to load, if handling pagination
		 * @return	static[]			The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			$categories = static::loadAllFor("parent", null);
			$products = Product::loadAllUncategorised();

			if(count($products) > 0)
			{
				$category = new ProductCategory;
				$category->name = "Uncategorised";

				$productLinks = [];

				foreach($products as $product)
				{
					$productLink = new ProductCategoryLink;
					$productLink->product = $product;
					$productLink->category = $category;
					$productLink->id = -$product->id; // So we can track this product when deleted
					$productLinks[] = $productLink;
				}

				$category->children = $productLinks;
				$categories[] = $category;
			}

			return $categories;
		}

		/**
		 * Loads an object that matches a slug (case insensitive)
		 * @param    string $slug        The slug to match against
		 * @param    Entity $parent      The parent of the object matching that slug
		 * @param    bool   $checkActive For use in Generator
		 * @return    static                    The matching object
		 */
		public static function loadForSlug($slug, Entity $parent = null, bool $checkActive = true)
		{
			if(static::SLUG_PROPERTY === "")
			{
				return static::makeNull();
			}

			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE LOWER(~slug) = ? ";

			$parameters = [strtolower($slug)];

			if ($parent instanceof Page)
			{
				$query .= "AND ~parent IS NULL ";
			}
			else if ($parent !== null)
			{
				$query .= "AND ~parent = ? ";
				$parameters[] = $parent->id;
			}

			$query .= "LIMIT 1";

			return static::makeOne($query, $parameters);
		}

		/**
		 * Gets the first Category
		 * @return	ProductCategory	The first Category
		 */
		public static function loadFirst()
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~active = TRUE "
				 . "AND ~parent IS NULL "
				. "ORDER BY ~position "
				. "LIMIT 1";

			return static::makeOne($query);
		}

		/**
		 * Loads the category options for a select box
		 * @param	bool                 $includeNone Whether to include a "none" option
		 * @param	ProductCategory|null $parent      A category to only load the children of
		 * @param	int                  $level       The number of dashes to insert in front of the name
		 * @param	ProductCategory|null $current     The current category, it and its descendants be left out of the options if provided
		 * @return	FormOption[]                        A list of category options
		 */
		public static function loadOptions(bool $includeNone = false, ?ProductCategory $parent = null, int $level = 0, ?ProductCategory $current = null): array
		{
			$options = [];

			if($includeNone)
			{
				$options[] = new FormOption("None", null);
			}

			if($parent === null)
			{
				$categories = static::loadAllFor('parent', null);
			}
			else
			{
				$categories = $parent->subcategories;
			}

			foreach($categories as $category)
			{
				if($category === $current)
				{
					continue;
				}

				$options[] = new FormOption(str_repeat("-", $level) . " {$category->name}", $category->id);
				$options = array_merge($options, static::loadOptions(false, $category, $level + 1, $current));
			}

			return $options;
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Checks that this object can be edited by a user
		 * @param	User	$user	The user to check
		 * @return	bool			Whether the user can edit this object
		 */
		public function canEdit(User $user)
		{
			if ($this->id === null)
			{
				return false;
			}
			return parent::canEdit($user);
		}

		/**
		 * Sets the Form Elements for this Category
		 */
		protected function elements()
		{
			$this->addElement(new Select('parent', 'Parent category', static::loadOptions(true, null, 0, $this)), "Content");
			$this->addElement((new Text("name", "Menu Text"))->setHint("Required")->addClasses(['half'])->addValidation(Text::REQUIRED), 'Content');
			parent::elements();

			$this->addElement((new Text("mainHeading", 'Main heading'))->setHint("if different to menu text"), 'Content');

			if(static::DO_HTML)
			{
				$this->addElement((new BasicEditor('content', 'Description'))->setHint("Optional"), "Content");
			}
			else
			{
				$this->addElement((new Textarea('description', 'Description'))->setHint("Optional"), "Content");
			}

			if(static::DO_IMAGE)
			{
				$this->addElement(new ImageElement("image", "Image"), "Content");
			}

			if(static::DO_BANNER)
			{
				$this->addElement(new GeneratorElement('banner', 'Banner (optional)'), 'Content');
			}

			$this->addElement((new Text('pageTitle', 'Page title'))->setHint('if different to content title'), 'Metadata / SEO');
			$this->addElement(new Textarea('metaDescription', 'Search result text'), 'Metadata / SEO');
		}

		/**
		 * Gets all the Products that belong to this Category and its subcategories
		 * @return	Product[]	The Products
		 */
		public function get_allProducts()
		{
			if($this->_allProducts === null)
			{
				$products = $this->products;

				foreach($this->subcategories as $subcategory)
				{
					$products = array_merge($products, $subcategory->products);
				}

				$this->_allProducts = $products;
			}

			return $this->_allProducts;
		}

		/**
		 * Gets the children of this category
		 * @return	Product[]|ProductCategory[]	The children of this category
		 */
		public function get_children()
		{
			if($this->_children === null)
			{
				$this->_children = array_merge($this->productLinks, $this->subcategories);

				usort($this->_children, function(Generator $first, Generator $second)
				{
					return $first->position <=> $second->position;
				});
			}

			return $this->_children;
		}

		/**
		 * Gets the path to this category
		 * @return	string	The path
		 */
		public function get_path()
		{
			if($this->_path === null)
			{
				$this->_path = "{$this->pathParent->path}{$this->slug}/";
			}

			return $this->_path;
		}

		/**
		 * Gets the path parent of this category, either a page or another category
		 * @return    Page|ProductCategory	$parent		The path parent of this category
		 */
		public function get_pathParent()
		{
			if ($this->_pathParent === null)
			{
				if ($this->parent->isNull())
				{
					$this->_pathParent = PageType::getPageOfType(PageType::PRODUCTS);
				}
				else
				{
					$this->_pathParent = $this->parent;
				}
			}
			return $this->_pathParent;
		}

		/**
		 * Gets the products that belong to this Category
		 * @return	Product[]	The Products
		 */
		public function get_products()
		{
			/** @var Product[] $products */
			$products = $this->getProperty("products");

			foreach($products as $product)
			{
				$product->category = $this;
			}

			return $products;
		}

		/**
		 * @return Product[] all contained products that can be displayed
		 * not as simple as filtering allProducts on active, because subcategory visibility also has to be taken into account
		 */
		public function getAllVisibleProducts()
		{
			if($this->allActiveProducts === null)
			{
				$products = $this->getVisibleProducts();

				foreach($this->getVisibleSubcategories() as $subcategory)
				{
					$products = array_merge($products, $subcategory->getAllVisibleProducts());
				}

				$this->allActiveProducts = $products;
			}

			return $this->allActiveProducts;
		}

		/**
		 * Generates a FormOptionGroup for this category, containing any child categories and products
		 * @param	Product					$product	The product to get options for (this product will be excluded from the list, since it would be odd to display a product as related to itself)
		 * @return	FormOptionGroup|null				The form option group, or null if this category has no child products
		 */
		public function getAssociatedOptionGroupExcludingProduct(Product $product): ?FormOptionGroup
		{
			$options = [];
			
			// Products are displayed first, to make sure that the relationship between them and their parent group is unambiguous
			foreach($this->productLinks as $productCategory)
			{
				if($productCategory->product !== $product)
				{
					$options[] = new FormOption("{$productCategory->product->name} [{$this->name}]", $productCategory->id);
				}
			}
			
			foreach($this->subcategories as $subcategory)
			{
				$optionGroup = $subcategory->getAssociatedOptionGroupExcludingProduct($product);
				if($optionGroup !== null) $options[] = $optionGroup;
			}
			
			return count($options) > 0 ? new FormOptionGroup($this->name, $options) : null;
		}

		/**
		 * // unused?
		 * @return array<Product|ProductCategory> all immediate children that can be displayed
		 */
		public function getVisibleChildren()
		{
			return filterActive($this->children);
		}

		/**
		 * @return Product[] all immediate children which are products that can be displayed
		 */
		public function getVisibleProducts()
		{
			return filterActive($this->products);
		}

		/**
		 * @return ProductCategory[] all immediate children which are subcategories that can be displayed
		 */
		public function getVisibleSubcategories()
		{
			return filterActive($this->subcategories);
		}

		/**
		 * Checks it this Generator is allowed to be deleted from the admin panel
		 * @return	bool	Whether it's allowed to be deleted
		 */
		public function hasDelete()
		{
			if ($this->id === null)
			{
				return false;
			}
			return true;
		}

		/**
		 * Check if this is a descendant category
		 * @param 	ProductCategory $possibleParent
		 * @return	 bool 			if this category is a descendant of $possibleParent
		 */
		public function isDescendantOf($possibleParent)
		{
			if ($this->parent->isNull())
			{
				return false;
			}
			else if ($this->parent->id === $possibleParent->id)
			{
				return true;
			}
			else
			{
				return $this->parent->isDescendantOf($possibleParent);
			}
		}

		/**
		 * Sets the children of this category
		 * @param	array<Product|ProductCategory> $children The children to set
		 */
		public function set_children($children)
		{
			$this->_children = $children;
		}

		/*~~~~~
		 * Interface methods
		 **/
		// region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(static::getAdminNavLink(), "Products", [static::class, Product::class], Registry::PRODUCTS);
		}

		//endregion

		// region BannerSource
		/**
		 * Get the items to display as the page banner/slideshow
		 *
		 * return SiteBanner[] the uploaded banner for the category or it's parent
		 */
		public function getVisibleBanners()
		{
			$banner = $this->banner;
			if($banner->image === null)
			{
				return $this->pathParent->getVisibleBanners();
			}
			else
			{
				return [$banner];
			}
		}

		// endregion

		// region NavItem

		/**
		 * Gets any children this item has
		 * @return	static[]	The children this item has
		 */
		public function getChildNavItems()
		{
			return $this->getVisibleSubcategories();
		}

		/**
		 * Gets the complete chain of Nav Items from parent to child, including the current Nav Item
		 * @return	NavItem[]	The chain of Nav Items
		 */
		public function getNavItemChain()
		{
			if ($this->parent->isNull())
			{
				$parentChain = PageType::getPageOfType(PageType::PRODUCTS)->getNavItemChain();
			}
			else
			{
				$parentChain = $this->parent->getNavItemChain();
			}

			return array_merge($parentChain, [$this]);
		}

		/**
		 * Gets the label for this item
		 * @return	string	The label for this item
		 */
		public function getNavLabel()
		{
			return $this->name;
		}

		/**
		 * Gets the path to this item
		 * @return	string	The path to this item
		 */
		public function getNavPath()
		{
			return $this->path;
		}

		/**
		 * Gets whether this item is the homepage
		 * @return	bool	Whether this item is the homepage
		 */
		public function isHomepage()
		{
			return false;
		}

		/**
		 * Gets whether this item is currently selected
		 * @param	NavItem $currentNavItem The current nav item
		 * @return	bool	Whether this item is currently selected
		 */
		public function isNavSelected(NavItem $currentNavItem = null)
		{
			if($this->isSelected === null)
			{
				$this->isSelected = isGeneratorSelected($this, $currentNavItem);
			}

			return $this->isSelected;
		}

		/**
		 * Gets whether this item opens in a new window
		 * @return	bool	Whether this item opens in a new window
		 */
		public function isOpenedInNewWindow()
		{
			return false;
		}

		// end region

		// region PageItem

		/**
		 * Gets the content title for the item (where this is not laid out by the user in an Editor element)
		 * don't do splitting and html wrapping here because getPageTitle() falls back to use this logic, set up a filter in Controller\Twig.php
		 * @return	string	Usually the contents of the page h1, but also useful for a subheading/link/list item.
		 */
		public function getMainHeading()
		{
			return $this->mainHeading === '' ? $this->getNavLabel() : $this->mainHeading;
		}

		/**
		 * Gets the <meta name="description" /> for the item
		 * @return	string	The text to use in the metadata description
		 */
		public function getMetaDescription()
		{
			// if we were autogenerating it from entered content in another field we'd do that here
			// for Category it is almost safe to assume we could straight up use description if metaDescription is blank - almost.
			return $this->metaDescription;
		}

		/**
		 * Gets the page <title> for the item
		 * @return	string	The text to use for the page title
		 */
		public function getPageTitle()
		{
			return $this->pageTitle !== '' ? $this->pageTitle : $this->getMainHeading();
		}

		/**
		 * Gets the page content. This will usually just return a property, but might be eg a rendered twig template.
		 * Most templates will probably never bother with this method, calling the property directly.
		 * @return	string	The html to output in the template
		 */
		public function getPageContent()
		{
			if(static::DO_HTML)
			{
				return $this->content;
			}
			elseif($this->description !== '')
			{
				return nl2br($this->description);
			}
			else
			{
				return '';
			}
		}

		// endregion

		//region SearchResultGenerator

		/**
		 * Generates a Search Result for this object
		 * @return    SearchResult    The Search Result
		 */
		public function generateSearchResult()
		{
			return (new SearchResult($this->path, $this->getNavLabel(), $this->getMetaDescription()))
						->setImage($this->image)
						->setRelevance($this->relevance);
		}

		//endregion

		// region SitemapItem

		/**
		 * return paths to active categories and products for inclusion in sitemap
		 * @param ProductCategory $parent
		 * @return string[]
		 */
		public static function getSitemapUrls($parent=null)
		{
			// check if site should be returning items for this module
			if(!Registry::PRODUCTS)
			{
				return [];
			}

			$paths = [];
			static $products = [];

			// completely disabling a parent category can be expected to remove all sub categories and items from the site.
			$categories = static::loadAllForMultiple(['parent'=> $parent, 'active' => true], []);

			foreach($categories as $category)
			{
				$paths[] = $category->path;

				// sub categories
				$paths = array_merge($paths, static::getSitemapUrls($category));

				// products
				foreach($category->getVisibleProducts() as $product)
				{
					// prevent the same product being added from multiple categories
					if(!in_array($product, $products, true))
					{
						$products[] = $product;
					}
				}
			}
			
			if($parent === null)
			{
				foreach($products as $product)
				{
					$paths[] = $product->getCanonicalLink();
				}
			}

			return $paths;
		}

		// endregion
	}