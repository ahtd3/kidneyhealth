<?php
	namespace Template\Banners;

	use Core\Generator;
	use Pages\Page;
	
	/**
	 * Class for a default page banner (single slide) as set in Configuration
	 */
	class DefaultBanner extends SiteBanner
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const SINGULAR = "Default banner";
		const PLURAL = "Banners";

		// SiteBanner
		const ONE_OF_MANY = false;
		const PARENT_CLASS = Page::class;
		const PARENT_TITLE_PROPERTY = parent::PARENT_TITLE_PROPERTY;

		// usually the only thing we will want to be able to set in Configuration are the images
		const DO_CAPTION = false;
		const DO_TITLE = false;
		const DO_TEXT = false;
		const DO_LINK = false;
		const DO_BUTTON = false;

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * if content from the parent (usually content title) displays in the banner we need to
		 * temporarily assign the parent to the banner
		 *
		 * @param Generator $parent
		 */
		public function setContentParent(Generator $parent)
		{
			$this->parent = $parent;
		}
	}
