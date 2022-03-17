<?php
	namespace Core\ValueWrappers;

	use Core\Entity;
	use Core\SaveHandling\EntityTracker;
	
	/**
	 * Stores linked Entities
	 */
	class LinkToWrapper extends LinkWrapper
	{
		/**
		 * Converts the output value into the database value
		 * @param	mixed	$value	The current output value
		 * @return	mixed			The related database value
		 */
		protected function convertOutputValue($value)
		{
			if($value !== null)
			{
				return $value->id;
			}

			return null;
		}
		
		public function getPrerequisiteTrackers(string $action): array
		{
			if($action === EntityTracker::ACTION_DELETE)
			{
				return []; // Don't need to save this relation if we're just linking to an entity
			}
			
			$propertyName = $this->property->getPropertyName();
			/** @var Entity $entity */
			$entity = $this->entity->$propertyName;
			
			return [new EntityTracker($entity, EntityTracker::ACTION_SAVE)];
		}
	}
