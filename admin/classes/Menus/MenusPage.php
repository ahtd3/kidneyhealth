<?php
	namespace Menus;

	use Pages\Page;
	use Template\NavItem;
	
	/**
	 * A MenusPage displays the Menus
	 */
	class MenusPage extends Page
	{
		/*~~~~~
		 * Interface methods
		 **/
		 
		// region NavItem
		
		/**
		 * Gets any children this item has
		 * @return	NavItem[]	The children this item has
		 */
		public function getChildNavItems()
		{
			return array_merge(parent::getChildNavItems(), Menu::loadAllFor('active', true));
		}
		
		// endregion
	}
