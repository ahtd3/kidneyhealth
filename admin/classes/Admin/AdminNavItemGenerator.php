<?php
	namespace Admin;
	
	/**
	 * An Admin Nav Item Generator generates an array of Admin Menu Items
	 */
	interface AdminNavItemGenerator
	{
		/**
		 * Gets the nav item for this class
		 * @return	AdminNavItem		The admin nav item for this class
		 */
		public static function getAdminNavItem();
	}