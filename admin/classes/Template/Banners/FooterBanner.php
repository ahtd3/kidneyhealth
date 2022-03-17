<?php
	namespace Template\Banners;

	use Configuration\Configuration;
	
	/**
	 * Class for a default Footer page banner (single slide) as set in Configuration
	 *	- these often appear behind a block of testimonials or some call to action
	 */
	class FooterBanner extends SiteBanner
	{
		// Entity / Generator
		const SINGULAR = "Footer banner";
		const PLURAL = "Banners";

		const PARENT_CLASS = Configuration::class;

		// SiteBanner
		const ONE_OF_MANY = false;
		
		const DESKTOP_IMAGE_HEIGHT = 840; // 2560 / 1900 * 624
		const RESPONSIVE_IMAGE_HEIGHT = 780; // 2560 / 1900 * 579

		// depending on the site the content as well as the image may or may not be defined in Configuration
		const PARENT_TITLE_PROPERTY = '';
		const DO_CAPTION = false;
		const DO_TITLE = false;
		const DO_TEXT = false;
		const DO_LINK = false;
		const DO_BUTTON = false;
	}
