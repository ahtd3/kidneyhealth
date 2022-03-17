<?php
	namespace Sass;
	
	use ScssPhp\ScssPhp\Compiler;
	use ScssPhp\Server\Server;
	
	/**
	 * Creates sourcemaps for generated CSS and runs everything through CSS Crush for its auto-prefixing
	 */
	class MappingServer extends Server
	{
		const TEST_MODE = false;
		
		private $scss;
		
		/**
		 * Constructor
		 * @param	string			$dir	Root directory to .scss files
		 * @param	Compiler|null	$scss	SCSS compiler instance
		 */
		public function __construct($dir, Compiler $scss = null)
		{
			$cacheDir = $dir . "/" . "scss_cache";
			
			// Create cache directory if it doesn't exist
			if(!file_exists($cacheDir))
			{
				mkdir($cacheDir);
				chmod($cacheDir, 0755);
			}
			
			if($scss === null)
			{
				$scss = new CustomCompiler();
				$scss->setImportPaths($dir);
			}
			
			parent::__construct($dir, $cacheDir, $scss);
			$this->scss = $scss;
			
			$this->showErrorsAsCSS();
		}
		
		/**
		 * Determine whether .scss file needs to be re-compiled.
		 *
		 * @param string $out  Output path
		 * @param string $etag ETag
		 *
		 * @return boolean True if compile required.
		 */
		protected function needsCompile($out, &$etag)
		{
			if(static::TEST_MODE)
			{
				return true;
			}
			
			$needsCompile = parent::needsCompile($out, $etag);
			
			// Update SASS files if universal.php or Registry.php has changed, since the various constants may have changed as well
			if(!$needsCompile)
			{
				$fileModificationTime = filemtime($out);
				$thisModificationTime = filemtime(__FILE__);
				$universalModificationTime = filemtime(DOC_ROOT . "/admin/scripts-includes/universal.php");
				$customCompilerModificationTime = filemtime(DOC_ROOT . "/admin/classes/Sass/CustomCompiler.php");
				$registryModificationTime = filemtime(DOC_ROOT . "/admin/classes/Configuration/Registry.php");
				
				if($fileModificationTime < max($thisModificationTime, $universalModificationTime, $registryModificationTime, $customCompilerModificationTime))
				{
					return true;
				}
			}
			
			return $needsCompile;
		}
		
		/**
		 * Compile .scss file
		 *
		 * @param string $in  Input path (.scss)
		 * @param string $out Output path (.css)
		 *
		 * @return array
		 */
		protected function compile($in, $out)
		{
			$this->scss->setSourceMap(Compiler::SOURCE_MAP_FILE);
			
			if($out !== null)
			{
				$cssUrl = str_replace(DOC_ROOT, "", $out);
				$mapUrl = "{$cssUrl}.map";
				
				$this->scss->setSourceMapOptions(
				[
					"sourceMapWriteTo" => DOC_ROOT . $mapUrl,
					"sourceMapURL" => $mapUrl,
					"sourceMapFilename" => $cssUrl,
					"sourceMapBasepath" => DOC_ROOT,
					"sourceRoot" => "/"
				]);
			}
			
			$result = parent::compile($in, $out);
			
			if($out !== null && $this->scss instanceof CustomCompiler && $this->scss->map !== null)
			{
				file_put_contents(DOC_ROOT . $mapUrl, $this->scss->map);
			}
			
			return $result;
		}
	}