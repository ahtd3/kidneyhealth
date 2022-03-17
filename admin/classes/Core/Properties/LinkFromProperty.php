<?php
	namespace Core\Properties;
	
	use Core\Entity;
	use Core\ValueWrappers\LinkFromSingleWrapper;
	use Core\ValueWrappers\ValueWrapper;
	
	/**
	 * Handles links from properties
	 */
	class LinkFromProperty extends LinkProperty
	{
		public $allowNull = false;
		
		/**
		 * Creates a new Database Object Property
		 * @param	string	$propertyName		The name of the property
		 * @param	string	$className			The class name that this Property links to
		 * @param	string	$linkPropertyName	The name of the property that links back to the class
		 */
		public function __construct($propertyName, $className, $linkPropertyName)
		{
			parent::__construct(new Property($propertyName, null, $className), $linkPropertyName);
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
			return new LinkFromSingleWrapper($entity, $this);
		}

		/**
		 * Checks whether this Property should be added into SELECT, INSERT and UPDATE queries
		 * @return	bool	Whether it should be part of a query
		 */
		public function shouldAddToQuery()
		{
			return false;
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

			if ($entity === null || $entity->id === null)
			{
				return $className::makeNull();
			}
			
			$result = $className::loadFor($this->getRelatedPropertyName(), $entity);
			
			if(!is_a($result, $className, true))
			{
				return $className::makeNull();
			}
			
			return $result;
		}

		/**
		 * Is run before the owner is deleted
		 * @param	Entity	$entity		The owner of this property
		 */
		public function beforeDelete(Entity $entity)
		{
			parent::beforeDelete($entity);

			if($this->autoDelete)
			{
				/** @var Entity $linkedEntity */
				$linkedEntity = $entity->{$this->getPropertyName()};
				$linkedEntity->delete();
			}
		}
	}
