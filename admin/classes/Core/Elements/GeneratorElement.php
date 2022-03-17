<?php
	namespace Core\Elements;

	use Admin\EditController;
	use Controller\JsonController;
	use Core\Elements\Base\ElementParent;
	use Core\Elements\Base\ResultElement;
	use Core\Generator;
	use Core\Properties\LinkProperty;
	use Exception;
	
	/**
	 * A group containing entire objects
	 */
	class GeneratorElement extends ResultElement
	{
		public $label = "";

		/** @var int|null */
		public $absolute = null;

		/** @var class-string<Generator> */
		public $class = "";

		/**
		 * Creates a new element
		 * @param	string				$name		The name of the element
		 * @param	string|null			$label		An optional heading for the generator block (particularly multi-generators)
		 * @param	string|Generator	$class		The class to create new objects with
		 * @param	mixed				$value		The value to use for this element
		 */
		public function __construct(string $name, ?string $label = null, $class = null, $value = self::EMPTY_VALUE)
		{
			parent::__construct($name, $value);

			$this->label = $label;
			$this->class = $class;
		}
		
		/**
		 * Creates a new instance of this object
		 * @param	string				$name		The name of the element
		 * @param	string|null			$label		An optional heading for the generator block (particularly multi-generators)
		 * @param	string|Generator	$class		The class to create new objects with
		 * @param	mixed				$value		The value to use for this element
		 */
		protected function createSubItem(string $name, ?string $label = null, $class = null, $value = self::EMPTY_VALUE)
		{
			return new GeneratorElement($name, $label, $class, $value);
		}

		/**
		 * Sets the number of child items that this element should have
		 * @param	int|null	$absolute	The absolute number of children to have, or null to allow any number of children
		 * @return	$this					This object, for chaining
		 */
		public function setAbsolute(?int $absolute): self
		{
			$this->absolute = $absolute;
			return $this;
		}

		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			parent::afterAdd($parent);

			if($this->class === null)
			{
				$property = $parent->getGenerator()::getProperties()[$this->name];
				assert($property instanceof LinkProperty);
				$this->class = $property->getType();
			}
		}

		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			if(is_array($this->getValue()))
			{
				return "generators/Generators.js";
			}
			else
			{
				return "generators/Generator.js";
			}
		}

		/**
		 * Creates a new object, and sets up any relationship with it's parent or linking objects
		 * @param	Generator|Generator[]	$value		The current value of this element
		 * @param	bool					$makeNull	Should make a null object
		 * @return	Generator							The new object
		 */
		public function getNewObject($value, bool $makeNull = false)
		{
			$class = $this->class;
			$object = $makeNull ? $class::makeNull() : new $class;
			$property = $this->generator::getProperties()[$this->name] ?? null;

			if($property === null || !$property instanceof LinkProperty)
			{
				return $object;
			}

			// Set up one-to-many relationship
			if(is_array($value))
			{
				$value[] = $object;

				$this->generator->{$this->name} = $value;
			}
			// Set up one-to-one relationship
			else
			{
				$this->generator->{$this->name} = $object;
			}

			return $object;
		}

		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			/** @var Generator|Generator[] $value */
			$value = $this->getValue();
			$output = [];

			if(!is_array($value))
			{
				$output = EditController::getJsonValue($value->getElements());
			}
			else
			{
				foreach($value as $generator)
				{
					if($generator->isNull())
					{
						continue;
					}

					$json = EditController::getJsonValue($generator->getElements());
					$json["id"] = $generator->id;

					if($generator::HAS_ACTIVE)
					{
						$json["active"] = $generator->active;
					}

					$output[] = $json;
				}
			}

			return $output;
		}

		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			/** @var Generator|Generator[] $value */
			$value = $this->getValue();
			$json = parent::getJson();


			if($this->label !== "")
			{
				$json["heading"] = $this->label;
			}

			// Linking to a single generator
			if(!is_array($value))
			{
				$json["elements"] = [];

				foreach($value->getElements() as $element)
				{
					$json["elements"][] = $element->getJson();
				}
			}
			// Linking to multiple generators
			else
			{
				$newObject = $this->getNewObject($value, true);
				$newGenerator = $this->createSubItem($this->name . ":0", null, $this->class, $newObject);
				$newGenerator->afterAdd($newObject);
				$newGeneratorJson = $newGenerator->getJson();
				$newGeneratorJson["dynamicLabel"] = $newObject->getDynamicLabelScript();

				if($newObject::HAS_ACTIVE)
				{
					$newGeneratorJson["active"] = $newObject->active;
				}

				if($newObject::HAS_POSITION)
				{
					$newGeneratorJson["position"] = $newObject->position;
				}

				$json["generators"] = [];
				// Cloning an object in JavaScript will consist of serialising then unserialising, so it's requires less processing to provide the serialised form
				$json["new"] = json_encode($newGeneratorJson, JsonController::getStandardEncodeOptions());
				$json["newValue"] = json_encode($newGenerator->getJsonValue(), JsonController::getStandardEncodeOptions());
				$json["singular"] = $this->class::SINGULAR;
				$json["labelKey"] = $this->class::LABEL_PROPERTY;
				$json["absolute"] = $this->absolute;

				foreach($value as $index => $generator)
				{
					$element = $this->createSubItem($this->name . ":" . $index, null, get_class($generator), $generator);
					$element->afterAdd($generator);
					$generatorJson = $element->getJson();
					$generatorJson["id"] = $generator->id;
					$generatorJson["temporaryId"] = uniqid();
					$generatorJson["dynamicLabel"] = $generator->getDynamicLabelScript();

					if($generator::HAS_ACTIVE)
					{
						$generatorJson["active"] = $generator->active;
					}

					if($generator::HAS_POSITION)
					{
						$generatorJson["position"] = $generator->position;
					}

					$json["generators"][] = $generatorJson;
				}
			}

			return $json;
		}

		/**
		 * Creates or loads an object and generates an associated element
		 * @param	int|null					$id		The id to load from
		 * @param	Generator|Generator[]				$value	The value(s) to use for the generator
		 * @param	int							$index	The index to use for the element name
		 * @return	array<Generator|static>				A pair, containing the object and the element
		 */
		protected function getObjectAndElement(?int $id, $value, int $index)
		{
			if($id === null)
			{
				$object = $this->getNewObject($value);
			}
			else
			{
				$object = $this->class::load($id);
			}

			$element = $this->createSubItem($this->name . ":" . $index, null, $this->class, $object);
			$element->afterAdd($object);
			$element->setResultHandler(function(){}); // These elements shouldn't be passed to the parent object, since they belong to this Generator FormElement

			return [$object, $element];
		}

		/**
		 * Gets the result of this element by converting JSON into something more usable
		 * @param	mixed					$json	The JSON to retrieve the result from
		 * @return	Generator|Generator[] 			The result that will be passed to the result handler
		 * @throws	Exception						If something goes wrong retrieving child results
		 */
		public function getResult($json)
		{
			/** @var Generator|Generator[] $value */
			$value = $this->getValue();
			$result = [];

			// For single object generators, will also be run for each object in a multi-object generator
			if(!is_array($value))
			{
				if($value->isNull())
				{
					$value = $this->getNewObject($value);
				}

				foreach($value->getElements() as $element)
				{
					if($element instanceof ResultElement && array_key_exists($element->name, $json))
					{
						$result[$element->name] = $element->getResult($json[$element->name]);
					}
				}
			}
			// For multi-object generators
			else
			{
				foreach($json as $index => $item)
				{
					[$object, $element] = $this->getObjectAndElement($item["id"], $value, $index);
					$result[$index] = $element->getResult($item);
					$result[$index]["id"] = $item["id"];

					if($object::HAS_ACTIVE && isset($item["active"]))
					{
						$result[$index]["active"] = $item["active"];
					}
				}
			}

			return $result;
		}

		/**
		 * Gets a validation error for this element
		 * @param mixed $result The result of this element
		 * @return    string[]                Any error messages to display
		 */
		public function validate($result): array
		{
			/** @var Generator|Generator[] $value */
			$value = $this->getValue();

			$errorMessages = [];

			if(!is_array($value))
			{
				foreach($value->getElements() as $element)
				{
					if($element instanceof ResultElement)
					{
						$errorMessages = array_merge($errorMessages, $element->validate($result[$element->name]));
					}
				}
			}
			else
			{
				foreach($result as $index => $item)
				{
					$element = $this->getObjectAndElement($item["id"], $value, $index)[1];
					$errorMessages = array_merge($errorMessages, $element->validate($item));
				}
			}

			return $errorMessages;
		}

		/**
		 * Passes the result of this element to the result handler, also runs through handleResult() on child elements
		 * @param	mixed	$result		The result to pass to the handler
		 */
		public function handleResult($result)
		{
			$value = $this->getValue();

			// If this is a single generator element
			if(!is_array($value))
			{
				// Create a new object if we don't have an existing one yet
				if($value->isNull())
				{
					$value = $this->getNewObject($value);
				}

				// Individually handles the elements for each child element
				foreach($value->getElements() as $element)
				{
					if($element instanceof ResultElement && isset($result[$element->name]))
					{
						$element->handleResult($result[$element->name]);
					}
				}

				// Send updated object back to parent
				parent::handleResult($value);
			}
			// If this is a multi object generator element
			else
			{
				$objects = [];

				// Iterate over each child result
				foreach($result as $index => $item)
				{
					// Drill down through the child element's handleResult(), which is the single generator branch of this function
					[$object, $element] = $this->getObjectAndElement($item["id"], $value, $index);
					$element->handleResult($result[$index]);

					// Active is defined separately from the rest of the elements
					if($object::HAS_ACTIVE && isset($result[$index]["active"]))
					{
						$object->active = $result[$index]["active"];
					}

					// So is position
					if($object::HAS_POSITION)
					{
						$object->position = $index;
					}

					$objects[] = $object;
				}

				// Pass the updated list of objects back to the parent (note, if any elements have been removed, they'll be automatically deleted by the LinkValueWrapper)
				parent::handleResult($objects);
			}
		}
	}