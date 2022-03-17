<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\ResultElement;
	
	/**
	 * A single hidden field
	 */
	class Hidden extends ResultElement
	{
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			return FormOption::sanitiseValue(parent::getValue());
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "Hidden.js";
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