<?php
	namespace Products\Options;

	use Products\Product;
	
	/**
	 * Contains options with prices, the product is expected to only have one of these
	 */
	class PricedOptionGroup extends OptionGroup
	{
		/*~~~~~
		 * setup
		 **/
		// OptionGroup
		const CHILD_CLASS_TYPE = PricedProductOption::class;

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Gets the products for this priced option group
		 * @return	Product	The product
		 */
		public function get_product()
		{
			return Product::loadFor("pricedOptionGroup", $this);
		}
	}
