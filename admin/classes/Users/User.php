<?php
	namespace Users;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Cart\Cart;
	use Configuration\Registry;
	use Core\Columns\PropertyColumn;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Output;
	use Core\Elements\Password;
	use Core\Elements\Select;
	use Core\Elements\Table;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use DateTime;
	use DateTimeImmutable;
	use Orders\Address;
	use Orders\Order;
	use Pagination;
	use Payments\BillPayments\BillPayment;
	
	/**
	 * A User is someone who uses this website
	 */
	class User extends Generator implements AdminNavItemGenerator
	{
		const TABLE = "users";
		const ID_FIELD = "user_id";
		const HAS_AUTOCAST = true;
		const SINGULAR = "User";
		const PLURAL = "Users";
		const LABEL_PROPERTY = "name";
		const HAS_ACTIVE = true;
		
		const REGISTRATION_ENABLED = true;

		const TYPES =
		[
			"User" => self::class,
			"Administrator" => Administrator::class,
			"Super Administrator" => SuperAdministrator::class
		];

		private static $current = null;

		public $name = "";
		public $email = "";
		public $passwordHash = "";

		/** @var ?string */
		public $password = null;

		/** @var DateTimeImmutable */
		public $creationDate = null;

		/** @var DateTime */
		public $lastActive = null;

		public bool $active = true;

		/** @var Cart */
		public $cart = null;

		/** @var Address */
		public $address = null;

		/** @var UserSession[] */
		public $sessions = null;

		/** @var Order[] */
		public $orders = null;
		
		/** @var bool **/
		protected $_clearSessions = false;

		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new Property("email", "email", "string"));
			static::addProperty(new Property("passwordHash", "password_hash", "string"));
			static::addProperty(new Property("creationDate", "creation_date", "datetime"));
			static::addProperty(new Property("lastActive", "last_active", "datetime"));
			static::addProperty((new LinkToProperty("cart", "cart_id", Cart::class))->setAutoDelete(true));
			static::addProperty((new LinkToProperty("address", "address_id", Address::class))->setAutoDelete(true));
			static::addProperty((new LinkFromMultipleProperty("sessions", UserSession::class, "user")));
			static::addProperty((new LinkFromMultipleProperty("orders", Order::class, "user"))->setAutoDelete(false));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn("email", "Email"));

			parent::columns();
		}
		
		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int			$page	The page to load, if handling pagination
		 * @return	static[]			The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1): array
		{
			$users = [];

			foreach(static::loadAll(["email" => true]) as $user)
			{
				if(!$user instanceof Administrator)
				{
					$users[] = $user;
				}
			}

			return $users;
		}

		/**
		 * Attempts to get the currently logged in user
		 * @return	self	The currently logged in user, or a null object
		 */
		public static function get(): self
		{
			if(self::$current === null)
			{
				self::$current = UserSession::get()->user;
			}

			return self::$current ?? static::makeNull();
		}

		/**
		 * Sets the current user
		 * @param	self	$user	The user that should be logged in
		 */
		public static function set(User $user)
		{
			self::$current = $user;
		}

		/**
		 * Loads a User matching an email and password
		 * @param	string	$email		The email to match against
		 * @param	string	$password	The password to match against
		 * @return	static				The user with that username and password
		 */
		public static function loadForEmailAndPassword(string $email, string $password): self
		{
			$user = User::loadForMultiple(
				[
					"email" => trim(strtolower($email)),
					"active" => true
				]);

			if($user->isNull())
			{
				// Spend a little bit of time hashing the password anyway, so that an attacker can't tell if that email address exists or not by how long it takes to load the page
				password_hash($password, PASSWORD_DEFAULT);
				return $user;
			}
			else if(password_verify(trim($password), $user->passwordHash))
			{
				if(password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT))
				{
					$user->password = $password;
					$user->save();
				}

				return $user;
			}
			else
			{
				return static::makeNull();
			}
		}

		/**
		 * General permissions for a user for this object
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can view, add, edit or delete these objects
		 */
		public static function canDo(User $user)
		{
			return $user instanceof Administrator;
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Select("class", "Type", self::TYPES), "Details");
			$this->addElement(new Text("name", "Name"), "Details");
			
			$emailElement = new Text("email", "Email");
			$emailElement->addValidation(Text::REQUIRED);
			
			$emailElement->addValidation("unique", function(string $value)
			{
				$current = User::loadFor("email", $value);
				
				if($current !== $this && !$current->isNull())
				{
					return "A user with that email address already exists";
				}
				else
				{
					return null;
				}
			});
			
			$this->addElement($emailElement, "Details");
			
			$passwordElement = (new Password("password", "Password"))->setDescription("Leave blank to retain current password");
			
			if($this->id === null)
			{
				$passwordElement->addValidation(Password::REQUIRED);
			}
			
			$passwordElement->setResultHandler(function($password)
			{
				if($password !== "")
				{
					$this->password = $password;
				}
			});

			$this->addElement($passwordElement, "Details");

			$this->addElement(new Output("creationDate", "Joined on", $this->creationDate->format("j F Y")), "Details");
			$this->addElement(new Output("lastActive", "Last active", $this->lastActive->format("j F Y")), "Details");

			if(Registry::ORDERS)
			{
				$this->addElement(new GeneratorElement("address"), "Address");
			}

			if($this->id !== null && Registry::ORDERS)
			{
				$this->addElement(new Table("orders", "Orders"), "Orders");
			}
		}

		/**
		 * Whether this user can access some aspect of the admin panel
		 * @return	bool	Whether they've got access
		 */
		public function hasAdminAccess()
		{
			return false;
		}

		/**
		 * Whether this user can access the database
		 * @return	bool	If this user has database access
		 */
		public function hasDatabaseAccess()
		{
			return false;
		}

		/**
		 * Gets this user's cart
		 * @return	Cart	This user's cart
		 */
		public function get_cart()
		{
			/** @var Cart $cart */
			$cart = $this->getValue("cart");

			if($cart->isNull())
			{
				$cart = new Cart();
				$cart->user = $this;
			}

			return $cart;
		}

		/**
		 * Gets a page of orders for this user
		 * @return	Pagination	The paginated orders
		 */
		public function getOrdersPage()
		{
			return Order::loadPageForUser($this, (int) ($_GET["page"] ?? 1), 10);
		}

		/**
		 * Gets a page of bill payments for this user
		 * @return	Pagination	The paginated payments
		 */
		public function getPaymentsPage()
		{
			return BillPayment::loadPageForUser($this, (int) ($_GET["page"] ?? 1), 10);
		}
				
		/**
		 * sets active with the added step of checking if active user sessions should be cleared
		 *
		 * @param bool $bool the value to change $active to
		 */
		public function set_active($bool = false)
		{
			if($this->active && !$bool)
			{
				$this->_clearSessions = true;
			}
			
			$this->setValue('active', $bool);
		}
		
		/**
		 * sets email enforcing lowercase
		 *
		 * @param string $str the email address
		 */
		public function set_email($str = '')
		{
			$this->setValue('email',trim(strtolower($str)));
		}

		/**
		 * Loads the object that have particular values
		 * @param    array $values Key/value pairs of property name => property value
		 * @return    static                The requested Object
		 */
		public static function loadForMultiple(array $values)
		{
			if(isset($values['email']))
			{
				$values['email'] = strtolower($values['email']);
			}
			
			return parent::loadForMultiple($values);
		}
		
		/**
		 * Loads all the Objects that have particular values
		 * @param    array  $values  Key/value pairs of property name => property value
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]                Array of Objects
		 */
		public static function loadAllForMultiple(array $values, array $orderBy = [])
		{
			if(isset($values['email']))
			{
				$values['email'] = strtolower($values['email']);
			}
			
			return parent::loadAllForMultiple($values, $orderBy);
		}
		
		/**
		 * Runs before the entity is saved
		 * @param bool $isCreate
		 */
		public function beforeSave(bool $isCreate)
		{
			parent::beforeSave($isCreate);
			
			if($this->password !== null)
			{
				$this->passwordHash = password_hash(trim($this->password), PASSWORD_DEFAULT);
			}
		}
		
		/**
		 * Runs after the entity is saved
		 * @param bool $isCreate
		 */
		public function afterSave(bool $isCreate)
		{
			parent::afterSave($isCreate);
			
			if($this->_clearSessions)
			{
				$sessions = UserSession::loadAllFor('user', $this);
				
				foreach($sessions as $session)
				{
					$session->delete();
				}
				
				if(count($sessions) > 0)
				{
					addMessage('User has been logged out of existing sessions');
				}
			}
		}

		//region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			$types = [];

			foreach(User::TYPES as $type)
			{
				if(!is_a($type, Administrator::class, true))
				{
					$types[] = $type;
				}
			}

			return new AdminNavItem(static::getAdminNavLink(), "Users", $types);
		}

		//endregion
	}
