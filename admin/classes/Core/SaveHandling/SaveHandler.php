<?php
	namespace Core\SaveHandling;
	
	use Core\Entity;
	use Core\ValueWrappers\ValueWrapper;
	use Database\Database;
	use Database\Sqlite;
	use Error;
	use Exception;
	
	/**
	 * Handles saving and deleting all entities just once, in order
	 */
	class SaveHandler
	{
		private static ?SaveHandler $current = null;
		
		/** @var EntityTracker[] */
		private array $prerequisiteStack = [];
		
		/** @var string[] */
		private array $alreadyProcessedIds = [];
		
		/** @var array<string, EntityTracker> */
		private array $trackers = [];
		
		/**
		 * Gets the current save handler, or a new one if there is no current save handler
		 * @return	SaveHandler		The current save handler
		 */
		public static function getCurrent(): SaveHandler
		{
			return self::$current ?? new self;
		}
		
		/**
		 * Saves an entity and all appropriate related entities
		 * @param	Entity	$entity		The entity to save
		 */
		public function save(Entity $entity)
		{
			if(self::$current === null)
			{
				self::$current = $this;
				$this->addTracker(new EntityTracker($entity, EntityTracker::ACTION_SAVE));
				$this->processChain();
				self::$current = null;
			}
			else
			{
				$this->addTracker(new EntityTracker($entity, EntityTracker::ACTION_SAVE));
			}
		}
		
		/**
		 * Deletes an entity and all related entities
		 * @param	Entity	$entity		The entity to delete
		 */
		public function delete(Entity $entity)
		{
			if(self::$current === null)
			{
				self::$current = $this;
				$this->addTracker(new EntityTracker($entity, EntityTracker::ACTION_DELETE));
				$this->processChain();
				self::$current = null;
			}
			else
			{
				$this->addTracker(new EntityTracker($entity, EntityTracker::ACTION_DELETE));
			}
		}
		
		/**
		 * Processes the chain of save/delete trackers
		 */
		private function processChain()
		{
			Database::beginTransaction();
			$newEntities = [];
			
			// Trackers get removed as they're run, so we need to store the existing trackers so we can run the afterSave()/afterDelete() actions
			$trackers = $this->trackers;
			
			foreach($trackers as $tracker)
			{
				if($tracker->action === EntityTracker::ACTION_SAVE && $tracker->entity->id === null)
				{
					$newEntities[] = $tracker->entity;
				}
			}
			
			/** @var EntityTracker[] $toProcessAgain */
			$toProcessAgain = [];
			
			while(count($this->trackers) > 0)
			{
				$linkRemoved = false;
				
				foreach($this->trackers as $tracker)
				{
					if(count($tracker->prerequisites) === 0)
					{
						$this->processTracker($tracker);
						$linkRemoved = true;
						break;
					}
				}
				
				if(!$linkRemoved)
				{
					// Finds the tracker with the lowest number of prerequisites, this is the most likely item to be blocking other items
					$toProcessAnyway = array_reduce($this->trackers, fn(?EntityTracker $current, EntityTracker $tracker) => $current === null || $tracker->getPrerequisiteLength() < $current->getPrerequisiteLength() ? $tracker : $current, null);
					
					// Since we've been forced to process this tracker out of order, we'll want to save it to the database a second time. This is just the database call, and will not trigger a second call to beforeSave() or beforeDelete()
					$toProcessAgain[] = $toProcessAnyway;
					$this->processTracker($toProcessAnyway);
				}
			}
			
			foreach($toProcessAgain as $tracker)
			{
				$this->processTracker($tracker);
			}
			
			// These are the trackers from before we ran through the chain
			foreach($trackers as $tracker)
			{
				if(in_array($tracker->getId(), $this->alreadyProcessedIds))
				{
					// We've already saved this object once, and even though a second save might be necessary, don't run afterSave() a second time
				}
				else if($tracker->action === EntityTracker::ACTION_SAVE)
				{
					$tracker->entity->afterSave(in_array($tracker->entity, $newEntities, true));
				}
				else
				{
					$tracker->entity->afterDelete();
				}
				
				$this->alreadyProcessedIds[] = $tracker->getId();
			}
			
			// If afterSave() or afterDelete() have added new links to the chain, we'll want to run through those now
			if(count($this->trackers) > 0)
			{
				$this->processChain();
			}
			
			$this->alreadyProcessedIds = [];
			
			Database::commitTransaction();
		}
		
		/**
		 * Adds a tracker to the save loop
		 * @param	EntityTracker $tracker The tracker to process
		 */
		public function addTracker(EntityTracker $tracker)
		{
			$id = $tracker->getId();
			
			// Make sure we don't get any duplicates, and we can ignored deleted items
			if(isset($this->trackers[$id]) || !$tracker->entity->canBeSaved(true))
			{
				return;
			}
			
			$this->trackers[$id] = $tracker;
			
			// Add this tracker to the stack so any calls to save() or delete() during beforeSave() or beforeDelete() can be captured and attributed as prerequisites to this tracker
			$this->prerequisiteStack[] = $tracker;
			
			if(in_array($tracker->getId(), $this->alreadyProcessedIds))
			{
				// We've already saved this object once, and even though a second save might be necessary, don't run beforeSave() a second time
			}
			else if($tracker->action === EntityTracker::ACTION_SAVE)
			{
				$tracker->entity->beforeSave($tracker->entity->id === null);
			}
			else
			{
				$tracker->entity->beforeDelete();
			}
			
			// We've finished adding prerequisites to this tracker, pop it back off the stack
			array_pop($this->prerequisiteStack);
			$prerequisites = [];
			
			// If save() or delete() was called from within a beforeSave() or beforeDelete() method, then that entity is a prerequisite
			if(count($this->prerequisiteStack) > 0)
			{
				$this->prerequisiteStack[count($this->prerequisiteStack) - 1]->prerequisites[] = $tracker;
			}
			
			if($tracker->entity->canBeSaved())
			{
				foreach($tracker->entity->getValueWrappers() as $valueWrapper)
				{
					foreach($valueWrapper->getPrerequisiteTrackers($tracker->action) as $prerequisiteTracker)
					{
						$this->addTracker($prerequisiteTracker);
						
						// Make sure the prerequisite wasn't discarded instead of being added, otherwise it will prevent this tracker from being run
						if(isset($this->trackers[$prerequisiteTracker->getId()]))
						{
							$prerequisites[$prerequisiteTracker->getId()] = $prerequisiteTracker;
						}
					}
					
					foreach($valueWrapper->getPostrequisiteTrackers($tracker->action) as $postrequisiteTracker)
					{
						// It doesn't matter if the postrequisite gets discarded, as it doesn't block anything
						$postrequisiteTracker->prerequisites = [$tracker->getId() => $tracker];
						$this->addTracker($postrequisiteTracker);
					}
				}
			}
			
			$tracker->prerequisites = array_merge($tracker->prerequisites, $prerequisites);
		}
		
		/**
		 * Removes this tracker from the save loop and then saves or deletes the entity
		 * @param	EntityTracker $tracker The tracker to process
		 */
		private function processTracker(EntityTracker $tracker)
		{
			unset($this->trackers[$tracker->getId()]);
			
			foreach($this->trackers as $otherTracker)
			{
				unset($otherTracker->prerequisites[$tracker->getId()]);
			}
			
			try
			{
				if($tracker->action === EntityTracker::ACTION_SAVE)
				{
					$this->saveToDatabase($tracker->entity);
				}
				else
				{
					$this->deleteFromDatabase($tracker->entity);
				}
			}
			catch(Exception | Error $exception)
			{
				Database::cancelTransaction();
				self::$current = null;
				throw $exception;
			}
		}
		
		/**
		 * Saves a specific entity to the database
		 * @param	Entity	$entity		The entity to save
		 */
		private function saveToDatabase(Entity $entity)
		{
			if(!$entity->canBeSaved())
			{
				return;
			}
			
			$idAttribute = $entity->getValueWrappers()["id"];
			$isNew = $entity->id === null; // When entity spans multiple tables, the ID will be set partway through the foreach loop
			
			/** @var array<string, array<string, string|int|float|null>> $map */
			$map = [];
			
			// Get the values to insert/update
			foreach($entity->getValueWrappers() as $valueWrapper)
			{
				if(!$valueWrapper->property->shouldAddToQuery() || $valueWrapper->getStatus() < ValueWrapper::MODIFIED)
				{
					continue;
				}
				
				$map[$valueWrapper->property->table][$valueWrapper->property->getDatabaseName()] = $valueWrapper->getForDatabase();
			}
			
			// Iterate over the tables, inserting/updating the values
			foreach($map as $table => $values)
			{
				$isDefaultTable = $table === $idAttribute->property->table;
				$values[$entity::ID_FIELD] = $entity->id;
				
				if($isNew)
				{
					$query = Database::generateInsertQuery($table, array_keys($values));
					$result = Database::query($query, array_values($values));
					
					if($isDefaultTable)
					{
						$entity->getValueWrappers()["id"]->setFromDatabase($result[0]);
					}
				}
				// sqlite does not handle on duplicate etc syntax
				else if($isDefaultTable || Database::getDefaultQueryRunner() instanceof Sqlite)
				{
					$query = Database::generateUpdateQuery($table, array_keys($values), "{$idAttribute->property->getDatabaseName()} = {$entity->id}");
					Database::query($query, array_values($values));
				}
				else
				{
					$query = Database::generateInsertOrUpdateQuery($table, array_keys($values));
					Database::query($query, array_merge(array_values($values), array_values($values)));
				}
			}
			
			foreach($entity->getValueWrappers() as $valueWrapper)
			{
				$valueWrapper->resetToSaved();
			}
		}
		
		/**
		 * Deletes a specific entity from the database
		 * @param	Entity	$entity		The entity to delete
		 */
		private function deleteFromDatabase(Entity $entity)
		{
			if(!$entity->canBeSaved() || $entity->id === null)
			{
				return;
			}
			
			foreach(Entity::getTablesFor(get_class($entity)) as $table)
			{
				$idField = $entity::ID_FIELD;
				
				$query = "DELETE FROM `{$table}` "
					   . "WHERE `{$table}`.`{$idField}` = ?";
				
				$processedQuery = Entity::processQueryFor(get_class($entity), $query);
				Database::query($processedQuery, [(int) $entity->id]);
			}
			
			$entity->deleted = true;
		}
	}