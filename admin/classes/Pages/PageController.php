<?php
	namespace Pages;

	use Controller\RedirectController;
	use Controller\UrlController;
	
	/**
	 * Displays a normal page to the user
	 */
	class PageController extends UrlController
	{
		const TEMPLATES_LOCATION = DOC_ROOT . "/resources/template/page-types/";

		protected $page;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return	PageController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return ['/$page/*' => self::class];
		}

		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController	$parent		The parent to the Page Child Controller
		 * @param	string[]		$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string			$pattern	The pattern that was matched
		 * @return	UrlController						An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			assert($parent === null || $parent instanceof PageController);
			
			$page = Page::loadForSlug($matches["page"], $parent === null ? null : $parent->page);

			if($page->isNull())
			{
				return null;
			}
			
			return $page->getController();
		}

		/**
		 * Creates a new Page Controller
		 * @param	Page	$page	The Page to output
		 */
		public function __construct(Page $page)
		{
			$this->page = $page;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			$pageTypes = PageType::get();
			$pageType = $this->page->pageType;
			$templateFile = 'pages/page.twig';

			if(isset($pageTypes[$pageType]))
			{
				$templateFile = $pageTypes[$pageType]->template;
			}

			return $templateFile;
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();
			$variables["banners"] = $this->page->getVisibleBanners();

			$variables["currentNavItem"] = $this->page;

			$variables["message"] = outputMessage();
			$variables["originalPage"] = $this->page;
			$variables["page"] = $this->page->getContentPage();

			return $variables;
		}

		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			if($this->page->isRedirect)
			{
				RedirectController::redirect($this->page->getNavPath());
			}
			else
			{
				parent::output();
			}
		}
	}