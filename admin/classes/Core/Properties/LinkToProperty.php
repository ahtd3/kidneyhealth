<?php
	namespace Core\Properties;
	
	use Core\Entity;
	use Core\ValueWrappers\LinkToWrapper;
	use Core\ValueWrappers\ValueWrapper;
	
	/**
	 * Handles links to properties
	 */
	class LinkToProperty extends LinkProperty
	{
		public $allowNull = false;
		
		/**
		 * Creates a new Database Object Property
		 * @param	string	$propertyName	The name of the property
		 * @param	string	$databaseName	The name of the database field
		 * @param	string	$className		The class name that this Property links to
		 */
		public function __construct($propertyName, $databaseName, $className)
		{
			parent::__construct(new Property($propertyName, $databaseName, $className));
		}
		
		/**
		 * Allows this property to be null during testing. Only turn on if the test would break without it.
		 * @param	bool	$allowNull	Whether to allow this property to remain null during testing
		 * @return	$this				This object, for chaining
		 */
		public function setAllowNull($allowNull)
		{
			$this->allowNull = $allowNull;
			
			return $this;
		}

		/**
		 * Gets an appropriate value wrapper for this Property
		 * @param	Entity						$entity		The owner of this Property
		 * @return	ValueWrapper							The value wrapper for this Property
		 */
		public function getValueWrapper(Entity $entity)
		{
			return new LinkToWrapper($entity, $this);
		}

		/**
		 * Loads the related objects
		 * @param	Entity|null		$entity			The entity to load related objects for
		 * @param	int				$externalId		The current value of the field
		 * @return	Entity|Entity[]					Related Object or Objects
		 */
		public function loadRelated(?Entity $entity, $externalId)
		{
			/** @var Entity|string $className */
			$className = $this->getType();

			return $className::load($externalId);
		}

		/**
		 * Is run after the owner is deleted
		 * @param	Entity	$entity		The owner of this property
		 */
		public function afterDelete(Entity $entity)
		{
			parent::afterDelete($entity);

			if($this->autoDelete)
			{
				/** @var Entity $linkedEntity */
				$linkedEntity = $entity->{$this->getPropertyName()};
				$linkedEntity->delete();
			}
		}
	}
