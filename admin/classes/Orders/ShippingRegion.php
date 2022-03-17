<?php
	namespace Orders;

	use Cart\Cart;
	use Configuration\Configuration;
	use Configuration\Registry;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Checkbox;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\Property;
	use Exception;
	use Files\Image;

	/**
	 * A region the customer might wish to ship to
	 */
	class ShippingRegion extends Generator implements LineItemGenerator
	{
		const TABLE = "shipping_regions";
		const ID_FIELD = "shipping_region_id";
		const SINGULAR = "Region";
		const PLURAL = "Regions";
		const LABEL_PROPERTY = "name";
		const HAS_POSITION = true;
		const HAS_ACTIVE = true;

		public $name = "";
		public $cost = 0;
		public $isWeightBased = false;
		public $weightRanges = null;
		private $_activeWeightRanges = null;
		public $activeWeightRanges = null;

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new Property("cost", "cost", "float"));
			static::addProperty(new Property("isWeightBased", "weight_based", "bool"));
			static::addProperty(new LinkFromMultipleProperty("weightRanges", WeightRange::class, "shippingRegion", ['upTo' => true]));
			static::addProperty(new Property("activeWeightRanges"));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn("name", "Name"));

			parent::columns();
		}

		/**
		 * Adds some HTML to display before the admin table
		 * @return	string	The HTML to display before the table
		 */
		public static function beforeTable(): string
		{
			$html = '';

			if (Registry::SHIPPING)
			{
				$html .= '<div class="free-shipping-threshold-form">';
					$html .= '<form action="/admin/action/edit-config/" method="post">';
						$html .= '<label>';
							$html .= '<span class="label">Free Shipping Above: </span>';
								$html .= ' $<input name="freeShippingThreshold" type="text" value="' . Configuration::acquire()->freeShippingThreshold . '" />';
							$html .= '</label>';
							$html .= '<input class="button" type="submit" value="Update" />';
							$html .= '<small>($0 to disable)</small>';
					$html .= '</form>';
				$html .= '</div>';
			}

			return $html;
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement(new Text("name", "Name"));

			if (Registry::WEIGHT_BASED_SHIPPING)
			{
				$this->addElement(new Checkbox("isWeightBased", "Weight Based"));
				$this->addElement((new Text('cost', 'Cost'))->addClass('currency')->setConditional("return !isWeightBased"));
				$this->addElement((new GeneratorElement('weightRanges', 'Weight Ranges'))->setConditional("return isWeightBased"));
			}
			else
			{
				$this->addElement((new Text('cost', 'Cost'))->addClass('currency'));
			}
		}

		/**
		 * Gets the active weight ranges
		 * @return	WeightRange[]
		 */
		public function get_activeWeightRanges()
		{
			if ($this->_activeWeightRanges === null)
			{
				$this->_activeWeightRanges = WeightRange::loadAllForMultiple(['active' => true, 'shippingRegion' => $this], ['upTo' => true]);
			}
			return $this->_activeWeightRanges;
		}

		/**
		 * Whether this is weight brased
		 * @return	bool
		 */
		public function get_isWeightBased()
		{
			return Registry::WEIGHT_BASED_SHIPPING ? $this->getValue('isWeightBased') : false;
		}

		/**
		 * For LineItem generation only
		 * Gets the costs of this region based on the weight of the current cart
		 * @return Float	$cost	The shipping cost of this region
		 */
		public function getCost()
		{
			$cart = Cart::get();

			if ($this->isWeightBased && Registry::WEIGHT_BASED_SHIPPING)
			{
				$range = $this->getApplicableWeightRange($cart->getWeight());

				if ($range != null)
				{
					return $range->cost;
				}
				else
				{
					throw new Exception();
				}
			}
			else
			{
				return $this->cost;
			}
		}

		/**
		 * Gets the weight range that a given weight falls into
		 * @param	Float 				$weight			the weight of a cart
		 * @return 	WeightRange|null	$weightRange	the weight range that the weight falls into, or null if it's too heavy to fit into any
		 */
		public function getApplicableWeightRange($weight)
		{
			$ranges = array_reverse($this->activeWeightRanges);

			foreach ($ranges as $index => $weightRange) //heaviest range first
			{
				if ($weight > $weightRange->upTo)
				{
					if ($index === 0)
					{
						return null;//heavier than any of them
					}
					else
					{
						return $ranges[$index - 1];
					}
				}
				else if ($weight <= $weightRange->upTo && $index + 1 === count($ranges))
				{
					//last weight range
					return $weightRange;
				}
			}

			return null;
		}

		/**
		 * Gets the maximum weight for this shipping region
		 * @return	int		The maximum weight
		 */
		public function maxWeight()
		{
			if (count($this->activeWeightRanges) > 0)
			{
				return array_reverse($this->activeWeightRanges)[0]->upTo;
			}
			return 0;
		}

		//region LineItemGenerator

		/**
		 * Gets a string that will identify this uniquely identify this class from other Line Item Generators
		 * @return    string    The identifier
		 */
		public static function getClassLineItemGeneratorIdentifier(): string
		{
			return "ShippingRegion";
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param    string $identifier The identifier that will identify a Line Item Generator
		 * @return    LineItemGenerator                    The original object that generated this Line Item, or null if such cannot be found
		 */
		public static function loadForLineItemGeneratorIdentifier($identifier): ?LineItemGenerator
		{
			return static::load($identifier);
		}

		/**
		 * Updates, replaces or deletes an existing Line Item
		 * @param	string		$identifier		The identifier that will identify the Line Item Generator
		 * @param	LineItem	$current		The line item to update
		 * @return	LineItem					The updated line item, or null if it's been removed
		 */
		public static function updateLineItem($identifier, LineItem $current): ?LineItem
		{
			$object = static::load($identifier);

			if($object->isNull() || !$object->active)
			{
				return null;
			}

			$current->price = $object->cost;

			return $current;
		}

		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getLineItemGeneratorIdentifier(): string
		{
			// can be null, needs to be cast to string to avoid type error
			return (string) $this->id;
		}

		/**
		 * Gets a Line Item from this object. The quantity, generatorClassIdentifier and generatorIdentifier will be filled in after you return the line item
		 * @return    LineItem    The generated line item
		 */
		public function getLineItem(): LineItem
		{
			$lineItem = new LineItem();
			$lineItem->quantity = 1;

			try
			{
				$lineItem->price = $this->getCost();
			}
			catch(Exception $exception)
			{
				$lineItem->price = 0;
				$lineItem->displayValue = "N/A";
			}

			$lineItem->title = sprintf("Shipping (%s)", $this->name);
			$lineItem->displayQuantity = false;

			return $lineItem;
		}

		//endregion

		/**
		 * Gets a representative thumbnail image for this Line Item Generator, may return null
		 * @return    Image    The representative image
		 */
		public function getLineItemImage(): ?Image
		{
			return null;
		}

		/**
		 * Gets a link to this Line Item Generator on the site, may return null
		 * @return    string    A link to view this item on the site
		 */
		public function getLineItemLink(): ?string
		{
			return null;
		}

		/**
		 * Gets a link to edit this Line Item Generator in the admin, may return null
		 * @return    string    The link to edit this generator in the admin panel
		 */
		public function getLineItemEditLink(): ?string
		{
			return $this->getEditLink();
		}

		/**
		 * Get a line item to display free shipping
		 *
		 * @return    LineItem    Free Shipping line item
		 */
		public static function getFreeShippingItem()
		{
			$shipping = new static;
			$shipping->name = 'Free over ' . formatPrice(Configuration::acquire()->freeShippingThreshold);
			$shipping->cost = 0;
			return $shipping->getLineItem();
		}
	}