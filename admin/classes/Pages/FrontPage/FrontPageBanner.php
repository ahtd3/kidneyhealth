<?php
	namespace Pages\FrontPage;

	use Pages\PageBanner;
	
	/**
	 * Banners / slides for the front page of the site
	 */
	class FrontPageBanner extends PageBanner
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		public $class = self::class;

		// SiteBanner
		// const DESKTOP_IMAGE_HEIGHT = 1064; // // 2560 / [design canvas width] * [design banner height]
		const PARENT_CLASS = FrontPage::class;
		
		const DO_CAPTION = parent::DO_CAPTION;
		const DO_TITLE = parent::DO_TITLE;
		const PARENT_TITLE_PROPERTY = parent::PARENT_TITLE_PROPERTY;
		const DO_TEXT = parent::DO_TEXT;
		const DO_LINK = parent::DO_LINK;
		const DO_BUTTON = parent::DO_BUTTON;
	}
