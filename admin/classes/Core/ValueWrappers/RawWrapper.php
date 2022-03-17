<?php
	namespace Core\ValueWrappers;
	
	/**
	 * Stores raw and serialised values
	 */
	class RawWrapper extends ValueWrapper
	{
		/**
		 * Converts the database value into the output value
		 * @param	mixed	$value	The current database value
		 * @return	mixed			The related output value
		 */
		protected function convertDatabaseValue($value)
		{
			if($value === null)
			{
				return $value;
			}

			if($this->property->getType() === "serialised")
			{
				return unserialize($value);
			}
			else
			{
				return $value;
			}
		}

		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			return $value;
		}

		/**
		 * Converts the output value into the database value
		 * @param	mixed	$value	The current output value
		 * @return	mixed			The related database value
		 */
		protected function convertOutputValue($value)
		{
			if($value === null)
			{
				return null;
			}

			if($this->property->getType() === "serialised")
			{
				return serialize($value);
			}
			else
			{
				return $value;
			}
		}
	}
