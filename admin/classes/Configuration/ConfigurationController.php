<?php
	namespace Configuration;

	use Admin\AdminController;
	use Controller\RedirectController;
	use Controller\UrlController;
	
	/**
	 * Handles admin processes
	 */
	class ConfigurationController extends AdminController
	{
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param    UrlController $parent  The parent to the Page Child Controller
		 * @param    string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param    string        $pattern The pattern that was matched
		 * @return    UrlController                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			switch($pattern)
			{
				case '/action/edit-config/':
					$config = Configuration::acquire();

					foreach ($_POST as $propertyName => $value) 
					{
						if (isset(Configuration::getProperties()[$propertyName])) 
						{
							$config->$propertyName = $value;
						}
						else 
						{
							addMessage("Configuration does not have a '" . $propertyName . "' property");
						}
					}

					$config->save();
					return new RedirectController('');
			}
			return null;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return    string    The location of the template
		 */
		protected function getTemplateLocation()
		{
			// Unused
			return "";
		}
	}