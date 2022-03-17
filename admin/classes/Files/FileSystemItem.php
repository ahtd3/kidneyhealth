<?php
	namespace Files;
	
	/**
	 * Enforces a single argument constructor for File System Items
	 */
	interface FileSystemItemInterface
	{
		/**
		 * Creates an item from a path
		 * @param string $path The full path to the item
		 */
		public function __construct(string $path);
	}
	
	/**
	 * A FileSystemItem is something that exists in the file system. Usually a file or a folder.
	 */
	abstract class FileSystemItem implements FileSystemItemInterface
	{
		public $path;
		
		/**
		 * Creates an item from a path
		 * @param string $path The full path to the item
		 */
		public function __construct(string $path)
		{
			$this->path = $path;
		}
		
		/**
		 * Gets the parent folder for this item
		 * @return	Folder	The parent folder for this item
		 */
		public function getFolder(): Folder
		{
			return new Folder(dirname($this->path));
		}
		
		/**
		 * Gets the filename for this item
		 * @return	string	The filename for this item
		 */
		public function getFilename()
		{
			return basename($this->path);
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
		 * Checks if this item exists
		 * @return	bool	Whether this item exists
		 */
		abstract public function exists();
		
		/**
		 * Renames this item
		 * @param	string	$name	The new name for this item
		 * @return	bool			Whether the file was successfully renamed
		 */
		public function rename(string $name)
		{
			if($name === $this->getFilename())
			{
				return false;
			}
			
			$newPath = $this->getFolder()->path . "/" . $name;
			
			if($this->exists())
			{
				rename($this->path, $newPath);
				$this->path = $newPath;
				return true;
			}
			
			return false;
		}
		
		/**
		 * Copies this item
		 * @param	string	$path	The new path for this item
		 * @return	static			A copy of this item
		 */
		abstract public function copy(string $path);
		
		/**
		 * Moves this item
		 * @param	string	$folder		The new parent folder for this item
		 * @return	bool				Whether the item was successfully moved
		 */
		public function move(string $folder)
		{
			$newPath = rtrim($folder, "/") . "/" . $this->getFilename();
			
			if($this->exists())
			{
				rename($this->path, $newPath);
				$this->path = $newPath;
				return true;
			}
			
			return false;
		}
		
		/**
		 * Deletes this item from the file system
		 */
		abstract public function delete();
	}