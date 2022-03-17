<?php
	namespace Template\Banners;
	
	/**
	 * An object which implements BannerSource may have a banner or banners which display in the template
	 * usually in the header
	 */
	interface BannerSource
	{
		/**
		 * Gets the banners to display for this object. If the object doesn't have a banner uploaded 
		 * it may fall back to a parent's banner/s or return an empty array
		 *
		 * @return	Banner[]			Banners for this Generator object
		 */
		public function getVisibleBanners();
	}