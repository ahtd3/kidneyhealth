<?php
	namespace Controller;
	
	use Files\File;
	
	/**
	 * Generates the relevant headers for a file and sends them to the user
	 */
	class DownloadController extends UrlController
	{
		/** @var File */
		private $file;
		
		/** @var string */
		private $filename;
		
		/**
		 * Outputs download headers
		 * @param	string		$contentType	The MIME type that's being outputted
		 * @param	string		$filename		The filename for the file to download
		 * @param	int|null	$fileSize		The size of the file to download (in bytes) (optional)
		 */
		public static function outputDownloadHeaders(string $filename, string $contentType, ?int $fileSize = null)
		{
			header("Content-Type: {$contentType}");
			header("Content-Transfer-Encoding: Binary");
			header("Content-Disposition: attachment; filename=\"{$filename}\"");
			
			if($fileSize !== null)
			{
				header("Content-Length: {$fileSize}");
			}
		}
		
		/**
		 * Creates a new Download Controller
		 * @param	File	$file		The file to download
		 * @param	string	$filename	An optional name to give the file, otherwise it will use the existing filename
		 */
		public function __construct(File $file, ?string $filename = null)
		{
			$this->file = $file;
			
			if($filename === null)
			{
				$this->filename = $file->getFilename();
			}
			else
			{
				$this->filename = $filename;
			}
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			static::outputDownloadHeaders($this->filename, $this->file->type, $this->file->getSize());
			readfile($this->file->path);
		}
		
		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			// unused
			return null;
		}
		
		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return	UrlController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			// unused
			return [];
		}
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController	$parent		The parent to the Page Child Controller
		 * @param	string[]		$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string			$pattern	The pattern that was matched
		 * @return	self						An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			// unused
			return null;
		}
	}