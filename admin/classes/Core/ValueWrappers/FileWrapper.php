<?php
	namespace Core\ValueWrappers;

	use Core\Properties\FileProperty;
	use Core\Properties\ImageProperty;
	use Files\File;
	use Files\Image;
	use Slugging\Slugging;
	
	/**
	 * Contains a file or image
	 */
	class FileWrapper extends ValueWrapper
	{
		/** @var File */
		public $oldFile = null;

		/**
		 * Converts the database value into the output value
		 * @param	mixed	$value	The current database value
		 * @return	mixed			The related output value
		 */
		protected function convertDatabaseValue($value)
		{
			if($value === null)
			{
				return null;
			}

			if($this->property instanceof ImageProperty)
			{
				return new Image(DOC_ROOT . $value);
			}
			else
			{
				return new File(DOC_ROOT . $value);
			}
		}

		/**
		 * Processes the output value for consistency
		 * @param	mixed	$value	The output value to set
		 * @return	mixed			The value to store in the value wrapper
		 */
		protected function processInputValue($value)
		{
			if($value === $this->getForOutput())
			{
				return $this->getForOutput();
			}

			if($this->oldFile === null)
			{
				$this->oldFile = $this->getForOutput();
			}

			if($value === null)
			{
				return null;
			}

			if($this->property instanceof ImageProperty)
			{
				$originalName = "";
				
				if(!is_object($value))
				{
					$image = new Image($value);
				}
				else if(is_object(($value)) && $value instanceof File)
				{
					$originalName = $value->originalName;
					$image = new Image($value->path);
				}
				else
				{
					return null;
				}

				if($this->property->resizeType === ImageProperty::SCALE)
				{
					$image = $image->scale($this->property->width, $this->property->height);
				}
				else
				{
					$image = $image->crop($this->property->width, $this->property->height);
				}
				
				$image->originalName = $originalName;

				return $image;
			}
			else if(is_object($value) && $value instanceof File)
			{
				return $value;
			}
			else
			{
				return new File($value);
			}
		}

		/**
		 * Converts the output value into the database value
		 * @param	mixed	$value	The current output value
		 * @return	mixed			The related database value
		 */
		protected function convertOutputValue($value)
		{
			if($value === null)
			{
				return null;
			}

			return str_replace(DOC_ROOT, "", $value->path);
		}

		/**
		 * Resets the modified status back to retrieved, should only be used on save()
		 */
		public function resetToSaved()
		{
			parent::resetToSaved();

			if ($this->oldFile !== null && ($this->getForOutput() === null || $this->oldFile->path !== $this->getForOutput()->path))
			{
				$this->oldFile->delete();
				$this->oldFile = null;
			}
		}

		/**
		 * Is run before the owner saves
		 */
		public function beforeSave()
		{
			/** @var FileProperty $property */
			$property = $this->property;
			$propertyName = $property->getPropertyName();

			/** @var File $file */
			$file = $this->entity->$propertyName;

			if($file === null)
			{
				return;
			}

			// newly uploaded file
			if ($file->originalName !== '')
			{
				$filename = substr($file->originalName, 0, strrpos($file->originalName, "."));
				$extension = substr($file->originalName, (strrpos($file->originalName, ".")));

				$filename = Slugging::slug($filename);
				$name = "{$filename}{$extension}";

				$counter = 1;
				$path = $property->location . '/' . $name;

				while (file_exists($path) && $path !== $file->path)
				{
					$name = $filename . '-' . $counter . $extension;
					$path = $property->location . '/' . $name;
					$counter += 1;
				}

				$file->rename($name);
			}

			if($file->getFolder()->path !== $property->location)
			{
				$file->move($property->location);
			}
		}

		/**
		 * Is run after the owner is deleted
		 */
		public function afterDelete()
		{
			$propertyName = $this->property->getPropertyName();
			$image = $this->entity->$propertyName;

			if($image !== null)
			{
				$image->delete();
			}
		}

		/**
		 * Runs on clone
		 */
		public function __clone()
		{
			parent::__clone();

			$file = $this->getForOutput();
			if($file === null)
			{
				return;
			}
			$newFile = $file->copy(sys_get_temp_dir() . '/' . uniqid());
			$newFile->originalName = $file->getFilename();
			$this->setFromInput($newFile);
			// prevent original file from beng deleted @see resetToSaved()
			$this->oldFile = null;
		}
	}
