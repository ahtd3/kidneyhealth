<?php
	namespace Database;
	
	/**
	 * An object that runs queries
	 */
	interface QueryRunner
	{
		/**
		 * Runs a new query and returns the following arrays:
		 *
		 * Select: An array of associative arrays
		 * Insert: An array containing a single value, representing the last inserted ID
		 * All other queries: An empty array
		 *
		 * @param	string	$query			The query to run
		 * @param	array	$parameters		The parameters to use in the query
		 * @return	array					The result of the query
		 */
		public function run(string $query, array $parameters = []): array;
		
		/**
		 * Begins a transaction
		 */
		public function beginTransaction();
		
		/**
		 * Commits the current transaction
		 */
		public function commitTransaction();
		
		/**
		 * Rolls back the current transaction
		 */
		public function cancelTransaction();
		
		/**
		 * Escapes a string (only really used for generating debug queries)
		 * @param	string	$value	The value to escape
		 * @return	string			The escaped value
		 */
		public function escapeString(string $value): string;
	}