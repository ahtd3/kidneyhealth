<?php
	namespace Core\Columns;
	
	use Core\Generator;
	
	/**
	 * A column just needs to provide a heading and a value for an object
	 */
	interface Column
	{
		public const LEFT = "left";
		public CONST CENTRE = "centre";
		public const RIGHT = "right";
		
		/**
		 * Gets the name for this columns
		 * @return	string	The column name
		 */
		public function getName(): string;
		
		/**
		 * Gets the heading for this column
		 * @return	string	The heading
		 */
		public function getHeading(): string;
		
		/**
		 * Gets the alignment for this column
		 * @return	string	One of the alignment constants
		 */
		public function getAlignment(): string;
		
		/**
		 * Gets the value for this column, given a CreatesTable object
		 * @param Generator $object The object to get the value from
		 * @return    ColumnCell                    The cell for this column
		 */
		public function getValueFor(Generator $object): ColumnCell;
	}