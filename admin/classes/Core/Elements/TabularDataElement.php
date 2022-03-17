<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\BasicElement;
	use Core\Elements\Base\ElementParent;
	use Core\Entity;
	use Core\Properties\LinkProperty;
	
	/**
	 * A simple text input
	 */
	class TabularDataElement extends BasicElement
	{
		public $class = "";
		public $rows = [];
		public $columns = [];

		public $rowClass;
		public $columnClass;
		public $rowProperty;
		public $columnProperty;
		public $valueProperty;
		
		public $customValues = false;


		/**
		 * Creates a new element
		 * @param	string                        	$name    	The name of the element
		 * @param	string                         	$label  	A label for this element
		 * @param	Entity[]	 					$rows 		An array of Entities to make up the rows
		 * @param	Entity[]	 					$columns 	An array of Entities to make up the columns
		 * @param	mixed                          	$value  	The value to use for this element
		 */
		public function __construct(string $name, string $label, array $rows = [], array $columns = [], $value = self::EMPTY_VALUE)
		{
			if ($value != self::EMPTY_VALUE) 
			{
				$this->customValues = true;
			}
			
			parent::__construct($name, $label, $value);
			
			$this->rows = $rows;
			$this->columns = $columns;
		}

		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			parent::afterAdd($parent);

			$property = $this->generator::getProperties()[$this->name];
			assert($property instanceof LinkProperty);
			$class = $property->getType();
			$this->class = $class;

			$linkObject = new $class;

			assert(defined($class . '::' . 'ROW_PROPERTY'), $class . ' has a ROW_PROPERTY constant');
			assert(defined($class . '::' . 'COLUMN_PROPERTY'), $class . ' has a COLUMN_PROPERTY constant');

			//used later, but might as well make sure it's here now
			assert(defined($class . '::' . 'VALUE_PROPERTY'), $class . ' has a VALUE_PROPERTY constant');
			$valueProperty = constant($this->class . '::' . 'VALUE_PROPERTY');
			$this->valueProperty = $valueProperty;
			
			//rows setup
			$rowProperty = constant($class . '::' . 'ROW_PROPERTY');
			
			/** @var class-string<Entity> $rowClass */
			$rowClass = get_class($linkObject->$rowProperty);

			if (empty($this->rows)) 
			{
				$this->rows = $rowClass::loadAll();
			}
			$this->rowClass = $rowClass;
			$this->rowProperty = $rowProperty;

			foreach ($this->rows as $row) 
			{
				assert(($row instanceof $rowClass), "Values for " . $this->name . "'s rows must be of class " . $rowClass);
			}

			//columns setup
			$columnProperty = constant($class . '::' . 'COLUMN_PROPERTY');
			
			/** @var class-string<Entity> $columnClass */
			$columnClass = get_class($linkObject->$columnProperty);

			if (empty($this->columns)) 
			{
				$this->columns = $columnClass::loadAll();
			}
			$this->columnClass = $columnClass;
			$this->columnProperty = $columnProperty;

			foreach ($this->columns as $column) 
			{
				assert(($column instanceof $columnClass), "Values for " . $this->name . "'s columns must be of class " . $columnClass);
			}

			//values setup
			if (count($this->getValue()) <= 0) 
			{
				$values = [];
				if (count($this->rows) > 0 && count($this->columns) > 0)
				{
					foreach ($this->rows as $row) 
					{
						foreach ($this->columns as $col) 
						{
							$linkPropertyName = $property->getRelatedPropertyName();
							$valueObject = $this->class::loadForMultiple([$this->rowProperty => $row, $this->columnProperty => $col, $linkPropertyName => $this->generator]);

							if ($valueObject->isNull()) 
							{
								$valueObject = new $class;
								$valueObject->$rowProperty = $row;
								$valueObject->$columnProperty = $col;
							}
							$values[] = $valueObject;
						}
					}
				}
				$this->value = $values;
			}

			//Check value objects for row and column properties, no duplicates etc
			$ids = [];
			$enoughColumns = true;
			$hasDoubleUp = false;
			foreach ($this->getValue() as $value) 
			{
				if ($ids[$value->$rowProperty->id] != null && in_array($value->$columnProperty->id, $ids[$value->$rowProperty->id]))
				{
					$hasDoubleUp = true;
					break;
				}
				else 
				{
					$ids[$value->$rowProperty->id][] = $value->$columnProperty->id;
				}
			}
			foreach ($ids as $rowId => $columns) 
			{
				if (count($columns) != count($this->columns))
				{
					$enoughColumns = false;
					break;
				}
			}
			
			if ($this->customValues) 
			{
				assert((!$hasDoubleUp && count($ids) === count($this->rows) && $enoughColumns), "Exacty one " . $class . " for each row and column combination is required for the " . $this->name . " property.");
			}
			else if (count($ids) !== count($this->rows) || !$enoughColumns)
			{
				//New things added, create links for them
				//if something was deleted, the links will have gone with them
				$values = $this->getValue();
	
				foreach ($this->rows as $row) 
				{
					foreach ($this->columns as $col) 
					{
						if ($ids[$row->id] == null || !in_array($col->id, $ids[$row->id]))
						{
							$valueObject = new $class;
							$valueObject->$rowProperty = $row;
							$valueObject->$columnProperty = $col;
							$values[] = $valueObject;
						}
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
			return "TabularDataElement.js";
		}

		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			$rowOptions = [];
			$columnOptions = [];

			foreach($this->rows as $object)
			{
				$rowClass = $this->rowClass;
				$rowOptions[] = new FormOption($object->{$rowClass::LABEL_PROPERTY}, $object);
			}

			foreach($this->columns as $object)
			{
				$columnClass = $this->columnClass;
				$columnOptions[] = new FormOption($object->{$columnClass::LABEL_PROPERTY}, $object);
			}

			return parent::getJson() + [
				"rows" => $rowOptions,
				"columns" => $columnOptions,
			];
		}

		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			$values = [];

			if (count($this->rows) > 0 && count($this->columns) > 0)
			{
				$class = $this->class;
				foreach ($this->rows as $row) 
				{
					$array = ['id' => $row->id, 'cells' => []];

					foreach ($this->columns as $col) 
					{
						$rowProperty = $this->rowProperty;
						$columnProperty = $this->columnProperty;
						$valueObjects = array_filter($this->getValue(), function($var) use ($row, $col, $rowProperty, $columnProperty) 
						{
							return $var->$rowProperty->id === $row->id && $var->$columnProperty->id === $col->id;
						});
						$valueObject = reset($valueObjects);//should be exactly one, checked in afterAdd()
						$valueProperty = $this->valueProperty;
						$value = $valueObject === null || $valueObject->isNull() ? 0 : $valueObject->$valueProperty;
						
						$array['cells'][] = ['id' => $col->id, 'value' => $value];
					}
					$values[] = $array;
				}
			}

			return $values;
		}

		/**
		 * Gets the result of this element
		 * @param	mixed	$json	The JSON to retrieve the result from
		 * @return	mixed            The result that will be passed to the result handler
		 */
		public function getResult($json)
		{
			$results = [];
			foreach ($json as $row) 
			{
				foreach ($row['cells'] as $col) 
				{
					$property = $this->generator::getProperties()[$this->name];
					assert($property instanceof LinkProperty);

					$linkPropertyName = $property->getRelatedPropertyName();
					$rowProperty = $this->rowProperty;
					$columnProperty = $this->columnProperty;
					$valueProperty = $this->valueProperty;

					$valueObject = $this->class::loadForMultiple([$this->rowProperty => $row['id'], $this->columnProperty => $col['id'], $linkPropertyName => $this->generator]);

					if ($valueObject->isNull()) 
					{
						$valueObject = new $this->class;
						
						$rowObject = $this->rowClass::load($row['id']);
						$columnObject = $this->columnClass::load($col['id']);

						$valueObject->$rowProperty = $rowObject;
						$valueObject->$columnProperty = $columnObject;
						$valueObject->$linkPropertyName = $this->generator;
					}

					$valueObject->$valueProperty = $col['value'];
					
					$results[] = $valueObject;
				}
			}
			return $results;
		}
	}