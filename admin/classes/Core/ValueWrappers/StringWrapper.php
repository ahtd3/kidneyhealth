<?php
	namespace Core\ValueWrappers;
	
	/**
	 * Stores a string or HTML value
	 */
	class StringWrapper extends ValueWrapper
	{
		/**
		 * Converts the database value into the output value
		 * @param	mixed	$value	The current database value
		 * @return	mixed			The related output value
		 */
		protected function convertDatabaseValue($value)
		{
			return $value;
		}

		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			if($value === null)
			{
				return null;
			}
			
			$string = (string) $value;

			if($this->property->getType() === "string")
			{
				$string = trim(strip_tags($string));
			}

			return $string;
		}

		/**
		 * Converts the output value into the database value
		 * @param    mixed $value The current output value
		 * @return    mixed            The related database value
		 */
		protected function convertOutputValue($value)
		{
			return $value;
		}
	}
