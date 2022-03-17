<?php
	namespace Core;

	use Core\Columns\Column;
	use Core\Columns\ColumnCell;
	use Pagination;
	use Users\User;
	
	/**
	 * A Creates Table object is capable of creating a table in the administration panel
	 */
	interface CreatesTable
	{
		/**
		 * Adds some HTML to display before the admin table
		 * @return	string	The HTML to display before the table
		 */
		public static function beforeTable(): string;
		
		/**
		 * Adds some HTML to display after the admin table
		 * @return	string	The HTML to display after the table
		 */
		public static function afterTable(): string;
		
		/**
		 * Gets the heading for the table
		 * @return	string	The heading for the table
		 */
		public static function tableHeading(): string;
		
		/**
		 * Loads all the objects to display in this table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	Generator[]|Pagination			The objects to display
		 */
		public static function loadAllForTable(int $page = 1);
		
		/**
		 * Gets the columns to display
		 * @return	Column[]	The columns to display
		 */
		public static function getColumns(): array;
		
		/**
		 * Gets the singular terms for the class names that can be added for this type of object
		 * @return	string[]	The singular names
		 */
		public static function getSingulars(): array;
		
		/**
		 * Gets whether this class can be viewed by a specific user
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can view the page
		 */
		public static function canView(User $user): bool;
		
		/**
		 * Gets addableness for every class
		 * @param	User					$user	The user to check
		 * @return	bool[]							The addableness
		 */
		public static function getCanAdds(User $user): array;
		
		/**
		 * Gets the normalised class names that can be added for this type of object
		 * @return	string[]	The classes that can be added
		 */
		public static function getNormalisedClassNames(): array;
		
		/**
		 * Whether this class supports positioning
		 * @return	bool	Whether it supports positioning
		 */
		public static function hasPositioning(): bool;
		
		/**
		 * Gets the value to display for a particular column
		 * @param	Column	$column		The column to display in
		 * @return	ColumnCell				The value to display
		 */
		public function getValueForColumn(Column $column): ColumnCell;
	}