<?php
	namespace Configuration;

	use Admin\AdminNavItem;
	use Blog\BlogArticle;
	use Forms\Form;
	use Galleries\Gallery;
	use Menus\Menu;
	use Orders\LineItemGenerator;
	use Orders\Order;
	use Orders\ShippingRegion;
	use Pages\Page;
	use Products\Options\PricedProductOption;
	use Products\Product;
	use Products\ProductCategory;
	use Redirect;
	use Template\ShortCodes\ShortCodeSupport;
	use Testimonial;
	use Users\Administrator;
	
	/**
	 * Handles global module configuration
	 * Will eventually be an ORM object pulling settings from the database
	 */
	class Registry
	{
		/**
		 *	Default admin email address, used for dev sites
	   	 * @var string DEV_EMAIL send site error alerts etc to this address, usually leave this
	   	 *		as the address of the responsible Activate Design developer or programmer/support@ad
		*/
		const DEV_EMAIL = 'programmer@activatedesign.co.nz';
		
		// Module constants, set to true to enable a specific module
		const ADMINISTRATORS = true;
		const BLOG = true;
		const CART = false;
		const CONFIGURATION = true;
		const DISCOUNTS = false;
		const FAQS = true;
		const FORMS = true;
		const GALLERIES = true;
		const HOVER_CART = false;
		const MENUS = false;
		const ORDERS = false;
		const PAGES = true;
		const BILL_PAYMENTS = false;
		const PRODUCTS = false;
		const SHIPPING = false;
		const WEIGHT_BASED_SHIPPING = false;
		const TESTIMONIALS = false;
		const USERS = false;

		// custom page
		const HOME = true;

		const SEARCHABLE_CLASSES = [Page::class, ProductCategory::class, Product::class, BlogArticle::class];

		/** @var string[]|ShortCodeSupport[] */
		const SHORTCODE_CLASSES = [Form::class, Gallery::class, Page::class];
		const SITEMAP_CLASSES = [Page::class, BlogArticle::class, ProductCategory::class, Menu::class];
		const SLUGGED_CLASSES = [Page::class, BlogArticle::class, ProductCategory::class, Product::class, Menu::class];
		
		/** @var string[]|LineItemGenerator[] */
		const LINE_ITEM_GENERATING_CLASSES = [Product::class, PricedProductOption::class, ShippingRegion::class];

		/**
		 * The page that shows up first in the admin
		 * @return	string	The path to the admin page
		 */
		public static function getDefaultView()
		{
			return Page::getAdminNavLink();
		}

		/**
		 * Gets the list of links for admin panel display
		 * @return	AdminNavItem[]		The nav items
		 */
		public static function getAdminNavItems()
		{
			/** @var AdminNavItem[] $items */
			$items =
			[
				Order::getAdminNavItem(),
				ProductCategory::getAdminNavItem(),
				Menu::getAdminNavItem(),
				Page::getAdminNavItem(),
				BlogArticle::getAdminNavItem(),
				Form::getAdminNavItem(),
				Gallery::getAdminNavItem(),
				Testimonial::getAdminNavItem(),
				Administrator::getAdminNavItem(),
				Configuration::getAdminNavItem(),
				Redirect::getAdminNavItem(),
				new AdminNavItem("https://www.activehost.co.nz/Help-Guides/CMS-Help", "Help", [], true, [], true)
			];

			return $items;
		}

		/**
		 * Checks if a module requires another module to be enabled, or something else to be created etc, 
		 * and outputs a message for the admin
		 */
		public static function checkModuleDependencies() 
		{
			if (static::WEIGHT_BASED_SHIPPING && !static::SHIPPING) 
			{
				addMessage('Weight Based Shipping module requires Shipping module');
			}
			
			if (static::WEIGHT_BASED_SHIPPING && (Form::SHIPPING_ENQUIRY_ID === null || Form::load(Form::SHIPPING_ENQUIRY_ID)->isNull()))
			{
				addMessage('Weight Based Shipping requires Form::SHIPPING_ENQUIRY_ID to be set and a form created');
			}
		}
	}
