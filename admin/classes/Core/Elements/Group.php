<?php
	namespace Core\Elements;
	
	use Admin\EditController;
	use Core\Elements\Base\Element;
	use Core\Elements\Base\ElementParent;
	use Core\Elements\Base\ResultElement;
	use Core\Generator;
	use Error;
	use Exception;
	
	/**
	 * A group of form elements
	 */
	class Group extends ResultElement implements ElementParent
	{
		/** @var string */
		public $heading = null;
		
		/** @var Element[] */
		public $children = [];
		
		/**
		 * Creates a new Group
		 * @param	string	$name		The name for the element
		 * @param	string	$heading	An (optional) heading for the group
		 */
		public function __construct(string $name, ?string $heading = null)
		{
			parent::__construct($name);
			
			$this->heading = $heading;
		}
		
		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			parent::afterAdd($parent);
			
			foreach($this->children as $child)
			{
				$child->afterAdd($this);
			}
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "Group.js";
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			return null;
		}
		
		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			return EditController::getJsonValue($this->children);
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return    mixed    The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			$json = parent::getJson() + ["children" => []];
			
			if($this->heading !== null)
			{
				$json["heading"] = $this->heading;
			}
			
			foreach($this->children as $child)
			{
				$childJson = $child->getJson();
				$json["children"][] = $childJson;
			}
			
			return $json;
		}
		
		/**
		 * Gets the result of this element
		 * @param	mixed		$json	The JSON to retrieve the result from
		 * @return	mixed				The result that will be passed to the result handler
		 * @throws	Exception			If something goes wrong while getting child results
		 */
		public function getResult($json)
		{
			$results = [];
			
			foreach($this->children as $child)
			{
				if($child instanceof ResultElement)
				{
					$results[$child->name] = $child->getResult($json[$child->name]);
				}
			}
			
			return $results;
		}
		
		/**
		 * Gets a validation error for this element
		 * @param mixed $result The result of this element
		 * @return    string[]                Any error messages to display
		 */
		public function validate($result): array
		{
			$errorMessages = [];
			
			foreach($this->children as $child)
			{
				if($child instanceof ResultElement)
				{
					$errorMessages = array_merge($errorMessages, $child->validate($result[$child->name]));
				}
			}
			
			return $errorMessages;
		}
		
		/**
		 * Passes the result of this element to the result handler
		 * @param	mixed	$result		The result to pass to the handler
		 */
		public function handleResult($result)
		{
			foreach($this->children as $child)
			{
				if($child instanceof ResultElement)
				{
					$child->handleResult($result[$child->name]);
				}
			}
		}
		
		/**
		 * Adds a child to this group
		 * @param	Element		$element		The element to add
		 * @param	string		$insertBefore	The name of an element to insert this element before
		 * @return	Element						The element that was added
		 */
		public function add(Element $element, ?string $insertBefore = null): Element
		{
			try
			{
				$this->children = mergeAssociative($this->children, [$element->name => $element], $insertBefore);
			}
			catch(Exception $exception)
			{
				throw new Error($exception->getMessage());
			}
			
			if($this->generator !== null)
			{
				$element->afterAdd($this);
			}
			
			return $element;
		}
		
		/**
		 * Removes a child from this group
		 * @param	string	$name	The name of the element to remove
		 * @return	$this			This element, for chaining
		 */
		public function remove(string $name): self
		{
			unset($this->children[$name]);
			
			return $this;
		}
		
		//region ElementParent
		
		/**
		 * Gets the Generator that any child elements should reference
		 * @return    Generator    $generator    Said generator
		 */
		public function getGenerator(): Generator
		{
			return $this->generator;
		}
		
		/**
		 * Searches for a child element with a specific name
		 * @param	string			$name	The name of the element
		 * @return	Element|null			An element with that name, or null if one can't be found
		 */
		public function findElement(string $name): ?Element
		{
			foreach($this->children as $element)
			{
				if($element->name === $name)
				{
					return $element;
				}
				else if($element instanceof ElementParent)
				{
					$childElement = $element->findElement($name);
					
					if($childElement !== null)
					{
						return $childElement;
					}
				}
			}
			
			return null;
		}
		
		//endregion
	}