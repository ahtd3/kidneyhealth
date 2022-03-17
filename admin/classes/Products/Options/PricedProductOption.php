<?php
	namespace Products\Options;

	use Configuration\Registry;

	use Core\Elements\Text;

	use Core\Properties\Property;

	use Exception;
	use Files\Image;

	use Orders\LineItem;
	use Orders\LineItemGenerator;

	use Products\Product;

	/**
	 * An option with a price
	 */
	class PricedProductOption extends ProductOption implements LineItemGenerator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity/Generator
		const TABLE = "priced_product_options";

		// ProductOption
		const PARENT_CLASS_TYPE = PricedOptionGroup::class;

		/** @var float */
		public $price = 0;
		public $weight = 0;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("price", "price", "float"));
			static::addProperty(new Property("weight", "weight", "float"));
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement((new Text("price", "Price"))->addClass('currency'));

			if (Registry::WEIGHT_BASED_SHIPPING)
			{
				$this->addElement((new Text("weight", "Weight"))->setHint('g'));
			}
		}

		/**
		 * @return float the price after accounting for any conditions like product on sale or type of user logged in
		 */
		public function getPrice()
		{
			return $this->price;
		}

		/*~~~~~
		 * Interface methods
		 **/
		//region LineItemGenerator

		/**
		 * Gets a string that will identify this uniquely identify this class from other Line Item Generators
		 * @return    string    The identifier
		 */
		public static function getClassLineItemGeneratorIdentifier(): string
		{
			return "PricedProductOption";
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param    string $identifier The identifier that will identify a Line Item Generator
		 * @return    LineItemGenerator                    The original object that generated this Line Item, or null if such cannot be found
		 */
		public static function loadForLineItemGeneratorIdentifier($identifier): ?LineItemGenerator
		{
			$option = static::load($identifier);

			if($option->isNull())
			{
				return null;
			}

			return $option;
		}

		/**
		 * Updates, replaces or deletes an existing Line Item
		 * Basically this is intended to check if the line item generator is still current (active, still exists etc)
		 * And update to whatever the current line item generator is like (price)
		 * @param    string   $identifier The identifier that will identify the Line Item Generator
		 * @param    LineItem $current    The line item to update
		 * @return    LineItem                    The updated line item, or null if it's been removed
		 */
		public static function updateLineItem($identifier, LineItem $current): ?LineItem
		{
			/** @var PricedProductOption $option */
			$option = static::loadForLineItemGeneratorIdentifier($identifier);

			if($option === null)
			{
				return null;
			}

			$updatedItem = Product::updateLineItem($option->group->product->id, $current);

			if($updatedItem !== null)
			{
				$updatedItem->title .= " ~ " . $option->name;
				$updatedItem->price = $option->getPrice();
				$updatedItem->itemWeight = $option->weight;
			}

			return $updatedItem;
		}

		/**
		 * Gets a Line Item from this object. The quantity, parentClassIdentifier and parentIdentifier will be filled in after you return the line item
		 * @return    LineItem    The generated line item
		 * @throws    Exception    If a line item cannot be generated, for whatever reason
		 */
		public function getLineItem(): LineItem
		{
			$lineItem = $this->group->product->getLineItem();
			$lineItem->title .= " ~ " . $this->name;
			$lineItem->price = $this->getPrice();
			$lineItem->itemWeight = $this->weight;

			return $lineItem;
		}

		/**
		 * Gets a link to edit this Line Item Generator in the admin, may return null
		 * @return    string    The link to edit this generator in the admin panel
		 */
		public function getLineItemEditLink(): ?string
		{
			return $this->group->product->getLineItemEditLink();
		}

		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getLineItemGeneratorIdentifier(): string
		{
			return $this->id;
		}

		/**
		 * Gets a representative thumbnail image for this Line Item Generator, may return null
		 * @return    Image    The representative image
		 */
		public function getLineItemImage(): ?Image
		{
			return $this->group->product->getLineItemImage();
		}

		/**
		 * Gets a link to this Line Item Generator on the site, may return null
		 * @return    string    A link to view this item on the site
		 */
		public function getLineItemLink(): ?string
		{
			return $this->group->product->getLineItemLink();
		}

		//endregion
	}