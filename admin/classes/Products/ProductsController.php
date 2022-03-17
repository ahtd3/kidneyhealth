<?php
	namespace Products;

	use Controller\RedirectController;
	use Controller\UrlController;
	use Pages\PageController;
	
	/**
	 * A Blog Controller handles displaying Blog Articles
	 */
	class ProductsController extends PageController
	{
		/** @var ProductCategory */
		public $category;

		/** @var Product */
		public $product;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * @return	PageController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$product/' => ProductsController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/$page/*' => PageController::class,
				'/$category/*' => self::class,
				'/$product/' => self::class
			];
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
			if(isset($matches["category"]))
			{
				if($parent instanceof ProductsController)
				{
					$category = ProductCategory::loadForSlug($matches["category"], $parent->category);
				}
				else
				{
					$category = ProductCategory::loadForSlug($matches["category"]);
				}

				if($category->isNull())
				{
					return null;
				}

				if($parent instanceof PageController)
				{
					$controller = new self($parent->page);
				}
				else
				{
					return null;
				}
				
				$controller->category = $category;

				return $controller;
			}
			else if(isset($matches["product"]) && $parent instanceof ProductsController)
			{
				$product = Product::loadForSlug($matches["product"], $parent->category);

				if($product->isNull())
				{
					return null;
				}

				$controller = new self($parent->page);
				$controller->category = $parent->category;
				$controller->product = $product;

				return $controller;
			}

			return null;
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			if ($this->category === null && $this->product === null)
			{
				$category = ProductCategory::loadFirst();
				if (!$category->isNull())
				{
					RedirectController::redirect($category->path);
					exit;
				}
			}
			
			parent::output();
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			if ($this->product !== null && !$this->product->isNull())
			{
				return 'products/product-page.twig';
			}
			else if ($this->category !== null && !$this->category->isNull())
			{
				return 'products/category-page.twig';
			}
			else
			{
				return 'products/products-page.twig';
			}
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();

			$variables["category"] = $this->category;
			$variables["product"] = $this->product;
			$variables["catNavItems"] = ProductCategory::getTopLevelCats();
			// if Categories display on main menu
			// $variables["currentNavItem"] = $this->category;

			if ($this->product !== null && !$this->product->isNull())
			{
				$variables['banners'] = $this->product->getVisibleBanners();

			}
			elseif($this->category && !$this->category->isNull())
			{
				$variables['banners'] = $this->category->getVisibleBanners();

			}

			return $variables;
		}
	}