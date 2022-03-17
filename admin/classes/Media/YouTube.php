<?php
	namespace Media;

	/**
	 * Converts URLs into YouTube embeds
	 */
	class YouTube extends StreamingVideo
	{
		const FULL = 4;

		// Automatically play the video on page load
		public $autoplay = false;

		// Turn on closed captions, even if the user has them off by default
		public $forceClosedCaptions = false;

		// Sets the progress bar to white, turning this on disables $modestBranding
		public $whiteProgressBar = false;

		// Whether the video controls are displayed
		public $displayControls = true;

		// Disable controlling the video with the keyboard
		public $disableKeyboardControls = false;

		// Whether the video should be controllable from API calls
		public $enableJavaScriptApi = false;

		// The point to end the video at, this is the time in the video, not the number of seconds after $startSeconds
		public $endSeconds = null;

		// Whether the user can view the video in full screen
		public $enableFullScreen = true;

		// ISO 639-1 two-letter language code to change the interface to
		public $forceInterfaceLanguage = null;

		// Start with annotations turned off
		public $turnOffAnnotations = false;

		// Loop the video
		public $loop = false;

		// Hides the YouTube logo most of the time
		public $modestBranding = false;

		// Security setting, only used in conjunction with $enableJavaScriptApi
		public $origin = PROTOCOL . SITE_ROOT;

		// Automatically displays in full screen on iOS
		public $automaticFullScreenOnIos = true;

		// Shows related videos once the video has finished
		// As of September 25, 2018 this no longer stops videos from showing at all.
		// It now just changes the related videos to be from the same chanel if it's set to false.
		public $showRelatedVideos = true;

		// Shows the video title and uploader before the video starts
		public $showInfo = true;

		// The point to start the video at. Imprecise, as it starts at the closest keyframe before the time, which may be a couple of seconds earlier
		public $startSeconds = null;

		public $mute = false;
		
		/**
		 * Gets the URL for the embed code
		 * @return	string	The embed URL
		 */
		public function getEmbedUrl(): string
		{
			$parameters =
			[
				"autoplay" => (int) $this->autoplay,
				"cc_load_policy" => (int) $this->forceClosedCaptions,
				"color" => $this->whiteProgressBar ? "white" : "red",
				"controls" => (int) $this->displayControls,
				"disablekb" => (int) $this->disableKeyboardControls,
				"enablejsapi" => (int) $this->enableJavaScriptApi,
				"fs" => (int) $this->enableFullScreen,
				"iv_load_policy" => $this->turnOffAnnotations ? 3 : 1,
				"loop" => (int) $this->loop,
				"modestbranding" => (int) $this->modestBranding,
				"origin" => $this->origin,
				"playsinline" => (int) $this->automaticFullScreenOnIos,
				"rel" => (int) $this->showRelatedVideos,
				"showinfo" => (int) $this->showInfo,
				"mute" => (int) $this->mute
			];

			if ($this->loop)
			{
				$parameters["playlist"] = $this->getIdentifier();
			}

			if($this->endSeconds !== null)
			{
				$parameters["end"] = $this->endSeconds;
			}

			if($this->forceInterfaceLanguage !== null)
			{
				$parameters["hl"] = $this->forceInterfaceLanguage;
			}

			if($this->startSeconds !== null)
			{
				$parameters["start"] = $this->startSeconds;
			}

			$query = http_build_query($parameters);

			return "https://www.youtube.com/embed/" . $this->getIdentifier() . "?" . $query;
		}

		/**
		 * Returns the url for a thumbnail image for this video
		 * @param	int		$size	One of the size constants
		 * @return	string			The URL to the image
		 */
		public function getThumbnailUrl(int $size = self::MEDIUM): string
		{
			$scale = "mqdefault.jpg";

			switch($size)
			{
				case self::SMALL:
					$scale = "default.jpg";
					break;

				case self::MEDIUM:
					$scale = "mqdefault.jpg";
					break;

				case self::LARGE:
					$scale = "hqdefault.jpg";
					break;

				case self::FULL:
					$scale = "maxresdefault.jpg";
					break;
			}

			return "https://img.youtube.com/vi/" . $this->getIdentifier() . "/" . $scale;
		}
	}
