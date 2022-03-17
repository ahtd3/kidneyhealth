<?php
	namespace Template;
	
	use Core\Generator;
	
	/**
	 * A Nav Item can provide a label, a path, a description, whether it's currently selected, whether it opens in a new window, whether this nav item is the homepage and any children it has
	 * Usually but not necessarily a Nav Item will also implement PageItem
	 */
	interface NavItem
	{
		/**
		 * Gets any children this item has
		 * @return	NavItem[]	The children this item has
		 */
		public function getChildNavItems();
		
		/**
		 * Gets the complete chain of Nav Items from parent to child, including the current Nav Item
		 * @return	NavItem[]	The chain of Nav Items
		 */
		public function getNavItemChain();
		
		/**
		 * Gets the label for this item
		 * @return	string	The label for this item
		 */
		public function getNavLabel();

		/**
		 * Gets the path to this item
		 * @return	string	The path to this item
		 */
		public function getNavPath();
		
		/**
		 * Gets whether this item is the homepage
		 * @return	bool	Whether this item is the homepage
		 */
		public function isHomepage();

		/**
		 * Gets whether this item is currently selected
		 * @param	NavItem $currentNavItem The current nav item
		 * @return	bool	Whether this item is currently selected
		 */
		public function isNavSelected(NavItem $currentNavItem = null);

		/**
		 * Gets whether this item opens in a new window
		 * @return	bool	Whether this item opens in a new window
		 */
		public function isOpenedInNewWindow();

	}