<?php
	namespace Files;

	
	/**
	 * Class for handling individual images
	 */
	class Image extends File
	{
		public $width;
		public $height;

		/**
		 * Gets the image size for an SVG
		 * @param	string	$path	The path to the image
		 * @return	float[]			The image size
		 */
		private static function getSvgImageSize($path)
		{
			$xml = simplexml_load_file($path);
			$width = (float) $xml->attributes()->width;
			$height = (float) $xml->attributes()->height;
			$viewBox = (string) $xml->attributes()->viewBox;

			if($viewBox === "")
			{
				$offsetX = 0;
				$offsetY = 0;
				$viewWidth = $width;
				$viewHeight = $height;
			}
			else
			{
				$matches = [];
				// A viewbox looks like "-5 -10 10 20". The numbers are, respectively, the horizontal offset, the vertical offset, the width and the height.
				preg_match("/(-?[0-9.]+)[,\s]+(-?[0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)/", trim($viewBox), $matches);
				$offsetX = $matches[1];
				$offsetY = $matches[2];
				$viewWidth = $matches[3];
				$viewHeight = $matches[4];
			}

			if($width == 0 && $height == 0)
			{
				$width = $viewWidth;
				$height = $viewHeight;
			}
			else if($height == 0)
			{
				$height = ($viewHeight / $viewWidth) * $width;
			}
			else if($width == 0)
			{
				$width = ($viewWidth / $viewHeight) * $height;
			}

			return
			[
				"width" => $width,
				"height" => $height,
				"offsetX" => $offsetX,
				"offsetY" => $offsetY,
				"viewWidth" => $viewWidth,
				"viewHeight" => $viewHeight
			];
		}

		/**
		 * Checks if this is an SVG
		 * @return	bool	Whether it's an SVG
		 */
		private function isSvg()
		{
			return $this->type === "image/svg+xml" || $this->type === "image/svg" || $this->type === "text/html" || $this->type === "text/plain";
		}

		/**
		 * Creates an Image from a path
		 * @param	string	$path	The full path to the file
		 */
		public function __construct(string $path)
		{
			parent::__construct($path);
			
			if(!$this->isSvg())
			{
				$imageSize = @getimagesize($this->path);
				$this->width = $imageSize[0];
				$this->height = $imageSize[1];
			}
			else
			{
				$imageSize = self::getSvgImageSize($this->path);
				$this->width = $imageSize["width"];
				$this->height = $imageSize["height"];
			}
		}
		
		/**
		 * Gets the file extension for this File's MIME type
		 * Note: This is something of a "best guess" system, it relies on reversing UNIX's own mapping from file extensions to MIME type, so although it will work for common file types, there will be plenty of obscure file types that it won't be able to reverse. In cases where there are multiple appropriate file extensions for a particular MIME type, the first file extension in the list will be used.
		 * Note: Since this uses a UNIX specific file, it won't work on Windows. To get it to work on OS X, change the MIME_FILE_LOCATION constant to "/etc/apache2/mime.types".
		 * @return	string	The file extension (including the . prefix), or ".xxx" if it doesn't know
		 */
		public function getFileExtensionFromType()
		{
			if($this->isSvg())
			{
				return ".svg";
			}
			
			return parent::getFileExtensionFromType();
		}

		/**
		 * Scales this Image and returns a new Image
		 * @param	int			$maxWidth	The maximum width to scale to (0 or null means no maximum)
		 * @param	int			$maxHeight	The maximum height to scale to (0 or null means no maximum)
		 * @return	Image					The scaled Image
		 */
		public function scale($maxWidth = null, $maxHeight = null)
		{
			$path = sys_get_temp_dir() . "/" . uniqid() . $this->getFileExtensionFromType();

			if($this->width == 0 || $this->height == 0)
			{
				return new Image("");
			}

			//find width and height for resize while keeping the aspect ratio of the current image
			if($this->isSvg() || ($maxWidth === null || $maxWidth === 0 || $maxWidth >= $this->width) && ($maxHeight === null || $maxHeight === 0 || $maxHeight >= $this->height))
			{
				//we don't have a max width or height set, or the image is already below the max so there's no need to resize
				return $this->copy($path);
			}
			else if($maxWidth === null || $maxWidth === 0)
			{
				$height = $maxHeight;
				$width = round($this->width * ($maxHeight / $this->height));//width for aspect ratio
			}
			else if($maxHeight === null || $maxHeight === 0)
			{
				$width = $maxWidth;
				$height = round($this->height * ($maxWidth / $this->width));//height for aspect ratio
			}
			else //have both $maxWidth and $widthHeight, need to figure out how to fit image within both of these
			{
				$widthRatio = $maxWidth / $this->width;
				$heightRatio = $maxHeight / $this->height;
				$ratio = min($widthRatio, $heightRatio);
				$width = $this->width * $ratio;
				$height = $this->height * $ratio;
			}

			return $this->scaleImagick($path, $width, $height);
		}
		
		/**
		 * Resizes and crops this Image and returns a new Image
		 * @param	int		$width		The width to crop this Image to (0 or null means keep the current width)
		 * @param	int		$height		The height to crop this Image to (0 or null means keep the current height)
		 * @return	Image				The cropped Image
		 */
		public function crop($width = null, $height = null)
		{
			$path = sys_get_temp_dir() . "/" . uniqid() . $this->getFileExtensionFromType();

			if($this->isSvg() || ($width === null || $width === 0 || $width === $this->width) && ($height === null || $height === 0 || $height === $this->height))
			{
				//we don't have a width or height set, or the image is the right dimentions, so there's no need to crop
				return $this->copy($path);
			}
			else if($width === null || $width === 0)
			{
				$width = $this->width;
			}
			else if($height === null || $height === 0)
			{
				$height = $this->height;
			}

			return $this->cropImagick($path, $width, $height);
		}

		/**
		 * Scales this Image using Imagick
		 * @param	string	$path		The path to the new image file
		 * @param	int		$width		The new width of the Image
		 * @param	int		$height		The new height of the Image
		 * @return	Image				The new Image
		 */
		private function scaleImagick(string $path, int $width, int $height): Image
		{
			$originalPath = escapeshellarg($this->path);
			$newPath = escapeshellarg($path);
			
			exec("convert {$originalPath} -resize {$width}x{$height} {$newPath}");

			return new static($path);
		}

		/**
		 * Resizes and crops this Image using Imagick
		 * @param	string	$path		The path to the new image file
		 * @param	int		$width		The width to crop this Image to
		 * @param	int		$height		The height to crop this Image to
		 * @return	Image				The new Image
		 */
		private function cropImagick(string $path, int $width, int $height): Image
		{
			$originalPath = escapeshellarg($this->path);
			$newPath = escapeshellarg($path);
			
			exec("convert {$originalPath} -background none -resize {$width}x{$height}^ -gravity center -extent {$width}x{$height} {$newPath}");

			return new static($path);
		}

		/**
		 * Creates an HTML tag for the image
		 * @param	string	$alt	The value for the alt tag
		 * @param string $class Class to add to the image tag
		 * @param string $id    ID to add to the image tag
		 * @return string The resulting tag
		 */
		public function tag($class = "", $alt = "", $id = "")
		{
			return "<img src='" . $this->getLink() . "' width='" . $this->width . "' height='" . $this->height . "' alt='" . htmlentities($alt, ENT_QUOTES | ENT_XHTML) . "' class='" . $class . "' id='" . $id . "' />\n";
		}
	}