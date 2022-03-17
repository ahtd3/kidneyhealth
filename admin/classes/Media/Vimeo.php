<?php
	namespace Media;

	/**
	 * Converts URLs into Vimeo embeds
	 */
	class Vimeo extends StreamingVideo
	{
		// Whether to automatically play the video on page load
		public $autoplay = false;

		// A hexadecimal colour to use for highlighting. No # symbol
		public $hexColour = "";

		// Whether to loop the video
		public $loop = false;

		// Whether to display the owner's portrait on the pause screen
		public $displayOwnerPortrait = true;

		// Whether to display the title of the video on the pause screen
		public $displayTitle = true;

		// Whether to display the byline (the owner's name) on the pause screen
		public $displayByline = true;

		//by defaul vimeo will only play one video at a time, and pause all others.
		public $autopause = true;

		//Whether play this video as a background video, muted and with no controls, as well as acting like autopause is false
		public $background = false;
		
		
		/**
		 * Gets the URL for the embed code
		 * @return	string	The embed URL
		 */
		public function getEmbedUrl(): string
		{
			$queryString = http_build_query(
			[
				"autoplay" => (int) $this->autoplay,
				"color" => (string) $this->hexColour,
				"loop" => (int) $this->loop,
				"portrait" => (int) $this->displayOwnerPortrait,
				"title" => (int) $this->displayTitle,
				"byline" => (int) $this->displayByline,
				"autopause" => (int) $this->autopause,
				"background" => (int) $this->background
			]);

			return "https://player.vimeo.com/video/" . $this->getIdentifier() . "?" . $queryString;
		}

		/**
		 * Returns the url for a thumbnail image for this video, note that Vimeo calls their API, so multiple requests might be a bad idea
		 * @param	int		$size	One of the size constants
		 * @return	string			The URL to the image
		 */
		public function getThumbnailUrl(int $size = self::MEDIUM): string
		{
			$scale = "thumbnail_medium";

			switch($size)
			{
				case self::SMALL:
					$scale = "thumbnail_small";
					break;

				case self::MEDIUM:
					$scale = "thumbnail_medium";
					break;

				case self::LARGE:
					$scale = "thumbnail_large";
					break;
			}

			$serialisedData = @file_get_contents("https://vimeo.com/api/v2/video/" . $this->getIdentifier() . ".php");

			if($serialisedData === false)
			{
				return "";
			}

			$data = @unserialize($serialisedData);

			if($data === false)
			{
				return "";
			}

			if(!isset($data[0][$scale]))
			{
				return "";
			}

			return $data[0][$scale];
		}
	}
