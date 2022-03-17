<?php
	namespace Template;
	
	use Core\Generator;
	
	/**
	 * A Page Item displays in a full page context in the site
	 * Often but not necessarily a Page Item will also implement NavItem and SitemapItem
	 */
	interface PageItem
	{
		/**
		 * Gets the content title for the item (where this is not laid out by the user in an Editor element)
		 * don't do splitting and html wrapping here because getPageTitle() usually falls back to use this logic
		 * instead set up a filter in Controller\Twig.php and call that from the template file, you're probably goong to want to use it 
		 * on multiple heading sources anyway. 
		 * @return	string	Usually the contents of the page h1, but also useful for a subheading/link/list item.
		 */
		public function getMainHeading();
		
		/**
		 * Gets the <meta name="description" /> for the item
		 * @return	string	The text to use in the metadata description
		 */
		public function getMetaDescription();

		/**
		 * Gets the page <title> for the item
		 * Usually falls back to getMainHeading(), if it doesn't just wrap that method.
		 * @return	string	The text to use for the page title
		 */
		public function getPageTitle();
		
		/**
		 * Gets the page content. This will usually just return a property, but might be eg a rendered twig template.
		 * Most templates will probably never bother with this method, calling the property directly.
		 * @return	string	The html to output in the template
		 */
		public function getPageContent();
	}