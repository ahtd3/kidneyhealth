<?php
	namespace Media;

	/**
	 * Converts URLs into YouTube embeds
	 */
	class Facebook extends StreamingVideo
	{
		const FULL = 4;

		// Automatically play the video on page load
		public $autoplay = false;

		// Whether the user can view the video in full screen
		public $allowFullScreen = true;

		// Turn on closed captions if available (desktop only)
		public $showCaptions = false;
		
		/**
		 * Gets the URL for the embed code
		 * @return	string	The embed URL
		 */
		public function getEmbedUrl(): string
		{
			$parameters =
			[
				"allowfullscreen" => $this->allowFullScreen ? "true" : "false",
				"autoplay" => $this->autoplay ? "true" : "false",
				"show-captions" => $this->showCaptions ? "true" : "false",
				"href" => $this->getUrl()
			];
			
			$queryString = http_build_query($parameters);
			
			return "https://www.facebook.com/v2.3/plugins/video.php?" . $queryString;
		}
		
		/**
		 * Retrieves the embed code for this Streaming Video
		 * @param	int		$width		The width to make the embedded video
		 * @param	int		$height		The height to make the embedded video
		 * @return	string				The embed code
		 */
		public function getEmbedCode(int $width = 640, int $height = 360): string
		{
			// Horizontal video has an aspect ratio of 16:9, so assume that we need to create an iframe of that size
			$base = parent::getEmbedCode($width, round($width * .5625));
			
			return "<div style='align-items: center; background: #000; display: flex; height: {$height}px; width: {$width}px;'>{$base}</div>";
		}
		
		/**
		 * Returns the url for a thumbnail image for this video
		 * @param	int		$size	One of the size constants
		 * @return	string			The URL to the image
		 */
		public function getThumbnailUrl(int $size = self::MEDIUM): string
		{
			// Thumbnails not supported by Facebook videos
			return "";
		}
	}
