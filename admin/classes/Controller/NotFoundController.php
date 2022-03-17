<?php
	namespace Controller;

	use Pages\PageController;
	use Pages\PageType;
	
	/**
	 * Displays the 404 error page
	 */
	class NotFoundController extends Controller
	{
		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			return null;
		}

		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			$defaultPageClass = PageType::get()['Page']->class;
			$page = $defaultPageClass::loadFor("isErrorPage", true);
			header("HTTP/1.0 404 Not Found");

			if($page->isNull())
			{
				$page = new $defaultPageClass;
				$page->active = true;
				$page->name = "404 - Not Found";
				$page->metaDescription = "404 - Not Found";

				$html = "";
				$html .= "<p>\n";
					$html .= "We are sorry, the page was not found at the requested address\n";
				$html .= "</p>\n";

				$page->content = $html;
			}

			(new PageController($page))->output();
		}
	}
