<?php
	namespace Users;

	use Admin\AdminNavItem;
	use Configuration\Registry;
	use Core\Elements\FormOption;
	use Core\Elements\Select;
	
	/**
	 * An administrator has access to the admin panel, but not to the database, and can't edit other administrators
	 */
	class Administrator extends User
	{
		const ACTIVATE_ID = 1;

		const SINGULAR = "Administrator";
		const PLURAL = "Administrators";
		
		/**
		 * Sets the Form Elements for this object
		 */
		public function elements()
		{
			parent::elements();

			$types = [];
			
			foreach(User::TYPES as $typeName => $class)
 			{
 				if(Registry::USERS || (!Registry::USERS && is_a($class, self::class, true)))
 				{
 					$types[] = new FormOption($typeName, $class);
 				}
 			}

			$this->addElement(new Select("class", "Type", $types), "Details");
		}
		
		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int			$page	The page to load, if handling pagination
		 * @return	static[]			The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1): array
		{
			$users = [];

			foreach(static::loadAll(["email" => true]) as $user)
			{
				if($user->id === static::ACTIVATE_ID)
				{
					continue;
				}

				$users[] = $user;
			}

			return $users;
		}

		/**
		 * General permissions for a user for this object
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can view, add, edit or delete these objects
		 */
		public static function canDo(User $user): bool
		{
			return $user instanceof SuperAdministrator;
		}

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		 public static function getAdminNavItem()
 		{
 			$types = [];

 			foreach(User::TYPES as $type)
 			{
 				if(is_a($type, self::class, true))
 				{
 					$types[] = $type;
 				}
 			}

			$administratorItem = new AdminNavItem(static::getAdminNavLink(), "Administrators", $types, Registry::ADMINISTRATORS);

 			if (Registry::USERS)
 			{
 				return new AdminNavItem(User::getAdminNavLink(), "Users", array_values(User::TYPES), true, [$administratorItem]);
 			}
 			else
 			{
 				return $administratorItem;
 			}
 		}

		/**
		 * Whether this user can access some aspect of the admin panel
		 * @return	bool	Whether they've got access
		 */
		public function hasAdminAccess()
		{
			return true;
		}
	}
