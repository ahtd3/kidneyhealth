<?php
	namespace Pages;

	use Blog\BlogController;
	use Configuration\Registry;
	use Menus\MenuController;
	use Menus\MenusPage;
	use Pages\Contact\ContactPage;
	use Pages\Contact\ContactPageController;
	use Pages\Faqs\FaqPage;
	use Pages\FrontPage\FrontPage;
	use Pages\FrontPage\FrontPageController;
	use Payments\BillPayments\BillPaymentController;
	use Products\ProductsController;
	use Pages\Linking\LinkingPage;

	/**
	 * Keeps track of the various page types
	 */
	class PageType
	{
		/* 
		 * PageType constants let us safely refer to a particular type in external classes by using "PageType::CONSTANT" 
		 * without worrying about whether the name itself may have been changed for a particular site eg Blog <=> News
		 * These are names which appear in the Page type dropdown on the page setup tab
		 */
		/** @var string /*/
		const PAGE = "Page";
		const BLOG = "Blog";
		const CONTACT = "Contact";
		const FAQS = "FAQs";
		const FRONT_PAGE = "Front Page";
		const MENUS = "Menus";
		const BILL_PAYMENTS = "Payments";
		const PRODUCTS = "Products";
		const TESTIMONIALS = "Testimonials";
		
		// custom type page
		const HOME = 'Home';

		/** @var class-string<Page> */
		public string $class = Page::class;
		public string $template = "page";

		/** @var class-string<PageController> */
		public string $controller = PageController::class;

		/**
		 * Gets the page types
		 * Add new items to appear in the Page type dropdown on the page Setup tab
		 * @return	self[]	The page types
		 */
		public static function get()
		{
			$types = [static::PAGE => new self];
			
			if(Registry::BLOG)
			{
				$types[static::BLOG] = self::createWithController(Page::class, BlogController::class);
			}
			
			$types[static::CONTACT] = new self(ContactPage::class, "pages/contact-page.twig", ContactPageController::class);
			
			if(Registry::FAQS)
			{
				$types[static::FAQS] = new self(FaqPage::class, "pages/faq-page.twig");
			}
			
			$types[static::FRONT_PAGE] = new self(FrontPage::class, 'pages/front-page.twig', FrontPageController::class);
			
			if(Registry::MENUS)
			{
				$types[static::MENUS] = self::createWithController(MenusPage::class, MenuController::class);
			}
			
			if(Registry::BILL_PAYMENTS)
			{
				$types[static::BILL_PAYMENTS] = new self(Page::class, "payments/bill-payments-page.twig", BillPaymentController::class);
			}
			
			if(Registry::PRODUCTS)
			{
				$types[static::PRODUCTS] = self::createWithController(Page::class, ProductsController::class);
			}
			
			if(Registry::TESTIMONIALS)
			{
				$types[static::TESTIMONIALS] = new self(Page::class, "pages/testimonials-page.twig");
			}

			if(Registry::HOME)
			{
				$types[static::HOME] = new self(Page::class, "pages/home-page.twig");
			}

			$types["Linking Page"] = new static(LinkingPage::class, "pages/linking-page.twig");
			$types["Your Kidneys Page"] = new static(LinkingPage::class, "pages/your-kidneys-page.twig");
			$types["Health Professionals Page"] = new static(LinkingPage::class, "pages/health-professionals-page.twig");
			$types["For Patients Page"] = new static(LinkingPage::class, "pages/for-patients-page.twig");
			$types["Regional Page"] = new static(Page::class, "pages/regional-page.twig");
			$types["Support Us Page"] = new static(Page::class, "pages/support-us.twig");
			$types["About us Page"] = new static(Page::class, "pages/about-us.twig");

			return $types;
		}

		/**
		 * A controller only option for when you have page templates defined in the controller
		 * @param	string	$class			The page's class
		 * @param	string	$controller		The page's controller
		 * @return	static 					A PageType that pulls its template from the controller
		 */
		private static function createWithController($class = Page::class, $controller = PageController::class)
		{
			$type = new self($class);
			$type->controller = $controller;
			return $type;
		}

		/**
		 * The path for an arbitrary page with a specific $pageType, usually for output in a template file
		 * @param	string	$type	Type of the page ('pageType' property)(usually a PageType constant)
		 * @param 	bool 	$restrictToActive	Usually we are looking for a page which can be displayed to visitors 
		 * @return	string			The path to that page
		 */
		public static function getPathForType(string $type, bool $restrictToActive = true): string
		{
			$page = static::getPageOfType($type, $restrictToActive);
			return $page->isNull() ? '' : $page->getNavPath();
		}

		/**
		 * Gets a page for an arbitrary page* with a specific $pageType
		 * (first one found ordered by position, id (positions aren't absolute because of tree structure))
		 * @param	string	$type	Type of the page ('pageType' property)
		 * @param 	bool 	$restrictToActive	Usually we are looking for a page which can be displayed to visitors
		 * @return	Page			A page of that type, or a null type page
		 */
		public static function getPageOfType(string $type, bool $restrictToActive = true): Page
		{
			if(!$restrictToActive)
			{
				return Page::loadFor('pageType', $type);
			}
			else
			{
				// try for an active page, but fall back to an inactive page if there are none of those available
				$page = Page::loadForMultiple(['pageType' => $type, 'active' => true]);
				return $page->isNull() ? static::getPageOfType($type, false) : $page; 
			}
		}
		
		/**
		 * Creates a new page type
		 * @param	class-string<Page>				$class			The page's class
		 * @param	string							$template		The page's template
		 * @param	class-string<PageController>	$controller		The page's controller
		 */
		private function __construct(string $class = Page::class, string $template = "pages/page.twig", $controller = PageController::class)
		{
			$this->class = $class;
			$this->template = $template;
			$this->controller = $controller;
		}
	}
