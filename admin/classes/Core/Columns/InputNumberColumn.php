<?php
	namespace Core\Columns;
	
	use Core\Generator;
	
	/**
	 * A column updates a numerical property from the list page
	 */
	class InputNumberColumn extends PropertyColumn
	{
		public $alignment = Column::CENTRE;
		
		/** @var callable */
		private $emptyIf;
		
		/**
		 * Creates a new Column
		 * @param	string		$property	The name of the property to pull from
		 * @param	string		$heading	The heading at the top of the column
		 * @param	callable	$emptyIf	A callable that is passed the specific object, and will result in this displaying an empty cell if it returns true
		 */
		public function __construct(string $property, string $heading, ?callable $emptyIf = null)
		{
			parent::__construct($property, $heading);
			
			$this->emptyIf = $emptyIf;
		}
		
		/**
		 * Gets the value for this column, given a CreatesTable object
		 * @param Generator $object The object to get the value from
		 * @return    ColumnCell                    The cell for this column
		 */
		public function getValueFor(Generator $object): ColumnCell
		{
			$emptyIf = $this->emptyIf;
			
			if($emptyIf !== null && $emptyIf($object))
			{
				return new ColumnCell("html-cell");
			}
			else
			{
				$propertyName = $this->property;

				return new ColumnCell('input-number', 
				[
					'className' => $object::normalisedClassName(), 
					'id' => $object->id, 
					'property' => $propertyName,
					'value' => $object->$propertyName
				], "", $this->alignment);
			}
		}
	}