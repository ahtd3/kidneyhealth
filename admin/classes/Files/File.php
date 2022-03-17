<?php
	namespace Files;
	
	/**
	 * Class for handling individual files
	 */
	class File extends FileSystemItem
	{
		const MIME_FILE_LOCATION = "/etc/mime.types";
		const TEMPORARY_UPLOADS_LOCATION = DOC_ROOT . "/resources/files/temporary-uploads/";

		public $type;
		public $originalName = '';

		/**
		 * Creates a file from a base64 string
		 * @param	string	$string		The base64 string to make the file from
		 * @return	static				A temporary file for that base64 string
		 */
		public static function createFromBase64($string)
		{
			$path = sys_get_temp_dir() . "/" . uniqid();
			file_put_contents($path, base64_decode($string));

			return new static($path);
		}

		/**
		 * Helper function for importing files / images
		 *
		 * @param 	String 	$url	the url of the file to be imported
		 * @return 	File 	$file 	new ORM file in /tmp
		 */
		public static function importFile($url)
		{
			$extension = pathinfo($url, PATHINFO_EXTENSION);
			$newPath = sys_get_temp_dir() . "/" . uniqid() . "." . $extension;

			if (copy($url, $newPath ))
			{
				return new static($newPath);
			}
			else
			{
				return null;
			}
		}

		/**
		 * Creates a file from a path
		 * @param	string	$path	The full path to the file
		 */
		public function __construct(string $path)
		{
			parent::__construct($path);

			$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
			$this->type = @finfo_file($fileInfo, $path);
			finfo_close($fileInfo);
		}

		/**
		 * Gets the filename without file extension for this file
		 * @return	string	The filename without file extension
		 */
		public function getFilenameWithoutExtension()
		{
			return pathinfo($this->path, PATHINFO_FILENAME);
		}

		/**
		 * Gets the file extension for this file
		 * @return	string	The file extension (including the . prefix)
		 */
		public function getFileExtension()
		{
			return "." . pathinfo($this->path, PATHINFO_EXTENSION);
		}

		/**
		 * Gets the full link to the file
		 * @return	string	The link to this file
		 */
		public function getFullLink()
		{
			return PROTOCOL . SITE_ROOT . $this->getLink();
		}

		/**
		 * Gets a link to the this file
		 * @return	string	The link to this file
		 */
		public function getLink()
		{
			return str_replace(DOC_ROOT, "", $this->path);
		}

		/**
		 * Gets the file extension for this File's MIME type
		 * Note: This is something of a "best guess" system, it relies on reversing UNIX's own mapping from file extensions to MIME type, so although it will work for common file types, there will be plenty of obscure file types that it won't be able to reverse. In cases where there are multiple appropriate file extensions for a particular MIME type, the first file extension in the list will be used.
		 * Note: Since this uses a UNIX specific file, it won't work on Windows. To get it to work on OS X, change the MIME_FILE_LOCATION constant to "/etc/apache2/mime.types".
		 * @return	string	The file extension (including the . prefix), or ".xxx" if it doesn't know
		 */
		public function getFileExtensionFromType()
		{
			if(file_exists(self::MIME_FILE_LOCATION))
			{
				$text = file_get_contents(self::MIME_FILE_LOCATION);
				$lines = explode("\n", $text);

				foreach($lines as $line)
				{
					if($line !== "" && $line[0] !== "#")
					{
						$parts = explode("\t", $line, 2);

						if(count($parts) < 2)
						{
							continue;
						}

						if($parts[0] === $this->type)
						{
							$extensions = explode(" ", trim($parts[1]));

							return "." . $extensions[0];
						}
					}
				}
			}

			return ".xxx";
		}

		/**
		 * Creates a base64 version of this file
		 * @return	string	The base64 string
		 */
		public function getBase64()
		{
			return base64_encode(file_get_contents($this->path));
		}

		/**
		 * Generates a base64 URL for this file
		 * @return	string	The base64 URL
		 */
		public function getBase64Url()
		{
			return "data:" . $this->type . ";base64," . $this->getBase64();
		}

		/**
		 * Gets the size of this file
		 * @return	int		The size of this file in bytes
		 */
		public function getSize()
		{
			return filesize($this->path);
		}

		/**
		 * Checks if the file exists and is not a directory
		 * @return	bool	Whether the file exists
		 */
		public function exists()
		{
			return is_file($this->path);
		}
		
		/**
		 * Renames this item
		 * @param	string	$name	The new name for this item
		 * @return	bool			Whether the file was successfully renamed
		 */
		public function rename(string $name)
		{
			$result = parent::rename($name);
			
			if($result)
			{
				chmod($this->path, 0644);
			}
			
			return $result;
		}
		
		/**
		 * Copies this File
		 * @param	string	$path	The new path for this file
		 * @return	static			A copy of this file
		 */
		public function copy(string $path)
		{
			copy($this->path, $path);
			chmod($path, 0644);
			return new static($path);
		}
		
		/**
		 * Moves this file
		 * @param	string	$folder		The new folder for this file
		 * @return	bool				Whether the item was successfully moved
		 */
		public function move(string $folder)
		{
			$result = parent::move($folder);
			
			if($result)
			{
				chmod($this->path, 0644);
			}
			
			return $result;
		}

		/**
		 * Deletes this file from the file system
		 */
		public function delete()
		{
			@unlink($this->path);
		}

		/**
		 * Runs when this object is removed from memory
		 */
		public function __destruct()
		{
			$folder = $this->getFolder();
			$path = $this->path;

			if($folder->path === sys_get_temp_dir())
			{
				register_shutdown_function(function() use ($path)
				{
					@unlink($path);
				});
			}
		}
	}
