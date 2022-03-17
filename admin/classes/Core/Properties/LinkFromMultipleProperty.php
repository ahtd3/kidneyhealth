<?php
	namespace Core\Properties;
	
	use Core\Entity;
	use Core\ValueWrappers\LinkFromMultipleWrapper;
	use Core\ValueWrappers\ValueWrapper;
	
	/**
	 * Handles links from properties
	 */
	class LinkFromMultipleProperty extends LinkProperty
	{
		public $autoDelete = true;
		
		/**
		 * Creates a new Database Object Property
		 * @param	string	$propertyName		The name of the property
		 * @param	string	$className			The class name that this Property links to
		 * @param	string	$linkPropertyName	The name of the property that links back to the class
		 * @param	bool[]	$orderBy			List of property name / boolean pairs (ASC true, DESC false) to order results by
		 */
		public function __construct($propertyName, $className, $linkPropertyName, array $orderBy = [])
		{
			parent::__construct(new Property($propertyName, null, $className), $linkPropertyName, $orderBy);
		}

		/**
		 * Gets an appropriate value wrapper for this Property
		 * @param	Entity						$entity		The owner of this Property
		 * @return	ValueWrapper							The value wrapper for this Property
		 */
		public function getValueWrapper(Entity $entity)
		{
			return new LinkFromMultipleWrapper($entity, $this);
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
		 * @return	Object|Object[]					Related Object or Objects
		 */
		public function loadRelated(?Entity $entity, $externalId)
		{
			if ($entity === null || $entity->id === null)
			{
				return [];
			}
			
			/** @var Entity|string $className */
			$className = $this->getType();
			
			return $className::loadAllFor($this->getRelatedPropertyName(), $entity, $this->getOrderBy());
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
				/** @var Entity[] $linkedEntities */
				$linkedEntities = $entity->{$this->getPropertyName()};

				foreach($linkedEntities as $linkedEntity)
				{
					$linkedEntity->delete();
				}
			}
		}
	}
