<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\BasicElement;
	
	/**
	 * An email input field
	 */
	class Email extends BasicElement
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "Email.js";
		}
	}