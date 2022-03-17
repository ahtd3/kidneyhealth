<?php
	namespace Core\ValueWrappers;

	use Core\Properties\LinkProperty;
	use Error;
	
	/**
	 * Stores linked Entities
	 */
	abstract class LinkWrapper extends ValueWrapper
	{
		protected $alreadySaved = false;

		/**
		 * Converts the database value into the output value
		 * @param	mixed	$value	The current database value
		 * @return	mixed			The related output value
		 */
		protected function convertDatabaseValue($value)
		{
			/** @var LinkProperty $property */
			$property = $this->property;

			return $property->loadRelated($this->entity, (int) $value);
		}

		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			if(is_object($value))
			{
				if(!is_a($value, $this->property->getType()))
				{
					throw new Error("Expected a " . $this->property->getType() . " and got a " . get_class($value) . ' for ' . $this->property->getPropertyName());
				}

				$processedValue = $value;
			}

			if(!isset($processedValue))
			{
				$processedValue = $this->convertDatabaseValue($value);
			}

			return $processedValue;
		}

		/**
		 * Converts the output value into the database value
		 * @param	mixed	$value	The current output value
		 * @return	mixed			The related database value
		 */
		protected function convertOutputValue($value)
		{
			return null;
		}

		/**
		 * Is run before the owner saves
		 */
		public function beforeSave()
		{
			if($this->getStatus() < ValueWrapper::CACHED || $this->alreadySaved)
			{
				return;
			}
		}

		/**
		 * Is run after the owner saves
		 * Gets all the linked objects and saves them
		 */
		public function afterSave()
		{
			if($this->getStatus() < ValueWrapper::CACHED || $this->alreadySaved)
			{
				return;
			}
		}
	}