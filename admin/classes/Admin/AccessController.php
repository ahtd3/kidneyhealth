<?php
	namespace Admin;
	
	use Controller\UrlController;
	
	/**
	 * Handles displaying an access error
	 */
	class AccessController extends AdminController
	{
		private $error;
		
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
		 * Retrieves the location of the template to display to the user
		 * @return    string    The location of the template
		 */
		protected function getTemplateLocation()
		{
			return "access-error-page.twig";
		}
		
		/**
		 * Creates a new access controller
		 * @param	string	$error	The error message to display
		 */
		public function __construct(string $error)
		{
			$this->error = $error;
		}
		
		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();
			$variables['error'] = $this->error;
			return $variables;
		}
	}