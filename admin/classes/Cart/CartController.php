<?php
	namespace Cart;

	use Configuration\Registry;
	use Controller\JsonController;
	use Controller\RedirectController;
	use Controller\UrlController;
	use Exception;
	use Orders\Address;
	use Orders\ShippingRegion;
	use Pages\Page;
	use Pages\PageController;
	use Payments\PaymentGateway;
	use Users\User;
	
	/**
	 * Handles displaying the cart and cart actions to the user
	 */
	class CartController extends PageController
	{
		const BASE_PATH = "/cart/";

		const LOGIN_STEP = "login";
		const DELIVERY_STEP = "delivery";
		const PAYMENT_STEP = "billing";
		const CONFIRM_STEP = "confirm";

		private $step = null;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return	PageController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/action/add/$classId/' => self::class,
				'/action/add/$classId/$id/' => self::class,
				'/action/add/$classId/$id/$quantity/' => self::class,
				'/action/discount/' => self::class,
				'/action/update/$lineItem/' => self::class,
				'/action/update/$lineItem/$quantity/' => self::class,
				'/action/remove/$lineItem/' => self::class,
				'/action/shipping/$regionId/' => self::class,
				'/action/order/' => self::class,
				'/action/step/$actionStep/' => self::class,
				'/json/' => self::class,
				'/step/$step/' => self::class,
			];
		}

		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController	$parent		The parent to the Page Child Controller
		 * @param	string[]		$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string			$pattern	The pattern that was matched
		 * @return	UrlController						An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			if(!Registry::CART)
			{
				return null;
			}

			if(isset($matches["classId"]) && isset($matches["id"]))
			{
				if(isset($matches["quantity"]))
				{
					return static::addItem($matches["classId"], $matches["id"], $matches["quantity"]);
				}
				else if(isset($_POST["quantity"]))
				{
					return static::addItem($matches["classId"], $matches["id"], $_POST["quantity"]);
				}
			}
			else if(isset($matches["classId"]) && isset($_POST["id"]) && isset($_POST["quantity"]))
			{
				return static::addItem($matches["classId"], $_POST["id"], $_POST["quantity"]);
			}
			else if(isset($matches["lineItem"]))
			{
				if(isset($matches["quantity"]))
				{
					return static::updateItem($matches["lineItem"], $matches["quantity"]);
				}
				else if(isset($_POST["quantity"]))
				{
					return static::updateItem($matches["lineItem"], $_POST["quantity"]);
				}
				else
				{
					return static::removeItem($matches["lineItem"]);
				}
			}
			else if($pattern === "/action/discount/" && isset($_POST["discount"]))
			{
				return static::setDiscount($_POST["discount"]);
			}
			else if(isset($matches["regionId"]))
			{
				return static::setShipping($matches["regionId"]);
			}
			else if($pattern === '/action/order/')
			{
				return static::createOrder();
			}
			else if($pattern === "/json/")
			{
				$cart = Cart::get();

				return new JsonController($cart);
			}
			else if(isset($matches["step"]))
			{
				$currentStep = $matches["step"];
				$cart = Cart::get();

				if(count($cart->normalLineItems) === 0)
				{
					return new RedirectController(static::BASE_PATH);
				}

				if($currentStep === static::CONFIRM_STEP && ($cart->paymentGateway === null || count($cart->billingAddress->verifyFields(Address::REQUIRED_BILLING_ADDRESS_FIELDS)) > 0))
				{
					$currentStep = static::PAYMENT_STEP;
				}

				// Shipping region is not included in shipping address
				$activeShippingRegions = ShippingRegion::loadAllFor("active", true);
				$needShippingRegion = Registry::SHIPPING && count($activeShippingRegions) > 0 && ($cart->shippingRegion->isNull() || !$cart->shippingRegion->active);
				
				if($currentStep === static::PAYMENT_STEP && ($needShippingRegion || count($cart->shippingAddress->verifyFields(Address::REQUIRED_SHIPPING_ADDRESS_FIELDS)) > 0))
				{
					$currentStep = static::DELIVERY_STEP;
				}

				// to be aware of when testing: having the admin panel open in another tab with AUTO_LOGIN = true can mess with this 
				// (User is not null without going through LOGIN_STEP)
				if($currentStep === static::LOGIN_STEP && (!Registry::USERS || !User::get()->isNull()))
				{
					$currentStep = static::DELIVERY_STEP;
				}

				if($currentStep === static::DELIVERY_STEP && !Registry::SHIPPING)
				{
					$currentStep = static::PAYMENT_STEP;
				}

				if($currentStep !== $matches["step"])
				{
					$allSteps = (new static)->getCheckoutSteps();
					return new RedirectController($allSteps[$currentStep]);
				}
				else
				{
					return new static($currentStep);
				}
			}
			else if(isset($matches["actionStep"]))
			{
				switch($matches["actionStep"])
				{
					case "delivery":
						return static::submitDelivery();

					case "payment":
						return static::submitPayment();
				}
			}
			else
			{
				return new static();
			}

			return null;
		}

		/**
		 * Gets the available checkout steps
		 * @return	string[]	The checkout steps and their paths
		 */
		public function getCheckoutSteps()
		{
			$steps = [];

			if(Registry::USERS && User::get()->isNull())
			{
				$steps[static::LOGIN_STEP] = static::BASE_PATH . "step/" . static::LOGIN_STEP . "/";
			}

			if(Registry::SHIPPING)
			{
				$steps[static::DELIVERY_STEP] = static::BASE_PATH . "step/" . static::DELIVERY_STEP . "/";
			}

			$steps[static::PAYMENT_STEP] = static::BASE_PATH . "step/" . static::PAYMENT_STEP . "/";
			$steps[static::CONFIRM_STEP] = static::BASE_PATH . "step/" . static::CONFIRM_STEP . "/";

			return $steps;
		}

		/**
		 * Adds an item to the cart
		 * @param	string				$classId	The identifier for the class
		 * @param	string				$id			The identifier for the object
		 * @param	int					$quantity	The number of items to add
		 * @return	RedirectController				Redirects the user to the cart page
		 */
		public static function addItem($classId, $id, $quantity)
		{
			$found = false;

			foreach(Registry::LINE_ITEM_GENERATING_CLASSES as $lineItemGeneratingClass)
			{
				if($quantity <= 0)
				{
					continue;
				}

				try
				{
					if($lineItemGeneratingClass::getClassLineItemGeneratorIdentifier() === $classId)
					{
						$generator = $lineItemGeneratingClass::loadForLineItemGeneratorIdentifier($id);
						$cart = Cart::get();
						$cart->addItem($generator, $quantity);
						$cart->save();

						$found = true;
					}
				}
				catch(Exception $e)
				{
					addMessage($e->getMessage());
				}
			}

			if($quantity > 0 && !$found)
			{
				addMessage("Item no longer exists, was not added to the cart");
			}

			return new RedirectController(static::BASE_PATH);
		}

		/**
		 * Updates the quantity of an item in the cart, setting the quantity to 0 will remove that item
		 * @param	string				$lineItem	The identifier for the line item
		 * @param	int					$quantity	The quantity to update to
		 * @return	RedirectController				Redirects the user to the cart page
		 */
		public static function updateItem($lineItem, $quantity)
		{
			if($quantity <= 0)
			{
				static::removeItem($lineItem);
			}
			else
			{
				$cart = Cart::get();
				$cart->updateItem($lineItem, $quantity);
				$cart->save();
			}

			return new RedirectController(static::BASE_PATH);
		}

		/**
		 * Removes an item from the cart
		 * @param	string				$lineItem	The identifier for the line item
		 * @return	RedirectController				Redirects the user to the cart page
		 */
		public static function removeItem($lineItem)
		{
			$cart = Cart::get();
			$cart->removeItem($lineItem);
			$cart->save();

			return new RedirectController(static::BASE_PATH);
		}

		/**
		 * Sets the shipping location on the user's cart
		 * @param	int					$regionId	The identifier for the shipping region
		 * @return	RedirectController				Redirects the user to the cart page
		 */
		public static function setShipping($regionId)
		{
			$region = ShippingRegion::load($regionId);

			if(!$region->isNull() && $region->active)
			{
				$cart = Cart::get();
				$cart->shippingRegion = $region;
				$cart->save();
			}
			else
			{
				addMessage("That shipping region is not available");
			}

			return new RedirectController(static::BASE_PATH);
		}

		/**
		 * Sets the discount for the cart
		 * @param	string $discountCode The discount to set
		 * @return	RedirectController				Redirects the user back to the cart
		 */
		public static function setDiscount($discountCode)
		{
			$cart = Cart::get();

			if($discountCode === "")
			{
				$cart->discount = null;
				$cart->save();
			}
			else
			{
				/** @var Discount $discount */
				$discount = Discount::loadForLineItemGeneratorIdentifier($discountCode);

				if($discount->isNull())
				{
					addMessage("Invalid discount code");
				}
				else
				{
					$cart->discount = $discount;
					$cart->save();
				}
			}

			return new RedirectController(null);
		}

		/**
		 * Handles submitting the delivery step
		 * @return	RedirectController	Redirects to the next step
		 */
		public static function submitDelivery()
		{
			$cart = Cart::get();
			$hasErrors = false;

			if ($cart->shippingAddress->isNull())
			{
				$cart->shippingAddress = new Address();
			}

			if(isset($_POST["name"]))
			{
				$cart->shippingAddress->name = $_POST["name"];
			}

			if(isset($_POST["phone"]))
			{
				$cart->shippingAddress->phone = $_POST["phone"];
			}

			if(isset($_POST["address"]))
			{
				$cart->shippingAddress->address = $_POST["address"];
			}

			if(isset($_POST["suburb"]))
			{
				$cart->shippingAddress->suburb = $_POST["suburb"];
			}

			if(isset($_POST["city"]))
			{
				$cart->shippingAddress->city = $_POST["city"];
			}

			if(isset($_POST["postCode"]))
			{
				$cart->shippingAddress->postCode = $_POST["postCode"];
			}

			if(isset($_POST["country"]))
			{
				$cart->shippingAddress->country = $_POST["country"];
			}

			if(isset($_POST["instructions"]))
			{
				$cart->shippingAddress->deliveryInstructions = $_POST["instructions"];
			}

			foreach($cart->shippingAddress->verifyFields(Address::REQUIRED_SHIPPING_ADDRESS_FIELDS) as $message)
			{
				addMessage($message);
				$hasErrors = true;
			}

			$activeShippingRegions = ShippingRegion::loadAllFor("active", true);
			$needShipping = Registry::SHIPPING && count($activeShippingRegions) > 0 && $cart->shippingRegion->isNull();

			if(($_POST["shippingRegion"] ?? "") !== "")
			{
				$shippingRegion = ShippingRegion::load($_POST["shippingRegion"]);

				if($shippingRegion->isNull() || !$shippingRegion->active)
				{
					addMessage("That region is invalid, please select a valid shipping region");
					$hasErrors = true;
				}
				else
				{
					$cart->shippingRegion = $shippingRegion;
				}
			}
			else if($needShipping)
			{
				addMessage("Please select a shipping region");
				$hasErrors = true;
			}

			$cart->save();

			if(!$hasErrors)
			{
				return new RedirectController(static::BASE_PATH . "step/" . static::PAYMENT_STEP . "/");
			}
			else
			{
				return new RedirectController(static::BASE_PATH . "step/" . static::DELIVERY_STEP . "/");
			}
		}

		/**
		 * Handles submitting the payment step
		 * @return	RedirectController	Redirects to the next step
		 */
		public static function submitPayment()
		{
			$cart = Cart::get();
			$hasErrors = false;

			$paymentGateways = static::getAvailablePaymentGateways($cart->total);

			if(count($paymentGateways) === 1)
			{
				$paymentGatewayClass = $paymentGateways[0];
				$cart->paymentGateway = $paymentGatewayClass::getClassIdentifier();
			}

			if(isset($_POST["payment"]))
			{
				$paymentClass = PaymentGateway::getGatewayClassForIdentifier($_POST["payment"]);

				if($paymentClass === null)
				{
					addMessage("Please select a valid payment method");
					$hasErrors = true;
				}
				else
				{
					$cart->paymentGateway = $_POST["payment"];
				}
			}
			else if($cart->paymentGateway === null)
			{
				addMessage("Please select a payment method");
				$hasErrors = true;
			}

			if ($cart->billingAddress->isNull())
			{
				$cart->billingAddress = new Address();
			}

			if(isset($_POST["email"]))
			{
				$cart->billingAddress->email = $_POST["email"];
			}

			if(isset($_POST["phone"]))
			{
				$cart->billingAddress->phone = $_POST["phone"];
			}

			$cart->sameAddress = isset($_POST["sameAddress"]);

			if($cart->sameAddress)
			{
				$cart->billingAddress->name = $cart->shippingAddress->name;
				$cart->billingAddress->address = $cart->shippingAddress->address;
				$cart->billingAddress->suburb = $cart->shippingAddress->suburb;
				$cart->billingAddress->city = $cart->shippingAddress->city;
				$cart->billingAddress->postCode = $cart->shippingAddress->postCode;
				$cart->billingAddress->country = $cart->shippingAddress->country;
			}
			else
			{
				if(isset($_POST["name"]))
				{
					$cart->billingAddress->name = $_POST["name"];
				}

				if(isset($_POST["address"]))
				{
					$cart->billingAddress->address = $_POST["address"];
				}

				if(isset($_POST["suburb"]))
				{
					$cart->billingAddress->suburb = $_POST["suburb"];
				}

				if(isset($_POST["city"]))
				{
					$cart->billingAddress->city = $_POST["city"];
				}

				if(isset($_POST["postCode"]))
				{
					$cart->billingAddress->postCode = $_POST["postCode"];
				}

				if(isset($_POST["country"]))
				{
					$cart->billingAddress->country = $_POST["country"];
				}
			}

			foreach($cart->billingAddress->verifyFields(Address::REQUIRED_BILLING_ADDRESS_FIELDS) as $message)
			{
				addMessage($message);
				$hasErrors = true;
			}

			if(isset($_POST["updateAddress"]) && !$hasErrors)
			{
				if (Registry::SHIPPING)
				{
					$address = $cart->shippingAddress;
				}
				else
				{
					$address = $cart->billingAddress;
				}

				if ($cart->user->address->isNull())
				{
					$cart->user->address = new Address;
				}

				$cart->user->address->name = $cart->billingAddress->name;
				$cart->user->address->phone = $cart->billingAddress->phone;
				$cart->user->address->address = $address->address;
				$cart->user->address->suburb = $address->suburb;
				$cart->user->address->city = $address->city;
				$cart->user->address->postCode = $address->postCode;
				$cart->user->address->country = $address->country;
				$cart->user->address->deliveryInstructions = $address->deliveryInstructions;
				$cart->user->address->save();
			}

			$cart->save();

			if(!$hasErrors)
			{
				return new RedirectController(static::BASE_PATH . "step/" . static::CONFIRM_STEP . "/");
			}
			else
			{
				return new RedirectController(static::BASE_PATH . "step/" . static::PAYMENT_STEP . "/");
			}
		}

		/**
		 * Generates an order from the current cart
		 * @return	RedirectController	Redirects the user to the payment gateway
		 */
		public static function createOrder()
		{
			$cart = Cart::get();
			$hasErrors = false;

			// For the cart page
			if(count($cart->normalLineItems) === 0)
			{
				addMessage("There is nothing in your cart");
				$hasErrors = true;
			}

			// For the delivery page
			if(!$hasErrors && Registry::SHIPPING)
			{
				$messages = $cart->shippingAddress->verifyFields(Address::REQUIRED_SHIPPING_ADDRESS_FIELDS);

				foreach($messages as $message)
				{
					addMessage($message);
					$hasErrors = true;
				}
			}

			// For the payment page
			if(!$hasErrors)
			{
				$messages = $cart->billingAddress->verifyFields(Address::REQUIRED_BILLING_ADDRESS_FIELDS);

				foreach($messages as $message)
				{
					addMessage($message);
					$hasErrors = true;
				}

				if($cart->paymentGateway === null)
				{
					addMessage("Please select a payment method");
					$hasErrors = true;
				}
				else if(PaymentGateway::getGatewayClassForIdentifier($cart->paymentGateway) === null)
				{
					addMessage("That payment method is unavailable, please select a different one");
					$hasErrors = true;
				}
			}

			if($hasErrors)
			{
				return new RedirectController(static::BASE_PATH . "step/" . static::CONFIRM_STEP . "/");
			}

			$order = $cart->convertToOrder();
			$paymentGateway = PaymentGateway::getGatewayForPayment($order->payment);

			try
			{
				$controller = $paymentGateway->getRedirectController();
				$order->save();
				$order->payment->handleCreation();
				return $controller;
			}
			catch(Exception $exception)
			{
				addMessage($exception->getMessage());
				$order->payment->handleFailure();

				return new RedirectController(static::BASE_PATH . "step/" . static::CONFIRM_STEP . "/");
			}
		}

		/**
		 * some payment gateways (eg AfterPay, GenoaPay) set minimum or maximum limits on the value of order which can be paid through them
		 * These may vary between different merchant accounts and may require the merchant /not/ to present the gateway as an option if the order total falls outside these limits
		 * @param float $total value of the order. Passing this means we can test without having a cart
		 * @return string[]  the class names of available payment gateways
		 */
		public static function getAvailablePaymentGateways($total = null)
		{
			$paymentGateways = [];
			foreach(PaymentGateway::getActivePaymentGatewayClasses() as $paymentGatewayClass)
			{
				try
				{
					if($paymentGatewayClass::availableForOrderValue($total) && $paymentGatewayClass::availableForUser(Cart::get()->user))
					{
						$paymentGateways[] = $paymentGatewayClass;
					}
				}
				catch(Exception $exception)
				{
					// an exception at this point probably means the API is not responding
					// we might want to put some logging in here, but for now just skip adding the gateway
					// output a message for devs
					if(IS_DEV_SITE || IS_DEBUG_IP)
					{
						addMessage($exception->getMessage());
					}
				}
			}

			return $paymentGateways;
		}

		/**
		 * Displays the cart
		 * @param	string	$step	The checkout step to display, or null to display the cart page
		 */
		public function __construct(?string $step = null)
		{
			$page = new Page();
			$page->name = "Cart";
			$page->pageType = "Cart";

			parent::__construct($page);

			$this->step = $step;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			switch($this->step)
			{
				case static::LOGIN_STEP:
					return "cart/login-page.twig";

				case static::DELIVERY_STEP:
					return "cart/delivery-page.twig";

				case static::PAYMENT_STEP:
					return "cart/payment-page.twig";

				case static::CONFIRM_STEP:
					return "cart/confirm-page.twig";

				default:
					return "cart/cart-page.twig";
			}
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			$shippingRegions = [];
			$paymentGateways = [];
			$cart = Cart::get();

			foreach(ShippingRegion::loadAllFor("active", true) as $shippingRegion)
			{
				if (!($shippingRegion->isWeightBased && count($shippingRegion->activeWeightRanges) === 0))
				{
					$shippingRegions[$shippingRegion->name] = $shippingRegion->id;
				}					
			}

			if($this->step === static::PAYMENT_STEP)
			{
				// this is the only step at which we actually need the list of gateways
				// and since PaymentGateway::availableforOrderValue has the potential
				// to result in API calls we should probably minimise how often we use it
				$paymentGatewayClasses = static::getAvailablePaymentGateways($cart->total);

				if(empty($paymentGatewayClasses))
				{
					// ruh-rho
					addMessage('No payment options were found. Checkout cannot continue.');
					addMessage('This may be due to an internet service not responding. Please try again shortly.');
				}

				foreach($paymentGatewayClasses as $paymentGatewayClass)
				{
					$paymentGateways[$paymentGatewayClass::getUserLabel($cart->total)] = $paymentGatewayClass::getClassIdentifier();
				}
			}

			$variables = parent::getTemplateVariables();

			$paymentGateway = $cart->paymentGateway === null ? null : PaymentGateway::getGatewayClassForIdentifier($cart->paymentGateway);

			$variables["step"] = $this->step;
			$variables["hasShipping"] = Registry::SHIPPING;
			$variables["shippingRegions"] = $shippingRegions;
			$variables["paymentGateways"] = $paymentGateways;
			$variables["cartConfirmHtml"] = $paymentGateway === null ? null : $paymentGateway::getConfirmHtml();
			$variables["hasDiscounts"] = Registry::DISCOUNTS;

			return $variables;
		}
	}
