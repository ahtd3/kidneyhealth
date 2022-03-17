<?php
	namespace Products;

	use Admin\PreviousPageDetails;

	use Configuration\Registry;

	use Core\Columns\CustomColumn;
	use Core\Columns\InputNumberColumn;
	use Core\Columns\ToggleColumn;

	use Core\Elements\BasicEditor;
	use Core\Elements\Checkbox;
	use Core\Elements\FormOption;
	use Core\Elements\FormOptionGroup;
	use Core\Elements\GeneratorElement;
	use Core\Elements\GridElement;
	use Core\Elements\Group;
	use Core\Elements\Html;
	use Core\Elements\MultipleCheckbox;
	use Core\Elements\Text;
	use Core\Elements\Textarea;

	use Core\Entity;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkFromProperty;
	use Core\Properties\LinkManyManyProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Exception;
	use Files\Image;
	use Forms\Form;
	use Orders\LineItem;
	use Orders\LineItemGenerator;
	use Products\Options\LineItemOption;
	use Products\Options\OptionGroup;
	use Products\Options\PricedOptionGroup;
	use Products\Options\ProductOption;

	use Search\SearchResult;
	use Search\SearchResultGenerator;

	use Template\Banners\BannerSource;
	use Template\PageItem;

	/**
	 * Handles products using the Database Object system
	 */
	class Product extends Generator implements BannerSource, LineItemGenerator, PageItem, SearchResultGenerator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity/Generator
		const TABLE = "products";
		const ID_FIELD = "product_id";
		const SINGULAR = 'Product';
		const PLURAL = 'Products';
		const HAS_ACTIVE = true;
		const LABEL_PROPERTY = "name";
		const PATH_PARENT = 'categories';
		const PARENT_PROPERTY = "category";
		const SLUG_PROPERTY = 'name';
		const SLUG_TAB = "Content";

		// Product
		const DO_ASSOCIATED = false;
		const DO_BANNER = ProductCategory::DO_BANNER;
		const DO_ENQUIRY_FORM = false;
		const DO_TABS = false;
		const DO_SALE = false;
		const DO_STOCK = false;

		/** @var bool */
		public $featured = false;
		public $onSale = false;

		/** @var float */
		public $price = 0;
		public $salePrice = 0;
		public $weight = 0;

		/** @var int */
		public $stock = 0;

		/** @var string */
		public $code = "";
		public $content = "";
		public $mainHeading = "";
		public $name = "";
		public $metaDescription = "";
		public $pageTitle = "";
		public $path = "";
		public $summary = "";

		/** @var AssociatedProduct[] */
		public $associatedProductCategories;
		public $_associatedProductCategories = null;

		/** @var OptionGroup[] */
		public $optionGroups;
		protected $_activeOptionGroups = null;

		/** @var PricedOptionGroup */
		public $pricedOptionGroup;

		/** @var ProductBanner */
		public $banner;

		/** @var ProductCategory */
		public $category;
		private $_category = null;

		/** @var ProductCategory[] */
		public $categories;

		/** @var ProductImage[] */
		public $images;

		/** @var ProductTab[] */
		public $tabs;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Product interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new Property("code", "code", "string"));
			static::addProperty(new Property("pageTitle", "page_title", "string"));
			static::addProperty(new Property("metaDescription", "meta_description", "string"));
			static::addProperty(new Property('mainHeading', 'main_heading', 'string'));
			static::addProperty(new Property("content", "content", "html"));

			static::addProperty(new Property("featured", "featured", "bool"));
			static::addProperty(new Property("onSale", "on_sale", "bool"));
			static::addProperty(new Property("price", "price", "float"));
			static::addProperty(new Property("salePrice", "sale_price", "float"));
			static::addProperty(new Property("stock", "stock", "int"));
			static::addProperty(new Property("weight", "weight", "float"));

			static::addProperty(new Property("category"));
			static::addProperty((new LinkFromMultipleProperty("productCategories", ProductCategoryLink::class, "product", ["position" => true]))->setAutoDelete(true));
			static::addProperty(new LinkManyManyProperty("categories", ProductCategoryLink::class, 'product')); // LinkManyMany returning Category[]
			static::addProperty((new LinkFromMultipleProperty("associatedProductCategories", AssociatedProduct::class, "from"))->setAutoDelete(true));

			static::addProperty(new LinkFromProperty('banner', ProductBanner::class, 'parent'));
			static::addProperty((new LinkFromMultipleProperty("images", ProductImage::class, "product"))->setAutoDelete(true));

			static::addProperty((new LinkToProperty("pricedOptionGroup", "priced_option_group_id", PricedOptionGroup::class))->setAutoDelete(true));
			static::addProperty((new LinkFromMultipleProperty("optionGroups", OptionGroup::class, "product"))->setAutoDelete(true));
			static::addProperty(new Property("path"));

			static::addProperty((new LinkFromMultipleProperty('tabs', ProductTab::class, 'product'))->setAutoDelete(true));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("name", "Name", function(Product $product)
			{
				return "<a href='{$product->path}' target='_blank'>{$product->name}</a>";
			}));

			static::addColumn(new InputNumberColumn('stock', 'Stock'));

			static::addColumn(new ToggleColumn("featured", "Featured"));

			if(static::DO_SALE)
			{
				static::addColumn(new ToggleColumn("onSale", "On sale"));
			}

			parent::columns();
		}

		/**
		 * Gets all the featured products for display
		 * @param	int			$limit	The limit to place on the number of returned products
		 * @return	static[]			All the featured products, ordered randomly
		 */
		public static function getFeatured($limit = PHP_INT_MAX)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~featured = TRUE "
				. "AND ~active = TRUE "
				. "ORDER BY RAND() "
				. "LIMIT ?";

			return static::makeMany($query, [$limit]);
		}

		/**
		 * Gets all the on sale products for display
		 * @param	int			$limit	The limit to place on the number of returned products
		 * @return	static[]			All the featured products, ordered randomly
		 */
		public static function getOnSale($limit = PHP_INT_MAX)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~onSale = TRUE "
				. "AND ~active = TRUE "
				. "ORDER BY RAND() "
				. "LIMIT ?";

			return static::makeMany($query, [$limit]);
		}

		/**
		 * Gets all uncategorised Products
		 * @return	static[]	Said Products
		 */
		public static function loadAllUncategorised()
		{
			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "WHERE ~id NOT IN "
				   . "("
				   .	"SELECT ~ProductCategoryLink.~product "
				   .	"FROM ~ProductCategoryLink"
				   . ")";

			return static::makeMany($query);
		}

		/**
		 * Loads an object that matches a slug (case insensitive)
		 * @param	string	$slug			The slug to match against
		 * @param	Entity	$parent			The parent of the object matching that slug
		 * @param 	bool 	$checkActive	Do we care about active or not
		 * @return	static					The matching object
		 */
		public static function loadForSlug($slug, Entity $parent = null, bool $checkActive = true)
		{
			if($parent === null)
			{
				// All products must belong to a category
				return static::makeNull();
			}

			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "WHERE ~slug = ? "
				   . ($checkActive ? "AND ~active = TRUE " : "")
				   . "AND ~id IN "
				   . "("
				   .	"SELECT ~ProductCategoryLink.~product "
				   .	"FROM ~ProductCategoryLink "
				   .	"WHERE ~ProductCategoryLink.~category = ? "
				   . ") "
				   . "LIMIT 1";

			return static::makeOne($query, [$slug, $parent->id]);
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{

			$this->addElement((new Text("name", "Product name"))->setHint("Required")->addValidation(Text::REQUIRED), 'Content');
			parent::elements();

			$saleGroup = (new Group('Sale Group'))->addClass('row-of-elements');
			$this->addElement($saleGroup, 'Content');
				$saleGroup->add((new Text('price', 'Price'))->addClass('currency'));
				if(static::DO_SALE)
				{
					$saleGroup->add((new Text('salePrice', 'Sale price'))->addClass('currency'));
					$saleGroup->add(new Checkbox('onSale', 'On Sale'));
				}

			if(static::DO_STOCK)
			{
				$this->addElement(new Text('stock', 'Stock'), 'Content');
			}

			if (Registry::WEIGHT_BASED_SHIPPING)
			{
				$this->addElement((new Text('weight', 'Weight'))->setHint('g'), 'Content');
			}

			$this->addElement((new Text("mainHeading", 'Main heading'))->setHint("if different to product name"), 'Content');
			$this->addElement(new BasicEditor("content", "Description"), "Content");

			if(static::DO_TABS)
			{
				$this->addElement(new GeneratorElement("tabs"), "Tabs");
			}

			$this->addElement(new GeneratorElement("pricedOptionGroup", "Priced Options"), "Options");
			$this->addElement(new GeneratorElement("optionGroups", "General Options"), "Options");

			$this->addElement(new MultipleCheckbox('categories', 'Category', ProductCategory::loadOptions()), 'Categories');

			if(static::DO_BANNER)
			{
				$this->addElement(new GeneratorElement('banner', 'Banner (optional)'), 'Images');
			}
			$this->addElement(new GridElement('images', 'Gallery'), "Images");

			if (static::DO_ASSOCIATED)
			{
				$this->addElement(new GeneratorElement("associatedProductCategories"), "Associated Products");
			}

			$this->addElement((new Text('pageTitle', 'Page title'))->setHint('if different to content title'), 'Metadata / SEO');
			$this->addElement(new Textarea('metaDescription', 'Search result text'), 'Metadata / SEO');
		}

		/**
		 * Gets a single parent category for this product
		 * @return	ProductCategory    The single parent category
		 */
		public function get_category()
		{
			if ($this->_category === null)
			{
				if (count($this->categories) > 0)
				{
					$this->_category = $this->categories[0];
				}
				else
				{
					$this->_category = ProductCategory::makeNull();
				}
			}

			return $this->_category;
		}

		/**
		 * Gets a possible path to this product
		 * @return	string	A possible path
		 */
		public function get_path()
		{
			$category = null;

			if($this->category !== null)
			{
				$category = $this->category;
			}
			else
			{
				foreach($this->categories as $cat)
				{
					if ($cat->active)
					{
						$category = $cat;
						break;
					}
				}
			}

			if ($category !== null && !$category->isNull())
			{
				return "{$category->path}{$this->slug}/";
			}
			else
			{
				return '';
			}
		}

		/**
		 * Gets Options to populate Associated Product dropdown. Does not include this product.
		 * @see AssociatedProduct::elements()
		 * @return	FormOptionGroup[] 	ProductCategoryLink options to link to
		 */
		public function getAssociatedOptions(): array
		{
			$options = [];

			foreach(ProductCategory::loadAllFor('parent', null) as $category)
			{
				$optionGroup = $category->getAssociatedOptionGroupExcludingProduct($this);
				if($optionGroup !== null) $options[] = $optionGroup;
			}

			return $options;
		}

		/**
		 * Gets the categories this product belongs to for display
		 * @return	ProductCategory[]    The categories
		 */
		public function getAssociatedProductCategories()
		{
			if(!static::DO_ASSOCIATED)
			{
				return [];
			}

			if($this->_associatedProductCategories === null)
			{
				$activeCategories = [];
				foreach($this->associatedProductCategories as $link)
				{
					if($link->from->active && $link->to->category->active)
					{
						$activeCategories[] = $link;
					}
				}

				$this->_associatedProductCategories = $activeCategories;
			}

			return $this->_associatedProductCategories;
		}

		public function getAvailableStock()
		{
			return static::DO_STOCK ? $this->stock : 1000;
		}

		/**
		 * @return string the "definitive" link for the product to include in sitemaps, meta tags,
		 *			and anywhere else it appears outside a category context eg featured products
		 */
		public function getCanonicalLink()
		{
			/** @var ProductCategoryLink|null $oldest */
			$oldest = null;
			// active categories only
			foreach ($this->getVisibleCategories() as $category)
			{
				if ($oldest === null || $category->id < $oldest->id)
				{
					$oldest = $category;
				}
			}
			return $oldest->path . $this->slug . "/";
		}

		/**
		 * Gets the categories this product belongs to for display
		 * @return	ProductCategory[]    The categories
		 */
		public function getVisibleCategories()
		{
			return filterActive($this->categories);
		}

		/**
		 * Get active images for display
		 * @return ProductImage[]
		 */
		public function getVisibleImages()
		{
			return filterActive($this->images);
		}

		/**
		 * @return PreviousPageDetails information for admin panel breadcrumbs
		 */
		public function getPreviousPageDetails(): PreviousPageDetails
		{
			return PreviousPageDetails::makeForTableClass(ProductCategory::class, null, static::PLURAL);
		}

		/**
		 * @return float the price after accounting for any conditions like product on sale or type of user logged in
		 */
		public function getPrice()
		{
			return (static::DO_SALE && $this->onSale) ? $this->salePrice : $this->price;
		}

		/**
		 * Gets the path parent sluggable objects for this object
		 * @return	Entity[]	The parent objects (null is considered top level)
		 */
		public function getSlugParents()
		{
			return $this->categories;
		}

		/**
		 * get active tabs for display
		 * @return ProductTab[]
		 */
		public function getVisibleTabs()
		{
			return static::DO_TABS ? filterActive($this->tabs) : [];
		}

		/**
		 * set the current category, adding to categories list if necessary
		 * @param mixed $category
		 */
		public function set_category($category)
		{
			if(!($category instanceof ProductCategory))
			{
				// id passed in as int or str
				$category = ProductCategory::load($category);
			}

			$inArray = false;
			foreach ($this->categories as $cat)
			{
				if ($cat->id === $category->id)
				{
					$inArray = true;
					break;
				}
			}
			if (!$inArray)
			{
				$this->categories = array_merge($this->categories, [$category]);
			}
			$this->_category = $category;
		}

		/*~~~~~
		 * Interface methods
		 **/

		// region BannerSource

		/**
		 * Get the items to display as the page banner/slideshow
		 *
		 * return SiteBanner[] the banner for the product, inheriting from current category
		 */
		public function getVisibleBanners()
		{
			$banner = $this->banner;
			if($banner->image === null)
			{
				return $this->category->getVisibleBanners();
			}
			else
			{
				return [$banner];
			}
		}

		// endregion

		//region LineItemGenerator

		/**
		 * Gets a string that will uniquely identify this class from other Line Item Generators
		 * @return    string    The identifier
		 */
		public static function getClassLineItemGeneratorIdentifier(): string
		{
			return "Product";
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param    string $identifier The identifier that will identify a Line Item Generator
		 * @return    LineItemGenerator                    The original object that generated this Line Item, or null if such cannot be found
		 */
		public static function loadForLineItemGeneratorIdentifier($identifier): ?LineItemGenerator
		{
			$product = static::load($identifier);
			return $product->isNull() ? null : $product;
		}

		/**
		 * Updates, replaces or deletes an existing Line Item
		 * Basically this is entended to check if the line item generator is still current (active, still exists etc)
		 * And update to whatever the current line item generator is like (price)
		 *
		 * @param    string   $identifier The identifier that will identify the Line Item Generator
		 * @param    LineItem $current    The line item to update
		 * @return    LineItem                    The updated line item, or null if it's been removed
		 */
		public static function updateLineItem($identifier, LineItem $current): ?LineItem
		{
			$product = static::load($identifier);

			if(!$product->active || $product->isNull())
			{
				return null;
			}

			if(static::DO_STOCK && $product->stock <= 0)
			{
				addMessage($product->name . ' is out of stock');
				return null;
			}

			if($current instanceof ProductLineItem)
			{
				foreach($current->options as $option)
				{
					if(!$option->optionGroup->active || $option->optionGroup->isNull() || !$option->option->active || $option->optionGroup->isNull())
					{
						return null;
					}
				}

				// New options must have been added to the product,
				if(count($current->options) !== count($product->optionGroups))
				{
					return null;
				}
			}

			if(static::DO_STOCK && $current->quantity > $product->stock)
			{
				//I don't think this message is displayed, but I also think this should never happen, since the quantity input should have a max value
				addMessage($product->name . ' has less than ' . $current->quantity . ' available, ' . $product->stock . ' has been added to your cart');
				$current->quantity = $product->stock;
			}

			$current->price = $product->getPrice();
			$current->title = $product->name;
			$current->itemWeight = $product->weight;

			return $current;
		}

		/**
		 * Gets a Line Item from this object. The quantity, parentClassIdentifier and parentIdentifier will be filled in after you return the line item
		 * @return    LineItem    The generated line item
		 */
		public function getLineItem(): LineItem
		{
			$lineItem = new ProductLineItem();
			$lineItem->title = $this->name;
			$lineItem->price = $this->getPrice();
			$lineItem->itemWeight = $this->weight;

			$lineItemOptions = [];

			foreach($this->optionGroups as $optionGroup)
			{
				if(!isset($_POST["options"][$optionGroup->id]))
				{
					throw new Exception("Please select a " . $optionGroup->name);
				}

				$option = ProductOption::load($_POST["options"][$optionGroup->id]);

				if(!$option->active || $option->group !== $optionGroup)
				{
					throw new Exception("That " . $optionGroup->name . " is not valid, please select another");
				}

				$lineItemOption = new LineItemOption();
				$lineItemOption->optionGroup = $optionGroup;
				$lineItemOption->option = $option;
				$lineItemOptions[] = $lineItemOption;
			}

			$lineItem->options = $lineItemOptions;

			return $lineItem;
		}

		/**
		 * Gets a link to edit this Line Item Generator in the admin, may return null
		 * @return    string    The link to edit this generator in the admin panel
		 */
		public function getLineItemEditLink(): ?string
		{
			return $this->getEditLink();
		}

		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getLineItemGeneratorIdentifier(): string
		{
			return $this->id;
		}

		/**
		 * Gets a representative thumbnail image for this Line Item Generator, may return null
		 * @return    Image    The representative image
		 */
		public function getLineItemImage(): ?Image
		{
			$activeImages = ProductImage::loadAllForMultiple(['active' => true, 'product' => $this]);
			return ($activeImages[0] ?? new ProductImage())->thumbnail;
		}

		/**
		 * Gets a link to this Line Item Generator on the site, may return null
		 * @return    string    A link to view this item on the site
		 */
		public function getLineItemLink(): ?string
		{
			$path = $this->path;

			if($path === "")
			{
				return null;
			}

			return $path;
		}

		//endregion

		// region PageItem

		/**
		 * Gets the content title for the item (where this is not laid out by the user in an Editor element)
		 * don't do splitting and html wrapping here because getPageTitle() falls back to use this logic, set up a filter in Controller\Twig.php
		 * @return	string	Usually the contents of the page h1, but also useful for a subheading/link/list item.
		 */
		public function getMainHeading()
		{
			return $this->mainHeading !== '' ? $this->mainHeading : $this->name;
		}

		/**
		 * Gets the <meta name="description" /> for the item
		 * @return	string	The text to use in the metadata description
		 */
		public function getMetaDescription()
		{
			// if we were autogenerating it from entered content in another field we'd do that here
			return $this->metaDescription;
		}

		/**
		 * Gets the page <title> for the item
		 * Usually falls back to getMainHeading(), if it doesn't just wrap that method.
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
			// unused, properly this would render a template from theme/twig/products/sections/ containing the product_content block from products/product-page.twig
			return $this->content;
		}

		// additional for Product
		/**
		 * Normally outputs the Product enquiry form with a hidden element for this product prepended
		 * On more complex sites might output a twig template
		 * @return String 	Html for an enquiry form
		 */
		public function outputEnquiryForm()
		{
			if(!static::DO_ENQUIRY_FORM)
			{
				return '';
			}
			else
			{
				$form = Form::load(Form::PRODUCT_ENQUIRY_ID);
				$form->prepend = '<input name="Product" type="hidden" value="' . $this->name . '" />';
				return $form->outputForm();
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
			/** @var ProductImage[] $images */
			$images = $this->getVisibleImages();
			return (new SearchResult($this->path, $this->name, $this->getMetaDescription()))
						->setImage(isset($images[0]) ? $images[0]->image : null)
						->setRelevance($this->relevance);
		}

		//endregion

		// region SitemapItem
		// adding Products to the sitemap is handled by ProductCategory
		// end region
	}
