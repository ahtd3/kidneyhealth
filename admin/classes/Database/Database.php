<?php
	namespace Database;
	
	/**
	 * Database interaction class. Uses static functions.
	 */
	class Database
	{
		private static $queryRunner = null;
		
		/**
		 * Gets the default Query Runner
		 * @return	QueryRunner		The default Query Runner
		 */
		public static function getDefaultQueryRunner(): QueryRunner
		{
			if(self::$queryRunner === null)
			{
				self::setDefaultQueryRunner(new Mysql());
			}
			
			return self::$queryRunner;
		}
		
		/**
		 * Sets the default Query Runner
		 * @param	QueryRunner		$queryRunner	The new default Query Runner
		 */
		public static function setDefaultQueryRunner(QueryRunner $queryRunner)
		{
			self::$queryRunner = $queryRunner;
		}
		
		/**
		 * Performs a query
		 * @param	string	$query		The SQL query to perform
		 * @param	array	$params		Array of values to insert
		 * @return	array				The result of the query
		 */
		public static function query(string $query, array $params = []): array
		{
			return static::getDefaultQueryRunner()->run($query, $params);
		}
		
		/**
		 * Begins a transaction
		 */
		public static function beginTransaction()
		{
			self::getDefaultQueryRunner()->beginTransaction();
		}
		
		/**
		 * Commits the current transaction
		 */
		public static function commitTransaction()
		{
			self::getDefaultQueryRunner()->commitTransaction();
		}
		
		/**
		 * Rolls back the current transaction
		 */
		public static function cancelTransaction()
		{
			self::getDefaultQueryRunner()->cancelTransaction();
		}
		
		/**
		 * Generates an insert query string from field names
		 * @param	string		$table		The table to insert into
		 * @param	string[]	$fields		Field names
		 * @return	string					The query
		 */
		public static function generateInsertQuery(string $table, array $fields): string
		{
			$insertFields = implode(", ", array_map(fn(string $field) => "`{$field}`",$fields));
			$placeholders = implode(", ", array_fill(0, count($fields), "?"));
			
			return "INSERT INTO `{$table}`({$insertFields}) "
				 . "VALUES({$placeholders})";
		}
		
		/**
		 * Generates an insert/update query from field names
		 * @param	string		$table		The table to insert into
		 * @param	string[]	$fields		Field names
		 * @return	string					The query
		 */
		public static function generateInsertOrUpdateQuery(string $table, array $fields): string
		{
			$baseInsert = static::generateInsertQuery($table, $fields);
			$updates = implode(", ", array_map(fn(string $field) => "`{$field}` = ?", $fields));
			
			return "{$baseInsert} "
				 . "ON DUPLICATE KEY UPDATE {$updates}";
		}
		
		/**
		 * Generates an update query from field names
		 * @param	string   $table       The table to insert into
		 * @param	string[] $fields      Field names
		 * @param	string   $whereClause A where clause, e.g. "product_id = 3"
		 * @return	string						The query
		 */
		public static function generateUpdateQuery(string $table, array $fields, string $whereClause): string
		{
			$updates = implode(", ", array_map(fn(string $field) => "`{$field}` = ?", $fields));
			
			return "UPDATE `{$table}` "
				 . "SET {$updates} "
				 . "WHERE {$whereClause}";
		}

		/**
		 * Ouputs what a query will evaluate to, strictly for debugging
		 * @param	string			$query			The SQL query to perform
		 * @param	array			$params			The parameters being passed to the query
		 * @param	QueryRunner		$queryRunner	A query runner to use to escape the string, otherwise the default query runner will be used
		 * @return	string							What it will evaluate to
		 */
		public static function outputActualQuery(string $query, array $params = [], ?QueryRunner $queryRunner = null): string
		{
			if($queryRunner === null)
			{
				$queryRunner = static::getDefaultQueryRunner();
			}
			
			foreach($params as $index => $param)
			{
				if(is_null($param))
				{
					$query = preg_replace("/\?/", "NULL", $query, 1);
				}
				elseif(is_string($param))
				{
					$query = preg_replace("/\?/", "'" . $queryRunner->escapeString($param) . "'", $query, 1);
				}
				else
				{
					//var_dump("/\?/", $param, $query);
					$query = preg_replace("/\?/", $param, $query, 1);
				}
			}
			
			return $query;
		}
	}