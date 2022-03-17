<?php
	namespace Core\ValueWrappers;

	use Core\Entity;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\SaveHandling\EntityTracker;
	use Error;
	
	/**
	 * Stores linked Entities
	 * @author	Callum Muir <callum@activatedesign.co.nz>
	 */
	class LinkFromMultipleWrapper extends LinkWrapper
	{
		// Is this value wrapper a clone? Used in afterSave() to prevent the original Entity being discarded
		private $cloned = false; 
		
		private $discarded = [];
		
		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			// Make sure this is an array, nothing else should be passed here
			if(is_array($value))
			{
				$property = $this->property;
				assert($property instanceof LinkFromMultipleProperty);
				
				/** @var Entity $entity */
				foreach($value as $entity)
				{
					// Assign the receiving entity as the parent of the new entity
					$propertyName = $property->getRelatedPropertyName();
					$entity->$propertyName = $this->entity;
				}
				
				foreach($this->getForOutput() as $entity)
				{
					// Track entities that have been discarded, so we can delete them later
					if(!in_array($entity, $value, true))
					{
						$this->discarded[] = $entity;
					}
				}

				return $value;
			}

			throw new Error("Expected an array");
		}
		
		/**
		 * Grabs any postrequisite database actions
		 * @param	string					$action			Whether we're saving or deleting the parent entity
		 * @return	EntityTracker[]					The actions
		 */
		public function getPostrequisiteTrackers(string $action): array
		{
			if($action !== EntityTracker::ACTION_SAVE || $this->getStatus() < self::CACHED)
			{
				return [];
			}
			
			$actions = [];

			
			$property = $this->property;
			assert($property instanceof LinkFromMultipleProperty);
			
			/** @var Entity $entity */
			foreach($this->entity->{$property->getPropertyName()} as $entity)
			{
				// Save all related entities, now that we know the parent entity has an ID
				$actions[] = new EntityTracker($entity, EntityTracker::ACTION_SAVE);
			}
			
			// We'll need this to set the parent to null if auto-delete is turned off
			$relatedPropertyName = $property->getRelatedPropertyName();
			
			foreach($this->discarded as $entity)
			{
				// Make sure we haven't adopted the object again
				// If $this->cloned the 'discarded' items are actually the original
				// Entities and we want them to stay put.
				if(!$this->cloned && !in_array($entity, $this->getForOutput(), true))
				{
					// We want to make sure we've deleted all orphans, or set the link to null if autodelete is turned off
					if($property->autoDelete)
					{
						$actions[] = new EntityTracker($entity, EntityTracker::ACTION_DELETE);
					}
					else
					{
						$entity->$relatedPropertyName = null;
						$actions[] = new EntityTracker($entity, EntityTracker::ACTION_SAVE);
					}
				}
			}
			
			$this->discarded = [];
			return $actions;

		}
		
		/**
		 * Runs on clone
		 */
		public function __clone()
		{
			parent::__clone();
			$this->cloned = true;
		}
	}