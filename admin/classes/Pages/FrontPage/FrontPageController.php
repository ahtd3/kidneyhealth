<?php
	namespace Pages\FrontPage;

	use Pages\PageController;
	
	/**
	 * A FrontPage Controller loads anything extra needed for the front page
	 */
	class FrontPageController extends PageController
	{
		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			return parent::getTemplateVariables() +
			[
			
			];
		}
	}
