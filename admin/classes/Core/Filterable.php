<?php
	namespace Core;
	
	/**
	 * Handles searching for objects via AJAX
	 */
	interface Filterable
	{
		/**
		 * Loads all objects that match a specific filter
		 * @param	string			$filter		The filter to apply
		 * @return	Generator[]					The filtered objects
		 */
		public static function loadForFilter(string $filter): array;
	}