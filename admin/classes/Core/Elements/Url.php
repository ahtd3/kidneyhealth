<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\BasicElement;
	
	/**
	 * A simple text input
	 */
	class Url extends BasicElement
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "UrlElement.js";
		}
	}