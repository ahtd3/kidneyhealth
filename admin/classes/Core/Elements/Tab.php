<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\ElementParent;
	use Error;
	
	/**
	 * A single tab of content
	 */
	class Tab extends Group
	{
		/**
		 * Creates a new Tab element
		 * @param	string	$name	The name of the element
		 * @param	string	$label	A label for the tab
		 */
		public function __construct(string $name, string $label)
		{
			parent::__construct($name, $label);
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "tabs/Tab.js";
		}
		
		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			if(!$parent instanceof TabGroup)
			{
				throw new Error("Tabs can only be added to a tab group");
			}
			
			parent::afterAdd($parent);
		}
	}