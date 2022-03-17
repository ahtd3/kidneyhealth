<?php
	namespace Admin;
	
	use Controller\UrlController;
	
	/**
	 * Displays the login form
	 */
	class LoginController extends AdminController
	{
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param    UrlController $parent  The parent to the Page Child Controller
		 * @param    string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param    string        $pattern The pattern that was matched
		 * @return    self                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			return null;
		}
		
		/**
		 * @inheritDoc
		 */
		protected function getTemplateLocation()
		{
			return "login-page.twig";
		}
	}