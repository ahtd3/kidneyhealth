<?php
	namespace Forms;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Configuration\Configuration;
	use Configuration\Registry;
	use Controller\Twig;
	use Core\Columns\CustomColumn;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Editor;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\Property;
	use Forms\Submissions\FormSubmission;
	use ReCaptcha\ReCaptcha;
	use Template\ShortCodes\ShortCode;
	use Template\ShortCodes\ShortCodeSupport;
	use Twig\Error\Error;

	/**
	 * Class for making it easier for a user to edit a basic form
	 */
	class Form extends Generator implements AdminNavItemGenerator, ShortCodeSupport
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		/** @var	string	The table that forms are stored in */
		const TABLE = "forms";

		/** @var	string	The field name for the primary key for the table */
		const ID_FIELD = "form_id";

		/** @var	string	The text to display to the user when the context of an operation is a single object */
		const SINGULAR = "Form";

		/** @var	string	The text to display to the user when the context of an operation is multiple objects */
		const PLURAL = "Forms";

		/** @var	string	The property to display to the user when operations are performed	 */
		const LABEL_PROPERTY = "name";

		// Form
		/** @var 	bool	Toggle between placeholders and labels */
		const PLACEHOLDERS = true;

		/** @var 	int		The id of the default contact form */
		const CONTACT_ID = 1;

		/** @var 	int|null		The id of the product enquiry form */
		const PRODUCT_ENQUIRY_ID = null;

		/** @var 	int|null		The id of the shipping enquiry form, for when your order is over the weight limit of a shipping region */
		const SHIPPING_ENQUIRY_ID = null;

		/** @var 	array<int|null>	The ids of forms which can not be deleted from the admin panel */
		const NO_DELETE_FORM_IDS = [self::CONTACT_ID, self::PRODUCT_ENQUIRY_ID, self::SHIPPING_ENQUIRY_ID];

		/**
		 * @var	string	The name of the form
		 * @var string $buttonText appears on the Submit button
		 * @var string $recipient email form is sent to
		 */
		public $name = "";
		public $recipient = '';
		public $buttonText = 'Send';

		public $response = '';

		/** @var	FormField[]		The Form Fields in this Form */
		public $fields = null;

		public $prepend = '';
		public $append = '';

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Sets the properties for this type of object
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new Property("name", "name", "string"));
			static::addProperty(new Property("recipient", "recipient", "string"));
			static::addProperty(new Property("buttonText", "button_text", "string"));
			static::addProperty(new Property("response", "response", "html"));
			static::addProperty(new LinkFromMultipleProperty("fields", FormField::class, "form"));
		}

		/**
		 * Sets the columns for this type of object
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn("name", "Name"));

			static::addColumn(new CustomColumn("includeCode", "Include code", function(Form $generator)
			{
				return ShortCode::generate($generator);
			}));

			parent::columns();
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Creates a new Form
		 */
		public function __construct()
		{
			parent::__construct();

			$this->recipient = Configuration::getAdminEmail();
			$this->response = '<h2>Thank you for contacting ' . Configuration::getSiteName() . '</h2><p>We will reply shortly.</p>';
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement(new Text("name", "Name"), "Details");
			$this->addElement(new Text("recipient", "Recipient"), "Details");
			$this->addElement(new Text("buttonText", "Button text"), "Details");
			$this->addElement(new Editor("response", "Response"), "Details");
			$this->addElement(new GeneratorElement("fields"), "Fields");
		}

		/**
		 * Gets the email recipient for this form
		 * @return	string	The recipient, if there isn't a valid email set make sure we use the admin email instead
		 */
		public function get_recipient()
		{
			$recipient = $this->getProperty('recipient');

			if(!isEmail($recipient))
			{
				$recipient = Configuration::getAdminEmail();
			}

			return $recipient;
		}

		/**
		 * Gets this form's submitted status from session
		 * @return	bool	Whether this form has been submitted
		 */
		public function getSubmitted()
		{
			return isset($_SESSION['submitted']['form'.$this->id]) ? $_SESSION['submitted']['form'.$this->id] : false;
		}

		/**
		 * Gets html to display success message
		 * @return	string	success message
		 * @throws	Error	If something goes wrong during rendering
		 */
		public function getSuccessMessage()
		{
			return Twig::render("forms/form-submitted.twig",
			[
				"form" => $this
			]);
		}

		/**
		 * Checks it this Generator is allowed to be deleted from the admin panel
		 * @return	bool	Whether it's allowed to be deleted
		 */
		public function hasDelete()
		{
			if (in_array($this->id, static::NO_DELETE_FORM_IDS))
			{
				return false;
			}
			else
			{
				return true;
			}
		}

		/**
		 * Outputs the HTML for this form
		 * @param	string	$action 	Where to send this form
		 * @param	string	$method 	What type this form is
		 * @return	string				The HTML for this form
		 * @throws	Error				If something goes wrong during Twig rendering
		 */
		public function outputForm($action = null, $method = "post")
		{
			if($action === null)
			{
				$action = "/Form/Submit/";

				if($this->id !== null)
				{
					$action .= "{$this->id}/";
				}
			}

			if ($this->getSubmitted())
			{
				$this->setSubmitted(false);
				return $this->getSuccessMessage();
			}
			else
			{
				return Twig::render("forms/form.twig",
				[
					"form" => $this,
					"action" => $action,
					"method" => $method,
					"class" => "js-ajax-form",
                    "config" => Configuration::acquire()
				]);
			}
		}

		/**
		 * Whether CAPTCHA has been successfully validated for the current page
		 * @return    bool    Whether or not it passed
		 */
		public static function passedCaptcha()
		{
			$config = Configuration::acquire();
			// presence of $_POST['g-recaptcha-response'] tells us if the visitor had javascript enabled
			// if they didn't we fall back to the image captcha
			if($config->recaptchaSiteKey !== '' && $config->recaptchaSecret !== '' && isset($_POST['g-recaptcha-response']))
			{
				// https://github.com/google/recaptcha
				$recaptcha = new ReCaptcha($config->recaptchaSecret);
				$resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
				if ($resp->isSuccess()) {
					// verified!
					// if Domain Name Validation turned off don't forget to check hostname field
					// if($resp->getHostName() === $_SERVER['SERVER_NAME']) {  }
					return true;
				} else {
					//add_message(print_r($resp->getErrorCodes(), true)); // debugging
					return false;
				}
			}
			// else
			if(!isset($_SESSION["security_code"]))
			{
				return false;
			}
			// else
			$names = ["auth", "security_code"];
			foreach($names as $name)
			{
				if(isset($_POST[$name]))
				{
					$index = array_search($_POST[$name], $_SESSION["security_code"]);

					if($index !== false)
					{
						unset($_SESSION["security_code"][$index]);

						return true;
					}
				}
			}
			// else

			return false;
		}

		/**
		 * Store the submitted status of a form in the session
		 * @param	bool	$submitted	Whether the form has been submitted or not
		 */
		public function setSubmitted($submitted)
		{
			$_SESSION['submitted']['form' . $this->id] = $submitted;
			unset($_SESSION['formdata']['form' . $this->id]);
		}

		/**
		 * Stores a temporary message to be output with the form next time it is displayed by the user
		 * @param	string	$message	Message for the user to see
		 */
		public function storeUserMessage($message)
		{
			$_SESSION["messages"]["form{$this->id}"] = (string) $message;
		}

		/**
		 * outputs the removes any temporary messages stored by the form process script
		 *
		 * @return string
		 */
		public function retrieveUserMessage()
		{
			$id = "form{$this->id}";
			$message = '';

			if(isset($_SESSION['messages']) && isset($_SESSION['messages'][$id]))
			{
				$message = $_SESSION['messages'][$id];
				unset($_SESSION['messages'][$id]);
			}

			return $message;
		}

		/**
		 * Server side validation of fields
		 * @return	string[]	Any error messages to display to the user
		 */
		public function validateForm()
		{
			$messages = [];

			foreach($this->fields as $field)
			{
				$message = $field->validateField();

				if($message !== null)
				{
					$messages[] = $message;
				}
			}

			return $messages;
		}

		/*~~~~~
		 * Interface methods
		 **/

		//region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(static::getAdminNavLink(), "Forms", [static::class], Registry::FORMS
			, [new AdminNavItem(FormSubmission::getAdminNavLink(), FormSubmission::PLURAL, [FormSubmission::class])]
			);
		}

		//endregion

		//region ShortCodeSupport

		/**
		 * Gets a unique identifier for this class
		 * @return    string    An identifier that uniquely identifies this class
		 */
		public static function getClassShortcodeIdentifier()
		{
			return "Form";
		}

		/**
		 * Format and output properties as a coherent string of HTML
		 * @return	string	The HTML for the gallery
		 * @throws	Error	If something goes wrong while rendering the gallery
		 */
		public function getShortcodeContent()
		{
			return $this->outputForm();
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param	string $identifier 	The identifier to load from
		 * @return	ShortCodeSupport	An object that can be outputted to the page, or null if the correct one cannot be found
		 */
		public static function loadForShortcodeIdentifier($identifier)
		{
			$form = static::load($identifier);

			return $form->isNull() ? null : $form;
		}

		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getShortcodeIdentifier()
		{
			return $this->id;
		}

		//endregion
	}
