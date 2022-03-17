<?php
	/**
	 * A Sitemap Item contributes to the generated sitemap
	 */
	interface SitemapItem
	{
		/**
		 * return paths to active pages for inclusion in sitemap
		 *
		 *	@param mixed $parent usually a Generator of some sort
		 *
		 * @return string[]
		 */
		static function getSitemapUrls($parent = null);
	}
