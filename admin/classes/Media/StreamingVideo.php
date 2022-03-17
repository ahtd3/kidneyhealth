<?php
	namespace Media;

	/**
	 * Converts URLs into streaming video embeds
	 */
	abstract class StreamingVideo
	{
		const SMALL = 0;
		const MEDIUM = 1;
		const LARGE = 2;

		/** @var	string	$url	The URL for this video */
		private $url = "";

		/** @var	string	$identifier		The identifier for this video */
		private $identifier = null;

		/**
		 * Gets the type of this video from a URL
		 * @param	string							$url	The URL to the YouTube or Vimeo video
		 * @return	class-string<StreamingVideo>			The class name for either Vimeo or YouTube
		 */
		public static function getVideoType(string $url)
		{
			if(stripos($url, "facebook") !== false)
			{
				return Facebook::class;
			}
			else if(stripos($url, "vimeo") !== false)
			{
				return Vimeo::class;
			}
			else // YouTube has more than one domain, just default to this
			{
				return YouTube::class;
			}
		}

		/**
		 * Extracts the video identifier from a URL
		 * @param	string	$url	The URL to the YouTube or Vimeo video
		 * @return	string			The identifier for that video
		 */
		public static function getVideoId(string $url): string
		{
			$urlData = parse_url($url);

			if(isset($urlData["query"]))
			{
				$queryData = [];
				parse_str($urlData["query"], $queryData);

				if(isset($queryData["v"]))
				{
					return $queryData["v"];
				}
			}

			$segments = explode("/", $urlData["path"]);
			return $segments[count($segments) - 1];
		}

		/**
		 * Generates a Streaming Video from a URL
		 * @param	string			$url	The URL to the video
		 * @return	StreamingVideo			The appropriate Streaming Video class
		 */
		public static function get(string $url): StreamingVideo
		{
			$class = self::getVideoType($url);

			return new $class($url);
		}

		/**
		 * Runs after a new Streaming Video is constructed
		 * @param	string	$url	The URL to the video
		 */
		public function __construct(string $url)
		{
			$this->url = $url;
		}

		/**
		 * Gets the URL for this Streaming video
		 * @return	string	The URL for the video
		 */
		public function getUrl(): string
		{
			return $this->url;
		}

		/**
		 * Gets the identifier for this Streaming video
		 * @return	string	The identifier for the video
		 */
		public function getIdentifier(): string
		{
			if($this->identifier === null)
			{
				$this->identifier = self::getVideoId($this->url);
			}

			return $this->identifier;
		}

		/**
		 * Gets the URL for the embed code
		 * @return	string	The embed URL
		 */
		abstract public function getEmbedUrl(): string;

		/**
		 * Retrieves the embed code for this Streaming Video
		 * @param	int		$width		The width to make the embedded video
		 * @param	int		$height		The height to make the embedded video
		 * @return	string				The embed code
		 */
		public function getEmbedCode(int $width = 640, int $height = 360): string
		{
			return "<iframe width='" . $width . "' height='" . $height . "' src='" . $this->getEmbedUrl() . "' allow='autoplay' allowfullscreen='allowfullscreen' webkitallowfullscreen='webkitallowfullscreen' mozallowfullscreen='mozallowfullscreen' style='border: 0'></iframe>";
		}

		/**
		 * Returns the url for a thumbnail image for this video
		 * @param	int		$size	One of the size constants
		 * @return	string			The URL to the image
		 */
		abstract public function getThumbnailUrl(int $size = self::MEDIUM): string;
	}
