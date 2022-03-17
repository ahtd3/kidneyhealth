<?php
	namespace Core\Properties;
	
	use Core\Entity;
	use Core\EntityLink;
	use Core\ValueWrappers\LinkManyManyWrapper;
	use Core\ValueWrappers\ValueWrapper;
	
	/**
	 * Handles many to many properties
	 */
	class LinkManyManyProperty extends LinkProperty
	{
		/**
		 * Creates a new Database Object Property
		 * @param	string	$propertyName		The name of the property
		 * @param	string	$linkingClassName	The class name of the linking object
		 * @param	string	$linkPropertyName	The name of the linking object's property that links back to the class
		 * @param	bool[]	$orderBy			List of property name / boolean pairs (ASC true, DESC false) to order results by
		 */
		public function __construct($propertyName, $linkingClassName, $linkPropertyName, array $orderBy = [])
		{
			assert(is_a((new $linkingClassName), EntityLink::class), $linkingClassName . " must be a subclass of EntityLink");
			parent::__construct(new Property($propertyName, null, $linkingClassName), $linkPropertyName, $orderBy);
		}

		/**
		 * Gets an appropriate value wrapper for this Property
		 * @param	Entity		$entity		The owner of this Property
		 * @return	ValueWrapper			The value wrapper for this Property
		 */
		public function getValueWrapper(Entity $entity)
		{
			return new LinkManyManyWrapper($entity, $this);
		}
		
		/**
		 * Loads the related objects
		 * @param	Entity|null		$entity			The object to load related objects for
		 * @param	int				$externalId		The current value of the field
		 * @return	Entity|Entity[]					Related Object or Objects
		 */
		public function loadRelated(?Entity $entity, $externalId)
		{
			/** @var string|EntityLink $className */
			$className = $this->getType();
			
			return $className::getRelatedEntities($entity, $this);
		}
	}
