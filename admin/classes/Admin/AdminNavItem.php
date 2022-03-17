<?php
	namespace Admin;
	
	/**
	 * Convenience class for setting up admin links
	 */
	class AdminNavItem
	{
		public $link;
		public $label;
		public $identifiers;
		public $display;
		public $subitems;
		public $newWindow;
		public $isVue = true;
		
		/**
		 * Creates a new Admin Nav Item
		 * @param	string         $link        The Link to the item
		 * @param	string         $label       The label to display for the item
		 * @param	string[]       $identifiers The identifier to compare to the current page's identifier
		 * @param	bool           $display     Whether to display the link
		 * @param	bool           $newWindow   Whether to open in a new window
		 * @param	AdminNavItem[] $subitems    Items to display in a subnav
		 */
		public function __construct($link, $label, $identifiers, $display = true, $subitems = [], $newWindow = false)
		{
			$this->link = $link;
			$this->label = $label;
			$this->identifiers = $identifiers;
			$this->display = $display;
			$this->subitems = $subitems;
			$this->newWindow = $newWindow;
		}
		
		/**
		 * Checks if this item is a current item
		 * @param	string	$current	The identifier for the current item
		 * @return	bool				Whether it's current
		 */
		public function isCurrent($current)
		{
			$isCurrent = in_array($current, $this->identifiers);
			
			foreach($this->subitems as $subitem)
			{
				if($subitem->isCurrent($current))
				{
					$isCurrent = true;
				}
			}
			
			return $isCurrent;
		}
		
		/**
		 * Whether this item has displayed children
		 * @return	bool	Whether there are displayed children
		 */
		public function isParent()
		{
			foreach ($this->subitems as $subitem)
			{
				if ($subitem->display)
				{
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Gets the path to this item, as well as the path to child items
		 * @return	string[]	The array of paths
		 */
		public function getPaths()
		{
			$paths = [$this->link];
			
			foreach($this->subitems as $subitem)
			{
				$paths = array_merge($paths, $subitem->getPaths());
			}
			
			return $paths;
		}
		
		/**
		 * Gets the identifiers for this item, as well as the identifiers for child items
		 * @return	string[]	All the identifiers
		 */
		public function getAllIdentifiers()
		{
			$identifiers = $this->identifiers;
			
			foreach($this->subitems as $subitem)
			{
				$identifiers = array_merge($identifiers, $subitem->getAllIdentifiers());
			}
			
			return $identifiers;
		}
		
		/**
		 * Checks if the item is a Vue link
		 * @return	bool	Whether it's a bool link
		 */
		public function isVue()
		{
			if(strpos($this->link, "http") === 0)
			{
				return false;
			}
			
			return $this->isVue;
		}
	}
