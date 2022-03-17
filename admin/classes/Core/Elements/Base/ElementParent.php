<?php
	namespace Core\Elements\Base;
	
	use Core\Generator;
	
	/**
	 * An object that contains child form elements
	 */
	interface ElementParent
	{
		/**
		 * Gets the Generator that any child elements should reference
		 * @return	Generator	$generator	Said generator
		 */
		public function getGenerator(): Generator;
		
		/**
		 * Searches for a child element with a specific name
		 * @param	string			$name	The name of the element
		 * @return	Element|null			An element with that name, or null if one can't be found
		 */
		public function findElement(string $name): ?Element;
	}