<?php
	namespace Pages;

	/**
	 * Uses any page of a particular type, rather than a particular page
	 */
	class PageTypeController extends PageController
	{
		/**
		 * Creates a new Page Type Controller
		 * @param	string	$pageType	The type of Page to use
		 */
		public function __construct($pageType)
		{
			$page = Page::loadForMultiple(
			[
				"active" => true,
				"module" => $pageType
			]);
			if ($page->isNull())
			{
				$defaultPageClass = PageType::get()['Page']->class;
				$page = new $defaultPageClass;
				assert($page instanceof Page);
				$page->pageType = $pageType;
			}

			parent::__construct($page);
		}
	}
