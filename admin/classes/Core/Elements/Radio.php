<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\OptionsElement;
	
	/**
	 * Displays multiple linked radio buttons
	 */
	class Radio extends OptionsElement
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "radio/Radio.js";
		}
		
		/**
		 * Gets the result of this element
		 * @param mixed $json The JSON to retrieve the result from
		 * @return    mixed            The result that will be passed to the result handler
		 */
		public function getResult($json)
		{
			return $json;
		}
	}