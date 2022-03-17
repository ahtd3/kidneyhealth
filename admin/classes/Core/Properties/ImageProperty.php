<?php
	namespace Core\Properties;
	
	/**
	 * An Image Property handles images attached to an Entity
	 */
	class ImageProperty extends FileProperty
	{
		const SCALE = "scale";
		const CROP = "crop";

		const DEFAULT_MAX_WIDTH = 2560;
		const DEFAULT_MAX_HEIGHT = 1440;

		public $width;
		public $height;
		public $resizeType;

		/**
		 * Creates a new Image Property
		 * @param	string	$propertyName	The name of the Property
		 * @param	string	$databaseName	The name of the field in the database
		 * @param	string	$location		The folder to place the file in
		 * @param	int		$width			The maximum or absolute width to resize to
		 * @param	int		$height			The maximum or absolute height to resize to
		 * @param	string	$resizeType		The type of resizing to apply
		 */
		public function __construct($propertyName, $databaseName, $location, $width = null, $height = null, $resizeType = ImageProperty::SCALE)
		{
			parent::__construct($propertyName, $databaseName, $location);

			if ($width === 0)
			{
				$width = null;
			}

			if ($height === 0)
			{
				$height = null;
			}
			
			assert($resizeType === ImageProperty::SCALE || ($width !== null && $height !== null), "Cropped images must have a width and height");
			
			if($width === null)
			{
				$width = static::DEFAULT_MAX_WIDTH;
			}
			
			if($height === null)
			{
				$height = static::DEFAULT_MAX_HEIGHT;
			}

			$this->width = $width;
			$this->height = $height;
			$this->resizeType = $resizeType;
		}
	}
