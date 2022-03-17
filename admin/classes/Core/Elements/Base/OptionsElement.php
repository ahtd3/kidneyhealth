<?php
	namespace Core\Elements\Base;
	
	use Core\Elements\FormOption;
	use Core\Elements\FormOptionGroup;
	use Core\Entity;
	use Core\Generator;
	
	/**
	 * An element that contains multiple options
	 */
	abstract class OptionsElement extends LabelledResultElement
	{
		/** @var FormOption[] */
		public $options = [];
		
		/**
		 * Creates a new element
		 * @param	string                         $name    The name of the element
		 * @param	string                         $label   A label for this element
		 * @param	string[]|Entity[]|FormOption[] $options An associative array of options, or an array of option objects
		 * @param	mixed                          $value   The value to use for this element
		 */
		public function __construct(string $name, string $label, array $options = [], $value = self::EMPTY_VALUE)
		{
			parent::__construct($name, $label, $value);
			
			foreach($options as $label => $option)
			{
				if($option instanceof FormOptionGroup)
				{
					$this->options[] = $option;
				}
				else if(is_int($label) && $option instanceof Generator)
				{
					// Assume we've been passed an array of generators
					$this->options[] = new FormOption($option->{$option::LABEL_PROPERTY}, $option);
				}
				else
				{
					// Probably an associative label => value array
					$this->options[] = new FormOption($label, $option);
				}
			}
		}
		
		/**
		 * Adds an option to this element
		 * @param	FormOptionGroup		$option		The option to add
		 * @return	$this							This element, for chaining
		 */
		public function add(FormOptionGroup $option): self
		{
			$this->options[] = $option;
			
			return $this;
		}
		
		/**
		 * Removes an option from this element
		 * @param	string	$label	The label of the option to remove
		 * @return	$this			This element, for chaining
		 */
		public function removeWithLabel(string $label): self
		{
			foreach($this->options as $index => $option)
			{
				if($option->label === $label)
				{
					unset($this->options[$index]);
					$this->options = array_values($this->options);
					break;
				}
			}
			
			return $this;
		}
		
		/**
		 * Removes an option from this element
		 * @param	string|Entity	$value	The value to remove
		 * @return	$this					This element, for chaining
		 */
		public function removeWithValue($value): self
		{
			$value = FormOption::sanitiseValue($value);
			
			foreach($this->options as $index => $option)
			{
				if($option->value === $value)
				{
					unset($this->options[$index]);
					$this->options = array_values($this->options);
					break;
				}
			}
			
			return $this;
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			$value = parent::getValue();
			
			if(is_array($value))
			{
				foreach($value as $index => $item)
				{
					$value[$index] = FormOption::sanitiseValue($item);
				}
			}
			else
			{
				$value = FormOption::sanitiseValue($value);
			}
			
			return $value;
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() + ["options" => $this->options];
		}
	}