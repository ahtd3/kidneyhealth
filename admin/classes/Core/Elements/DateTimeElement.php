<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\ResultElement;
	use DateTimeInterface;
	
	/**
	 * An input for a date and time
	 */
	class DateTimeElement extends Date
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "datetime/DateTimeElement.js";
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			$value = ResultElement::getValue();
			
			if($value instanceof DateTimeInterface)
			{
				return $value->format("Y-m-d H:i");
			}
			else
			{
				return null;
			}
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() +
			[
				"withTime" => true
			];
		}
	}