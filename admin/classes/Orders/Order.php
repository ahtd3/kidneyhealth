<?php
	namespace Orders;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Cart\Cart;
	use Cart\Discount;
	use Configuration\Configuration;
	use Configuration\Registry;
	use Controller\Twig;
	use Core\Attributes\Data;
	use Core\Attributes\Dynamic;
	use Core\Attributes\LinkFromMultiple;
	use Core\Attributes\LinkTo;
	use Core\Columns\ColumnCell;
	use Core\Columns\CustomColumn;
	use Core\Columns\WidgetColumn;
	use Core\Elements\Base\ResultElement;
	use Core\Elements\FormOption;
	use Core\Elements\Html;
	use Core\Elements\Output;
	use Core\Elements\Select;
	use Core\Elements\Text;
	use Core\Generator;
	use DateTimeImmutable;
	use Error;
	use Exception;
	use Mailer;
	use Pagination;
	use Payments\BillPayments\BillPayment;
	use Payments\OnAccount;
	use Payments\Payment;
	use Payments\PaymentGateway;
	use Twig\Error\Error as TwigError;
	use Users\SuperAdministrator;
	use Users\User;

	/**
	 * An order that has been placed on the site
	 */
	class Order extends Generator implements AdminNavItemGenerator, OrderDetails
	{
		const TABLE = "orders";
		const ID_FIELD = "order_id";
		const SINGULAR = "Order";
		const PLURAL = "Orders";
		const LABEL_PROPERTY = "label";

		const ORDERS_PER_PAGE = 50;

		const PENDING = 'pending';
		const PAID = 'paid';
		const DISPATCHED = 'dispatched';
		const PICKED_UP = 'picked up';
		const ARCHIVED = 'archived';

		#[Data("reference")]
		public string $reference = "";

		#[Data("status")]
		public string $status = 'pending';

		#[Data("tracking_code")]
		public string $trackingCode = "";

		#[Dynamic]
		public string $label;

		#[Data("date", "datetime")]
		public DateTimeImmutable $date;

		#[Data("date_dispatched", "datetime")]
		public ?DateTimeImmutable $dateDispatched = null;

		#[LinkTo("user_id")]
		public User $user;

		#[LinkTo("shipping_address_id")]
		public Address $shippingAddress;

		#[LinkTo("billing_address_id")]
		public Address $billingAddress;

		#[LinkTo("payment_id")]
		public OrderPayment $payment;

		#[LinkTo("cart_id")]
		public Cart $cart;

		#[LinkTo("courier_id")]
		public Courier $courier;

		/** @var OrderLineItemLink[] */
		#[LinkFromMultiple(OrderLineItemLink::class, "order")]
		public array $orderLineItemLinks;

		/** @var LineItem[] */
		#[Dynamic]
		public array $lineItems;

		// @see Order::beforeSave()
		private $sendUpdateEmail = false;
		private $oldStatus = '';

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("order", "Order", function(Order $order)
			{
				$html = "";
				$html .= "<div>\n";
				$html .= "<strong>Order #" . $order->reference . "</strong>\n";
				$html .= "</div>\n";
				$html .= "<div>\n";
				$html .= $order->date->format("F j, Y") . "\n";
				$html .= "</div>\n";

				return $html;
			}));

			static::addColumn(new CustomColumn("items", "Items", function(Order $order)
			{
				$html = "";

				foreach($order->getOrderDetailsNormalLineItems() as $lineItem)
				{
					$link = null;
					$generator = $lineItem->getGenerator();

					if($generator !== null)
					{
						$link = $generator->getLineItemEditLink();
					}

					$name = $lineItem->title;

					if($link !== null)
					{
						$name = "<a href='" . $link . "'>" . $lineItem->title . "</a>\n";
					}

					$html .= "<div>\n";
					$html .= $lineItem->quantity . " × <strong>" . $name . "</strong>\n";
					$html .= "</div>\n";
				}

				return $html;
			}));

			static::addColumn(new CustomColumn("total", "Total", function(Order $order)
			{
				$html = "";
				$html .= "<div>\n";
				$html .= "Subtotal: " . formatPrice($order->getOrderDetailsSubtotal()) . "\n";
				$html .= "</div>\n";
				$html .= "<div>\n";
				$html .= "Total: <strong>" . formatPrice($order->getTotal()) . "</strong>\n";
				$html .= "</div>\n";

				return $html;
			}));

			static::addColumn(new CustomColumn("status", "Status", function(Order $order)
			{
				$html = "";
				$html .= "<div>\n";
				$html .= ucfirst($order->status) . "\n";
				$html .= "</div>\n";
				$html .= "<div>\n";
				$html .= "<a href='" . $order->getEditLink() . "'>View order details</a>\n";
				$html .= "</div>\n";

				return $html;
			}));

			if(User::get() instanceof SuperAdministrator)
			{
				// copied from Generator::columns()
				static::addColumn(new WidgetColumn("delete", "Delete", function(Generator $object)
				{
					if($object->canDelete(User::get()) && $object->hasDelete())
					{
						return new ColumnCell("remove",
							[
								"type" => $object::SINGULAR,
								"label" => $object->{$object::LABEL_PROPERTY},
								"confirm" => true
							]);
					}

					return new ColumnCell("html-cell");
				}));
			}
		}

		/**
		 * Adds some HTML to display before the admin table
		 * @return	string	The HTML to display before the table
		 */
		public static function beforeTable(): string
		{
			$html = '';
			$html .= "<p>\n";
				if(isset($_GET["archive"]))
				{
					$html .= "<a href='" . static::getAdminNavLink() . "'>View all " . ucfirst(static::PLURAL) ."</a>\n";
				}
				else
				{
					$html .= "<a href='" . static::getAdminNavLink() . "?archive'>View archived " . ucfirst(static::PLURAL) ."</a>\n";
				}
			$html .= "<p>\n";
			return $html;
		}

		/**
		 * Changes the table heading to say Archived when viewing archived orders, for clarity
		 * @return	string	The table heading
		 */
		public static function tableHeading(): string
		{
			if (isset($_GET['archive']))
			{
				return 'Archived ' . parent::tableHeading();
			}
			else
			{
				return parent::tableHeading();
			}
		}

		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	static[]|Pagination				The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			$params = [];

			$query = "SELECT ~PROPERTIES "
					. "FROM ~TABLE "
					. "INNER JOIN ~/Payments/Payment "
					. "ON ~payment = ~/Payments/Payment.~id "
					. "WHERE true ";

			if (isset($_GET["archive"]))
			{
				$query .= "AND ~status = ? ";
			}
			else
			{
				$query .= "AND ~status != ? ";
			}
			$params[] = static::ARCHIVED;

			$query .= 'ORDER BY ~date DESC ';

			return static::makePages($query, $params, $page, static::ORDERS_PER_PAGE);
		}

		/**
		 * Loads a page of orders for a particular user
		 * @param	User		$user			The user to load the orders for
		 * @param	int			$currentPage	The current page
		 * @param	int			$perPage		The number of orders per page
		 * @return	Pagination					The paginated orders
		 */
		public static function loadPageForUser(User $user, $currentPage, $perPage)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "INNER JOIN ~/Payments/Payment "
				. "ON ~payment = ~/Payments/Payment.~id "
				. "WHERE ~user = ? "
				. "AND ( "
					. "~/Payments/Payment.~status = ? "
					. "OR (~/Payments/Payment.~status = ? AND ~/Payments/Payment.~paymentMethod = ? ) "
				. ") "
				. "ORDER BY ~date DESC ";

			return static::makePages($query, [$user->id, Payment::SUCCESS, Payment::PENDING, OnAccount::getClassIdentifier()], $currentPage, $perPage);
		}

		/**
		 * Whether a specific user can create a new object of this type
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can add a new object
		 */
		public static function canAdd(User $user): bool
		{
			return false;
		}

		/**
		 * Gets the possible order statuses
		 * @return	FormOption[]	The order statuses
		 */
		public static function getStatuses()
		{
			$statuses =
			[
				new FormOption("Pending", static::PENDING),
				new FormOption("Paid", static::PAID)
			];

			if(count(Courier::loadAll()) > 0)
			{
				$statuses[] = new FormOption("Dispatched", static::DISPATCHED);
			}

			$statuses[] = new FormOption("Picked up", static::PICKED_UP);
			$statuses[] = new FormOption("Archived", static::ARCHIVED);

			return $statuses;
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$variables = ["config" => Configuration::acquire(), "orderDetails" => $this, "hasShipping" => true];

			try
			{
				$html = Twig::render("orders/sections/order-details-template.twig", $variables);
			}
			catch(TwigError $exception)
			{
				throw new Error($exception->getMessage(), $exception->getCode(), $exception);
			}

			$statusSelect = new Select('status', 'Status', static::getStatuses());

			if(count(Courier::loadAll()) > 0)
			{
				$statusSelect->setDescription("Setting this to 'dispatched' will send the user an email containing the shipping details");
			}
			else if (Registry::SHIPPING)
			{
				$statusSelect->setDescription("Add Couriers to enable 'dispatched' status");
			}
			$this->addElement($statusSelect, "Details");
			$this->addElement(new Html("orderDetails", $html), "Details");

			if(count(Courier::loadAll()) > 0)
			{
				$courierSelect = new Select("courier", "Courier", array_merge(array_merge([new FormOption("None", "")], Courier::loadOptions())));
				$trackingInput = new Text("trackingCode", "Tracking Code");

				if ($this->status === static::DISPATCHED)
				{
					$this->addElement(new Output('dateDispatched', 'Date dispatched', $this->dateDispatched->format("F j, Y")), "Dispatch");
					$this->addElement(new Html('sendsEmailHeading', '<p class="label">Updating the following fields will send an update email to the user</p>'), "Dispatch");
					$courierSelect->addValidation(ResultElement::REQUIRED);
					$trackingInput->addValidation(ResultElement::REQUIRED);
				}

				$this->addElement($courierSelect, "Dispatch");
				$this->addElement($trackingInput, "Dispatch");
			}
		}

		/**
		 * Gets the total for this order
		 * @return	float	The total
		 */
		public function getTotal()
		{
			$total = 0;

			foreach($this->orderLineItemLinks as $orderLineItemLink)
			{
				$total += $orderLineItemLink->lineItem->total;
			}

			return $total;
		}

		/**
		 * Gets the tracking link for this order
		 * @return	string	The tracking link
		 */
		public function getTrackingLink()
		{
			return $this->courier->getUrl($this->trackingCode);
		}

		/**
		 * Gets a short summary of the order, eg 1 of Product and 3 other items
		 * @return String 	A short description
		 */
		public function getDescription()
		{
			$lineItem = $this->orderLineItemLinks[0]->lineItem;

			$description = $lineItem->quantity . ' of ' . $lineItem->title;

			if (count($this->orderLineItemLinks) > 1)
			{
				$totalRemainingQuantity = 0;

				foreach ($this->orderLineItemLinks as $index => $orderLineItemLink)
				{
					if ($index > 0)
					{
						$totalRemainingQuantity += $orderLineItemLink->lineItem->quantity;
					}
				}
				$description .= ' and ' . $totalRemainingQuantity . ' other items';
			}

			return $description;
		}

		/**
		 * Generates a payment for this order
		 * @param	string			$gatewayIdentifier	The identifier for the payment gateway
		 * @return	OrderPayment						The payment
		 */
		public function generatePayment($gatewayIdentifier)
		{
			$payment = new OrderPayment();
			$payment->name = $this->billingAddress->name;
			$payment->email = $this->billingAddress->email;
			$payment->amount = $this->getTotal();
			$payment->paymentMethod = $gatewayIdentifier;
			$payment->user = $this->user;
			$payment->order = $this;

			$gatewayClass = PaymentGateway::getGatewayClassForIdentifier($gatewayIdentifier);
			$payment->gatewayLabel = $gatewayClass::getPaymentlabel($this->getTotal());

			return $payment;
		}

		/**
		 * Gets all the line items in this Order
		 * @return	LineItem[]	The line items
		 */
		public function get_lineItems()
		{
			$lineItems = [];

			foreach($this->orderLineItemLinks as $orderLineItemLink)
			{
				$lineItems[] = $orderLineItemLink->lineItem;
			}

			return $lineItems;
		}

		/**
		 * Gets a label for this Order
		 * @return	string	A label
		 */
		public function get_label()
		{
			return "#" . $this->reference;
		}

		/**
		 * Sends a shipping confirmation email
		 * @throws	TwigError	If something goes wrong while rendering the email
		 */
		private function sendShippingEmail()
		{
			if($this->courier->isNull() || $this->trackingCode === "")
			{
				return;
			}

			$variables = ["config" => Configuration::acquire(), "order" => $this, "isUpdate" => $this->sendUpdateEmail];
			$html = Twig::render("orders/user-shipping-email.twig", $variables);
			$subject = $this->sendUpdateEmail ? "The shipping details for order #" . $this->reference . " have been updated" : "Order #" . $this->reference . " has shipped";

			Mailer::sendEmail($html, $subject, $this->billingAddress->email, Configuration::getAdminEmail());

			$this->sendUpdateEmail = false;
		}

		/**
		 * Sets the courier for this order
		 * @param	int|Courier		$courier	The courier to set
		 */
		public function set_courier($courier)
		{
			$oldCourierId = $this->courier->id;

			$this->setValue('courier', $courier);

			if ($this->status === static::DISPATCHED && $this->courier->id !== $oldCourierId)
			{
				$this->sendUpdateEmail = true;
			}
		}

		/**
		 * Sets the tracking code for this order
		 * @param	string	$code	The tracking code
		 */
		public function set_trackingCode($code)
		{
			if ($this->status === static::DISPATCHED && $this->trackingCode !== $code)
			{
				$this->sendUpdateEmail = true;
			}

			$this->setValue('trackingCode', $code);
		}

		/**
		 * Sets the status of this order
		 * @param	string	$status		The status to set
		 */
		public function set_status($status)
		{
			// if oldStatus has already been set don't overwrite it with a possibly unsaved newer status
			// @see Order::beforeSave()
			if($this->oldStatus === '')
			{
				$this->oldStatus = $this->status;
			}

			$this->setValue('status', $status);
		}

		/**
		 * Runs before the entity is saved
		 * @param	bool	$isCreate	Whether this is a new entity or not
		 */
		public function beforeSave(bool $isCreate)
		{
			parent::beforeSave($isCreate);

			try
			{
				$paidStatuses = [static::PAID, static::DISPATCHED, static::PICKED_UP];

				if($this->oldStatus === static::PENDING && in_array($this->status, $paidStatuses))
				{
					$payment = $this->payment;
					if(!$payment->gatewayDoesPayment())
					{
						$payment->status = OrderPayment::SUCCESS;
						$payment->remoteReference = "MANUAL";
					}
				}
				elseif($this->status === static::PENDING && in_array($this->oldStatus, $paidStatuses))
				{
					// order has been reversed
					$payment = $this->payment;

					if(!$payment->gatewayDoesPayment())
					{
						$payment->status = OrderPayment::PENDING;
						$payment->remoteReference = null;
					}
				}

				if($this->status === static::DISPATCHED && $this->oldStatus !== '' && $this->oldStatus !== static::DISPATCHED)
				{
					if(!$this->courier->isNull() && $this->trackingCode !== '')
					{
						$this->dateDispatched = new DateTimeImmutable();
						$this->sendShippingEmail();
						addMessage('Shipping email sent');
						// prevent possible recurrence
						$this->oldStatus = $this->status;

					}
					else
					{
						addMessage('Courier and tracking code must be set to mark this order as dispatched');
						$this->status = $this->oldStatus;
					}
				}
				else if($this->sendUpdateEmail)
				{
					// sendShippingEmail() sets sendUpdateEmail to false to prevent recurrence
					$this->sendShippingEmail();
					addMessage('Shipping email sent');
				}
			}
			catch(Exception $exception)
			{
				addMessage("Something went wrong while generating email content, failed to send.");
			}
		}

		/**
		 * Runs after the entity is saved
		 * @param bool $isCreate
		 */
		public function afterSave(bool $isCreate)
		{
			parent::afterSave($isCreate);

			if($this->reference === "" && $this->id !== null)
			{
				$this->reference = str_pad($this->id, 6, "0", STR_PAD_LEFT);
				$this->save();
			}
		}

		//region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			$shippingNavItem = new AdminNavItem(ShippingRegion::getAdminNavLink(), "Shipping", [ShippingRegion::class]);
			$paymentLogNavItem = new AdminNavItem(Payment::getAdminNavLink(), "Payment Log", [Payment::class]);
			$couriersNavItem = new AdminNavItem(Courier::getAdminNavLink(), "Couriers", [Courier::class]);
			$discountsNavItem = new AdminNavItem(Discount::getAdminNavLink(), "Discounts", [Discount::class]);
			$billPaymentsNavItem = new AdminNavItem(BillPayment::getAdminNavLink(), ucfirst(BillPayment::PLURAL), [BillPayment::class]);

			$subitems = [];

			if(Registry::BILL_PAYMENTS)
			{
				$subitems[] = $billPaymentsNavItem;
			}

			$subitems[] = $paymentLogNavItem;

			if(Registry::DISCOUNTS)
			{
				$subitems[] = $discountsNavItem;
			}

			if(Registry::SHIPPING)
			{
				$subitems[] = $shippingNavItem;
			}

			if(Registry::SHIPPING)
			{
				$subitems[] = $couriersNavItem;
			}

			//make sure bill payments is included, even if orders aren't. You can have a payment form without the products module.
			if (!Registry::ORDERS && Registry::BILL_PAYMENTS)
			{
				$billPaymentsNavItem->subitems = [$paymentLogNavItem];
				return $billPaymentsNavItem;
			}
			else
			{
				return new AdminNavItem(static::getAdminNavLink(), "Orders", [self::class], Registry::ORDERS, $subitems);
			}
		}

		//endregion

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
			return $this->payment->getGatewayLabel();
		}

		/**
		 * Gets the normal line items in the order
		 * @return    LineItem[]    The normal line items to display
		 */
		public function getOrderDetailsNormalLineItems(): array
		{
			$lineItems = [];

			foreach($this->orderLineItemLinks as $orderLineItemLink)
			{
				if($orderLineItemLink->isNormal)
				{
					$lineItems[] = $orderLineItemLink->lineItem;
				}
			}

			return $lineItems;
		}

		/**
		 * Gets the special line items in the order
		 * @return    LineItem[]    The special line items to display
		 */
		public function getOrderDetailsSpecialLineItems(): array
		{
			$lineItems = [];

			foreach($this->orderLineItemLinks as $orderLineItemLink)
			{
				if(!$orderLineItemLink->isNormal)
				{
					$lineItems[] = $orderLineItemLink->lineItem;
				}
			}

			return $lineItems;
		}

		/**
		 * Gets the subtotal for the order
		 * @return    float    The subtotal
		 */
		public function getOrderDetailsSubtotal(): float
		{
			$total = 0;

			foreach($this->getOrderDetailsNormalLineItems() as $lineItem)
			{
				$total += $lineItem->total;
			}

			return $total;
		}

		/**
		 * Gets the total for the order
		 * @return    float    The total
		 */
		public function getOrderDetailsTotal(): float
		{
			return $this->getTotal();
		}

		//endregion
	}
