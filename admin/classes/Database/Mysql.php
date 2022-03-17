<?php
	namespace Database;
	
	use Error;
	use mysqli;
	use mysqli_stmt;
	
	/**
	 * A MySQL query runner runs queries on a MySQL database
	 */
	class Mysql implements QueryRunner
	{
		const SERVER = MYSQLHOST;
		const USERNAME = MYSQLUSER;
		const PASSWORD = MYSQLPASSWD;
		const DATABASE = DATABASE;
		
		/** @var mysqli|null */
		private static $connection = null;
		
		/**
		 * Prepares the connection to the database
		 * @return	mysqli	The database connection
		 */
		private static function getConnection(): mysqli
		{
			if(self::$connection === null)
			{
				self::$connection = new mysqli(self::SERVER, self::USERNAME, self::PASSWORD, self::DATABASE);
				self::$connection->set_charset("utf8");
			}
			
			return self::$connection;
		}
		
		/**
		 * Prepares a query for execution
		 * @param    string		$query   The SQL query to perform
		 * @param    string		$typeDef Prepared statement data types
		 * @param    array  	$params  Array of values to insert into the query
		 * @return   mysqli_stmt	The prepared mysqli statement
		 */
		private function prepareStatement(string $query, string $typeDef, array $params): mysqli_stmt
		{
			$statement = self::getConnection()->prepare($query);
			
			if(!$statement)
			{
				throw new Error(self::getConnection()->error);
			}
			
			if(strlen($typeDef) !== count($params))
			{
				throw new Error("Number of types in the type definition do not match the number of values in the parameter list");
			}
			
			if($typeDef !== "")
			{
				$predicates = [$typeDef];
				
				foreach($params as $index => $param)
				{
					$predicates[$index + 1] = &$params[$index];
				}
				
				if(!call_user_func_array([$statement, "bind_param"], $predicates))
				{
					throw new Error($statement->error);
				}
			}
			
			return $statement;
		}
		
		/**
		 * Gets the results of the query, either using the native driver's get_result() method, or by manually binding
		 * @param	mysqli_stmt		$statement	The statement to get the results from
		 * @return	array						The result of the query, or false if there is no result
		 */
		private function getResult(mysqli_stmt $statement)
		{
			if(method_exists($statement, "get_result"))
			{
				$result = $statement->get_result();
				
				if($result === false)
				{
					return [];
				}
				
				return $result->fetch_all(MYSQLI_ASSOC);
			}
			
			$fields = (object) [];
			$metadata = $statement->result_metadata();
			
			if($metadata === false)
			{
				return [];
			}
			
			$fieldsData = $metadata->fetch_fields();
			$parameters = [];
			
			foreach($fieldsData as $fieldData)
			{
				$fields->{$fieldData->name} = null;
				$parameters[] = &$fields->{$fieldData->name};
			}
			
			if(!call_user_func_array([$statement, "bind_result"], $parameters))
			{
				throw new Error("Failed to bind results to variable");
			}
			
			$rows = [];
			
			while(($fetched = $statement->fetch()) !== null)
			{
				if(!$fetched)
				{
					throw new Error("Could not fetch row from database");
				}
				
				$row = [];
				
				foreach((array) $fields as $key => $value)
				{
					$row[$key] = $value;
				}
				
				$rows[] = $row;
			}
			
			return $rows;
		}
		
		/**
		 * Performs a query
		 * @param	string	$query	The SQL query to perform
		 * @param	array	$params		Array of values to insert, or a single value to insert
		 * @return	array		The result of the query
		 */
		private function query(string $query, array $params = []): array
		{
			$typeDef = "";
			
			if(!is_array($params))
			{
				$params = [$params];
			}
			
			foreach($params as $param)
			{
				if(is_bool($param) || is_int($param))
				{
					$typeDef .= "i";
				}
				else if(is_float($param))
				{
					$typeDef .= "d";
				}
				else
				{
					$typeDef .= "s";
				}
			}
			
			$statement = self::prepareStatement($query, $typeDef, $params);
			
			if($statement === false)
			{
				throw new Error(self::getConnection()->error);
			}
			
			if(!$statement->execute())
			{
				throw new Error($statement->error);
			}
			
			if($statement->affected_rows > 0 && $statement->insert_id > 0)
			{
				return [$statement->insert_id];
			}
			
			return $this->getResult($statement);
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
			self::getConnection()->begin_transaction();
		}
		
		/**
		 * Commits the current transaction
		 */
		public function commitTransaction()
		{
			self::getConnection()->commit();
		}
		
		/**
		 * Rolls back the current transaction
		 */
		public function cancelTransaction()
		{
			self::getConnection()->rollback();
		}
		
		/**
		 * Escapes a string (only really used for generating debug queries)
		 * @param string $value The value to escape
		 * @return    string            The escaped value
		 */
		public function escapeString(string $value): string
		{
			return self::getConnection()->escape_string($value);
		}
	}