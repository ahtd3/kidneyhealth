<?php
	namespace Pages\Contact;
	
	use Forms\Form;
	
	use Pages\PageController;

	/**
	 * Displays a contact page to the user
	 */
	class ContactPageController extends PageController
	{
		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();

			$variables["contactForm"] = Form::load(Form::CONTACT_ID);

			return $variables;
		}
	}
