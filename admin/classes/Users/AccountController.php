<?php
	namespace Users;

	use Cart\Cart;
	use Cart\CartController;
	use Configuration\Configuration;
	use Configuration\Registry;
	use Controller\JsonController;
	use Controller\RedirectController;
	use Controller\Twig;
	use Controller\UrlController;
	use Exception;
	use Forms\Form;
	use Mailer;
	use Orders\Address;
	use Orders\Order;
	use Pages\Page;
	use Pages\PageController;
	use Payments\BillPayments\BillPayment;
	
	/**
	 * Controls user output
	 */
	class AccountController extends PageController
	{
		const LOGIN = 0;
		const REGISTER = 1;
		const RESET_PASSWORD = 2;
		const CHANGE_PASSWORD = 3;
		const RESET_PASSWORD_INSTRUCTIONS = 4;
		const ACCOUNT = 5;
		const ADDRESS = 6;
		const ORDERS = 7;
		const ORDER = 8;
		const PAYMENTS = 9;
		const PAYMENT = 10;

		const BASE_PATH = "/account/";
		const MIN_PASSWORD_LENGTH = 5;

		private static $accountPage = null;
		public static $registrationEnabled = User::REGISTRATION_ENABLED;

		private $type = self::ACCOUNT;
		private $requestId = null;
		private $token = null;
		private $order = null;
		private $payment = null;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return	UrlController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/action/login/' => self::class,
				'/action/logout/' => self::class,
				'/action/order-again/$order/' => self::class,
				'/action/register/' => self::class,
				'/action/reset-password/' => self::class,
				'/action/reset-password-request/' => self::class,
				'/action/update-address/' => self::class,
				'/action/update-details/' => self::class,
				'/address/' => self::class,
				'/login/' => self::class,
				'/order/$order/' => self::class,
				'/orders/' => self::class,
				'/payment/$payment/' => self::class,
				'/payments/' => self::class,
				'/register/' => self::class,
				'/reset-password/' => self::class,
				'/reset-password/$requestId/$token/' => self::class
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
			/** @var PageController $parent */
			$user = User::get();

			switch($pattern)
			{
				case self::BASE_PATH . "*":
					if($parent === null)
					{
						if($user->isNull())
						{
							return new AccountController(AccountController::getAccountPage(), static::LOGIN);
						}
						else
						{
							return new AccountController(AccountController::getAccountPage(), static::ACCOUNT);
						}
					}
					break;

				case '/action/login/':
					return self::login();

				case '/action/logout/':
					return self::logout();

				case '/action/order-again/$order/':
					$order = Order::loadFor("reference", $matches["order"]);

					if(!$order->isNull() && $order->user === $user)
					{
						$cart = Cart::get();

						foreach($order->getOrderDetailsNormalLineItems() as $normalLineItem)
						{
							// null if the generator class has been removed or renamed
							$item = $normalLineItem->getGenerator();
							
							// If there are any issues with this item, it will be caught on the next page when the items are checked
							if($item !== null)
							{
								try
								{
									$cart->addItem($item, $normalLineItem->quantity);
								}
								catch(Exception $exception)
								{
									addMessage($exception->getMessage());
								}
							}
						}

						$cart->save();

						return new RedirectController(CartController::BASE_PATH);
					}
					else
					{
						addMessage("Invalid order ID");
						return new RedirectController(null);
					}

				case '/action/register/':
					return self::register();

				case '/action/reset-password/':
					return self::resetPassword();

				case '/action/reset-password-request/':
					return self::resetPasswordRequest();

				case '/action/update-details/':
					return self::updateDetails();

				case '/action/update-address/':
					return self::updateAddress();

				case '/address/':
					return new AccountController(AccountController::getAccountPage(), static::ADDRESS);

				case '/login/':
					if($user->isNull())
					{
						return new self(static::getAccountPage(), static::LOGIN);
					}
					else
					{
						return new RedirectController(static::BASE_PATH);
					}

				case '/register/':
					if($user->isNull())
					{
						if (static::$registrationEnabled)
						{
							return new self(static::getAccountPage(), static::REGISTER);
						}
						else
						{
							return new self(static::getAccountPage(), static::LOGIN);
						}
					}
					else
					{
						return new RedirectController(static::BASE_PATH);
					}

				case '/order/$order/':
					$order = Order::loadFor("reference", $matches["order"]);

					if(!$order->isNull() && $order->user === $user)
					{
						$controller = new self(static::getAccountPage(), static::ORDER);
						$controller->order = $order;
						return $controller;
					}
					else
					{
						return new RedirectController(static::BASE_PATH);
					}

				case '/payment/$payment/':
					$payment = BillPayment::loadFor("localReference", $matches["payment"]);

					if(!$payment->isNull() && $payment->user === $user)
					{
						$controller = new self(static::getAccountPage(), static::PAYMENT);
						$controller->payment = $payment;
						return $controller;
					}
					break;

				case '/orders/':
					return new self(static::getAccountPage(), static::ORDERS);

				case '/payments/':
					return new self(static::getAccountPage(), static::PAYMENTS);

				case '/reset-password/':
					return new self(static::getAccountPage(), static::RESET_PASSWORD);

				case '/reset-password/$requestId/$token/':
					return new self(static::getAccountPage(), static::CHANGE_PASSWORD, $matches["requestId"], $matches["token"]);
			}

			return null;
		}

		/**
		 * Gets the Account Page
		 * @return	Page	The account page
		 */
		public static function getAccountPage()
		{
			if(self::$accountPage === null)
			{
				$page = new Page;
				$page->name = "Account";
				$page->slug = trim(static::BASE_PATH, "/");

				self::$accountPage = $page;
			}

			return self::$accountPage;
		}

		/**
		 * Logs the user in and then sends them back to the previous page
		 * @return	UrlController	The controller that will return them to the previous page
		 */
		private static function login()
		{
			$errors = [];
			$redirect = null;

			if(!isset($_POST["email"]) || $_POST["email"] === "")
			{
				$errors[] = "Please enter your email address";
			}

			if(!isset($_POST["password"]) || trim($_POST["password"]) === "")
			{
				$errors[] = "Please enter your password";
			}

			if(count($errors) === 0)
			{
				$session = UserSession::loginWithEmailAndPassword($_POST["email"], $_POST["password"]);

				if(!$session->isNull())
				{
					$session->saveCookie();
					$session->user->cart->mergeWithTemporary();
				}
				else
				{
					$errors[] = "Incorrect email address or password";
					$redirect = static::BASE_PATH . "login/";
				}
			}
			
			if(isset($_GET["json"]))
			{
				return new JsonController(["success" => count($errors) === 0, "errors" => $errors]);
			}
			else
			{
				foreach($errors as $error)
				{
					addMessage($error);
				}
				
				return new RedirectController($redirect);
			}
		}

		/**
		 * Logs the user out and sends them to the homepage
		 * @return	RedirectController	The controller that will send them to the homepage
		 */
		private static function logout()
		{
			UserSession::get()->delete();
			return new RedirectController(null);
		}

		/**
		 * Registers the user and then sends them back to the previous page
		 * @return	RedirectController	The controller that will return them to the previous page
		 */
		private static function register()
		{
			$hasErrors = false;

			if (!static::$registrationEnabled)
			{
				addMessage("Registration is not enabled for this site");
				$hasErrors = true;
			}

			if(!isset($_POST["name"]) || $_POST["name"] === "")
			{
				addMessage("Please enter your name");
				$hasErrors = true;
			}

			if(!isset($_POST["email"]) || $_POST["email"] === "")
			{
				addMessage("Please enter your email address");
				$hasErrors = true;
			}
			else if(!isEmail($_POST["email"]))
			{
				addMessage("Email address is invalid, please enter a valid email address");
				$hasErrors = true;
			}
			else if(!User::loadFor("email", $_POST["email"])->isNull())
			{
				addMessage("That email address is already in use, please try another");
				$hasErrors = true;
			}

			if(!isset($_POST["password"]) || $_POST["password"] === "")
			{
				addMessage("Please enter your password");
				$hasErrors = true;
			}
			else if(strlen($_POST["password"]) < static::MIN_PASSWORD_LENGTH)
			{
				addMessage("Please enter a password of at least " . static::MIN_PASSWORD_LENGTH . " characters");
				$hasErrors = true;
			}

			if(!Form::passedCaptcha())
			{
				addMessage("Failed security check, please try again");
				$hasErrors = true;
			}

			if(!$hasErrors)
			{
				$user = new User();
				$user->name = $_POST["name"];
				$user->email = trim($_POST["email"]);
				$user->password = $_POST["password"];
				$user->save();

				$userSession = new UserSession();
				$userSession->user = $user;
				$userSession->saveCookie();
				$userSession->user->cart->mergeWithTemporary();

				return new RedirectController(null);
			}
			else
			{
				return new RedirectController(null);
			}
		}

		/**
		 * Resets the user's password and then sends them to the account page
		 * @return	RedirectController	The controller that will send them to the account page
		 */
		private static function resetPassword()
		{
			$hasErrors = false;

			if(!isset($_POST["requestId"]) || !isset($_POST["token"]))
			{
				addMessage("Reset token is missing");
				$hasErrors = true;
			}

			if(!isset($_POST["password"]) || $_POST["password"] === "")
			{
				addMessage("Please enter your new password");
				$hasErrors = true;
			}
			else if(strlen($_POST["password"]) < static::MIN_PASSWORD_LENGTH)
			{
				addMessage("Please enter a password of at least " . static::MIN_PASSWORD_LENGTH . " characters");
				$hasErrors = true;
			}

			if(!$hasErrors)
			{
				$resetPasswordRequest = ResetPasswordRequest::load($_POST["requestId"]);

				if(password_verify($_POST["token"], $resetPasswordRequest->tokenHash))
				{
					$resetPasswordRequest->user->password = $_POST["password"];
					$userSession = new UserSession();
					$userSession->user = $resetPasswordRequest->user;
					$userSession->saveCookie();
					$userSession->user->cart->mergeWithTemporary();
					$resetPasswordRequest->delete();

					addMessage("Password changed successfully");

					return new RedirectController(static::BASE_PATH);
				}
				else
				{
					addMessage("Invalid reset token");
				}
			}

			$url = sprintf(static::BASE_PATH . "reset-password/%d/%s/", $_POST["requestId"] ?? 0, $_POST["token"] ?? "missing");
			return new RedirectController($url);
		}

		/**
		 * Sends the user a reset password email, and then displays the reset password instructions page
		 * @return	UrlController	The appropriate controller
		 */
		private static function resetPasswordRequest()
		{
			$hasErrors = false;

			if(!isset($_POST["email"]) || $_POST["email"] === "")
			{
				addMessage("Please enter an email address");
				$hasErrors = true;
			}
			else if(!isEmail($_POST["email"]))
			{
				addMessage("Email address is invalid, please enter a valid email address");
				$hasErrors = true;
			}
			else if(!Form::passedCaptcha())
			{
				addMessage("Failed security check, please try again");
				$hasErrors = true;
			}

			if(!$hasErrors)
			{
				$user = User::loadFor("email", $_POST["email"]);

				if($user->isNull())
				{
					addMessage("No user with that email address exists within our system, please check your email address and try again");
				}
				else
				{
					$request = new ResetPasswordRequest();
					$request->user = $user;
					$request->save();

					$content = Twig::render("account/password-reset-email.twig",
					[
						"config" => Configuration::acquire(),
						"user" => $user,
						"request" => $request
					]);

					Mailer::sendEmail($content, "Password reset request", $user->email, "");

					return new self(static::getAccountPage(), static::RESET_PASSWORD_INSTRUCTIONS);
				}
			}

			return new RedirectController(static::BASE_PATH . "reset-password/");
		}

		/**
		 * Updates the user's details
		 * @return	RedirectController	Redirects the user back to the account page
		 */
		private static function updateDetails()
		{
			$user = User::get();

			if($user->isNull())
			{
				addMessage("You must be logged in to change your account details");
				return new RedirectController(static::BASE_PATH . "login/");
			}

			$hasErrors = false;

			if(!isset($_POST["name"]) || $_POST["name"] === "")
			{
				addMessage("Please enter your name");
				$hasErrors = true;
			}
			else
			{
				$user->name = $_POST["name"];
			}

			$existing = User::loadFor('email', $_POST["email"]);

			if(!isset($_POST["email"]) || $_POST["email"] === "")
			{
				addMessage("Please enter your email address");
				$hasErrors = true;
			}
			else if(!isEmail($_POST["email"]))
			{
				addMessage("Email address is invalid, please enter a valid email address");
				$hasErrors = true;
			}
			else if (!$existing->isNull() && $existing->id !== $user->id)
			{
				addMessage("That email address is already in use, please try another");
				$hasErrors = true;
			}
			else
			{
				$user->email = $_POST["email"];
			}

			if(trim($_POST["password"] ?? "") !== "")
			{
				if(strlen($_POST["password"]) < static::MIN_PASSWORD_LENGTH)
				{
					addMessage("Please enter a password of at least " . static::MIN_PASSWORD_LENGTH . " characters");
					$hasErrors = true;
				}
				else
				{
					$user->password = $_POST["password"];
				}
			}

			if(isset($_POST["shippingName"]))
			{
				$user->address->name = $_POST["shippingName"];
			}

			if(isset($_POST["phone"]))
			{
				$user->address->phone = $_POST["phone"];
			}

			if(isset($_POST["address"]))
			{
				$user->address->address = $_POST["address"];
			}

			if(isset($_POST["suburb"]))
			{
				$user->address->suburb = $_POST["suburb"];
			}

			if(isset($_POST["city"]))
			{
				$user->address->city = $_POST["city"];
			}

			if(isset($_POST["postCode"]))
			{
				$user->address->postCode = $_POST["postCode"];
			}

			if(isset($_POST["country"]))
			{
				$user->address->country = $_POST["country"];
			}

			if(isset($_POST["deliveryInstructions"]))
			{
				$user->address->deliveryInstructions = $_POST["deliveryInstructions"];
			}

			$user->save();

			if(!$hasErrors)
			{
				addMessage("Account details updated successfully");
			}

			return new RedirectController(static::BASE_PATH);
		}

		/**
		 * Updates the user's address
		 * @return	RedirectController	Redirects the user back to the account page
		 */
		private static function updateAddress()
		{
			$user = User::get();

			if($user->isNull())
			{
				addMessage("You must be logged in to change your address");
				return new RedirectController(static::BASE_PATH . "login/");
			}

			if ($user->address->isNull()) 
			{
				$user->address = new Address;
			}

			$hasErrors = false;

			if(isset($_POST["shippingName"]))
			{
				$user->address->name = $_POST["shippingName"];
			}

			if(isset($_POST["phone"]))
			{
				$user->address->phone = $_POST["phone"];
			}

			if(isset($_POST["address"]))
			{
				$user->address->address = $_POST["address"];
			}

			if(isset($_POST["suburb"]))
			{
				$user->address->suburb = $_POST["suburb"];
			}

			if(isset($_POST["city"]))
			{
				$user->address->city = $_POST["city"];
			}

			if(isset($_POST["postCode"]))
			{
				if($_POST["postCode"] !== "" && !ctype_digit($_POST["postCode"]))
				{
					addMessage("Postal code is invalid, please enter a valid postal code");
				}
				else
				{
					$user->address->postCode = $_POST["postCode"];
				}
			}

			if(isset($_POST["country"]))
			{
				$user->address->country = $_POST["country"];
			}

			if(isset($_POST["deliveryInstructions"]))
			{
				$user->address->deliveryInstructions = $_POST["deliveryInstructions"];
			}

			$user->save();

			if(!$hasErrors)
			{
				addMessage("Address updated successfully");
			}

			if (Registry::SHIPPING && !$user->cart->shippingAddress->isNull())
			{
				$user->cart->shippingAddress = clone $user->address;
			}

			return new RedirectController(static::BASE_PATH . "address/");
		}

		/**
		 * Creates a new Account Controller
		 * @param	Page	$page		The page generating the account
		 * @param	int		$type		The type of template to generate
		 * @param	int		$requestId	The identifier for the change password request (if appropriate)
		 * @param	string	$token		The token for the change password request (if appropriate)
		 */
		public function __construct(Page $page, $type = self::ACCOUNT, $requestId = null, $token = null)
		{
			parent::__construct($page);

			$this->type = $type;
			$this->requestId = $requestId;
			$this->token = $token;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		public function getTemplateLocation()
		{
			if (!User::get()->isNull())
			{
				switch($this->type)
				{
					case static::ADDRESS:
						return "account/address-page.twig";

					case static::ORDERS:
						return "account/orders-page.twig";

					case static::ORDER:
						return "account/order-page.twig";
					
					case static::PAYMENTS:
						return "account/payments.twig";
					
					case static::PAYMENT:
						return "account/payment.twig";
				}

				return "account/account-details-page.twig";
			}
			else
			{
				switch($this->type)
				{
					case static::REGISTER:
						return "account/register-page.twig";

					case static::RESET_PASSWORD:
						return "account/reset-password-page.twig";

					case static::CHANGE_PASSWORD:
						return "account/change-password-page.twig";

					case static::RESET_PASSWORD_INSTRUCTIONS:
						return "account/reset-password-instructions-page.twig";
				}

				return "account/login-page.twig";
			}
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		public function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();

			$variables["requestId"] = $this->requestId;
			$variables["token"] = $this->token;
			$variables["cartEnabled"] = Registry::CART;
			$variables["paymentsEnabled"] = Registry::BILL_PAYMENTS;
			$variables["order"] = $this->order;
			$variables["payment"] = $this->payment;
			$variables["registrationEnabled"] = static::$registrationEnabled;

			return $variables;
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			if(strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? "") == 'xmlhttprequest')
			{
				echo "You are not logged in. Please <a href='/admin/' target='_blank'>login</a> before trying again.";
			}
			else
			{
				parent::output();
			}
		}
	}
