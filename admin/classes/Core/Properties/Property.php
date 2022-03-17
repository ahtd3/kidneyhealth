<?php
	namespace Core\Properties;
	
	use Closure;
	use Core\Entity;
	use Core\ValueWrappers\BoolWrapper;
	use Core\ValueWrappers\DateTimeWrapper;
	use Core\ValueWrappers\FileWrapper;
	use Core\ValueWrappers\NumberWrapper;
	use Core\ValueWrappers\RawWrapper;
	use Core\ValueWrappers\StringWrapper;
	use Core\ValueWrappers\ValueWrapper;
	use Error;
	use ReflectionFunction;
	
	/**
	 * Class for handling the automated properties in an Object
	 */
	class Property
	{
		/** @var	string	The name of this Property */
		private string $propertyName = "";

		/** @var	string|null	The field name in the database for this Property */
		private ?string $databaseName = "";

		/** @var	string|null	The data type of this Property */
		private ?string $type = "";
		
		public bool $searchable = false;
		public ?string $table = null;
		public ?Closure $getter = null;
		public ?Closure $setter = null;
		public int $setterParameterCount = 0;

		/**
		 * Creates a new Property
		 * @param	string	$propertyName	The name of the property
		 * @param	string	$databaseName	The name of the database field
		 * @param	string	$type	The data type of the property
		 */
		public function __construct($propertyName, $databaseName = null, $type = null)
		{
			$this->propertyName = $propertyName;
			$this->databaseName = $databaseName;
			$this->type = $type;
		}

		/**
		 * Checks whether this Property should be added into SELECT, INSERT and UPDATE queries
		 * @return	bool	Whether it should be part of a query
		 */
		public function shouldAddToQuery()
		{
			return $this->databaseName !== null;
		}
		
		/**
		 * Gets an appropriate value wrapper for this Property
		 * @param	Entity						$entity		The owner of this Property
		 * @return	ValueWrapper							The value wrapper for this Property
		 */
		public function getValueWrapper(Entity $entity)
		{
			switch($this->getType())
			{
				case "bool":
					$class = BoolWrapper::class;
				break;
				
				case "date":
				case "datetime":
				case "time":
					$class = DateTimeWrapper::class;
				break;
				
				case "file":
				case "image":
					$class = FileWrapper::class;
				break;
				
				case "html":
				case "string":
					$class = StringWrapper::class;
				break;
				
				case "int":
				case "float":
					$class = NumberWrapper::class;
				break;
				
				case "raw":
				case "serialised":
				case null:
					$class = RawWrapper::class;
				break;
				
				default:
					$className = $entity::class;
					throw new Error("Type '" . $this->getType() . "' does not exist for {$className}::\${$this->propertyName}");
			}
			
			return new $class($entity, $this);
		}
		
		/**
		 * Gets the property name for this Property
		 * @return	string	The property name
		 */
		public function getPropertyName()
		{
			return $this->propertyName;
		}
		
		/**
		 * Sets the property name for this Property
		 * @param	string	$propertyName	The Property name
		 */
		protected function setPropertyName($propertyName)
		{
			$this->propertyName = $propertyName;
		}
		
		/**
		 * Gets the database name for this Property
		 * @return	string	The database name
		 */
		public function getDatabaseName()
		{
			return $this->databaseName;
		}
		
		/**
		 * Gets the type of this Property
		 * @return	string	The type of property
		 */
		public function getType()
		{
			return $this->type;
		}
		
		/**
		 * Sets whether this Property is part of a Full Text search
		 * @param	bool	$searchable		Whether this property is part of a full text search
		 * @return	self					For chaining
		 */
		public function setIsSearchable($searchable)
		{
			$this->searchable = $searchable;
			
			return $this;
		}
		
		/**
		 * Is run before the owner is deleted
		 * @param	Entity	$entity		The owner of this property
		 */
		public function beforeDelete(Entity $entity){}
		
		/**
		 * Is run after the owner is deleted
		 * @param	Entity	$entity		The owner of this property
		 */
		public function afterDelete(Entity $entity){}
		
		/**
		 * Sets the setter for this property
		 * Note 1: Inside the setter, $this is set to the entity we're setting the value on. However, phpstan has no way to detect this is what you're doing, so it will throw an error. It's best to use the entity parameter if this is going into the repo.
		 * Note 2: If a one-argument function is passed in, we just pass the value. Otherwise we pass the entity and the value.
		 * @param	callable|null	$setter		The setter, or null for no setter. Should be either callable(Entity, mixed): void, or callable(mixed): void
		 * @return	$this						This object, for chaining
		 */
		public function setSetter(?callable $setter): self
		{
			$this->setter = ($setter === null) ? null : Closure::fromCallable($setter);
			$this->setterParameterCount = ($setter === null) ? 0 : (new ReflectionFunction($setter))->getNumberOfParameters();
			return $this;
		}
		
		/**
		 * Sets the getter for this property
		 * Note: Inside the getter, $this is set to the entity we're setting the value on. However, phpstan has no way to detect this is what you're doing, so it will throw an error. It's best to use the entity parameter if this is going into the repo.
		 * @param	callable|null	$getter		The getter, or null for no getter. Should be either callable(Entity): mixed, or callable(): mixed
		 * @return	$this						This object, for chaining
		 */
		public function setGetter(?callable $getter): self
		{
			$this->getter = ($getter === null) ? null : Closure::fromCallable($getter);
			return $this;
		}
	}