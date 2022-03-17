<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\ElementParent;
	use Core\Elements\Base\ResultElement;
	use Core\Generator;
	
	/**
	 * An element for when you need to be able to edit fields of a linking object. 
	 * Eg, you have a ProductPrice for each PriceGroup, which are created elsewhere in the admin,
	 * and a ProductPrice has both a wholesale and a regular price.
	 */
	class ArrayElement extends GeneratorElement
	{
		public $class = null;
		public $linkGeneratorObjects = [];
		public $linkGeneratorClass = '';
		public $linkGeneratorPropertyName = '';
		public $customValues = false;

		/**
		 * Creates a new element
		 * @param	string                  $name    					he name of the element
		 * @param	string                  $label  					A label for this element
		 * @param	string                  $linkGeneratorPropertyName  The linking object's other property that will need to be assigned to one of the $linkGeneratorObjects
		 * @param	FormOption[]	 		$linkGeneratorObjects		The objects for which we need to create a linking object between that and this element's generator
		 * @param	string|Generator		$class						The class to create new objects with
		 * @param	mixed                   $value  					The value to use for this element
		 */
		public function __construct(string $name, string $label, string $linkGeneratorPropertyName, array $linkGeneratorObjects = [], $class = null, $value = self::EMPTY_VALUE)
		{
			if ($value != self::EMPTY_VALUE) 
			{
				$this->customValues = true;
			}

			parent::__construct($name, $label, $class, $value);
			
			$this->linkGeneratorObjects = $linkGeneratorObjects;
			$this->linkGeneratorPropertyName = $linkGeneratorPropertyName;
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
			return new self($name, $label, $class, $value);
		}

		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			parent::afterAdd($parent);

			$class = $this->class;
			$relation = $this->linkGeneratorPropertyName;

			
			$linkObject = new $class;
			$linkGeneratorProperty = $linkObject::getProperties()[$relation];
			$linkGeneratorClass = $linkGeneratorProperty->getType();

			if (count($this->linkGeneratorObjects) <= 0)
			{
				$this->linkGeneratorObjects = $linkGeneratorClass::loadAll();
			}

			if (count($this->getValue()) <= 0) 
			{
				$values = [];
				foreach ($this->linkGeneratorObjects as $linkGeneratorObject) 
				{
					$object = new $class;
					$object->$relation = $linkGeneratorObject;

					$values[] = $object;
				}
				$this->value = $values;
			}

			//check everything is as it should be
			$linkGeneratorIds = [];
			$hasDoubleUp = false;
			foreach ($this->getValue() as $value) 
			{
				assert(($value instanceof $class), "Values for " . $this->name . " must be of class " . $class);
				if (!($value instanceof $class))
				{
					//Not being of the right class causes an infinite loop somewhere, and it's bad anyways so let's just die
					die();
				}

				if (in_array($value->$relation->id, $linkGeneratorIds)) 
				{
					$hasDoubleUp = true;
					break;
				}
				else 
				{
					$linkGeneratorIds[] =  $value->$relation->id;
				}
			}

			if ($this->customValues) 
			{
				assert((!$hasDoubleUp && count($linkGeneratorIds) == count($this->linkGeneratorObjects)), "Exacty one " . $class . " for each " . $linkGeneratorClass . " is required for the " . $this->name . " property.");
			}
			else if (count($linkGeneratorIds) < count($this->linkGeneratorObjects))
			{
				//New things added, create links for them
				//if something was deleted, the links will have gone with them
				$values = $this->getValue();

				foreach ($this->linkGeneratorObjects as $linkGeneratorObject) 
				{
					if (!in_array($linkGeneratorObject->id, $linkGeneratorIds))
					{
						$object = new $class;
						$object->$relation = $linkGeneratorObject;
						
						$values[] = $object;
					}
				}

				$this->value = $values;
			}
		}

		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "ArrayElement.js";
		}

		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		
		public function getJson()
		{
			$json = ResultElement::getJson();
			foreach($this->getValue() as $index => $generator)
			{
				$element = new GeneratorElement($this->name . ":" . $index, null, get_class($generator), $generator);
				$element->afterAdd($generator);
				$generatorJson = $element->getJson();
				$generatorJson["id"] = $generator->id;
				$generatorJson["temporaryId"] = uniqid();
				$generatorJson["dynamicLabel"] = $generator->getDynamicLabelScript();
				
				$json["generators"][] = $generatorJson;
			}

			return $json;
		}
	}