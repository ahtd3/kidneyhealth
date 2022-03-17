<?php
	namespace Core\Properties;
	
	use Core\Entity;
	use Core\ValueWrappers\LinkWrapper;
	use Core\ValueWrappers\ValueWrapper;
	use Error;
	
	/**
	 * Handles Properties that link to Objects
	 */
	abstract class LinkProperty extends Property
	{
		const SHOULD_CACHE = true;

		/** @var	string	The table this has */
		private $linkedTable = "";

		/** @var	string	The name of the property that links to this one */
		private $relatedPropertyName = "";

		/** @var	bool[]	The properties to order this by */
		private $orderBy = [];

		public $autoDelete = false;

		/**
		 * Creates a new Database Object Property
		 * @param   Property $baseProperty        The base values for this Link Property
		 * @param	string  $relatedPropertyName Name of the property that creates a link between the two tables
		 * @param	bool[]  $orderBy             List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @param	string  $table               The name of the linking table
		 */
		public function __construct(Property $baseProperty, $relatedPropertyName = null, array $orderBy = [], $table = null)
		{
			parent::__construct($baseProperty->getPropertyName(), $baseProperty->getDatabaseName(), $baseProperty->getType());

			/** @var Entity|string $className */
			$className = $baseProperty->getType();

			// Link To Multiple Properties belong to each other, so this would always trigger if they're included
			if($relatedPropertyName !== null && is_a($className, Entity::class, true))
			{
				if(!isset($className::getProperties()[$relatedPropertyName]))
				{
					throw new Error("Class " . $className . " does not have a property named " . $relatedPropertyName);
				}

				if(!$className::getProperties()[$relatedPropertyName] instanceof LinkToProperty)
				{
					throw new Error("Property " . $relatedPropertyName . " is not a LinkToProperty");
				}
			}

			$this->relatedPropertyName = $relatedPropertyName;
			$this->orderBy = $orderBy;
			$this->table = $table;
		}

		/**
		 * Gets an appropriate value wrapper for this Property
		 * @param	Entity						$entity		The owner of this Property
		 * @return	ValueWrapper							The value wrapper for this Property
		 */
		public function getValueWrapper(Entity $entity)
		{
			throw new Error("Attempted to get attribute in abstract class");
		}

		/**
		 * Gets the linking table
		 * @return	string	The name of the linking table
		 */
		public function getTable()
		{
			return $this->linkedTable;
		}

		/**
		 * Gets the name of the property that links to this
		 * @return	string	The name of the property
		 */
		public function getRelatedPropertyName()
		{
			return $this->relatedPropertyName;
		}

		/**
		 * Gets what to order the results by
		 * @return	bool[]	What to order the results by
		 */
		public function getOrderBy()
		{
			return $this->orderBy;
		}

		/**
		 * Sets whether linked objects should be automatically deleted as well
		 * @param	bool	$autoDelete		Whether linked object should be automatically deleted
		 * @return	$this					This object
		 */
		public function setAutoDelete($autoDelete)
		{
			$this->autoDelete = $autoDelete;

			return $this;
		}

		/**
		 * Loads the related objects
		 * @param	Entity|null		$entity			The object to load related objects for
		 * @param	int				$externalId		The current value of the field
		 * @return	Entity|Entity[]					Related Object or Objects
		 */
		abstract public function loadRelated(?Entity $entity, $externalId);
	}