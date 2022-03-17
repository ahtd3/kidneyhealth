<?php
	namespace Search;
	
	/**
	 * A Searchable object can be searched using a search term
	 */
	interface Searchable
	{
		/**
		 * Performs a search using the supplied string
		 * @param	string		$term	The term to search
		 * @return	static[]			Search Results for this Searchable
		 */
		public static function search($term);
	}