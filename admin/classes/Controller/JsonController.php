<?php
	namespace Controller;
	
	use Exception;
	use Pages\Page;
	use Pages\PageController;
	
	/**
	 * Outputs some JSON
	 */
	class JsonController extends PageController
	{
		private $data;
		
		/**
		 * Gets standard JSON encode options
		 * @return	int		The standard options
		 */
		public static function getStandardEncodeOptions(): int
		{
			$options = 0;
			
			if(defined("JSON_THROW_ON_ERROR"))
			{
				$options = constant("JSON_THROW_ON_ERROR");
			}
			
			return $options;
		}
		
		/**
		 * Creates a new JsonController
		 * @param	mixed	$data	The unencoded data to output as JSON
		 */
		public function __construct($data)
		{
			parent::__construct(new Page());
			
			$this->data = $data;
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			header("Content-type: application/json");
			
			try
			{
				echo json_encode($this->data, static::getStandardEncodeOptions());
			}
			catch(Exception $exception)
			{
				echo json_encode(["error" => $exception->getMessage()]);
			}
		}
	}