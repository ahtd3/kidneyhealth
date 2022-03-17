<?php
	namespace Core\ValueWrappers;

	use Core\Entity;
	use Core\Properties\Property;
	use Core\SaveHandling\EntityTracker;
	
	/**
	 * Keeps track of everything relating to a value wrapper of a single Entity
	 */
	abstract class ValueWrapper
	{
		const PENDING = 0; //not yet loaded from the database
		const RETRIEVED = 1;
		const CACHED = 2;
		const MODIFIED = 3;

		private $status = self::MODIFIED;//modified means it gets saved to the database, so for new objects we want this
		private $databaseValue = null;
		private $outputValue = null;
		public $property = null;
		protected $entity = null;

		/**
		 * Creates a new value wrapper
		 * @param	Entity		$entity		The Entity this value wrapper belongs to
		 * @param	Property	$property	The Property this value wrapper belongs to
		 */
		public function __construct(Entity $entity, Property $property)
		{
			$this->entity = $entity;
			$this->property = $property;
		}

		/**
		 * Converts the database value into the output value
		 * @param	mixed	$value	The current database value
		 * @return	mixed			The related output value
		 */
		abstract protected function convertDatabaseValue($value);

		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		abstract protected function processInputValue($value);

		/**
		 * Converts the output value into the database value
		 * @param	mixed	$value	The current output value
		 * @return	mixed			The related database value
		 */
		abstract protected function convertOutputValue($value);

		/**
		 * Gets this value wrapper's current status
		 * @return	int		One of the status constants in this object
		 */
		public function getStatus()
		{
			return $this->status;
		}

		/**


		 * Sets the Entity this value wrapper belongs to
		 * @param	Entity	$entity		The Entity this value wrapper belongs to
		 */
		public function setEntity(Entity $entity)
		{
			$this->entity = $entity;
		}

		/**
		 * Sets the initial value of this value wrapper
		 * @param	mixed	$value	The initial value
		 */
		public function setFromInitial($value)
		{
			$this->databaseValue = $value;
		}

		/**
		 * Sets the value of this value wrapper from the database
		 * @param	mixed	$value	The database value to set
		 */
		public function setFromDatabase($value)
		{
			$this->databaseValue = $value;
			$this->outputValue = null;
			$this->status = self::RETRIEVED;
		}

		/**
		 * Gets the value of this value wrapper to insert into the database
		 * @return	mixed	The database value
		 */
		public function getForDatabase()
		{
			if($this->databaseValue === null)
			{
				$this->databaseValue = $this->convertOutputValue($this->outputValue);
			}

			return $this->databaseValue;
		}

		/**
		 * Sets the value of this value wrapper from outside the database
		 * @param	mixed	$value	The value to set
		 */
		public function setFromInput($value)
		{
			$this->outputValue = $this->processInputValue($value);
			$this->databaseValue = null;
			$this->status = self::MODIFIED;
		}

		/**
		 * Gets the output value for this value wrapper
		 * @return	mixed	The value to access
		 */
		public function getForOutput()
		{
			if($this->outputValue === null)
			{
				$this->outputValue = $this->convertDatabaseValue($this->databaseValue);
			}

			if($this->status < self::CACHED)
			{
				$this->status = self::CACHED;
			}

			return $this->outputValue;
		}

		/**
		 * Resets the modified status back to retrieved, should only be used on save()
		 */
		public function resetToSaved()
		{
			if($this->status === self::PENDING || $this->status === self::RETRIEVED)
			{
				return;
			}

			$this->status = self::CACHED;
		}

		/**
		 * Resets the modified status back to pending, should only be used on load()
		 */
		public function resetToPending()
		{
			if($this->status === self::RETRIEVED)
			{
				return;
			}

			$this->status = self::PENDING;
		}
		
		/**
		 * Grabs any prerequisite database actions
		 * @param	string					$action			Whether we're saving or deleting the parent entity
		 * @return	EntityTracker[]					The actions
		 */
		public function getPrerequisiteTrackers(string $action): array
		{
			return [];
		}
		
		/**
		 * Grabs any postrequisite database actions
		 * @param	string					$action			Whether we're saving or deleting the parent entity
		 * @return	EntityTracker[]					The actions
		 */
		public function getPostrequisiteTrackers(string $action): array
		{
			return [];
		}

		/**
		 * Is run before the owner saves
		 */
		public function beforeSave(){}

		/**
		 * Is run after the owner saves
		 */
		public function afterSave(){}

		/**
		 * Is run after the owner is deleted
		 */
		public function afterDelete()
		{
			$this->property->afterDelete($this->entity);
		}

		/**
		 * Runs on clone, otherwise cloned value wrappers do not save when cloned Entity is saved

		 */
		public function __clone()
		{
			if($this->status !== self::PENDING)
			{
				$this->status = self::MODIFIED;
			}
		}
	}