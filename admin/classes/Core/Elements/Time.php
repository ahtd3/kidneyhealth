<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\LabelledResultElement;
	use DateTimeImmutable;
	use DateTimeInterface;
	use Exception;
	
	/**
	 * An input for a time (but not a date)
	 */
	class Time extends LabelledResultElement
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "datetime/TimeElement.js";
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			$value = parent::getValue();
			
			if($value instanceof DateTimeInterface)
			{
				return $value->format("H:i");
			}
			else
			{
				return null;
			}
		}
		
		/**
		 * Gets the result of this element
		 * @param	mixed	$json	The JSON to retrieve the result from
		 * @return	mixed            The result that will be passed to the result handler
		 */
		public function getResult($json)
		{
			try
			{
				return new DateTimeImmutable($json);
			}
			catch(Exception $exception)
			{
				return new DateTimeImmutable();
			}
		}
	}