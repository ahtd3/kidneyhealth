<?php
	namespace Core\ValueWrappers;
	
	use Core\EntityLink;
	use Core\Properties\LinkManyManyProperty;
	use Core\SaveHandling\EntityTracker;
	use Error;
	
	/**
	 * Stores linked Entities

	 */
	class LinkManyManyWrapper extends LinkWrapper
	{
		private static bool $allowProcessing = true;
		
		/** @var EntityLink[] */
		private $toAdd = [];
		
		/** @var EntityLink[] */
		private $toRemove = [];
		
		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			if(!self::$allowProcessing)
			{
				return $value;
			}
			
			$property = $this->property;
			assert($property instanceof LinkManyManyProperty);
			
			/** @var string|EntityLink $linkingType */
			$linkingType = $property->getType();
			$type = $linkingType::getOppositeType($property);
			
			$currentEntities = $this->getForOutput();
			
			// Make sure we're dealing with an array
			if(is_array($value))
			{
				// So we don't infinite loop when this is done from the other object in the relationship
				self::$allowProcessing = false;
				
				$newEntities = [];
				
				// Make sure that each item is an Entity of the right type, or assume we're dealing with integers and just load the opposite entities in
				foreach($value as $item)
				{
					if(is_object($item))
					{
						if(!$item instanceof $type)
						{
							throw new Error("Expected an array of {$type}, but it contained a " . get_class($item));
						}
						
						$newEntities[] = $item;
					}
					else
					{
						$newEntities[] = $type::load($item);
					}
				}
				
				// Setup and keep track of any linking objects being added
				foreach($newEntities as $newEntity)
				{
					if(!in_array($newEntity, $currentEntities))
					{
						$this->toAdd[] = $linkingType::addEntityToProperty($this->entity, $property, $newEntity);
					}
				}
				
				// Keep track of any linking objects being removed
				foreach($currentEntities as $currentEntity)
				{
					if(!in_array($currentEntity, $newEntities))
					{
						$this->toRemove[] = $linkingType::removeEntityFromProperty($this->entity, $property, $currentEntity);
					}
				}
				
				self::$allowProcessing = true;
				
				return $newEntities;
			}
			
			throw new Error("Expected an array");
		}
		
		public function getPostrequisiteTrackers(string $action): array
		{
			$actions = [];
			
			if($action === EntityTracker::ACTION_SAVE)
			{
				// Delete any linking objects that are no longer part of the system
				foreach($this->toRemove as $toRemove)
				{
					$actions[] = new EntityTracker($toRemove, EntityTracker::ACTION_DELETE);
				}
				
				// Add any linking objects that have been created
				foreach($this->toAdd as $toAdd)
				{
					if($toAdd->alreadyExistsInDatabase())
					{
						continue;
					}
					
					$actions[] = new EntityTracker($toAdd, EntityTracker::ACTION_SAVE);
				}
				
				$this->toRemove = [];
				$this->toAdd = [];
			}
			else if($action === EntityTracker::ACTION_DELETE)

			{
				$property = $this->property;
				assert($property instanceof LinkManyManyProperty);
				
				/** @var string|EntityLink $linkingType */
				$linkingType = $property->getType();
				
				// Delete all linking objects that once linked to this entity
				foreach($linkingType::loadAllFor($property->getRelatedPropertyName(), $this->entity->id) as $linkingObject)
				{
					$actions[] = new EntityTracker($linkingObject, EntityTracker::ACTION_DELETE);
				}
			}
			
			return $actions;
		}
	}