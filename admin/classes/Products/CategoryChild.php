<?php
	namespace Products;

	use Core\GeneratorGroup;

	/**
	 * Handles the possible children for a Category
	 */
	class CategoryChild extends GeneratorGroup
	{
		/*~~~~~
		 * setup
		 **/
		// GeneratorGroup
		const GROUP_MEMBERS = [ProductCategory::class, ProductCategoryLink::class];

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the order to display the columns, any missing columns will be displayed in an arbitrary order
		 * @return	string[]	The column headings, in desired order
		 */
		public static function getColumnOrder(): array
		{
		return ["name", "featured", "active", "edit", "delete"];
		}

		/**
		 * Gets the heading for the table
		 * @return    string    The heading for the table
		 */
		public static function tableHeading(): string
		{
			return "";
		}

		/**
		 * Gets the normalised class names that can be added for this type of object
		 * @return	string[]	The classes that can be added
		 */
		public static function getNormalisedClassNames(): array
		{
			return [ProductCategory::normalisedClassName(), Product::normalisedClassName()];
		}
	}
