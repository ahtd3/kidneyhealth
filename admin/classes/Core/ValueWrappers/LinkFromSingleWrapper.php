<?php
	namespace Core\ValueWrappers;

	use Core\Properties\LinkFromProperty;
	
	use Core\SaveHandling\EntityTracker;
	
	/**
	 * Stores linked Entities. Not to be confused with LinkFromMultipleValueWrapper, this is the class that a LinkFromProperty wants to be associated with



	 */
	class LinkFromSingleWrapper extends LinkWrapper
	{
		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			$processedValue = parent::processInputValue($value);

			$property = $this->property;
			assert($property instanceof LinkFromProperty);
			$propertyName = $property->getRelatedPropertyName();
			$processedValue->$propertyName = $this->entity;

			return $processedValue;
		}
		
		public function getPostrequisiteTrackers(string $action): array

		{
			if($action !== EntityTracker::ACTION_SAVE)


			{
				return [];
			}
			
			$actions = [];
			
			$property = $this->property;

			assert($property instanceof LinkFromProperty);
			
			$propertyName = $property->getPropertyName();
			$entityProperty = $property->getRelatedPropertyName();
			
			$entities = [$this->entity->$propertyName];
			
			foreach($entities as $entity)
			{
				if(isset($entity::getProperties()[$entityProperty]))
				{
					$actions[] = new EntityTracker($entity, EntityTracker::ACTION_SAVE);
				}
			}
			
			return $actions;
		}
	}