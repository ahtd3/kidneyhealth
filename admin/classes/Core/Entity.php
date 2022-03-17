<?php
	namespace Core;

	use Closure;
	use Core\Attributes\PropertyAttribute;
	use Core\Elements\FormOption;
	use Core\ValueWrappers\ValueWrapper;
	use Core\ValueWrappers\LinkWrapper;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkProperty;
	use Core\Properties\Property;
	use Core\SaveHandling\SaveHandler;
	use Database\Database;
	use Error;
	use JsonSerializable;
	use Pagination;
	use ReflectionException;
	use ReflectionFunction;
	use Search\Searchable;
	use Slugging\Slugging;
	use WeakMap;
	use WeakReference;

	/**
	 * Enforce 0-argument constructor for entities
	 */
	interface EntityInterface
	{
		/**
		 * Creates a new item of this type
		 */
		public function __construct();
	}

	/**
	 * Class for managing Objects
	 */
	abstract class Entity implements EntityInterface, JsonSerializable, Searchable
	{
		/** @var	string|null	The table where the data for this object type are stored */
		const TABLE = null;

		/** @var	string|null	The field name for the primary key of this object */
		const ID_FIELD = null;

		/** @var    string|null	The property that links to the parent for this object, if it has such */
		const PARENT_PROPERTY = null;

		/** @var    string|null	The property for whatever object is higher up the URL chain. eg. could be a category, or could be a page type */
		const PATH_PARENT = null;

		/** @var    bool	Will generate a property, class, that contains the class name to insure the object keeps the right class */
		const HAS_AUTOCAST = false;

		/** @var    string|null	A property that automatically generates a slug when changed */
		const SLUG_PROPERTY = null;

		/** @var	float	The cutoff under which search results are not considered relevant enough to display in results */
		const RELEVANCE_CUTOFF = 0.6;

		/** @var	array<class-string<Entity>, array<int, WeakReference<Entity>>>		The Objects that have already been loaded */
		private static array $loaded = [];

		/** @var      Property[][]    The Properties that belong to this class */
		protected static $properties = [];

		/** @var    int        The database identifier for this Object */
		public $id = null;

		public $slug = "";

		// This will be inserted automatically
		public $class = "";

		/** @var Entity|null */
		protected $oldParent = null;

		protected $relevance = 0.5;

		/** @var    ValueWrapper[]        All values that were originally contained in properties */
		protected $valueWrappers = [];

		/** @var    bool    Whether this Object is an empty shell */
		private $isNull = false;

		public $deleted = false;

		private static $dumpedObjects = null;
		protected $queryRunner;
		private ?SaveHandler $saveHandler = null;

		/**
		 * Adds a Property to the class
		 * @template	T of Property				The type of property being added
		 * @param		T				$property	The Property to add
		 * @param		string			$table		The table to add the property to
		 * @return		T							The property that was just added
		 */
		protected static function addProperty(Property $property, $table = null): Property
		{
			$className = get_called_class();
			$propertyName = $property->getPropertyName();

			if($table === null && isset(static::$properties[$className][$propertyName]))
			{
				$currentProperty = static::$properties[$className][$propertyName];

				if($currentProperty->table !== null)
				{
					$table = $currentProperty->table;
				}
			}

			if($table === null)
			{
				$table = static::TABLE;
			}

			$property->table = $table;
			static::$properties[$className][$propertyName] = $property;
			
			return $property;
		}

		/**
		 * Sets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			static::addProperty(new Property("id", static::ID_FIELD, "int"));

			if(static::SLUG_PROPERTY !== null)
			{
				static::addProperty(new Property("slug", "slug", "string"));
			}

			if(static::HAS_AUTOCAST)
			{
				static::addProperty(new Property("class", "class", "string"));
			}
		}

		/**
		 * Retrieves a single value from the loaded array
		 * @param	class-string<Entity>|null	$class	The class of object to load the entity for
		 * @param	int|null					$id		The id for that entity
		 * @return	self|null							The loaded value, or null if it hasn't been loaded or has been garbage collected
		 */
		private static function getLoaded(?string $class, ?int $id): ?self
		{
			if($class === null || $id === null || !isset(self::$loaded[$class][$id]))
			{
				return null;
			}

			$entity = self::$loaded[$class][$id]->get();
			assert($entity instanceof self);

			return $entity;
		}

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 * @return    Property[]    Name => Property pairs
		 */
		public static function getProperties()
		{
			$className = get_called_class();

			if(!isset(static::$properties[$className]))
			{
				/** @var string|Entity $parent */
				$parent = get_parent_class(static::class);

				if($parent !== false)
				{
					foreach($parent::getProperties() as $propertyName => $property)
					{
						static::$properties[$className][$propertyName] = $property;
					}
				}

				try
				{
					foreach(PropertyAttribute::retrievePropertiesFromAttributes(static::class) as $property)
					{
						static::addProperty($property);
					}
				}
				catch(ReflectionException)
				{
					// It shouldn't be possible for this class not to exist
				}

				static::properties();

				assert(isset(static::$properties[$className]["id"]), "parent::properties() has been called");
			}

			return static::$properties[$className];
		}

		/**
		 * Gets a specific property
		 * @template 	T								The type of property to return
		 * @param		string					$name	The name of the property to get
		 * @param		class-string<T>|null	$type	The type of property to return (for convenient static analysis and type checking)
		 * @return		Property|T						That property
		 */
		public static function getProperty(string $name, ?string $type = null)
		{
			$property = static::getProperties()[$name] ?? null;

			if($property === null)
			{
				$class = static::class;
				throw new Error("There is no property named '{$name}' in '{$class}'");
			}

			if($type !== null && !is_a($property, $type, true))
			{
				$class = static::class;
				$actual = $property::class;
				throw new Error("Expected {$type} and got {$actual} for property '{$name}' in '{$class}'");
			}

			return $property;
		}

		/**
		 * Creates a nullified version of this Object
		 * @return    static    The nullifed Object
		 */
		public static function makeNull()
		{
			$entity = new static();
			$entity->isNull = true;

			return $entity;
		}

		/**
		 * Gets the top level class with a non empty table name
		 * @return    Entity|string    The top level class
		 */
		public static function getTopLevelClass()
		{
			if(static::TABLE === null)
			{
				return null;
			}

			/** @var Entity $parentClass */
			$parentClass = get_parent_class(get_called_class());

			$nextLevel = $parentClass::getTopLevelClass();

			if($nextLevel !== null)
			{
				return $nextLevel;
			}

			return get_called_class();
		}

		/**
		 * Converts an unprocessed class name to an FQN
		 * @param	string|Entity	$current	The class that's doing the calling
		 * @param	string			$className	The name to convert
		 * @return	string|Entity				The converted name
		 */
		private static function processClassName($current, string $className)
		{
			if($className[0] !== "/")
			{
				$callingClass = str_replace("\\", "/", is_string($current) ? $current : get_class($current));
				$namespace = dirname($callingClass);

				if($namespace != ".")
				{
					$className = $namespace . "/" . $className;
				}
				else
				{
					$className = "/" . $className;
				}
			}

			return str_replace("/", "\\", $className);
		}

		/**
		 * Replaces names preceded by ~ with replacement names
		 * Specifically:
		 * Class names preceded by a ~ will be replaced by the associated table, ~ClassName becomes `table`
		 * Class names followed by a dot and a property name will be replaced with the associated table and field, ~ClassName.~property becomes `table`.`field`
		 * Namespaces are represented as forward slashes, and are relative to the namespace of the current class
		 * Property names that belong to the current class, preceded by a ~ will be replaced by the associated field preceded by this table name, ~property becomes `table`.`field`
		 * The reserved keyword TABLE is replaced with the table that belongs to the current class, so ~TABLE becomes `table`
		 * The reserved keyword PROPERTIES is replaced by the select string for the current class, so ~PROPERTIES becomes `table`.`field1`, `table`.`field2`, etc.
		 * @param	Entity|string	$current	The current class
		 * @param	string			$query		The query to process
		 * @return	string						The SQL appropriate query
		 */
		public static function processQueryFor($current, string $query)
		{
			$newQuery = $query;

			$newQuery = str_replace("~TABLE", $current::tableString(), $newQuery);

			//tilde followed by class name dot tilde property name eg ~Product.~active
			$newQuery = preg_replace_callback("/~(\\/?[A-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff\\/]*)\\.~([a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)/", function($matches) use($current)
			{
				/** @var class-string<Entity> $className */
				$className = $matches[1];
				$className = self::processClassName($current, $className);
				$propertyName = $matches[2];

				if($propertyName === 'PROPERTIES')
				{
					return $className::selectString();
				}
				else if(!isset($className::getProperties()[$propertyName]))
				{
					throw new Error("Property " . $propertyName . " does not exist in " . $className);
				}

				/** @var Property $property */
				$property = $className::getProperties()[$propertyName];
				$field = $property->getDatabaseName();

				$table = $property->table;

				if($propertyName === "id")
				{
					$table = $className::TABLE;
				}

				return "`" . $table . "`.`" . $field . "`";
			}, $newQuery);

			//single tilde followed by a single name eg ~active or ~PROPERTIES or ~Product
			$newQuery = preg_replace_callback("/~(\\/?[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff\\/]*)/", function($matches) use($current)
			{
				$replaceName = $matches[1];

				//is this a class or a property? Checking if lowercase version of first character is different - then it's a class
				if($replaceName[0] !== strtolower($replaceName[0]) || $replaceName[0] === "/")
				{
					$className = $replaceName;

					if($className === "PROPERTIES")
					{
						return $current::selectString();
					}

					$className = self::processClassName($current, $className);

					return "`" . $className::TABLE . "`";
				}
				else
				{
					if(!$current::hasProperty($replaceName))
					{
						throw new Error("Property " . $replaceName . " does not exist in " . get_called_class());
					}

					$property = $current::getProperties()[$replaceName];

					return "`" . $property->table . "`.`" . $property->getDatabaseName() . "`";
				}
			}, $newQuery);

			return $newQuery;
		}

		/**
		 * Shortcut for calling processQuery(static::class, $query)
		 * @param	string	$query	The query to process
		 * @return	string			The processed query
		 */
		protected static function processQuery(string $query)
		{
			return self::processQueryFor(static::class, $query);
		}

		/**
		 * test if the object has a property
		 *    in it's own method so it can be extended for objects which draw properties from multiple
		 *    sources eg children
		 * @param string $propertyName
		 * @return bool
		 */
		public static function hasProperty($propertyName)
		{
			return isset(static::getProperties()[$propertyName]);
		}

		/**
		 * expose the database names for properties
		 *    in it's own method so it can be extended for objects which draw properties from multiple
		 *    sources eg children
		 * @param string $propertyName
		 * @return string
		 */
		public static function getDatabaseNameForProperty($propertyName)
		{
			return static::getProperties()[$propertyName]->getDatabaseName();
		}

		/**
		 * Gets the class a database row should be cast to
		 * @param    array $row The database row as an associative array
		 * @return    class-string<Entity>            The class to cast to
		 */
		protected static function getClassNameForRow(array $row)
		{
			$className = get_called_class();

			if(static::HAS_AUTOCAST)
			{
				$property = static::getProperties()["class"];
				$className = $row[$property->getDatabaseName()];
			}

			return $className;
		}

		/**
		 * Makes a Object from a row retrieved from the database
		 * @param    array $row The database row as an associative array
		 * @return    static    The new Object
		 */
		protected static function makeFromRow(array $row)
		{
			$id = $row[static::ID_FIELD];

			if($id === null)
			{
				return static::makeNull();
			}

			$className = static::getClassNameForRow($row);
			$topLevel = static::getTopLevelClass();
			$entity = self::getLoaded($topLevel, $id);

			if($entity === null)
			{
				/** @var  Entity $entity */
				$entity = new $className();

				foreach($className::getProperties() as $propertyName => $property)
				{
					$valueWrapper = $entity->valueWrappers[$propertyName];

					if($property->shouldAddToQuery())
					{
						if(array_key_exists($property->getDatabaseName(), $row))
						{
							$valueWrapper->setFromDatabase($row[$property->getDatabaseName()]); // Set value wrapper
						}
						else
						{
							//Didn't find the data we want here, could be something from a subclass, so this still needs to be loaded later from there
							$valueWrapper->resetToPending();
						}
					}
					else
					{
						$valueWrapper->setFromDatabase(null);
					}
				}

				self::$loaded[$topLevel][$entity->id] = WeakReference::create($entity);

				// In the event that slugs are enabled on this class after some objects have been added to the database, we'll trigger save which will automatically generate a new slug
				if(static::SLUG_PROPERTY !== null && $entity->slug === "")
				{
					$entity->save();
				}
			}


			if(isset($row["_relevance"]))
			{
				$entity->relevance = $row["_relevance"];
			}

			return $entity;
		}

		/**
		 * Makes a single Object from a query
		 * @param       string $query  The query string to be run
		 * @param       array  $params Array of parameters
		 * @return static The new Object
		 */
		protected static function makeOne($query, $params = [])
		{
			$processedQuery = static::processQuery($query);

			$result = Database::getDefaultQueryRunner()->run($processedQuery, $params);

			/** @var Entity|string $className */
			$className = get_called_class();

			if(count($result) === 0)
			{
				return $className::makeNull();
			}

			$id = $result[0][static::ID_FIELD];

			$topLevel = static::getTopLevelClass();
			$entity = self::getLoaded($topLevel, $id) ?? static::makeFromRow($result[0]);


			if(!$entity instanceof static)
			{
				return static::makeNull();
			}

			return $entity;
		}

		/**
		 * Makes an array of Objects from a query
		 * @param       string $query  The query string to be run
		 * @param       array  $params Array of parameters
		 * @return static[] The array of Objects
		 */
		protected static function makeMany($query, $params = [])
		{
			$processedQuery = static::processQuery($query);
			$result = Database::getDefaultQueryRunner()->run($processedQuery, $params);

			$entities = [];

			foreach($result as $row)
			{
				$class = static::getClassNameForRow($row);

				if(is_a($class, static::class, true))
				{
					$entity = static::makeFromRow($row);
					$entities[] = $entity;
				}
			}

			return $entities;
		}

		/**
		 * Generates a query that counts the number of rows that would be returned by an unprocessed query
		 * @param	string	$query	The unprocessed query to generate the paginated query from
		 * @return	string			The counting query
		 */
		public static function generateCountQuery(string $query): string
		{
			$query = str_replace("DISTINCT ~PROPERTIES", "COUNT(DISTINCT ~id) AS row_count", $query); // Handle distinct queries slightly differently first
			$query = str_replace("~PROPERTIES", "COUNT(~id) AS row_count", $query);

			return static::processQuery($query);
		}

		/**
		 * Generates a query that limits the number of rows from an unprocessed query
		 * @param	string	$query	The unprocessed query to generate the paginated query from
		 * @return	string			The paginated query
		 */
		public static function generatePaginationQuery(string $query): string
		{
			$processedQuery = static::processQuery($query);
			return $processedQuery . " LIMIT ?, ?";
		}

		/**
		 * Creates a Pagination from a query
		 * @param    string $query   The query string to be run
		 * @param    array  $params  Array of parameters
		 * @param    int    $current Current page of the pagination
		 * @param    int    $perPage Number of rows per page
		 * @return    Pagination                    Object containing the results of the Pagination query
		 */
		protected static function makePages($query, $params, $current, $perPage)
		{
			$currentOffset = (($current - 1) * $perPage);

			$processedCountQuery = static::generateCountQuery($query);
			$processedResult = Database::getDefaultQueryRunner()->run($processedCountQuery, $params)[0];
			$totalRows = $processedResult["row_count"];

			$limitedQuery = static::generatePaginationQuery($query);
			$params[] = $currentOffset;
			$params[] = $perPage;

			$results = static::makeMany($limitedQuery, $params);

			return new Pagination($results, $totalRows, $currentOffset, $perPage);
		}

		/**
		 * Returns the string for selecting all the relevant fields from the database
		 * @param	string|Entity	$class	The name of the class to get the select string for
		 * @return	string 					The selection string
		 */
		public static function selectStringFor($class): string
		{
			$values = [];

			foreach($class::getProperties() as $property)
			{
				if($property->shouldAddToQuery() && in_array($property->table, $class::getTables()))
				{
					$values[] = "`" . $property->table . "`.`" . $property->getDatabaseName() . "`";
				}
			}

			return implode(", ", $values);
		}

		/**
		 * A shortcut for calling selectStringFor(static::class)
		 * @return	string	The selection string
		 */
		protected static function selectString(): string
		{
			return static::selectStringFor(static::class);
		}

		/**
		 * Gets all the tables belonging to an Entity class
		 * @param	string|Entity	$class	The name of the class to get the tables for
		 * @return	string[]				The tables
		 */
		public static function getTablesFor($class): array
		{
			$tables = [];

			foreach($class::getProperties() as $property)
			{
				$tables[$property->table] = true;
			}

			return array_keys($tables);
		}

		/**
		 * A shortcut for getTablesFor(static::class)
		 * @return	string[]	The tables
		 */
		protected static function getTables(): array
		{
			return static::getTablesFor(static::class);
		}

		/**
		 * Returns the string for selecting all the relevant tables from the database
		 * @param	string|Entity	$class	The name of the class to get the tables for
		 * @return	string    				The selection string
		 */
		public static function tableStringFor($class): string
		{
			$mainTable = $class::getProperties()["id"]->table;

			$tables = [$mainTable => true];
			$tableString = "`" . $mainTable . "`";

			foreach($class::getTables() as $table)
			{
				if(!isset($tables[$table]))
				{
					$tables[$table] = true;

					$tableString .= " LEFT JOIN `" . $table . "` "
								  . "ON `" . $table . "`.`" . $class::ID_FIELD . "` = `" . $mainTable . "`.`" . $class::ID_FIELD . "`";
				}
			}

			return $tableString;
		}

		/**
		 * A shortcut for tableStringFor(static::class)
		 * @return	string	The selection string
		 */
		protected static function tableString(): string
		{
			return static::tableStringFor(static::class);
		}

		/**
		 * Loads the object that have particular values
		 * @param    array $values Key/value pairs of property name => property value
		 * @return    static                The requested Object
		 */
		public static function loadForMultiple(array $values)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE ";

			$insertValues = [];
			$whereStrings = [];
			$properties = static::getProperties();

			foreach($values as $propertyName => $value)
			{
				if(!isset($properties[$propertyName]))
				{
					throw new Error($propertyName . " in " . get_called_class() . " is a private, protected or non-existent property.");
				}

				if($value !== null)
				{
					$insertValue = self::processValueForQuery($value, $propertyName);

					if(is_object($value) && isset($value->id))
					{
						$insertValue = $value->id;
					}

					$insertValues[] = $insertValue;
					$whereStrings[] = "~" . $propertyName . " = ? ";
				}
				else
				{
					$whereStrings[] = "~" . $propertyName . " IS NULL ";
				}
			}

			if(count($whereStrings) > 0)
			{
				$query .= "WHERE BINARY " . implode(" AND BINARY ", $whereStrings);
			}

			$query .= "LIMIT 1";

			return static::makeOne($query, $insertValues);
		}

		/**
		 * Converts a value into something to be passed into a query
		 * @param mixed		$value			the value to be used in the query
		 * @param string	$propertyName	the name of the Property to which the value belongs
		 * @return	mixed					The database value
		 */
		private static function processValueForQuery($value, $propertyName)
		{
			$property = static::getProperties()[$propertyName];

			$valueWrapper = $property->getValueWrapper(new static);
			$valueWrapper->setFromInput($value);

			return $valueWrapper->getForDatabase();
		}

		/**
		 * Loads the Object that has a particular value
		 * @param    string $propertyName The name of the property to filter by
		 * @param    mixed  $value        The value to filter by
		 * @return    static                    The requested Object
		 */
		public static function loadFor($propertyName, $value)
		{
			return static::loadForMultiple([$propertyName => $value]);
		}

		/**
		 * Loads the Object from the database based on the database identifier
		 * @param    int $id The database identifier
		 * @return    static    The loaded Object
		 */
		public static function load($id)
		{
			if($id === null)
			{
				return static::makeNull();
			}

			$topLevel = static::getTopLevelClass();
			return self::getLoaded($topLevel, $id) ?? static::loadFor("id", $id);
		}

		/**
		 * Returns a query and values that loads all the Entities that have particular values
		 * @param    array  $values  Key / value pairs of property name => property values
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    array                A tuple containing the query and the insert values
		 */
		private static function loadAllForMultipleQuery(array $values, array $orderBy = [])
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE ";

			$insertValues = [];
			$whereStrings = [];
			$properties = static::getProperties();

			foreach($values as $propertyName => $value)
			{
				if(!isset($properties[$propertyName]))
				{
					throw new Error($propertyName . " in " . get_called_class() . " is a private, protected or non-existent property.");
				}

				if($value !== null)
				{
					$insertValue = self::processValueForQuery($value, $propertyName);

					if(is_object($value) && isset($value->id))
					{
						$insertValue = $value->id;
					}

					$insertValues[] = $insertValue;
					$whereStrings[] = "~" . $propertyName . " = ?";
				}
				else
				{
					$whereStrings[] = "~" . $propertyName . " IS NULL";
				}
			}

			if(count($whereStrings) > 0)
			{
				$query .= "WHERE " . implode(" AND ", $whereStrings);
			}

			$orderByStrings = [];

			foreach($orderBy as $propertyName => $direction)
			{
				/** @var string|Entity $className */
				$className = get_called_class();
				$property = $className::getProperties()[$propertyName];
				$field = $property->getDatabaseName();

				if($field !== null && $field !== '')
				{
					$orderByStrings[] = "~" . $propertyName . " " . ($direction ? "ASC" : "DESC");
				}
			}

			if(count($orderByStrings) > 0)
			{
				$query .= " ORDER BY ";
				$query .= implode(", ", $orderByStrings);
			}

			return [
				$query,
				$insertValues
			];
		}

		/**
		 * Creates pages from objects that have particular values
		 * @param    array  $values  Key/value pairs of property name => property value
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @param    int    $current The current page to display
		 * @param    int    $perPage The number of items to display per page
		 * @return    Pagination                The Pagination object
		 */
		public static function loadPagesForMultiple(array $values, array $orderBy = [], $current = 1, $perPage = 10)
		{
			[$query, $insertValues] = self::loadAllForMultipleQuery($values, $orderBy);

			return static::makePages($query, $insertValues, $current, $perPage);
		}

		/**
		 * Loads all the Objects that have particular values
		 * @param    array  $values  Key/value pairs of property name => property value
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]                Array of Objects
		 */
		public static function loadAllForMultiple(array $values, array $orderBy = [])
		{
			[$query, $insertValues] = self::loadAllForMultipleQuery($values, $orderBy);

			return static::makeMany($query, $insertValues);
		}

		/**
		 * Creates pages from all the objects of this type
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @param    int    $current The current page to display
		 * @param    int    $perPage The number of items to display per page
		 * @return    Pagination                The Pagination object
		 */
		public static function loadAllPages(array $orderBy = [], $current = 1, $perPage = 10)
		{
			return static::loadPagesForMultiple([], $orderBy, $current, $perPage);
		}

		/**
		 * Loads all the Objects of this particular type from the database
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]    All the loaded Objects
		 */
		public static function loadAll(array $orderBy = [])
		{
			return static::loadAllForMultiple([], $orderBy);
		}

		/**
		 * Creates pages from all the objects that have a particular value
		 * @param    string $propertyName The property to filter by
		 * @param    mixed  $value        The value to filter by
		 * @param    bool[] $orderBy      List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @param    int    $current      The current page to display
		 * @param    int    $perPage      The number of items to display per page
		 * @return    Pagination                    The Pagination object
		 */
		public static function loadAllPagesFor($propertyName, $value, array $orderBy = [], $current = 1, $perPage = 10)
		{
			return static::loadPagesForMultiple([$propertyName => $value], $orderBy, $current, $perPage);
		}

		/**
		 * Loads all the Objects that have a particular value
		 * @param    string $propertyName The property to filter by
		 * @param    mixed  $value        The value to filter by
		 * @param    bool[] $orderBy      List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]                    Array of Objects
		 */
		public static function loadAllFor($propertyName, $value, array $orderBy = [])
		{
			return static::loadAllForMultiple([$propertyName => $value], $orderBy);
		}

		/**
		 * Loads all the Objects that are linked to from a Link To Multiple Property
		 * @param    string $propertyName The property in this Entity to filter by
		 * @param    int    $id           The identifier for the opposite object to load for
		 * @return    static[]                    The linked objects
		 */
		public static function loadAllLinked($propertyName, $id)
		{
			/** @var LinkProperty $property */
			$property = static::getProperties()[$propertyName];

			/** @var Entity|string $className */
			$className = $property->getType();

			$matchingProperty = $className::getProperties()[$property->getRelatedPropertyName()];

			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "INNER JOIN `" . $property->getTable() . "` "
				. "ON `" . $property->getTable() . "`.`" . $property->getDatabaseName() . "` = ~id "
				. "WHERE `" . $property->getTable() . "`.`" . $matchingProperty->getDatabaseName() . "` = ? "
				. "ORDER BY `" . $property->getTable() . "`.position";

			return static::makeMany($query, [$id]);
		}

		/**
		 * Loads all objects of this type that match a particular slug
		 * @param	string		$slug	The slug to match against
		 * @return	static[]			All the objects in this class that match this slug
		 */
		public static function loadAllForSlug(string $slug)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE LOWER(~slug) = ?";

			return static::makeMany($query, [strtolower($slug)]);
		}

		/**
		 * Loads an object that matches a slug (case insensitive)
		 * @param	string	$slug			The slug to match against
		 * @param	Entity	$parent			The parent of the object matching that slug
		 * @param	bool	$checkActive	Whether to only load entities that are "active"
		 * @return	static					The matching object
		 */
		public static function loadForSlug($slug, Entity $parent = null, bool $checkActive = false)
		{
			if(static::SLUG_PROPERTY === null)
			{
				return static::makeNull();
			}

			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE LOWER(~slug) = ? ";

			$parameters = [strtolower($slug)];

			$properties = static::getProperties();
			$pathParentProperty = null;
			if(static::PATH_PARENT !== null && isset($properties[static::PATH_PARENT]))
			{
				$pathParentProperty = $properties[static::PATH_PARENT];
			}

			if($pathParentProperty !== null && $pathParentProperty->getDatabaseName() !== null)
			{
				if($parent === null)
				{
					$query .= "AND ~" . static::PATH_PARENT . " IS NULL ";
				}
				else
				{
					$query .= "AND ~" . static::PATH_PARENT . " = ? ";

					$parameters[] = $parent->id;
				}
			}

			$query .= "LIMIT 1";

			return static::makeOne($query, $parameters);
		}

		/**
		 * Returns an array of options. In child classes, this should be overwritten to use names or whatever other human readable identifier is needed
		 * @param	bool		$includeNone	Whether to include a "None" option, with a value of null
		 * @return	FormOption[]					The options to choose from
		 */
		public static function loadOptions(bool $includeNone = false): array
		{
			$options = [];

			if($includeNone)
			{
				$options[] = new FormOption("None", null);
			}

			foreach(static::loadAll() as $entity)
			{
				$options[] = new FormOption($entity->id, $entity);
			}

			return $options;
		}

		/**
		 * Returns an array of options, including a selected option, if such is not already selected
		 * @param    string  $valueProperty  The property name to use for the option values
		 * @param    string  $outputProperty The property name to use to display to the users
		 * @param    mixed[] $currentValue   A double containing the key and value for the current option (or just the key, if you don't want to keep the current option when it's not included in the options)
		 * @return    string    The HTML for the options
		 */
		public static function makeOptions($valueProperty, $outputProperty, array $currentValue = null)
		{
			$html = "";
			$hasCurrent = false;

			if($currentValue === null || !isset($currentValue[1]))
			{
				$hasCurrent = true;
			}

			foreach(static::loadAll([]) as $entity)
			{
				$selected = "";

				if($currentValue !== null && $entity->$valueProperty === $currentValue[0])
				{
					$selected = "selected='selected'";
					$hasCurrent = true;
				}

				$html .= "<option value='" . htmlspecialchars($entity->$valueProperty, ENT_QUOTES & ~ENT_COMPAT) . "' " . $selected . ">" . $entity->$outputProperty . "</option>\n";
			}

			if(!$hasCurrent)
			{
				$html = "<option value='" . htmlspecialchars($currentValue[0], ENT_QUOTES & ~ENT_COMPAT) . "' selected='selected'>" . $currentValue[1] . "</option>\n" . $html;
			}

			return $html;
		}

		/**
		 * Loads any value wrappers that haven't been loaded yet

		 * @param    Property $property The Property that triggered the load
		 */
		public function updateUnloadedValueWrappers(Property $property)
		{
			$unloadedValueWrappers = [];

			foreach($this->valueWrappers as $propertyName => $valueWrapper)
			{
				$loopProperty = static::getProperties()[$propertyName];

				if($valueWrapper->getStatus() === ValueWrapper::PENDING && $loopProperty->table === $property->table)
				{
					$unloadedValueWrappers[] = "~" . $propertyName;
				}
			}

			// No pending value wrappers found
			if(count($unloadedValueWrappers) === 0)
			{
				return;
			}

			$query = "SELECT " . implode(", ", $unloadedValueWrappers) . " "
				. "FROM `" . $property->table . "` "
				. "WHERE `" . $property->table . "`.`" . static::ID_FIELD . "` = ?";

			$results = Database::getDefaultQueryRunner()->run(static::processQuery($query), [$this->id]);

			if(count($results) === 0)
			{
				return;
			}

			// Results found, set value wrappers

			$result = $results[0];

			foreach($this->valueWrappers as $propertyName => $valueWrapper)
			{
				$loopProperty = static::getProperties()[$propertyName];

				if($valueWrapper->getStatus() === ValueWrapper::PENDING && $loopProperty->table === $property->table)
				{
					$property = static::getProperties()[$propertyName];
					$value = $result[$property->getDatabaseName()];
					$valueWrapper->setFromDatabase($value);
				}
			}
		}

		/**
		 * Moves properties into the value wrappers array
		 */
		protected function assignValueWrappers()
		{
			foreach(static::getProperties() as $propertyName => $property)
			{
				$valueWrapper = $property->getValueWrapper($this);

				if(isset($this->$propertyName))
				{
					$value = $this->$propertyName;
				}
				else
				{
					$value = null;
				}

				$valueWrapper->setFromInitial($value);
				$this->valueWrappers[$propertyName] = $valueWrapper;

				unset($this->$propertyName);
			}
		}

		/**
		 * Instantiates default objects
		 */
		protected function setDefaults()
		{
			foreach(static::getProperties() as $propertyName => $property)
			{
				switch($property->getType())
				{
					case "date":
					case "datetime":
					case "time":
					case "timestamp":
						$this->valueWrappers[$propertyName]->setFromInitial(date("Y-m-d H:i:s"));
						break;
				}

				if(static::HAS_AUTOCAST)
				{
					$this->class = static::class;
				}
			}
		}

		/**
		 * Creates a new Object and sets any properties that need default objects
		 */
		public function __construct()
		{
			$this->assignValueWrappers();
			$this->setDefaults();
		}

		/**
		 * Gets the attributes for this entity
		 * @return	ValueWrapper[]		The attributes
		 */
		public function getValueWrappers(): array
		{
			return $this->valueWrappers;
		}

		/**
		 * Checks whether this entity can be saved to the database
		 * @param	bool	$justTriggerProcesses	Whether we're checking if we should just trigger save processes

		 */
		public function canBeSaved(bool $justTriggerProcesses = false): bool
		{
			return !$this->isNull() && !$this->deleted;

		}

		/**
		 * Runs before the entity is saved
		 * @param	bool	$isCreate	Whether this is a new entity or not

		 */
		public function beforeSave(bool $isCreate)
		{
			$this->setSlug($this->slug);

			foreach($this->valueWrappers as $valueWrapper)
			{
				$valueWrapper->beforeSave();
			}
		}

		/**
		 * Runs after the entity is saved
		 * @param	bool	$isCreate	Whether this was a new entity, or not
		 */
		public function afterSave(bool $isCreate)
		{
			foreach($this->valueWrappers as $valueWrapper)
			{
				$valueWrapper->afterSave();
			}

			self::$loaded[static::getTopLevelClass()][$this->id] = WeakReference::create($this);
		}

		/**
		 * Either updates or inserts the Object into the database
		 */
		public final function save()
		{
			SaveHandler::getCurrent()->save($this);
		}

		/**
		 * Runs before this entity is deleted

		 */
		public function beforeDelete()
		{
			foreach(static::getProperties() as $property)
			{
				$property->beforeDelete($this);
			}
		}

		/**
		 * Runs after this entity is deleted

		 */
		public function afterDelete()
		{
			unset(self::$loaded[static::getTopLevelClass()][$this->id]);
		}

		/**
		 * Deletes the Entity from the database

		 */
		public final function delete()
		{
			SaveHandler::getCurrent()->delete($this);
		}

		/**
		 * Checks if this is a null object
		 * @return    bool    Whether this is a null object
		 */
		public function isNull()
		{
			return $this->isNull;
		}

		/**
		 * Gets the value of a specific property
		 * @param    string $name The name of the property to get
		 * @return    mixed                                    The value of that property
		 */
		protected function getValue($name)
		{
			if(!isset($this->valueWrappers[$name]))
			{
				throw new Error($name . " in " . get_called_class() . " is a private, protected or non-existent property.");
			}

			$valueWrapper = $this->valueWrappers[$name];

			if($valueWrapper->getStatus() === ValueWrapper::PENDING)
			{
				$this->updateUnloadedValueWrappers(static::getProperties()[$name]);
			}

			return $valueWrapper->getForOutput();
		}

		/**
		 * Gets the path parent sluggable objects for this object
		 * @return	self[]|null[]	The parent objects (null is considered top level)
		 */
		public function getSlugParents()
		{
			if(static::PATH_PARENT === null)
			{
				return [null];
			}
			else
			{
				return [$this->{static::PATH_PARENT}];
			}
		}

		/**
		 * Sets the value of the slug
		 * @param    string $value The value to use for the slug
		 */
		protected function setSlug($value)
		{
			if(static::SLUG_PROPERTY === null)
			{
				return;
			}

			// If the value is empty, uses the value of the slug property. If that's empty, uses a unique identifier.
			$value = $value ?: $this->{static::SLUG_PROPERTY} ?: uniqid();

			$baseSlug = Slugging::slug($value);
			$this->valueWrappers["slug"]->setFromInput($baseSlug);
			$counter = 1;

			while(Slugging::conflictExists($this))
			{
				$counter += 1;
				$this->valueWrappers["slug"]->setFromInput("{$baseSlug}-{$counter}");
			}
		}

		/**
		 * Sets the slug property (will be overridden in Generator)
		 * @param	string	$value	The value to set
		 */
		protected function setSlugProperty($value)
		{
			// Empty slugs should be ignored, they'll be dealt with at save time
			if($value === "")
			{
				return;
			}

			$this->setSlug($value);
		}

		/**
		 * Sets the value of a specific property
		 * @param    string $name  The name of the property to set
		 * @param    mixed  $value The value of that property
		 */
		protected function setValue($name, $value)
		{
			if(!isset($this->valueWrappers[$name]))
			{
				throw new Error($name . " in " . get_called_class() . " is a private, protected or non-existent property.");
			}

			$valueWrapper = $this->valueWrappers[$name];
			$valueWrapper->setFromInput($value);

			if($name === static::SLUG_PROPERTY)
			{
				$this->setSlugProperty($value);
			}
		}

		/**
		 * Gets a property if it belongs to the properties method
		 * @param     string $name The name of the property to get
		 * @return mixed The value of that property
		 */
		public function __get($name)
		{
			if($name === '')
			{
				throw new Error("Incorrect argument supplied in __get() in " . get_called_class());
			}

			$property = static::getProperties()[$name] ?? null;

			if($property === null)
			{
				trigger_error($name . " in " . get_called_class() . " is a private, protected or non-existent property", E_USER_WARNING);
			}

			if(method_exists($this, "get_{$name}"))
			{
				return $this->{"get_{$name}"}();
			}
			else if($property->getter === null)
			{
				return $this->getValue($name);
			}
			else
			{
				return $property->getter->call($this, $this);
			}
		}

		/**
		 * Sets a property if it belongs to the properties method
		 * @param     string $name  The name of the property to set
		 * @param     mixed  $value The value to set the property to
		 */
		public function __set($name, $value)
		{
			if($name === '')
			{
				throw new Error("Incorrect argument supplied in __set() in " . get_called_class());
			}

			$property = static::getProperties()[$name] ?? null;

			if($property === null)
			{
				trigger_error($name . " in " . get_called_class() . " is a private, protected or non-existent property", E_USER_WARNING);
			}

			if(method_exists($this, "set_{$name}"))
			{
				$this->{"set_{$name}"}($value);
			}
			else if($property->setter === null)
			{
				$this->setValue($name, $value);
			}
			elseif($property->setterParameterCount === 1)
			{
				$property->setter->call($this, $value);
			}
			else
			{
				$property->setter->call($this, $this, $value);
			}
		}

		/**
		 * Checks if a property is set
		 * @param     string $name The name of the property to check
		 * @return bool
		 */
		public function __isset($name)
		{
			if(isset(static::getProperties()[$name]) || !property_exists($this, $name))
			{
				$value = isset($this->$name) || isset($this->valueWrappers[$name]);
			}
			else
			{
				throw new Error($name . " in " . get_called_class() . " is a private, protected or non-existent property.");
			}

			return $value;
		}

		/**
		 * Unset a property
		 * @param     string $name The name of the property to check
		 */
		public function __unset($name)
		{
			if(isset(static::getProperties()[$name]) || !property_exists($this, $name))
			{
				unset($this->$name);
			}
			else
			{
				throw new Error($name . " in " . get_called_class() . " is a private, protected or non-existent property.");
			}
		}

		/**
		 * Specifies what data should be serialised when json_encode is called
		 * @return    array    Name/data pairs for the data in this object
		 */
		public function jsonSerialize(): mixed
		{
			$class = static::class;
			throw new Error("{$class} must implement jsonSerialize()");
		}

		/**
		 * Runs on clone
		 */
		public function __clone()
		{
			$this->setSlug($this->slug);

			foreach($this->valueWrappers as $propertyName => $attribute)
			{
				$this->valueWrappers[$propertyName] = clone $attribute;
				$this->valueWrappers[$propertyName]->setEntity($this);
			}

			foreach (static::getProperties() as $propertyName => $property)
			{
				if($property instanceof LinkFromMultipleProperty)
				{
					$className = $property->getType();

					if(is_a($className, GeneratorGroup::class, true))
					{
						// letting ParentChild be cloned ends up with a clone of the child under the original parent
						// if you are cloning down a tree (eg categories /subcategories) this results in a number of clones
						// of the child equal to n-1 where n is the number of ancestors
						// the mechanism by which ORM arrivers at this is unknown at the time of adding this fix
						// As anything being handled by ParentChild should also have its own class-specfic
						// LinkFromMultiple property in the parent which will be processed in this loop
						// skipping this should have no other effect.
						continue;
					}

					$entities = [];

					foreach($this->$propertyName as $entity)
					{
						$entities[] = clone $entity;
					}

					$this->$propertyName = $entities;
				}
			}

			$this->id = null;
		}

		/**
		 * Serialisation
		 */
		public function __wakeup()
		{
			foreach(static::getProperties() as $propertyName => $property)
			{
				unset($this->$propertyName);
			}
		}

		/**
		 * Gets the information that should be shown on a var_dump
		 * @return array An array of debug information
		 */
		public function __debugInfo()
		{
			$started = false;

			if(self::$dumpedObjects === null)
			{
				self::$dumpedObjects = [];
				$started = true;
			}

			if(in_array($this, self::$dumpedObjects))
			{
				return
					[
						"class" => get_class($this),
						"id" => $this->id,
						"RECURSION" => true
					];
			}

			self::$dumpedObjects[] = $this;

			$values = [];
			$values["isNull"] = $this->isNull();

			foreach($this->valueWrappers as $name => $valueWrapper)
			{
				$values[$name] = $valueWrapper->getForOutput();

				if($valueWrapper instanceof LinkWrapper)
				{
					$values[$name] = $valueWrapper->getForDatabase();
				}
			}

			if($started)
			{
				self::$dumpedObjects = null;
			}

			return $values;
		}

		//region Searchable

		/**
		 * Performs a search using the supplied string
		 * @param	string		$term	The term to search
		 * @return	static[]			Search Results for this Searchable
		 */
		public static function search($term)
		{
			$searchableProperties = [];

			foreach(static::getProperties() as $propertyName => $property)
			{
				if($property->searchable)
				{
					$searchableProperties[$property->table][] = "~" . $propertyName;
				}
			}

			if(count($searchableProperties) === 0)
			{
				return [];
			}

			$matchTerms = [];
			$parameters = [];

			foreach($searchableProperties as $score => $properties)
			{
				$matchTerms[] = "MATCH(" . implode(", ", $properties) . ") AGAINST(?)";
				$parameters[] = $term;
			}

			$query = "SELECT ~PROPERTIES, " . implode(" + ", $matchTerms) . " AS _relevance "
				   . "FROM ~TABLE "
				   . "HAVING _relevance > ? "
				   . "ORDER BY _relevance DESC";

			$parameters[] = static::RELEVANCE_CUTOFF;

			return static::makeMany($query, $parameters);
		}

		//endregion
	}