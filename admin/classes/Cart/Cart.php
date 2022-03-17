<?php
	namespace Cart;

	use Configuration\Configuration;
	use Configuration\Registry;
	use Core\Entity;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkFromProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Exception;
	use Forms\Form;
	use Orders\Address;
	use Orders\LineItem;
	use Orders\LineItemGenerator;
	use Orders\Order;
	use Orders\OrderDetails;
	use Orders\OrderLineItemLink;
	use Orders\ShippingRegion;
	use Payments\PaymentGateway;
	use Users\User;
	
	/**
	 * A Cart contains the Line Items that a user wishes to purchase
	 */
	class Cart extends Entity implements OrderDetails
	{
		const TABLE = "carts";
		const ID_FIELD = "cart_id";
		const SESSION_VARIABLE = "alice_cart";

		/** @var Cart */
		private static $current = null;

		public $paymentGateway = null;
		public $awaitingPayment = false;
		public $sameAddress = true;
		public $subtotal = 0;
		public $total = 0;

		/** @var User */
		public $user = null;

		/** @var ShippingRegion */
		public $shippingRegion = null;

		/** @var Discount */
		public $discount = null;

		/** @var Address */
		public $shippingAddress = null;

		/** @var Address */
		public $billingAddress = null;

		/** @var CartLineItemLink[] */
		public $cartLineItemLinks = null;

		/** @var LineItem[] */
		public $normalLineItems = null;

		/** @var LineItem[] */
		public $specialLineItems = null;

		/** @var LineItem[] */
		public $lineItems = null;

		/**
		 * Sets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("paymentGateway", "payment_gateway", "string"));
			static::addProperty(new Property("sameAddress", "same_address", "bool"));
			static::addProperty(new Property("subtotal"));
			static::addProperty(new Property("total"));
			static::addProperty(new Property("normalLineItems"));
			static::addProperty(new Property("specialLineItems"));
			static::addProperty(new Property("lineItems"));
			static::addProperty(new LinkFromProperty("user", User::class, "cart"));
			static::addProperty(new LinkToProperty("shippingRegion", "shipping_region_id", ShippingRegion::class));
			static::addProperty(new LinkToProperty("discount", "discount_id", Discount::class));
			static::addProperty((new LinkToProperty("shippingAddress", "shipping_address_id", Address::class))->setAutoDelete(true));
			static::addProperty((new LinkToProperty("billingAddress", "billing_address_id", Address::class))->setAutoDelete(true));
			static::addProperty(new LinkFromMultipleProperty("cartLineItemLinks", CartLineItemLink::class, "cart"));
		}

		/**
		 * Gets the current user's cart
		 * @return	static	The cart for the current user
		 */
		public static function get()
		{
			if(self::$current === null)
			{
				$user = User::get();

				if(!$user->isNull())
				{
					self::$current = $user->cart;
				}
				else
				{
					self::$current = $_SESSION[static::SESSION_VARIABLE] ?? new Cart();
				}

				self::$current->updateLineItems();
			}

			return self::$current;
		}

		/**
		 * Creates a new Object and sets any properties that need default objects
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * Adds a line item to this cart
		 * @param	LineItem|LineItemGenerator	$object		Either a Line Item or a Line Item Generator to add to this cart
		 * @param	int							$quantity	The number of items of this type to add
		 * @throws	Exception								If the line item cannot be created
		 */
		public function addItem($object, $quantity)
		{
			if($object instanceof LineItemGenerator)
			{
				assert(in_array(get_class($object), Registry::LINE_ITEM_GENERATING_CLASSES));
				
				$lineItem = $object->getLineItem();
				$lineItem->generatorClassIdentifier = $object::getClassLineItemGeneratorIdentifier();
				$lineItem->generatorIdentifier = $object->getLineItemGeneratorIdentifier();
			}
			else
			{
				$lineItem = $object;
			}

			$lineItem->quantity = $quantity;

			$cartLineItemLink = new CartLineItemLink();
			$cartLineItemLink->cart = $this;
			$cartLineItemLink->lineItem = $lineItem;

			$cartLineItemLinks = $this->cartLineItemLinks;
			$cartLineItemLinks[] = $cartLineItemLink;
			$this->cartLineItemLinks = $cartLineItemLinks;
		}

		/**
		 * Updates the quantity for a line item in this cart, setting it to <= 0 will remove the line item
		 * @param	string	$identifier		The identifier for the line item to update
		 * @param	int		$quantity		The quantity to update to
		 */
		public function updateItem($identifier, $quantity)
		{
			foreach($this->normalLineItems as $lineItem)
			{
				if((string) $lineItem->identifier === (string) $identifier)
				{
					if($quantity >= 0)
					{
						$lineItem->quantity = $quantity;
					}
					else
					{
						$this->removeItem($lineItem);
					}

					break;
				}
			}
		}

		/**
		 * Removes a line item from this cart
		 * @param	string	$identifier		The identifier for the line item to remove
		 */
		public function removeItem($identifier)
		{
			$cartLineItemLinks = $this->cartLineItemLinks;

			foreach($cartLineItemLinks as $index => $cartLineItemLink)
			{
				if((string) $cartLineItemLink->lineItem->identifier === (string) $identifier)
				{
					$cartLineItemLink->delete();
					unset($cartLineItemLinks[$index]);
					break;
				}
			}

			// array_values() will remove any empty indexes we've left behind
			$this->cartLineItemLinks = array_values($cartLineItemLinks);
		}

		/**
		 * Updates all the line items in the cart, making sure they're up to date
		 * Note: Since this deletes existing items in the cart, this will save the cart as well
		 */
		private function updateLineItems()
		{
			$cartLineItemLinks = $this->cartLineItemLinks;

			foreach($this->cartLineItemLinks as $index => $cartLineItemLink)
			{
				$updatedItem = $cartLineItemLink->lineItem->getUpdated();

				// If the item is no longer available
				if($updatedItem === null)
				{
					$cartLineItemLink->lineItem->delete();
					unset($cartLineItemLinks[$index]);
				}
				// If the item has been replaced with another
				else if($updatedItem->id !== $cartLineItemLink->lineItem->id)
				{
					$cartLineItemLink->lineItem->delete();
					$cartLineItemLink = new CartLineItemLink();
					$cartLineItemLink->lineItem = $updatedItem;
					$cartLineItemLink->cart = $this;
					$cartLineItemLinks[$index] = $cartLineItemLink;
				}
			}

			// array_values() will remove any empty indexes we've left behind
			$this->cartLineItemLinks = array_values($cartLineItemLinks);
			$this->save();
		}

		/**
		 * This will merge the items in another cart into this cart
		 * @param	Cart	$cart	The cart to merge with
		 */
		public function mergeWithCart(Cart $cart)
		{
			foreach($cart->normalLineItems as $lineItem)
			{
				try
				{
					$this->addItem($lineItem, $lineItem->quantity);
				}
				catch(Exception $exception)
				{
					addMessage("An item could not be restored to your cart");
				}
			}
		}

		/**
		 * Merges this cart with the temporary one in the session, if such exists
		 * Note: This will save the user's cart, because we want to delete the temporary cart out of the session
		 */
		public function mergeWithTemporary()
		{
			if(isset($_SESSION[static::SESSION_VARIABLE]))
			{
				/** @var Cart $temporaryCart */
				$temporaryCart = $_SESSION[static::SESSION_VARIABLE];

				$this->mergeWithCart($temporaryCart);

				unset($_SESSION[static::SESSION_VARIABLE]);
			}

			$this->save();
		}

		/**
		 * Converts this cart to a session
		 * Note: This will save the order, but won't delete this cart until the payment response comes through. However, this cart will be disassociated from the user.
		 * @return	Order	The order this cart was converted to
		 */
		public function convertToOrder()
		{
			// Grab the user before we set it to null
			$user = $this->user;

			$this->awaitingPayment = true;
			$this->user = User::makeNull();
			unset($_SESSION[static::SESSION_VARIABLE]);
			$this->save();

			$order = new Order();
			$order->shippingAddress = clone $this->shippingAddress;
			$order->billingAddress = clone $this->billingAddress;

			$order->user = $user;
			$order->cart = $this;

			$orderLineItemLinks = [];

			foreach($this->normalLineItems as $lineItem)
			{
				$orderLineItemLink = new OrderLineItemLink();
				$orderLineItemLink->order = $order;
				$orderLineItemLink->lineItem = $lineItem;
				$orderLineItemLinks[] = $orderLineItemLink;
			}

			// Special line items should be marked as not normal, so they won't be regenerated if the user re-orders
			foreach($this->specialLineItems as $lineItem)
			{
				$orderLineItemLink = new OrderLineItemLink();
				$orderLineItemLink->order = $order;
				$orderLineItemLink->lineItem = $lineItem;
				$orderLineItemLink->isNormal = false;
				$orderLineItemLinks[] = $orderLineItemLink;
			}

			$order->orderLineItemLinks = $orderLineItemLinks;
			$order->payment = $order->generatePayment($this->paymentGateway);
			$order->save();

			return $order;
		}

		/**
		 * Outputs the Shipping enquiry form with a hidden element for products and total weight
		 * @return String 	Html for an enquiry form
		 */
		public function outputShippingEnquiryForm($region = null) 
		{
			if ($region === null) 
			{
				$region = $this->shippingRegion;
			}
			
			$form = Form::load(Form::SHIPPING_ENQUIRY_ID);
			$prepend = '<h4>' . $form->name . '</h4>';
			$prepend .= '<strong>Products</strong>';
			$prepend .= '<ul>';
				foreach ($this->normalLineItems as $lineItem) 
				{
					$prepend .= '<li>' . $lineItem->title . ' x ' . $lineItem->quantity . '</li>';
					$prepend .= '<input name="Products[]" type="hidden" value="' . $lineItem->title . ' x ' . $lineItem->quantity . '" />';
				}
			$prepend .= '</ul>';
			$prepend .= '<p>Total weight: ' . $this->getWeight() . 'kg</p>';
			$prepend .= '<input name="Total-Weight" type="hidden" value="' . $this->getWeight() . 'kg" />';
			
			$prepend .= '<p>Shipping Region: ' . $region->name . '</p>';//TODO 
			$prepend .= '<input name="Shipping Region" type="hidden" value="' . $region->name . '" />';
			
			$prepend .= '<p>Max weight: ' . $region->maxWeight() . 'kg</p>';
			$prepend .= '<input name="Max-Weight" type="hidden" value="' . $region->maxWeight() . 'kg" />';
			
			$form->prepend = $prepend;
			return $form->outputForm();
		}

		/**
		 * Get weight in kg
		 */
		public function getWeight() 
		{
			$weight = 0;

			foreach($this->normalLineItems as $lineItem)
			{
				$weight += $lineItem->getWeight();//weight in g
			}

			return $weight / 1000;
		}

		/**
		 * Checks if the cart is overweight for a given shipping region
		 * @param 	ShippingRegion 	$region		Region to calculate weight for
		 * @return	Bool			if the cart is overweight or not
		 */
		public function isOverweight(ShippingRegion $region = null) 
		{
			if ($region === null || $region->isNull()) 
			{
				$region = $this->shippingRegion;
			}

			if ($region->isWeightBased) 
			{
				$weightRange = $region->getApplicableWeightRange($this->getWeight());
				
				return $weightRange === null;
			}
			return false;
		}

		/**
		 * Gets the subtotal for this cart
		 * @return	float	The total amount of all normal line items
		 */
		public function get_subtotal()
		{
			$total = 0;

			foreach($this->normalLineItems as $lineItem)
			{
				$total += $lineItem->total;
			}

			return $total;
		}

		/**
		 * Gets the total for this cart
		 * @return	float	The total for this cart
		 */
		public function get_total()
		{
			$total = $this->subtotal;

			foreach($this->specialLineItems as $lineItem)
			{
				$total += $lineItem->total;
			}

			return $total;
		}

		/**
		 * Gets the normal line items, the ones that have been inserted, for this cart
		 * @return	LineItem[]	The normal line items
		 */
		public function get_normalLineItems()
		{
			$lineItems = [];

			foreach($this->cartLineItemLinks as $cartLineItemLink)
			{
				$lineItems[] = $cartLineItemLink->lineItem;
			}

			return $lineItems;
		}

		/**
		 * Gets special line items, generated by the cart
		 * @return	LineItem[]	The special line items
		 */
		public function get_specialLineItems()
		{
			$items = [];
			$cartPrice = $this->subtotal;
			$discountItem = null;

			//Do this first so we can apply it to the price to check for free shipping, but add it later so the order of things in the summary makes sense
			if(!$this->discount->isNull())
			{
				$discount = Discount::loadForLineItemGeneratorIdentifier($this->discount->code);
				
				assert($discount === null || $discount instanceof Discount);

				// check if discount code is still valid
				if($discount !== null && !$discount->isNull())
				{
					try
					{
						$discountItem = $discount->getLineItem();
						$discountItem->quantity = 1;
						$discountItem->generatorClassIdentifier = $discount::getClassLineItemGeneratorIdentifier();
						$discountItem->generatorIdentifier = $discount->getLineItemGeneratorIdentifier();

						$cartPrice += $discountItem->price;
					}
					catch(Exception $exception)
					{
						addMessage("An error occurred while applying the discount to your cart");
					}
				}
			}
			
			if (Configuration::acquire()->freeShippingThreshold > 0 && $cartPrice >= Configuration::acquire()->freeShippingThreshold && !$this->isOverweight()) 
			{
				$items[] = ShippingRegion::getFreeShippingItem();
			}
			else if(!$this->shippingRegion->active || ($this->shippingRegion->isWeightBased && count($this->shippingRegion->activeWeightRanges) === 0))
			{
				$this->shippingRegion = null;
			}
			else if(!$this->shippingRegion->isNull())
			{
				$shippingItem = $this->shippingRegion->getLineItem();
				$shippingItem->quantity = 1;
				$shippingItem->generatorClassIdentifier = $this->shippingRegion::getClassLineItemGeneratorIdentifier();
				$shippingItem->generatorIdentifier = $this->shippingRegion->getLineItemGeneratorIdentifier();
				$items[] = $shippingItem;
			}
			
			if ($discountItem != null) 
			{
				$items[] = $discountItem;
			}

			return $items;
		}

		/**
		 * Gets all the line items for this cart
		 * @return	LineItem[]	The line items belonging to this cart
		 */
		public function get_lineItems()
		{
			return array_merge($this->normalLineItems, $this->specialLineItems);
		}
		
		/**
		 * Checks whether this entity can be saved to the database
		 * @param	bool	$justTriggerProcesses	Whether we're checking if we should just trigger save processes
		 */
		public function canBeSaved(bool $justTriggerProcesses = false): bool
		{
			if($justTriggerProcesses || !$this->user->isNull() || $this->awaitingPayment)
			{
				return parent::canBeSaved();

			}
			else
			{
				// Carts should not be saved to the database if they belong to an anonymous user who isn't currently using a payment gateway
				return false;
			}
		}
		
		/**
		 * Runs before the entity is saved
		 * @param	bool	$isCreate	Whether this is a new entity or not
		 */
		public function beforeSave(bool $isCreate)
		{
			parent::beforeSave($isCreate);
			
			if($this->user->isNull() && !$this->awaitingPayment)
			{
				$_SESSION[static::SESSION_VARIABLE] = $this;
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
				"subtotal" => formatPrice($this->subtotal),
				"lineItems" => $this->normalLineItems
			];
		}
		
		public function __wakeup()
		{
			parent::__wakeup();
			
			$this->shippingRegion = ShippingRegion::load($this->shippingRegion->id);
		}

		//region OrderDetails

		/**
		 * Gets the shipping address for the order
		 * @return    Address        The shipping address
		 */
		public function getOrderDetailsShippingAddress(): Address
		{
			return $this->shippingAddress;
		}

		/**
		 * Gets the billing address for the order
		 * @return    Address        The billing address
		 */
		public function getOrderDetailsBillingAddress(): Address
		{
			return $this->billingAddress;
		}

		/**
		 * Gets the payment gateway
		 * @return    string    The payment gateway identifier
		 */
		public function getOrderDetailsPaymentGateway(): string
		{
			$paymentGateway = PaymentGateway::getGatewayClassForIdentifier($this->paymentGateway);

			if($paymentGateway === null)
			{
				return $this->paymentGateway;
			}

			return $paymentGateway::getPaymentLabel($this->total);
		}

		/**
		 * Gets the normal line items in the order
		 * @return    LineItem[]    The normal line items to display
		 */
		public function getOrderDetailsNormalLineItems(): array
		{
			return $this->normalLineItems;
		}

		/**
		 * Gets the special line items in the order
		 * @return    LineItem[]    The special line items to display
		 */
		public function getOrderDetailsSpecialLineItems(): array
		{
			return $this->specialLineItems;
		}

		/**
		 * Gets the subtotal for the order
		 * @return    float    The subtotal
		 */
		public function getOrderDetailsSubtotal(): float
		{
			return $this->subtotal;
		}

		/**
		 * Gets the total for the order
		 * @return    float    The total
		 */
		public function getOrderDetailsTotal(): float
		{
			return $this->total;
		}

		//endregion
	}