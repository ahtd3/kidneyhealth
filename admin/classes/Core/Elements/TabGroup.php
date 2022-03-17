<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\Element;
	use Error;
	
	/**
	 * A group of tabs
	 */
	class TabGroup extends Group
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "tabs/TabGroup.js";
		}
		
		/**
		 * Adds a child to this group
		 * @param	Element		$element		The element to add
		 * @param	string		$insertBefore	The name of an element to insert this element before
		 * @return	Tab							The element that was added
		 */
		public function add(Element $element, string $insertBefore = null): Element
		{
			if(!$element instanceof Tab)
			{
				throw new Error("Only tabs may be added to a tab group");
			}
			
			return parent::add($element, $insertBefore);
		}
	}