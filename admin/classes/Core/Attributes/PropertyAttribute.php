<?php
	namespace Core\Attributes;
	
	use Core\Entity;
	use Core\Properties\LinkProperty;
	use Core\Properties\Property;
	use Forms\Form;
	use Generator;
	use ReflectionClass;
	use ReflectionException;
	
	/**
	 * Base class for all property attributes
	 */
	abstract class PropertyAttribute
	{
		/**
		 * Retrieves all the properties from property attributes in a specific entity class
		 * @param	class-string<Entity>	$class	The class to get the properties for
		 * @return	Generator<Property>				A generator for iterating over the properties (note: this is a native Generator)
		 * @throws	ReflectionException				If the class being passed in doesn't exist
		 */
		public static function retrievePropertiesFromAttributes(string $class): Generator
		{
			$classReflector = new ReflectionClass($class);
			
			foreach($classReflector->getProperties() as $propertyReflector)
			{
				foreach($propertyReflector->getAttributes() as $attribute)
				{
					if(is_a($attribute->getName(), self::class, true))
					{
						$attributeProperty = $attribute->newInstance();
						assert($attributeProperty instanceof PropertyAttribute);
						
						// Massage type to get rid of "null" and "?"
						$segments = explode("|", ltrim($propertyReflector->getType(), "?"));
						$filteredSegments = array_filter($segments, fn(string $segment) => $segment !== "null");
						$mappedSegments = array_map(fn(string $segment) => ($segment === "self") ? $propertyReflector->class : $segment, $filteredSegments);
						$type = implode("|", $mappedSegments);
						
						// Generate the property and make sure it's assigned to the correct table
						$property = $attributeProperty->getProperty($propertyReflector->getName(), $type);
						$propertyClass = $propertyReflector->getDeclaringClass()->getName();
						assert(is_a($propertyClass, Entity::class, true));
						$property->table = $propertyClass::getTopLevelClass()::TABLE;
						
						if(count($propertyReflector->getAttributes(IsSearchable::class)) > 0)
						{
							$property->setIsSearchable(true);
						}
						
						if($property instanceof LinkProperty)
						{
							foreach($propertyReflector->getAttributes(WithAutoDelete::class) as $withAutoDelete)
							{
								assert($withAutoDelete instanceof WithAutoDelete);
								$property->setAutoDelete($withAutoDelete->hasAutoDelete);
							}
						}
						
						yield $property;
					}
				}
			}
		}
		
		/**
		 * Retrieves the property from this attribute
		 * @param	string		$variableName	The name of the variable
		 * @param	string		$variableType	The type hint for the variable
		 * @return	Property					The actual property for the variable
		 */
		abstract public function getProperty(string $variableName, string $variableType): Property;
	}