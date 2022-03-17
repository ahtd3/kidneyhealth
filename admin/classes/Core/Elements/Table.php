<?php
	namespace Core\Elements;
	
	use Admin\ListController;
	use Core\Elements\Base\Element;
	use Core\Elements\Base\ResultElement;
	use Core\EntityLink;
	use Core\Generator;
	use Core\Properties\LinkManyManyProperty;
	
	/**
	 * Displays a list of child generators
	 */
	class Table extends Element
	{
		public $label = "";
		public $values = [];
		public $className = "";
		public $actions = "";
		public $description = "";
		
		/**
		 * Creates a new element
		 * @param	string				$name		The name of the element
		 * @param	string				$label		The label for the value
		 * @param	string|Generator	$className	The name of the class that will be used to construct the table
		 * @param	mixed				$value		The value to use for this element
		 */
		public function __construct(string $name, string $label, $className = ResultElement::EMPTY_VALUE, $value = ResultElement::EMPTY_VALUE)
		{
			parent::__construct($name);
			
			$this->label = $label;
			$this->values = $value;
			$this->className = $className;
		}
		
		/**
		 * Gets the class name for this table, pulling from the generator if such hasn't been set
		 * @return	string|Generator	$className	The class name
		 */
		public function getClassName()
		{
			if($this->className !== ResultElement::EMPTY_VALUE)
			{
				return $this->className;
			}
			else
			{
				$property = $this->generator::getProperties()[$this->name];
				
				if($property instanceof LinkManyManyProperty)
				{
					/** @var string|EntityLink $relatedClass */
					$relatedClass = $property->getType();
					
					return $relatedClass::getOppositeType($property);
				}
				
				return $property->getType();
			}
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValues()
		{
			if($this->values !== ResultElement::EMPTY_VALUE)
			{
				return $this->values;
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
			return parent::getJson() +
			[
				"id" => $this->generator->id,
				"label" => $this->label,
				"actions" => $this->actions,
				"list" => ListController::generateJsonArray($this->getValues(), $this->getClassName()),
				"info" => $this->description
			];
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "TableElement.js";
		}
		
		/**
		 * Actions displayed between the table heading and the filter field; eg a print button (link)
		 * @param	string	$actions		The html for the action buttons / links
		 * @return	$this					This element, for chaining
		 */
		public function setActions(?string $actions): self
		{
			$this->actions = $actions;
			
			return $this;
		}
		
		/**
		 * A description displayed below the table
		 * @param	string	$description	The description
		 * @return	$this					This element, for chaining
		 */
		public function setDescription(?string $description): self
		{
			$this->description = $description;
			
			return $this;
		}
	}