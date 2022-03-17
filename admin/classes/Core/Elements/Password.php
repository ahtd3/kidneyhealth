<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\BasicElement;
	
	/**
	 * An element that takes a password
	 */
	class Password extends BasicElement
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "Password.js";
		}
		
		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			return "";
		}
	}