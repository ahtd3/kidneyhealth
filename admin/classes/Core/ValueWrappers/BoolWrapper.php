<?php
	namespace Core\ValueWrappers;
	
	/**
	 * Contains a boolean value
	 */
	class BoolWrapper extends ValueWrapper
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
				return null;
			}

			return (bool) $value;
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

			return (bool) $value;
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
				return $value;
			}

			return (int) $value;
		}

		/**
		 * Sets the initial value of this value wrapper
		 * @param	mixed	$value	The initial value
		 */
		public function setFromInitial($value)
		{
			parent::setFromInitial((int) $value);
		}
	}
