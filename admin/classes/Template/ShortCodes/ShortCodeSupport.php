<?php
	namespace Template\ShortCodes;
	
	/**
	 * Interface file for the ShortCodeSupport interface
	 * 
	 * An object with shortcode support can provide content to be inserted in the content of another object
	 *
	 */
	interface ShortCodeSupport
	{
		/**
		 * @return	string	An identifier that uniquely identifies this class
		 */
		public static function getClassShortcodeIdentifier();
		
		/**
		 * @return string the content to be output at the shortcode location
		 */
		public function getShortcodeContent();
		
		/**
		 * @return	string	An identifier that uniquely identifies this object
		 */
		public function getShortcodeIdentifier();
		
		/**
		 * Loads an object for this class, given an identifier
		 * @param	string		$identifier		The identifier to load from
		 * @return	ShortCodeSupport			An object that can be outputted to the page, or null if the correct one cannot be found
		 */
		public static function loadForShortcodeIdentifier($identifier);
		
	}