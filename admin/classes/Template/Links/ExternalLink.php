<?php
	namespace Template\Links;

	/**
	 * An external link always opens in a new tab
	 * Although this generator /can/ be used directly you should really extend it for your module.
	 */
	class ExternalLink extends AbstractLink
	{
		/*~~~~~
		 * setup
		 **/

		// AbstractLink

		/** @var bool */
		const CUSTOMISABLE_OPEN = false;

		/** @var bool */
		public $newTab = true;
	}
