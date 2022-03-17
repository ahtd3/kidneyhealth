<?php
	namespace Database;
	
	use Error;
	use SQLite3;
	use SQLite3Stmt;
	
	/**
	 * An object that runs queries on SQLite databases
	 */
	class Sqlite implements QueryRunner
	{
		private $filename;
		private $connection = null;
		private $transactionStarted = false;
		
		/**
		 * Creates a new SQLite Query Runner
		 * @param	string	$filename	The name of the database file
		 */
		public function __construct(string $filename = "")
		{
			$this->filename = $filename;
		}
		
		/**
		 * Gets the connection for all Queries generated from this Runner
		 * @return	SQLite3		The SQLite connection
		 */
		public function getConnection()
		{
			if($this->connection === null)
			{
				$this->connection = new SQLite3($this->filename);
			}
			
			return $this->connection;
		}
		
		/**
		 * Prepares a query for execution
		 * @param    string			$query   The SQL query to perform
		 * @param    array  		$params  Array of values to insert into the query
		 * @return   SQLite3Stmt			The prepared mysqli statement
		 */
		private function prepareStatement(string $query, array $params = []): SQLite3Stmt
		{
			$statement = @$this->getConnection()->prepare($query);
			
			if(!$statement)
			{
				throw new Error($this->getConnection()->lastErrorMsg());
			}
			
			if(count($params) > 0)
			{
				foreach($params as $index => $param)
				{
					if($param === null)
					{
						$typeDef = SQLITE3_NULL;
					}
					else if(is_bool($param) || is_int($param))
					{
						$typeDef = SQLITE3_INTEGER;
					}
					else if(is_float($param))
					{
						$typeDef = SQLITE3_FLOAT;
					}
					else
					{
						$typeDef = SQLITE3_TEXT;
					}
					
					$statement->bindValue($index + 1, $param, $typeDef);
				}
			}
			
			return $statement;
		}
		
		/**
		 * Performs a query
		 * @param	string	$query	The SQL query to perform
		 * @param	array	$params		Array of values to insert, or a single value to insert
		 * @return	array		The result of the query
		 */
		private function query(string $query, array $params = []): array
		{
			$query = str_replace("BINARY", "", $query);
			
			if(!is_array($params))
			{
				$params = [$params];
			}
			
			$statement = self::prepareStatement($query,$params);
			
			if($statement === false)
			{
				throw new Error($this->getConnection()->lastErrorMsg());
			}
			
			$result = $statement->execute();
			
			if($result === false)
			{
				throw new Error($this->getConnection()->lastErrorMsg());
			}
			
			if(strpos(strtolower(trim($query)), "insert") === 0)
			{
				return [$this->getConnection()->lastInsertRowID()];
			}
			
			$rows = [];
			
			$strippedQuery = strtolower(trim($query, " \t\n\r\0\x0B()"));
			
			// So, apparently running fetchArray() for the first time executes the statement again.
			// We really only want to display results if we're using a select query, so if we strip out all the brackets and whitespace at the start of the query, and check if it starts with the select keyword, we can safely run the query again
			if(strpos($strippedQuery, "select") === 0)
			{
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $result->fetchArray(SQLITE3_ASSOC))
				{
					$rows[] = $row;
				}
			}
			
			return $rows;
		}
		
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
		public function run(string $query, array $parameters = []): array
		{
			return $this->query($query, $parameters);
		}
		
		/**
		 * Begins a transaction
		 */
		public function beginTransaction()
		{
			if($this->transactionStarted)
			{
				$this->commitTransaction();
			}
			
			$this->transactionStarted = true;
			$this->getConnection()->exec("BEGIN TRANSACTION");
		}
		
		/**
		 * Commits the current transaction
		 */
		public function commitTransaction()
		{
			$transactionStarted = $this->transactionStarted;
			$this->transactionStarted = false;
			
			if(!$transactionStarted)
			{
				return;
			}
			
			$this->getConnection()->exec("COMMIT TRANSACTION");
		}
		
		/**
		 * Rolls back the current transaction
		 */
		public function cancelTransaction()
		{
			$this->transactionStarted = false;
			$this->getConnection()->exec("ROLLBACK TRANSACTION");
		}
		
		/**
		 * Escapes a string (only really used for generating debug queries)
		 * @param string $value The value to escape
		 * @return    string            The escaped value
		 */
		public function escapeString(string $value): string
		{
			return SQLite3::escapeString($value);
		}
	}