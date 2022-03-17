<?php
	namespace Pages;

	use Template\Banners\SiteBanner;
	
	/**
	 * Class for managing Page -> Slideshow information relationships
	 */
	class PageBanner extends SiteBanner
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;

		// SiteBanner
		const PARENT_CLASS = Page::class;

		const DO_CAPTION = parent::DO_CAPTION;
		const DO_TITLE = parent::DO_TITLE;
		const PARENT_TITLE_PROPERTY = 'mainHeading';
		const DO_TEXT = parent::DO_TEXT;
		const DO_LINK = parent::DO_LINK;
		const DO_BUTTON = parent::DO_BUTTON;
	}
