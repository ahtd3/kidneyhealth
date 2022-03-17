<?php
	namespace Search;
	
	/**
	 * A Search Result Generator can generate a Search Result for a type of object
	 */
	interface SearchResultGenerator
	{
		/**
		 * Generates a Search Result for this object
		 * @return	SearchResult	The Search Result
		 */
		public function generateSearchResult();
	}