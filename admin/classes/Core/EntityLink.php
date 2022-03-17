<?php
	namespace Core;

	use Core\ValueWrappers\LinkManyManyWrapper;
	use Core\Properties\LinkManyManyProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Database\Database;
	use PhpParser\Error;

	/**
	 * Represents a many-many link between two other Entities
	 */
	abstract class EntityLink extends Generator
	{
		const LINK_PROPERTIES = [];//used as a 2-tuple

		/**
		 * Loads all the entities that are related to a different entity
		 * @param Entity               $entity       The entity to load the entities for
		 * @param LinkManyManyProperty $leftProperty The property linking to this object
		 * @return    Entity[]                                The entities belonging to that entity
		 */
		public static function getRelatedEntities(Entity $entity, LinkManyManyProperty $leftProperty): array
		{
			$linkingObjectLeftProperty = static::getProperties()[$leftProperty->getRelatedPropertyName()];
			$linkingObjectRightProperty = null;

			foreach(static::LINK_PROPERTIES as $linkingObjectPropertyName)
			{
				if($linkingObjectPropertyName !== $leftProperty->getRelatedPropertyName())
				{
					$linkingObjectRightProperty = static::getProperties()[$linkingObjectPropertyName];
				}
			}

			$className = "/" . str_replace("\\", "/", static::class);

			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "INNER JOIN ~{$className} "
				   . "ON ~{$className}.~{$linkingObjectRightProperty->getPropertyName()} = ~id "
				   . "WHERE ~{$className}.~{$linkingObjectLeftProperty->getPropertyName()} = ?";

			/** @var class-string<Entity> $rightClass */
			$rightClass = $linkingObjectRightProperty->getType();
			$rightClassName = "/" . str_replace("\\", "/", $rightClass);

			if(count($leftProperty->getOrderBy()) > 0)
			{
				$segments = array_map(fn($field, $direction) => "~{$rightClassName}.~{$field} " . ($direction ? "ASC" : "DESC"), array_keys($leftProperty->getOrderBy()), array_values($leftProperty->getOrderBy()));
				$query .= " ORDER BY " . implode(", ", $segments);
			}
			else if(static::HAS_POSITION)
			{
				$query .= " ORDER BY ~{$className}.~position";
			}

			return $rightClass::makeMany($query, [$entity->id]);
		}

		/**
		 * Gets the opposite type from an existing property
		 * @param LinkManyManyProperty $leftProperty The property we're linking from
		 * @return    string|Entity                            The type we want
		 */
		public static function getOppositeType(LinkManyManyProperty $leftProperty)
		{
			foreach(static::LINK_PROPERTIES as $linkingObjectPropertyName)
			{
				if($linkingObjectPropertyName === $leftProperty->getRelatedPropertyName())
				{
					continue;
				}

				if (isset(static::getProperties()[$linkingObjectPropertyName]))
				{
					return static::getProperties()[$linkingObjectPropertyName]->getType();
				}
				else
				{
					throw new Error($linkingObjectPropertyName . " is not a property");
				}
			}

			//Theoretically this shouldn't be needed, as properties() has an assert to make sure we have the right number of property names in LINK_PROPERTY
			throw new Error("Could not find opposite type from {$leftProperty->getPropertyName()} on " . static::class);
		}

		/**
		 * Adds an entity to another entity
		 * @param Entity               $leftEntity   The base entity
		 * @param LinkManyManyProperty $leftProperty The property the entities are being added to
		 * @param Entity               $rightEntity  The entity being added to the base entity
		 * @return    self                                    A new entity to be saved later
		 */
		public static function addEntityToProperty(Entity $leftEntity, LinkManyManyProperty $leftProperty, Entity $rightEntity): self
		{
			$newLink = new static;

			foreach(static::LINK_PROPERTIES as $linkingPropertyName)
			{
				$linkingProperty = static::getProperties()[$linkingPropertyName];
				assert($linkingProperty instanceof LinkToProperty);

				if($linkingProperty->getPropertyName() === $leftProperty->getRelatedPropertyName())
				{
					$newLink->{$linkingProperty->getPropertyName()} = $leftEntity;
				}
				else
				{
					$newLink->{$linkingProperty->getPropertyName()} = $rightEntity;
				}
			}

			$leftValueWrapper = $leftEntity->valueWrappers[$leftProperty->getPropertyName()];
			assert($leftValueWrapper instanceof LinkManyManyWrapper);

			// Add entity to creating value wrapper
			$leftValues = $leftValueWrapper->getForOutput();
			$leftValues[] = $rightEntity;
			$leftValueWrapper->setFromInput($leftValues);

			$rightProperty = static::getOppositeProperty($leftProperty);
			$rightValueWrapper = $rightEntity->valueWrappers[$rightProperty->getPropertyName()];
			assert($rightValueWrapper instanceof LinkManyManyWrapper);

			// Add entity to opposite value wrapper
			$rightValues = $rightValueWrapper->getForOutput();
			$rightValues[] = $leftEntity;
			$rightValueWrapper->setFromInput($rightValues);

			return $newLink;
		}

		/**
		 * Gets the opposite property from an existing property
		 * @param LinkManyManyProperty $leftProperty The property in that entity
		 * @return    LinkManyManyProperty                    The property in the opposite entity
		 */
		public static function getOppositeProperty(LinkManyManyProperty $leftProperty): LinkManyManyProperty
		{
			$linkingObjectLeftProperty = static::getProperties()[$leftProperty->getRelatedPropertyName()];
			$linkingObjectRightProperty = null;

			foreach(static::LINK_PROPERTIES as $linkingObjectPropertyName)
			{
				$linkingObjectProperty = static::getProperties()[$linkingObjectPropertyName];

				if($linkingObjectProperty !== $linkingObjectLeftProperty)
				{
					$linkingObjectRightProperty = $linkingObjectProperty;
					break;
				}
			}

			assert($linkingObjectRightProperty instanceof LinkToProperty);
			/** @var string|Entity $rightClassName */
			$rightClassName = $linkingObjectRightProperty->getType();

			foreach($rightClassName::getProperties() as $rightProperty)
			{
				if($rightProperty instanceof LinkManyManyProperty && $rightProperty->getRelatedPropertyName() === $linkingObjectRightProperty->getPropertyName())
				{
					return $rightProperty;
				}
			}

			throw new Error("Could not find opposite property from {$leftProperty->getPropertyName()}");
		}

		/**
		 * Removes an entity from another entity
		 * @param Entity               $leftEntity   The base entity
		 * @param LinkManyManyProperty $leftProperty The property the entities are being removed from
		 * @param Entity               $rightEntity  The entity being removed from the base entity
		 * @return    self|null                                The object that has been removed, to be deleted later
		 */
		public static function removeEntityFromProperty(Entity $leftEntity, LinkManyManyProperty $leftProperty, Entity $rightEntity): ?self
		{
			$leftValueWrapper = $leftEntity->valueWrappers[$leftProperty->getPropertyName()];
			assert($leftValueWrapper instanceof LinkManyManyWrapper);

			$leftLinkedEntities = $leftValueWrapper->getForOutput();
			$leftIndex = array_search($rightEntity, $leftLinkedEntities);

			// Remove link from removing entity
			if($leftIndex !== false)
			{
				array_splice($leftLinkedEntities, $leftIndex, 1);
				$leftValueWrapper->setFromInput($leftLinkedEntities);
			}

			$rightProperty = static::getOppositeProperty($leftProperty);
			$rightValueWrapper = $rightEntity->valueWrappers[$rightProperty->getPropertyName()];
			assert($rightValueWrapper instanceof LinkManyManyWrapper);

			$rightLinkedEntities = $rightValueWrapper->getForOutput();
			$rightIndex = array_search($leftEntity, $rightLinkedEntities);

			// Remove link from removed entity
			if($rightIndex !== false)
			{
				array_splice($rightLinkedEntities, $rightIndex, 1);
				$rightValueWrapper->setFromInput($rightLinkedEntities);
			}

			if(static::LINK_PROPERTIES[0] === $leftProperty->getRelatedPropertyName())
			{
				$linkingObject = static::loadForMultiple([
					static::LINK_PROPERTIES[0] => $leftEntity,
					static::LINK_PROPERTIES[1] => $rightEntity
				]);
			}
			else
			{
				$linkingObject = static::loadForMultiple([
					static::LINK_PROPERTIES[1] => $leftEntity,
					static::LINK_PROPERTIES[0] => $rightEntity
				]);
			}

			if(!$linkingObject->isNull())
			{
				return $linkingObject;
			}

			return null;
		}

		/**
		 * Check whether this object already exists in the database
		 * @return	bool	Whether it exists
		 */
		public function alreadyExistsInDatabase()
		{
			assert($this->id === null, "EntityLink's alreadyExistsInDatabase() method should only be called on new entities, as existing entities already exist in the database by definition");
			$query = "SELECT COUNT(~id) AS row_count "
				. "FROM ~TABLE "
				. "WHERE ~" . static::LINK_PROPERTIES[0] . " = ? "
				. "AND ~" . static::LINK_PROPERTIES[1] . " = ?";

			$result = Database::query(static::processQuery($query), [$this->{static::LINK_PROPERTIES[0]}->id, $this->{static::LINK_PROPERTIES[1]}->id]);

			return $result[0]["row_count"] > 0;
		}

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			//this function is called for each parent class to get properties in the right table
			//so only do this if TABLE is set, otherwise it'll be called on this abstract class and fail the assert
			if(static::TABLE !== null)
			{
				assert(count(static::LINK_PROPERTIES) === 2, "LINK_PROPERTIES array contains exactly two property names");
			}
		}

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 * @return    Property[]    Name => Property pairs
		 */
		public static function getProperties()
		{
			$propertiesSet = isset(static::$properties[static::class]);
			$properties = parent::getProperties();

			if(!$propertiesSet)
			{
				foreach (static::LINK_PROPERTIES as $propertyName)
				{
					assert(isset($properties[$propertyName]), static::class . " has a " . $propertyName ." property");
				}
			}

			return $properties;
		}
	}
