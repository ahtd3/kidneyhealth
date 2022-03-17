<?php
	namespace Core;

	use Core\Columns\Column;
	use Core\Columns\ColumnCell;
	use Pagination;
	use Users\User;
	
	/**
	 * A Parent Child allows a Category to contain more than one type of Generator
	 */
	abstract class GeneratorGroup implements CreatesTable
	{
		/** @var Generator[]|string[]	The class names of the classes in this group */
		const GROUP_MEMBERS = [];
		
		//region CreatesTable
		
		/**
		 * Adds some HTML to display before the admin table
		 * @return    string    The HTML to display before the table
		 */
		public static function beforeTable(): string
		{
			return "";
		}
		
		/**
		 * Adds some HTML to display after the admin table
		 * @return    string    The HTML to display after the table
		 */
		public static function afterTable(): string
		{
			return "";
		}
		
		/**
		 * Loads all the objects to display in this table
		 * @param int $page The page to load, if handling pagination
		 * @return    CreatesTable[]	The objects to display
		 */
		public static function loadAllForTable(int $page = 1)
		{
			$allResults = [];
			
			foreach(static::GROUP_MEMBERS as $groupMember)
			{
				$results = $groupMember::loadAllForTable($page);
				assert(!$results instanceof Pagination, "Grouped results should not be paginated");
				$allResults = array_merge($allResults, $results);
			}
			
			return $allResults;
		}
		
		/**
		 * Gets the order to display the columns, any missing columns will be displayed in an arbitrary order
		 * @return	string[]	The column headings, in desired order
		 */
		abstract public static function getColumnOrder(): array;
		
		/**
		 * Gets the columns to display
		 * @return    Column[]    The columns to display
		 */
		public static function getColumns(): array
		{
			$columns = [];
			
			foreach(static::GROUP_MEMBERS as $groupMember)
			{
				foreach($groupMember::getColumns() as $column)
				{
					$columns[$column->getName()] = $column;
				}
			}
			
			$order = static::getColumnOrder();
			
			uksort($columns, function(string $first, string $second) use($order)
			{
				$firstIndex = array_search($first, $order);
				$secondIndex = array_search($second, $order);
				
				return $firstIndex <=> $secondIndex;
			});
			
			return $columns;
		}
		
		/**
		 * Gets the normalised class names that can be added for this type of object
		 * @return	string[]|Generator[]	The classes that can be added
		 */
		public static function getSingulars(): array
		{
			return array_map(function($groupMember)
			{
				/** @var string|Generator $groupMember */
				return $groupMember::SINGULAR;
			}, static::GROUP_MEMBERS);
		}
		
		/**
		 * Gets the normalised class names that can be added for this type of object
		 * @return	string[]	The classes that can be added
		 */
		public static function getNormalisedClassNames(): array
		{
			return array_map(function($groupMember)
			{
				/** @var string|Generator $groupMember */
				return $groupMember::normalisedClassName();
			}, static::GROUP_MEMBERS);
		}
		
		/**
		 * Gets whether this class can be viewed by a specific user
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can view the page
		 */
		public static function canView(User $user): bool
		{
			/** @var string|Generator $groupMember */
			foreach(static::GROUP_MEMBERS as $groupMember)
			{
				if(!$groupMember::canView($user))
				{
					return false;
				}
			}
			
			return true;
		}
		
		/**
		 * Gets the normalised class names that can be added for this type of object
		 * @param	User					$user	The user to check
		 * @return	string[]|Generator[]			The classes that can be added
		 */
		public static function getCanAdds(User $user): array
		{
			return array_map(function($groupMember) use($user)
			{
				/** @var string|Generator $groupMember */
				return $groupMember::canAdd($user);
			}, static::GROUP_MEMBERS);
		}
		
		/**
		 * Whether this class supports positioning
		 * @return	bool	Whether it supports positioning
		 */
		public static function hasPositioning(): bool
		{
			foreach(static::GROUP_MEMBERS as $groupMember)
			{
				if($groupMember::HAS_POSITION)
				{
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Gets the value to display for a particular column, unused since this is a static wrapper
		 * @param	Column		$column		The column to display in
		 * @return	ColumnCell				The value to display
		 */
		public function getValueForColumn(Column $column): ColumnCell
		{
			return new ColumnCell("html-cell");
		}
		
		//endregion
	}
