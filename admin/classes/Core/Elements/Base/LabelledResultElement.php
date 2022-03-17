<?php
	namespace Core\Elements\Base;
	
	/**
	 * An element that has a label
	 */
	abstract class LabelledResultElement extends ResultElement
	{
		public $label = "";
		public $hint = null;
		public $description = null;
		
		/**
		 * Gets the validation checks that can be used for this class
		 * @return	callable[]	A list of callables that can be used
		 */
		public static function getPredefinedValidations()
		{
			$validations = parent::getPredefinedValidations();
			
			$validations["required"] = function($result, LabelledResultElement $element)
			{
				if($result === "" || $result === null || $result === false || $result === [])
				{
					return "'" . $element->label . "' is a required element";
				}
				
				return null;
			};
			
			return $validations;
		}
		
		/**
		 * Creates a new element
		 * @param	string	$name	The name of the element
		 * @param	string	$label	A label for this element
		 * @param	mixed	$value	The value to use for this element
		 */
		public function __construct(string $name, string $label, $value = self::EMPTY_VALUE)
		{
			parent::__construct($name, $value);
			
			$this->label = $label;
		}
		
		/**
		 * A hint displayed in brackets next to the name
		 * @param	string	$hint	The hint
		 * @return	$this			This element, for chaining
		 */
		public function setHint(?string $hint): self
		{
			$this->hint = $hint;
			
			return $this;
		}
		
		/**
		 * A description displayed below the element
		 * @param	string	$description	The description
		 * @return	$this					This element, for chaining
		 */
		public function setDescription(?string $description): self
		{
			$this->description = $description;
			
			return $this;
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() +
			[
				"label" => $this->label,
				"info" => $this->description,
				"details" => $this->hint
			];
		}
	}