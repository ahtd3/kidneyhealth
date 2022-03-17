<?php
	namespace Assets\Picker;
	
	use Database\Database;
	use Database\Sqlite;
	use Exception;
	use Files\File;
	use Files\FileSystemItem;
	use Files\Folder;
	use Files\Image;
	use Slugging\Slugging;
	
	/**
	 * Handles the logic behind the TinyMCE integrated file picker
	 */
	class Picker
	{
		public $root;
		public $db;
		
		/**
		 * Runs some code with the default query runner set to $this->db
		 * @param	callable	$callable	The code to run
		 */
		private function runInSqlite(callable $callable)
		{
			$originalDb = Database::getDefaultQueryRunner();
			Database::setDefaultQueryRunner($this->db);
			$callable();
			Database::setDefaultQueryRunner($originalDb);
		}
		
		/**
		 * Creates a new picker object, with a specific root
		 * @param	string	$root	The path to the root of selectable files for this picker
		 */
		public function __construct(string $root)
		{
			$this->root = new Folder($root);
			$dbLocation = "{$this->root->getFolder()->path}{$this->root->getFilename()}.db";
			$dbExists = file_exists($dbLocation);
			$this->db = new Sqlite($dbLocation);
			
			if(!$dbExists)
			{
				$query = "CREATE TABLE files "
					   . "( "
					   .	"file_id INTEGER PRIMARY KEY AUTOINCREMENT, "
					   .	"filename TEXT UNIQUE, "
					   .	"path TEXT, "
					   .	"original INTEGER DEFAULT 1 "
					   . ")";
				
				$this->db->run($query);
				$this->refreshCache($this->root);
			}
		}
		
		/**
		 * Checks that a file system item belongs to this picker
		 * @param	FileSystemItem	$item	The item to check
		 * @return	bool					Whether it belongs to this picker
		 */
		private function parentOf(FileSystemItem $item)
		{
			return strpos($item->path, $this->root->path) === 0;
		}
		
		/**
		 * Refreshes the cache for a specific folder
		 * @param	Folder		$folder		The folder to start refreshing from
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function refreshCache(Folder $folder)
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			foreach($folder->getContents() as $item)
			{
				if($item instanceof Folder)
				{
					$this->refreshCache($item);
				}
				else
				{
					$this->runInSqlite(function() use($item)
					{
						$pickerFile = PickerFile::loadFor("filename", $item->getFilename());
						
						if($pickerFile->isNull())
						{
							$pickerFile = new PickerFile();
							$pickerFile->filename = $item->getFilename();
						}
						
						$pickerFile->path = $item->path;
						$pickerFile->save();
					});
				}
			}
		}
		
		/**
		 * Gets the parent folder for a specific folder
		 * @param	Folder		$folder		The folder to get the parent folder for
		 * @return	Folder|null				The parent folder for that folder, or null if the folder is the root of this picker
		 * @throws	Exception				If the folder does not belong to the parent folder, or if the folder is the root folder
		 */
		public function getParentFolder(Folder $folder): ?Folder
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			else if($folder->path === $this->root->path)
			{
				return null;
			}
			
			return $folder->getFolder();
		}
		
		/**
		 * Gets all the folders in a particular folder
		 * @param	Folder		$folder		The folder to get the child folders from
		 * @return	Folder[]				The child folders for that folder
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function getChildFolders(Folder $folder): array
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			return array_values(array_filter($folder->getContents(), function(FileSystemItem $item)
			{
				return $item instanceof Folder && $item->getFilename()[0] !== ".";
			}));
		}
		
		/**
		 * Gets all the files in a particular folder
		 * @param	Folder		$folder		The folder to get the files from
		 * @return	File[]					The files in that folder
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function getFiles(Folder $folder): array
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			return array_values(array_filter($folder->getContents(), function(FileSystemItem $item)
			{
				return $item instanceof File && $item->getFilename()[0] !== ".";
			}));
		}
		
		/**
		 * Creates a new folder, or just retrieves a folder if it already exists
		 * @param	Folder		$parent		The parent folder to insert the new folder into
		 * @param	string		$filename	The filename for the new folder
		 * @return	Folder					The newly created folder
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function createNewFolder(Folder $parent, string $filename): Folder
		{
			if(!$this->parentOf($parent))
			{
				throw new Exception("Could not find parent folder");
			}
			
			$path = $parent->path . Slugging::slug($filename);
			
			if(!file_exists($path))
			{
				mkdir($path);
				chmod($path, 0755);
			}
			
			return new Folder($path);
		}
		
		/**
		 * Renames a folder
		 * @param	Folder		$folder		The folder to rename
		 * @param	string		$filename	The name to rename it to
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function renameFolder(Folder $folder, string $filename)
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			$folder->rename(Slugging::slug($filename));
			$this->refreshCache($folder);
		}
		
		/**
		 * Moves a folder to a different folder
		 * @param	Folder		$folder		The folder to move
		 * @param	Folder		$parent		The folder to move the folder to
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function moveFolder(Folder $folder, Folder $parent)
		{
			if(!$this->parentOf($folder) || !$this->parentOf($parent))
			{
				throw new Exception("Could not find folder");
			}
			
			$folder->move($parent->path);
			$this->refreshCache($folder);
		}
		
		/**
		 * Deletes a folder
		 * @param	Folder		$folder		The folder to delete
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function deleteFolder(Folder $folder)
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			foreach($this->getChildFolders($folder) as $childFolder)
			{
				$this->deleteFolder($childFolder);
			}
			
			foreach($this->getFiles($folder) as $file)
			{
				$this->deleteFile($file);
			}
			
			$folder->delete();
		}
		
		/**
		 * Checks if a filename exists in the database
		 * @param	string	$filename	The filename to check
		 * @return	bool				Whether it exists
		 */
		public function filenameExists(string $filename)
		{
			$query = "SELECT COUNT(file_id) AS file_count "
				   . "FROM files "
				   . "WHERE filename = ?";
			
			$result = $this->db->run($query, [$filename]);
			return $result[0]["file_count"] > 0;
		}
		
		/**
		 * Inserts a file
		 * @param	File		$file		The file to insert
		 * @param	Folder		$folder		The folder to insert the file into
		 * @param	bool		$original	Whether this is an original file
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function insertFile(File $file, Folder $folder, bool $original = true)
		{
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			$base = Slugging::slug(pathinfo($file->originalName, PATHINFO_FILENAME));
			$extension = pathinfo($file->originalName, PATHINFO_EXTENSION);
			$newFilename = "{$base}.{$extension}";
			$i = 1;
			
			while(file_exists("{$folder->path}{$newFilename}") || $this->filenameExists($newFilename))
			{
				$i += 1;
				$newFilename = "{$base}-{$i}.{$extension}";
			}
			
			$file->rename($newFilename);
			$file->move($folder->path);
			
			$this->runInSqlite(function() use($file, $newFilename, $original)
			{
				$pickerFile = new PickerFile();
				$pickerFile->filename = $newFilename;
				$pickerFile->path = $file->path;
				$pickerFile->original = $original;
				$pickerFile->save();
			});
		}
		
		/**
		 * Renames a file
		 * @param	File		$file		The file to rename
		 * @param	string		$filename	The filename to rename it to
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function renameFile(File $file, string $filename)
		{
			if(!$this->parentOf($file))
			{
				throw new Exception("Could not find file");
			}
			
			$oldFilename = $file->getFilename();
			
			$base = Slugging::slug(pathinfo($filename, PATHINFO_FILENAME));
			$extension = $file->getFileExtension();
			$newFilename = "{$base}{$extension}";
			$i = 1;
			
			while(file_exists("{$file->getFolder()->path}{$newFilename}") || $this->filenameExists($newFilename))
			{
				$i += 1;
				$newFilename = "{$base}-{$i}{$extension}";
			}
			
			$file->rename($newFilename);
			
			$this->runInSqlite(function() use($file, $newFilename, $oldFilename)
			{
				$pickerFile = PickerFile::loadFor("filename", $oldFilename);
				$pickerFile->file = new File($newFilename);
				$pickerFile->path = $file->path;
				$pickerFile->save();
			});
		}
		
		/**
		 * Moves a file to a different folder
		 * @param	File		$file		The file to move
		 * @param	Folder		$folder		The folder to move it to
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function moveFile(File $file, Folder $folder)
		{
			if(!$this->parentOf($file) && !$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			$file->move($folder->path);
			
			$this->runInSqlite(function() use($file)
			{
				$pickerFile = PickerFile::loadFor("filename", $file->getFilename());
				$pickerFile->path = $file->path;
				$pickerFile->save();
			});
		}
		
		/**
		 * Inserts or replaces an image
		 * @param	File	$file		The file to insert
		 * @param	Folder	$folder		The folder to insert it into
		 * @return	Image				The recently inserted image
		 * @throws	Exception				If the folder does not belong to the parent folder
		 */
		public function insertOrReplaceImage(File $file, Folder $folder): Image
		{
			$image = new Image($file->path);
			$image = $image->scale(1920, 1080);
			$image->originalName = $file->originalName;
			
			if(!$this->parentOf($folder))
			{
				throw new Exception("Could not find folder");
			}
			
			$this->runInSqlite(function() use($image, $folder)
			{
				$pickerFile = PickerFile::loadFor("filename", $image->originalName);
				
				if($pickerFile->isNull() || $pickerFile->original)
				{
					$this->insertFile($image, $folder, $pickerFile->isNull());
				}
				else
				{
					$image->rename($pickerFile->filename);
					$image->move($pickerFile->file->getFolder()->path);
				}
			});
			
			return $image;
		}
		
		/**
		 * Deletes a file
		 * @param	File		$file	The file to delete
		 * @throws	Exception			If the folder does not belong to the parent folder
		 */
		public function deleteFile(File $file)
		{
			if(!$this->parentOf($file))
			{
				throw new Exception("Could not find file");
			}
			
			$this->runInSqlite(function() use($file)
			{
				$pickerFile = PickerFile::loadFor("filename", $file->getFilename());
				$pickerFile->delete();
			});
			
			$file->delete();
		}
		
		/**
		 * Gets the folder to output files for for a specific folder
		 * @param	Folder	$folder		The folder to get the files and folders for
		 * @return	Folder				The folder to output the files for
		 */
		public function getOutputFolder(Folder $folder): Folder
		{
			if(!$this->parentOf($folder))
			{
				$folder = $this->root;
			}
			
			return $folder;
		}
	}