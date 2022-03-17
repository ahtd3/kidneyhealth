<?php
	namespace Assets;
	
	use RuntimeException;
	
	/**
	 * Combines multiple scripts into one script
	 */
	class Script
	{
		private const HTTP2 = true;
		
		private const CACHE_LOCATION = "/resources/cache/";
		private const DEBUG_MODE = false;
		
		private static $scripts = [];
		private static $date = 0;
		
		/**
		 * Adds a JavaScript file, relative to the site root
		 * @param	string	$script		The script to add
		 */
		public function add($script)
		{
			$scriptLocation = DOC_ROOT . "/" . ltrim($script, "/");
			
			if(!file_exists($scriptLocation))
			{
				throw new RuntimeException("Included script is missing: " . $scriptLocation);
			}
			
			self::$date = max(self::$date, filemtime($scriptLocation));
			self::$scripts[] = $script;
		}
		
		/**
		 * Outputs a link to the combined JavaScript files
		 * @return	string	The script tag for the combined files
		 */
		public function output()
		{
			if(self::HTTP2 || self::DEBUG_MODE)
			{
				$output = "";
				
				foreach(self::$scripts as $script)
				{
					$scriptLocation = DOC_ROOT . "/" . ltrim($script, "/");
					$output .= "<script type='text/javascript' src='" . $script . "?date=" . filemtime($scriptLocation) . "' defer='defer'></script>\n";
				}
				
				return $output;
			}
			else
			{
				$name = "combined-" . md5(implode("|", self::$scripts)) . ".js";
				$location = DOC_ROOT . self::CACHE_LOCATION . $name;
				
				// File hasn't been generated, generate it now
				if(!file_exists($location) || filemtime($location) < self::$date)
				{
					$combinedContent = "";
					
					foreach(self::$scripts as $script)
					{
						$scriptLocation = DOC_ROOT . "/" . ltrim($script, "/");
						$scriptContent = file_get_contents($scriptLocation);
						$combinedContent .= $scriptContent . ";\n";
					}
					
					file_put_contents($location, $combinedContent);
				}
				
				return "<script type='text/javascript' src='" . self::CACHE_LOCATION . $name . "?date=" . self::$date . "' async='async' defer='defer'></script>";
			}
		}
	}
