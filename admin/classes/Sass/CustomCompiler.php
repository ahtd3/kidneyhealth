<?php
	namespace Sass;

	use Configuration\Registry;
	use Pages\Page;
	use ScssPhp\ScssPhp\CompilationResult;
	use ScssPhp\ScssPhp\Compiler;
	use ScssPhp\ScssPhp\Exception\SassException;

	/**
	 * A custom Compiler that automatically adds the extra SASS functions we want
	 */
	class CustomCompiler extends Compiler
	{
		public ?string $map = null;

		/**
		 * Constructor
		 */
		public function __construct()
		{
			parent::__construct();

			// Setup the various custom functions
			$this->registerFunction("has-slideshow", [$this, "hasSlideshow"]);
			$this->registerFunction("has-blog", [$this, "hasBlog"]);
			$this->registerFunction("has-cart", [$this, "hasCart"]);
			$this->registerFunction("has-products", [$this, "hasProducts"]);
			$this->registerFunction("has-menus", [$this, "hasMenus"]);
			$this->registerFunction("has-testimonials", [$this, "hasTestimonials"]);
		}

		/**
		 * Compile scss
		 * @param string      $source
		 * @param string|null $path
		 * @return CompilationResult
		 */
		public function compileString($source, $path = null)
		{
			$result = parent::compileString($source, $path);
			$this->map = $result->getSourceMap();

			return $result;
		}

		/**
		 * Checks if this site supports slideshows
		 * @return	bool	Whether it supports slideshows
		 */
		public function hasSlideshow()
		{
			return Page::DO_BANNER;
		}

		/**
		 * Checks if this site contains a blog
		 * @return	bool	Whether it contains a blog
		 */
		public function hasBlog()
		{
			return Registry::BLOG;
		}

		/**
		 * Checks if this site contains a shopping cart
		 * @return	bool	Whether it contains a cart
		 */
		public function hasCart()
		{
			return Registry::CART;
		}

		/**
		 * Checks if this site supports menus
		 * @return	bool	Whether it contains menus
		 */
		public function hasMenus()
		{
			return Registry::MENUS;
		}

		/**
		 * Checks if this site has products
		 * @return	bool	Whether it contains products
		 */
		public function hasProducts()
		{
			return Registry::PRODUCTS;
		}

		/**
		 * Checks if this site has testimonials
		 * @return	bool	Whether it has testimonials
		 */
		public function hasTestimonials()
		{
			return Registry::TESTIMONIALS;
		}

		/**
		 * Checks if this site has frontend users
		 * @return	bool	Whether it has frontend users
		 */
		public function hasUsers()
		{
			return Registry::USERS;
		}
	}
