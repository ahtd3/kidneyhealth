<?php
	use Core\Attributes\Data;
	use Core\Attributes\ImageValue;
	use Core\Entity;
	use Core\Properties\ImageProperty;
	use Files\Image;
	
	/**
	 * Represents a single image that has been resized
	 */
	class ResizedImage extends Entity
	{
		const TABLE = "resized_images";
		const ID_FIELD = "resized_image_id";
		
		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/resized/";
		
		const SCALE = "SCALE";
		const CROP = "CROP";
		
		#[Data("path")]
		public string $path = "";
		
		#[Data("resize_type")]
		public string $resizeType = ImageProperty::SCALE;
		
		#[Data("width")]
		public int|null $width = null;
		
		#[Data("height")]
		public int|null $height = null;
		
		#[Data("last_accessed", "datetime")]
		public DateTime $lastAccessed;
		
		#[ImageValue("image", self::IMAGE_LOCATION)]
		public Image|null $image = null;
		
		/**
		 * Flushes all the images that haven't been accessed since a certain date
		 * @param	DateTimeInterface	$since		When to delete images before
		 */
		public static function flushImages(DateTimeInterface $since)
		{
			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "WHERE ~lastAccessed < ?";
				   
			$resizedImages = static::makeMany($query, [$since->format("Y-m-d H:i:s")]);
			
			foreach($resizedImages as $resizedImage)
			{
				$resizedImage->delete();
			}
		}
		
		/**
		 * Creates or retrives an image
		 * @param	Image	$image			The image to resize from
		 * @param	string	$resizeType		The type of image to resize to
		 * @param	int		$width			The width to resize the image to
		 * @param	int		$height			The height to resize the image to
		 * @return	Image					The resized image
		 */
		private static function resize(Image $image, $resizeType, $width = null, $height = null)
		{
			$stored = static::loadForMultiple(
			[
				"path" => $image->getLink(),
				"resizeType" => $resizeType,
				"width" => $width,
				"height" => $height
			]);
			
			if($stored->isNull())
			{
				if($resizeType === static::SCALE)
				{
					$newImage = $image->scale($width, $height);
				}
				else
				{
					$newImage = $image->crop($width, $height);
				}
				
				$stored = new static;
				$stored->path = $image->getLink();
				$stored->resizeType = $resizeType;
				$stored->width = $width;
				$stored->height = $height;
				$stored->image = $newImage;
			}
			else
			{
				$stored->lastAccessed = new DateTime;
			}
			
			$stored->save();
			
			return $stored->image;
		}
		
		/**
		 * Creates or retrieves a scaled image
		 * @param	Image	$image		The image to resize from
		 * @param	int		$width		The width to scale the image to
		 * @param	int		$height		The height to scale the image to
		 * @return	Image				The scaled image
		 */
		public static function scale(Image $image, $width = null, $height = null)
		{
			return self::resize($image, static::SCALE, $width, $height);
		}
		
		/**
		 * Creates or retrieves a cropped image
		 * @param	Image	$image		The image to resize from
		 * @param	int		$width		The width to crop the image to
		 * @param	int		$height		The height to crop the image to
		 * @return	Image				The cropped image
		 */
		public static function crop(Image $image, $width, $height)
		{
			return self::resize($image, static::CROP, $width, $height);
		}	
	}
	
	// Don't want this running during tests
	if(php_sapi_name() !== "cli")
	{
		ResizedImage::flushImages(new DateTime("3 months ago"));
	}