<?php
	namespace Orders;

	use Cart\Cart;
	use Cart\CartLineItemLink;
	use Configuration\Registry;

	use Core\Entity;
	use Core\Properties\LinkFromProperty;
	use Core\Properties\Property;
	use Files\Image;
	use Payments\Payment;
	
	/**
	 * A Line Item represents one item in a cart or order
	 */
	class LineItem extends Entity
	{
		const TABLE = "line_items";
		const ID_FIELD = "line_item_id";
		const HAS_AUTOCAST = true;

		public $title = "";
		public $quantity = 0;
		public $displayQuantity = true;
		public $displayValue = null;//can be displayed instead of price for special line items eg shipping

		// Note: This is the price for each item, not the total
		public $price = 0;
		public $generatorClassIdentifier = "";
		public $generatorIdentifier = "";
		public $total = 0;
		public $identifier = "";
		public $itemWeight = 0;//individual item weight, not total

		/** @var Image */
		public $image = null;

		/** @var string */
		public $link = null;

		/** @var CartLineItemLink */
		public $cartLineItemLink = null;

		/** @var OrderLineItemLink */
		public $orderLineItemLink = null;
		
		public $options = [];

		public $temporaryId = "";

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("title", "title", "string"));
			static::addProperty(new Property("quantity", "quantity", "int"));
			static::addProperty(new Property("displayQuantity", "display_quantity", "bool"));
			static::addProperty(new Property("price", "price", "float"));
			static::addProperty(new Property("generatorClassIdentifier", "generator_class_identifier", "string"));
			static::addProperty(new Property("generatorIdentifier", "generator_identifier", "string"));
			static::addProperty(new Property("identifier"));
			static::addProperty(new Property("total"));
			static::addProperty(new Property("image"));
			static::addProperty(new Property("link"));
			static::addProperty(new Property("displayValue"));
			static::addProperty(new LinkFromProperty("cartLineItemLink", CartLineItemLink::class, "lineItem"));
			static::addProperty(new LinkFromProperty("orderLineItemLink", OrderLineItemLink::class, "lineItem"));
			static::addProperty(new Property("itemWeight", "item_weight", "float"));
		}

		/**
		 * Gets all the Line Items that belong to a specific cart
		 * @param	Cart		$cart	The cart the line items belong to
		 * @return	static[]			All the line items belonging to that cart
		 */
		public static function loadAllForCart(Cart $cart)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "INNER JOIN ~CartLineItemLink "
				. "ON ~CartLineItemLink.~lineItem = ~id "
				. "WHERE ~CartLineItemLink.~cart = ?";

			return static::makeMany($query, [$cart->id]);
		}

		/**
		 * Creates a new Object and sets any properties that need default objects
		 */
		public function __construct()
		{
			parent::__construct();

			$this->temporaryId = uniqid();
		}

		/**
		 * Some functionality (eg stock update) to happen after a transaction has been completed, eg Order has been placed, BillPayment has been made.
		 * To be overridden in child classes
		 * 
		 * @param Payment	$payment	Some functionality may depend on the payment status, eg stock wont be decreased if the order was made with some kind of defered payment method (eg BankDeposit)
		 */
		public function onPurchase(Payment $payment) 
		{
			//do nothing by default
		}

		/**

		 * Gets the original generating object for this line item
		 * @return	LineItemGenerator	The generating object, or null if one can't be found
		 */
		public function getGenerator()
		{
			foreach(Registry::LINE_ITEM_GENERATING_CLASSES as $lineItemGeneratingClass)
			{
				if($lineItemGeneratingClass::getClassLineItemGeneratorIdentifier() === $this->generatorClassIdentifier)
				{
					return $lineItemGeneratingClass::loadForLineItemGeneratorIdentifier($this->generatorIdentifier);
				}
			}

			return null;
		}

		/**
		 * Gets an updated line item for this line item
		 * @return	LineItem	The updated line item, or null if it no longer exists
		 */
		public function getUpdated()
		{
			foreach(Registry::LINE_ITEM_GENERATING_CLASSES as $lineItemGeneratingClass)
			{
				if($lineItemGeneratingClass::getClassLineItemGeneratorIdentifier() === $this->generatorClassIdentifier)
				{
					return $lineItemGeneratingClass::updateLineItem($this->generatorIdentifier, $this);
				}
			}

			return null;
		}
		
		/**
		 * Gets the total weight of this line item
		 * @return	float|int	The total weight
		 */
		public function getWeight() 
		{
			return $this->quantity * $this->itemWeight;
		}

		/**
		 * Gets the total price for this line item
		 * @return	float	The total price
		 */
		public function get_total()
		{
			return $this->quantity * $this->price;
		}

		/**
		 * Gets a unique identifier for this line item
		 * @return	string	The unique identifier
		 */
		public function get_identifier()
		{
			return $this->id ?? $this->temporaryId;
		}

		/**
		 * Gets an image for this line item
		 * @return	Image	The image to display for this line item, or null if there is none
		 */
		public function get_image()
		{
			$generator = $this->getGenerator();
			return $generator->getLineItemImage();
		}

		/**
		 * Gets a link for this line item to the original item
		 * @return	string	A link to the original item, or null if there is none
		 */
		public function get_link()
		{
			$generator = $this->getGenerator();
			
			if ($generator !== null) 
			{
				return $generator->getLineItemLink();
			}
			
			return null;
		}
		
		/**
		 * Runs before this entity is deleted
		 */
		public function beforeDelete()
		{
			parent::beforeDelete();
			
			if(!$this->cartLineItemLink->isNull())
			{
				$this->cartLineItemLink->delete();
			}
			
			if(!$this->orderLineItemLink->isNull())
			{
				$this->orderLineItemLink->delete();
			}

		}

		/**
		 * Specifies what data should be serialised when json_encode is called
		 * @return    array    Name/data pairs for the data in this object
		 */
		public function jsonSerialize(): array
		{
			return
			[
				"id" => $this->id ?? $this->temporaryId,
				"quantity" => $this->quantity,
				"name" => $this->title,
				"price" => formatPrice($this->total),
				"image" => $this->image !== null ? $this->image->getLink() : null
			];
		}
	}