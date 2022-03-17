<?php
	namespace Template\Banners;

	use Files\Image;
	
	/**
	 * A banner is an image which displays in the template, usually full width at the top or bottom of a page
	 * In this implementation banners may have up to two images for display under different conditions
	 */
	interface Banner
	{
		/**
		 * Gets the caption for this banner
		 * @return	string	The caption for this banner
		 */
		public function getCaption(): string;

		/**
		 * Gets the image for this banner
		 * @return	Image	The image for this banner
		 */
		public function getLargeImage(): ?Image;

		/**
		 * Gets the responsive image for this banner
		 *
		 * Can always return null. Template uses this to just output non-responsive code without caring
		 * why there is only one image (not enabled or not uploaded)
		 *
		 * @return    Image    The smaller image for this banner
		 */
		public function getSmallImage(): ?Image;
	}