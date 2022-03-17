<?php
	namespace Files;
	
	use PhpParser\Error;
	
	/**
	 * Class for handling entire folders
	 */
	class Folder extends FileSystemItem
	{
		/**
		 * Creates a new folder object
		 * @param	string	$path	The path to the folder
		 */
		public function __construct(string $path)
		{
			// Make sure folders always have a trailing slash
			if($path[strlen($path) - 1] !== "/")
			{
				$path .= "/";
			}
			
			parent::__construct($path);
		}
		
		/**
		 * Gets the contents of this folder
		 * @return	FileSystemItem[]	The items in this folder
		 */
		public function getContents(): array
		{
			$contents = [];
			
			foreach(scandir($this->path) as $childFilename)
			{
				// Ignore current and parent folders
				if($childFilename === "." || $childFilename === "..")
				{
					continue;
				}
				
				$childPath = $this->path . $childFilename;
				
				if(is_file($childPath))
				{
					$contents[] = new File($childPath);
				}
				else
				{
					$contents[] = new Folder($childPath);
				}
			}
			
			return $contents;
		}
		
		/**
		 * Checks if this folder exists and is definitely a directory
		 * @return	bool	Whether the folder exists
		 */
		public function exists()
		{
			return is_dir($this->path);
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
				chmod($this->path, 0755);
			}
			
			return $result;
		}
		
		/**
		 * Copies this Folder
		 * @param	string	$path	The new path for this folder
		 * @return	static			A copy of this folder
		 */
		public function copy(string $path)
		{
			$origin = escapeshellarg($this->path);
			$destination = escapeshellarg($path);
			
			// Using a system command to copy a folder, since PHP does not support directory copying
			system("cp -r {$origin} {$destination}");
			chmod($path, 0755);
			return new static($path);
		}
		
		/**
		 * Moves this folder
		 * @param	string	$folder		The new parent folder for this folder
		 * @return	bool				Whether the folder was successfully moved
		 */
		public function move(string $folder)
		{
			$result = parent::move($folder);
			
			if($result)
			{
				chmod($this->path, 0755);
			}
			
			return $result;
		}
		
		/**
		 * Deletes this folder from the file system
		 */
		public function delete()
		{
			if($this->path === DOC_ROOT . "/")
			{
				throw new Error("You roll a 1. Your attempt to delete the site root has been blocked by an overly helpful sanity check");
			}
			
			foreach($this->getContents() as $childItem)
			{
				$childItem->delete();
			}
			
			@rmdir($this->path);
		}
	}