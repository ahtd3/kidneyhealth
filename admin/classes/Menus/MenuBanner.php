<?php
	namespace Menus;

	use Template\Banners\SiteBanner;
	
	/**
	 * Class for managing Menu banners
	 */
	class MenuBanner extends SiteBanner
	{
		// SiteBanner
		const PARENT_CLASS = Menu::class;

		const DO_CAPTION = parent::DO_CAPTION;
		const DO_TITLE = parent::DO_TITLE;
		const PARENT_TITLE_PROPERTY = 'name';
		const DO_TEXT = parent::DO_TEXT;
		const DO_LINK = parent::DO_LINK;
		const DO_BUTTON = parent::DO_BUTTON;
	}
