<?php
	namespace Users;
	
	use Admin\PreviousPageDetails;
	
	/**
	 * A Super Administrator is an administrator with database access and can edit other administrators
	 */
	class SuperAdministrator extends Administrator
	{
		/**
		 * Whether this user can access the database
		 * @return	bool	If this user has database access
		 */
		public function hasDatabaseAccess()
		{
			return true;
		}
		
		public function getPreviousPageDetails(): ?PreviousPageDetails
		{
			return Administrator::getPreviousPageDetails();
		}
	}