<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\Element;
	use Core\Elements\Base\ResultElement;
	use DateTimeInterface;
	
	/**
	 * Displays a labelled value
	 */
	class Output extends Element
	{
		public $label = "";
		public $value = "";
		
		/**
		 * Creates a new element
		 * @param	string	$name	The name of the element
		 * @param	string	$label	The label for the value
		 * @param	mixed	$value	The value to use for this element
		 */
		public function __construct(string $name, string $label, $value = ResultElement::EMPTY_VALUE)
		{
			parent::__construct($name);
			
			$this->label = $label;
			$this->value = $value;
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			if($this->value !== ResultElement::EMPTY_VALUE)
			{
				return $this->value;
			}
			else
			{
				return $this->generator->{$this->name};
			}
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			$value = $this->getValue();
			
			if ($value instanceof DateTimeInterface)
			{
				$value = $value->format("F j, Y");
			}
			
			return parent::getJson() +
			[
				"label" => $this->label,
				"value" => $value
			];
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "OutputElement.js";
		}
	}