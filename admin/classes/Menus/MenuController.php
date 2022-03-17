<?php
	namespace Menus;

	use Controller\UrlController;
	use Pages\Page;
	use Pages\PageController;
	
	/**
	 * A Menu Controller handles displaying Menus
	 */
	class MenuController extends PageController
	{
		private $menu;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * @return	PageController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$product/' => ProductController::class]
		 */
		protected static function getChildPatterns()
		{
			return ['/$menu/' => self::class];
		}
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController	$parent		The parent to the Page Child Controller
		 * @param	string[]		$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string			$pattern	The pattern that was matched
		 * @return	UrlController						An object of this type, or null if one can't be found
		 */
		public static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			$menu = Menu::loadForSlug($matches["menu"]);

			if($menu->isNull() || !$parent instanceof PageController)
			{
				return null;
			}

			return new self($parent->page, $menu);
		}

		/**
		 * Creates a new Menu Controller object
		 * @param	Page $page The page to display
		 * @param	Menu $menu The article to display
		 */
		public function __construct(Page $page, Menu $menu = null)
		{
			parent::__construct($page);

			$this->menu = $menu;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			if($this->menu !== null)
			{
				return "menus/menu-page.twig";
			}
			else
			{
				return "menus/menus-page.twig";
			}
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();

			if($this->menu !== null)
			{
				$variables["currentNavItem"] = $this->menu;
				$variables["menu"] = $this->menu;
			}
			else
			{
				$variables["menus"] = Menu::loadAllFor('active', true);
			}

			return $variables;
		}
	}
