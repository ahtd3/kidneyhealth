<?php
	namespace Core\ValueWrappers;

	use DateTime;
	use DateTimeImmutable;
	use Exception;
	
	/**
	 * Contains a single date, time or datetime value
	 */
	class DateTimeWrapper extends ValueWrapper
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

			try
			{
				return new DateTimeImmutable($value);
			}
			catch(Exception $e)
			{
				return null;
			}
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

			if($value instanceof DateTime)
			{
				return new DateTimeImmutable($value->format("c"));
			}
			else if($value instanceof DateTimeImmutable)
			{
				return $value;
			}

			try
			{
				return new DateTimeImmutable($value);
			}
			catch(Exception $e)
			{
				return null;
			}
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

			switch($this->property->getType())
			{
				case "date":
					return $value->format("Y-m-d");

				case "time":
					return $value->format("H:i:s");

				default:
					return $value->format("Y-m-d H:i:s");
			}
		}
	}
