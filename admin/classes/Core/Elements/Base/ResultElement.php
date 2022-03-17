<?php
	namespace Core\Elements\Base;
	
	use Exception;
	
	/**
	 * An element that generates a result
	 */
	abstract class ResultElement extends Element
	{
		public const EMPTY_VALUE = "~~~[EMPTY]~~~";
		public const REQUIRED = "required";
		
		public $value;
		public $resultHandler;
		
		/** @var callable[] */
		private $validations = [];
		
		/**
		 * Gets the validation checks that can be used for this class
		 * @return	callable[]	A list of callables that can be used
		 */
		public static function getPredefinedValidations()
		{
			return
			[
				static::REQUIRED => function($result, ResultElement $element)
				{
					if(trim($result) === "" || $result === null || $result === false || $result === [])
					{
						return "'" . $element->name . "' is a required element";
					}
					
					return null;
				}
			];
		}
		
		/**
		 * Creates a new element
		 * @param	string	$name	The name of the element
		 * @param	mixed	$value	The value to use for this element
		 */
		public function __construct(string $name, $value = self::EMPTY_VALUE)
		{
			parent::__construct($name);
			
			$this->value = $value;
		}
		
		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			parent::afterAdd($parent);
			
			if($this->resultHandler === null)
			{
				$this->resultHandler = function($result)
				{
					$this->generator->setFromElement($this->name, $result);
				};
			}
		}
		
		/**
		 * Sets the handler that will handle the result
		 * @param	callable	$resultHandler	The handler for the result
		 * @return	$this						This element, for chaining
		 */
		public function setResultHandler(callable $resultHandler): self
		{
			$this->resultHandler = $resultHandler;
			
			return $this;
		}
		
		/**
		 * Adds a validation to this element
		 * @param	string		$label			A label for the validation
		 * @param	callable	$validation		A callback for the validation, or null if the label references a predefined validation
		 * @return	$this						This element, for chaining
		 */
		public function addValidation(string $label, ?callable $validation = null): self
		{
			assert($validation !== null || isset(static::getPredefinedValidations()[$label]));
			$this->validations[$label] = $validation;
			
			return $this;
		}
		
		/**
		 * Removes a validation from this element
		 * @param	string	$label	The label for the validation to remove
		 * @return	$this			This element, for chaining
		 */
		public function removeValidation(string $label): self
		{
			unset($this->validations[$label]);
			
			return $this;
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() + ["validations" => array_keys($this->validations)];
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			if($this->value !== self::EMPTY_VALUE)
			{
				return $this->value;
			}
			else
			{
				return $this->generator->{$this->name};
			}
		}
		
		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			return $this->getValue();
		}
		
		/**
		 * Gets the result of this element
		 * @param	mixed		$json	The JSON to retrieve the result from
		 * @return	mixed				The result that will be passed to the result handler
		 * @throws	Exception			If something exceptional goes wrong
		 */
		abstract public function getResult($json);
		
		/**
		 * Gets validation errors for this element
		 * @param	mixed		$result		The result of this element
		 * @return	string[]				Any error messages to display
		 */
		public function validate($result): array
		{
			$errors = [];
			$predefined = static::getPredefinedValidations();
			
			foreach($this->validations as $label => $validation)
			{
				// Use one the predefined validations
				if($validation === null)
				{
					$validation = $predefined[$label];
				}
				
				$check = $validation($result, $this);
				
				if($check !== null)
				{
					$errors[] = $check;
				}
			}
			
			return $errors;
		}
		
		/**
		 * Passes the result of this element to the result handler
		 * @param	mixed	$result		The result to pass to the handler
		 */
		public function handleResult($result)
		{
			if($this->resultHandler !== null)
			{
				$handler = $this->resultHandler;
				$handler($result);
			}
		}
	}