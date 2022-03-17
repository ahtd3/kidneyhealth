<?php
	namespace Cart;

	use Core\Attributes\Data;
	use Configuration\Registry;
	use Core\Columns\CustomColumn;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Date;
	use Core\Elements\FormOption;
	use Core\Elements\Radio;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\Property;
	use DateTimeImmutable;
	use Exception;
	use Files\Image;
	use Orders\LineItem;
	use Orders\LineItemGenerator;
	
	/**
	 * Discounts get applied to the cart
	 */
	class Discount extends Generator implements LineItemGenerator
	{
		const TABLE = "discounts";
		const ID_FIELD = "discount_id";
		const SINGULAR = "Discount";
		const PLURAL = "Discounts";
		const LABEL_PROPERTY = "code";
		const HAS_ACTIVE = true;

		const PERCENTAGE = "percentage";
		const DOLLAR_AMOUNT = "dollar";
		const FREE_SHIPPING = "free shipping";

		#[Data("code")]
		public string $code = "";
		
		#[Data("amount")]
		public float $amount = 0;
		
		#[Data("type")]
		public string $type = self::PERCENTAGE;
		
		#[Data("start", "date")]
		public DateTimeImmutable|null $start = null;
		
		#[Data("finish", "date")]
		public DateTimeImmutable|null $finish = null;

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn("code", "Code"));

			static::addColumn(new CustomColumn("amount", "Amount", function(Discount $discount)
			{
				if($discount->type === static::PERCENTAGE)
				{
					return $discount->amount . "%";
				}
				else if($discount->type === static::FREE_SHIPPING)
				{
					return 'Free Shipping';
				}
				else
				{
					return formatPrice($discount->amount);
				}
			}));

			static::addColumn(new CustomColumn("start", "Start", function(Discount $discount)
			{
				return $discount->start === null ? "-" : $discount->start->format("j F, Y");
			}));

			static::addColumn(new CustomColumn("finish", "Finish", function(Discount $discount)
			{
				return $discount->finish === null ? "-" : $discount->finish->format("j F, Y");
			}));

			parent::columns();
		}

		/**
		 * Creates a new Object and sets any properties that need default objects
		 */
		public function __construct()
		{
			parent::__construct();

			$this->start = null;
			$this->finish = null;
		}

		/**
		 * Sets the Form Elements for this object
		 */
		public function elements()
		{
			parent::elements();
			
			$freeShipping = json_encode(static::FREE_SHIPPING);

			$this->addElement(new Text("code", "Code"));

			$typeOptions =
			[
				new FormOption("Percentage", static::PERCENTAGE),
				new FormOption("Dollar Amount", static::DOLLAR_AMOUNT)
			];
			
			if (Registry::SHIPPING)
			{
				$typeOptions[] = new FormOption("Free Shipping", static::FREE_SHIPPING);
			}

			$this->addElement(new Radio("type", "Discount type", $typeOptions));

			$this->addElement((new Text("amount", "Discount amount"))->setConditional("return type !== {$freeShipping}"));
			$this->addElement((new Date("start", "Start"))->setAllowNull(true));
			$this->addElement((new Date("finish", "Finish"))->setAllowNull(true));
		}

		//region LineItemGenerator

		/**
		 * Gets a string that will identify this uniquely identify this class from other Line Item Generators
		 * @return    string    The identifier
		 */
		public static function getClassLineItemGeneratorIdentifier(): string
		{
			return "Discount";
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param    string $identifier The identifier that will identify a Line Item Generator
		 * @return    LineItemGenerator                    The original object that generated this Line Item, or null if such cannot be found
		 */
		public static function loadForLineItemGeneratorIdentifier($identifier): ?LineItemGenerator
		{
			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "WHERE ~code = ? " // Matching the line item identifier
				   . "AND ~active = TRUE " // Is active
				   . "AND "
				   . "( "
				   .	"~start <= CURDATE() "
				   .	"OR ~start IS NULL " // Where the start date is not applicable or already gone
				   . ") "
				   . "AND "
				   . "( "
				   .	"~finish >= CURDATE() "
				   .	"OR ~finish IS NULL " // And the end date is not applicable, or not yet been
				   . ") "
				   . "LIMIT 1";
			
			return static::makeOne($query, [$identifier]);
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
			$discount = static::loadForLineItemGeneratorIdentifier($identifier);

			if($discount === null)
			{
				return null;
			}

			try
			{
				return $discount->getLineItem();
			}
			catch(Exception $exception)
			{
				addMessage("An error occurred while applying the discount to your cart");
				return null;
			}
		}

		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getLineItemGeneratorIdentifier(): string
		{
			return $this->code;
		}

		/**
		 * Gets a Line Item from this object. The quantity, parentClassIdentifier and parentIdentifier will be filled in after you return the line item
		 * @return    LineItem    The generated line item
		 * @throws    Exception    If a line item cannot be generated, for whatever reason
		 */
		public function getLineItem(): LineItem
		{
			$subtotal = Cart::get()->subtotal;
			$lineItem = new LineItem();
			$lineItem->identifier = $this->code;
			$lineItem->title = "Discount (" . $this->code . ")";
			$lineItem->displayQuantity = false;

			if($this->type === static::PERCENTAGE)
			{
				$price = $subtotal * ($this->amount / 100);
			}
			else if($this->type === static::FREE_SHIPPING)
			{
				if (Cart::get()->shippingRegion === null || !Cart::get()->shippingRegion->active)
				{
					$price = 0;
				}
				else 
				{
					$price = Cart::get()->shippingRegion->getLineItem()->price;
				}
			}
			else
			{
				// Make sure we never take off more than the cart subtotal
				$price = min($subtotal, $this->amount);
			}

			// Negative, so that it will remove the price from the cart
			$lineItem->price = -$price;

			return $lineItem;
		}

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

		//endregion
	}
