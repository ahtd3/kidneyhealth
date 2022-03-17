<?php
	namespace Search;

	use Configuration\Configuration;
	use Controller\UrlController;
	use Pages\Page;
	use Pages\PageController;
	
	/**
	 * Displays the results of a search
	 */
	class SearchController extends PageController
	{
		const BASE_PATH = "/search/";
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController	$parent		The parent to the Page Child Controller
		 * @param	string[]		$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string			$pattern	The pattern that was matched
		 * @return	UrlController						An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			if($parent !== null)
			{
				return null;
			}
			
			$page = new Page;
			$page->pageType = "Search";
			$page->active = true;
			$page->name = "Search the " . Configuration::getSiteName() . " website";
			$page->slug = trim(static::BASE_PATH, "/");
			return new self($page);
		}
		
		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			return 'searching/search-page.twig';
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$search = $_GET["search"] ?? "";
			
			$moduleSearcher = new ModuleSearcher($search);
			$results = $moduleSearcher->getResults();
			
			$variables = parent::getTemplateVariables();
			
			$variables["search"] = $search;
			$variables["results"] = $results;

			return $variables;
		}
	}
