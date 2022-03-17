<?php
	namespace Payments;

	use Cart\CartController;
	use Core\Columns\ColumnCell;
	use Core\Columns\CustomColumn;
	use Core\Columns\PropertyColumn;
	use Core\Columns\WidgetColumn;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use DateTimeImmutable;
	use Exception;
	use Pagination;
	use Users\SuperAdministrator;
	use Users\User;
	
	/**
	 * A Payment Action keeps tracks of all the payment attempts that have been made
	 */
	class Payment extends Generator
	{
		const TABLE = "payments";
		const ID_FIELD = "payment_id";
		const SINGULAR = "Payment";
		const PLURAL = "Payments";
		const LABEL_PROPERTY = "label";
		const HAS_AUTOCAST = true;

		const PENDING = "pending";
		const SUCCESS = "success";
		const FAILURE = "failure";

		public const DATE_FORMAT = "j F Y g:ia";

		const ACTIONS_PER_PAGE = 50;

		public $name = "";
		public $email = "";
		public $amount = 0;
		public $gatewayLabel = '';
		public $label = '';
		public $status = self::PENDING;

		/** @var string */
		public $paymentMethod = null;
		public $localReference = "";
		public $remoteReference = null;

		/** @var DateTimeImmutable */
		public $date = null;

		/** @var User */
		public $user = null;

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new Property("email", "email", "string"));
			static::addProperty(new Property("amount", "amount", "float"));
			static::addProperty(new Property("gatewayLabel", "gateway_label", "string"));
			static::addProperty(new Property("status", "status", "string"));
			static::addProperty(new Property("paymentMethod", "payment_method", "string"));
			static::addProperty(new Property("localReference", "local_reference", "string"));
			static::addProperty(new Property("remoteReference", "remote_reference", "string"));
			static::addProperty(new Property("date", "date", "datetime"));
			static::addProperty(new Property("label"));
			static::addProperty(new LinkToProperty("user", "user_id", User::class));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("date", "Date", function(Payment $payment)
			{
				return $payment->date->format(static::DATE_FORMAT);
			}));

			static::addColumn(new PropertyColumn("name", "Name"));
			static::addColumn(new PropertyColumn("email", "Email"));

			static::addColumn(new CustomColumn("status", "Status", function(Payment $payment)
			{
				return ucfirst($payment->status);
			}));

			static::addColumn(new CustomColumn("method", "Method", function(Payment $payment)
			{
				return ucfirst($payment->paymentMethod);
			}));

			static::addColumn(new CustomColumn("localReference", "Local Reference", function(Payment $payment)
			{
				return $payment->outputLocalReference();
			}));

			static::addColumn(new CustomColumn("remoteReference", "Remote Reference", function(Payment $payment)
			{
				return '<div class="break">' . $payment->remoteReference . '</div>';
			}));
			
			static::addColumn(new CustomColumn("type", "Type", function(Payment $payment)
			{
				return $payment->getTypeColumnContent();
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
		 * Loads all the Generators to be displayed in the table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	Pagination				The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			return static::loadAllPages(["date" => false], $page, static::ACTIONS_PER_PAGE);
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
		 * Creates a new Object and sets any properties that need default objects
		 */
		public function __construct()
		{
			parent::__construct();

			$this->localReference = hexdec(bin2hex(openssl_random_pseudo_bytes(4)));
		}

		/**
		 * Outputs the reference
		 * @return	string	The reference to the payment
		 */
		public function outputLocalReference()
		{
			return $this->localReference;
		}

		/**
		 * A convient method to check if the gateway that was used is a gateway that does payment (eg, not bank account)
		 * @return Bool		if this payment's gateway does payment
		 */
		public function gatewayDoesPayment() 
		{
			return (PaymentGateway::getGatewayClassForIdentifier($this->paymentMethod))::DOES_PAYMENT;
		}

		/**
		 * Outputs text for the Type column
		 * To be overridden in child classes that know what the payment was for (eg Order or BillPayment)
		 * 
		 * @return String 	Text to be displayed in the Type column
		 */
		public function getTypeColumnContent() 
		{
			return '';
		}

		/**
		 * Handles this payment being created in the database
		 * @throws	Exception	If something goes wrong during creation
		 */
		public function handleCreation()
		{
			if(!$this->gatewayDoesPayment())
			{
				//Gateways that don't actually handle payments (ie BankDeposit) don't get redirected back through the controller which would trigger success or failure handling. 
				//They also can't possibly fail, so they're successfull as soon as they're created.
				$this->handleSuccess();
			}
		}

		/**
		 * Handles this payment succeeding
		 */
		public function handleSuccess()
		{
			if($this->gatewayDoesPayment())
			{
				$this->status = self::SUCCESS;
				$this->save();
			}
		}

		/**
		 * Handles this payment failing
		 */
		public function handleFailure()
		{
			$this->status = self::FAILURE;
			$this->save();
		}

		/**
		 * Gets a label for this Order
		 * @return	string	A label
		 */
		public function get_label()
		{
			return $this->name . " - " . $this->date->format(static::DATE_FORMAT);
		}
		
		/**
		 * Gets the redirect for successful payment handling
		 * @return	string	The successful payment path
		 */
		public function getSuccessRedirect()
		{
			return PaymentController::BASE_PATH . "invoice/" . $this->localReference . "/";
		}
		
		/**
		 * Gets the redirect for failed payment handling
		 * @return	string	The failed payment path
		 */
		public function getFailureRedirect()
		{
			return CartController::BASE_PATH;
		}

		/**
		 * Gets the name of the payment gateway as it's displayed to the user (eg Credit Card)
		 * @return 	String 		A nice label for the gateway
		 */
		public function getGatewayLabel()
		{
			$paymentGateway = PaymentGateway::getGatewayClassForIdentifier($this->paymentMethod);

			if($paymentGateway === null)
			{
				return $this->paymentMethod;
			}
			
			// gatewayLabel is a snapshot, needed when dealing with payment gateways which work in installments
			// eg AfterPay, GenoaPay, where the installment amount is included in the label
			if($this->gatewayLabel !== '')
			{
				return $this->gatewayLabel;
			}

			return $paymentGateway::getPaymentLabel($this->amount);
		}
	}
