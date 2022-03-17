<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\Properties\ImageProperty;
	
	/**
	 * The attribute for an image property
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class ImageValue extends FileValue
	{
		const SCALE = ImageProperty::SCALE;
		const CROP = ImageProperty::CROP;
		
		/**
		 * Creates a new image value
		 * @param	string		$databaseName	The name of the associated field in the database
		 * @param	string		$location		The folder where the files should be stored
		 * @param	int|null	$width			The resize width of the image
		 * @param	int|null	$height			The resize height of the image
		 * @param	string		$resizeType		The type of resize for the image
		 */
		public function __construct(string $databaseName, string $location, public int|null $width = null, public int|null $height = null, public string $resizeType = ImageProperty::SCALE)
		{
			parent::__construct($databaseName, $location);
		}
		
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): ImageProperty
		{
			return new ImageProperty($variableName, $this->databaseName, $this->location, $this->width, $this->height, $this->resizeType);
		}
	}