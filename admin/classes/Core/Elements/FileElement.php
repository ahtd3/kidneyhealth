<?php
	namespace Core\Elements;

	use Core\Elements\Base\LabelledResultElement;
	use Exception;
	use Files\File;
	
	/**
	 * Allows a user to select a file
	 */
	class FileElement extends LabelledResultElement
	{
		public $accept = null;

		/**
		 * Sets the accept parameter for this element
		 *
		 * Note: Valid types consist of:
		 * - File extensions, including the dot (.png).
		 * - MIME types (image/png).
		 * - Wildcard mime types for image, audio and video (image/*).
		 *
		 * @param	string	$accept		A list of comma separated types this element accepts
		 * @return	$this				This object, for chaining
		 */
		public function setAccept($accept): self
		{
			$this->accept = $accept;

			return $this;
		}

		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "FileElement.js";
		}

		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() + ["accept" => $this->accept];
		}
		
		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			$file = $this->getValue();
			
			if($file === null)
			{
				return null;
			}
			
			assert($file instanceof File);
			return $file->getLink();
		}
		
		/**
		 * Gets the result of this element
		 * @param	mixed		$json	The JSON to retrieve the result from
		 * @return	File|null			The result that will be passed to the result handler
		 * @throws	Exception			If the temporary file doesn't exist
		 */
		public function getResult($json)
		{
			if($json === null)
			{
				return null;
			}
			else if($json[0] === "/")
			{
				//json is a path to the file, from the server

				if($this->getValue() === null)
				{
					// case where file has been copied to new object from server before object is created (saved).
					// @see Core/Generator::copy()
					$file = new File(DOC_ROOT . $json);

					if($file->exists() && ($file->getFolder()->path . "/") === File::TEMPORARY_UPLOADS_LOCATION)
					{
						return $file;
					}
					else
					{
						return null;
					}
				}
				else
				{
					return $this->getValue();
				}
			}
			else
			{
				//the file that was uploaded via JavaScript is processed here

				/** @var File[] $files */
				$files = [];
				
				// Get the list of chunked files to be concatenated back into an actual file
				foreach($json as $uploadIdentifier)
				{
					$path = File::TEMPORARY_UPLOADS_LOCATION . basename($_SESSION["temporaryUploads"][$uploadIdentifier]["tmp_name"]);
					
					if(!is_file($path))
					{
						throw new Exception("Could not find file: " . $path);
					}
					
					$file = new File($path);
					$file->originalName = $_SESSION["temporaryUploads"][$uploadIdentifier]["name"];
					$files[] = $file;
				}
				
				// Use the first file as the base, and grab it's original filename
				$newFile = $files[0]->copy(sys_get_temp_dir() . "/" . uniqid());
				$newFile->originalName = $files[0]->originalName;
				
				// Only need to worry about concatenation if we've got multiple files to concatenate
				if(count($files) > 1)
				{
					$concatenatedFilePointer = fopen($newFile->path, "ab");
					$size = 100 * 1024; // Read up to 100kb at a time
					
					// Concatenate chunks back into the original file
					for($i = 1; $i < count($files); $i += 1)
					{
						$partialFilePointer = fopen($files[$i]->path, "rb");
						
						while($chunk = fread($partialFilePointer, $size))
						{
							fwrite($concatenatedFilePointer, $chunk, $size);
						}
						
						fclose($partialFilePointer);
					}
					
					fclose($concatenatedFilePointer);
				}
				
				return $newFile;
			}
		}
	}