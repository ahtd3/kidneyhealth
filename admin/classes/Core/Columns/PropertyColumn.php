<?php
	namespace Core\Columns;

	use Core\Generator;

	/**
	 * Class for handling columns in a Generator
	 */
	class PropertyColumn implements Column
	{
		public $heading = "";
		public $property = "";
		public $alignment = Column::LEFT;

		/**
		 * Creates a new Column
		 * @param string $property The name of the property to pull from
		 * @param string $heading  The heading at the top of the column
		 */
		function __construct(string $property, string $heading)
		{
			$this->heading = $heading;
			$this->property = $property;
		}

		/**
		 * Sets the alignment for this column
		 * @param	string	$alignment	One of the alignment constants
		 * @return	$this				This object, for chaining
		 */
		public function setAlignment(string $alignment): self
		{
			$this->alignment = $alignment;

			return $this;
		}

		//region Column

		/**
		 * Gets the name for this columns
		 * @return	string	The column name
		 */
		public function getName(): string
		{
			return $this->property;
		}

		/**
		 * Gets the heading for this column
		 * @return    string    The heading
		 */
		public function getHeading(): string
		{
			return $this->heading;
		}

		/**
		 * Gets the alignment for this column
		 * @return    string    One of the alignment constants
		 */
		public function getAlignment(): string
		{
			return $this->alignment;
		}

		/**
		 * Gets the value for this column, given a CreatesTable object
		 * @param Generator $object The object to get the value from
		 * @return    ColumnCell                    The cell for this column
		 */
		public function getValueFor(Generator $object): ColumnCell
		{
			return new ColumnCell("html-cell", ["html" => (string) $object->{$this->property}], "", $this->alignment);
		}

		//endregion
	}
