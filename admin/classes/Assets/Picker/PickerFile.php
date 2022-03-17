<?php
	namespace Assets\Picker;
	
	use Core\Attributes\Data;
	use Core\Attributes\Dynamic;
	use Core\Entity;
	use Core\Properties\Property;
	use Files\File;
	
	/**
	 * Represents a single file that can be selected from the picker
	 */
	class PickerFile extends Entity
	{
		const TABLE = "files";
		const ID_FIELD = "file_id";
		
		#[Data("filename")]
		public string $filename = "";
		
		#[Data("path")]
		public string $path = "";
		
		#[Data("original")]
		public bool $original = true;
		
		// Note: We're not using a FileProperty, because FileProperties expect an absolute directory path, while a PickerFile can be found in multiple directories at any depth.
		#[Dynamic]
		public File|null $file = null;
		
		/**
		 * Gets the file that this object points to
		 * @return	File|null	The file this object points to
		 */
		public function get_file(): ?File
		{
			if($this->path === "")
			{
				return null;
			}
			
			return new File($this->path);
		}
		
		/**
		 * Gets the path to this file
		 * @return	string	The path
		 */
		public function get_path()
		{
			$path = $this->getValue("path");
			
			if($path === "")
			{
				return "";
			}
			
			return DOC_ROOT . $path;
		}
		
		/**
		 * Sets the path for this file
		 * @param	string	$path	The path to this file
		 */
		public function set_path(string $path)
		{
			$this->setValue("path", str_replace(DOC_ROOT, "", $path));
		}
	}