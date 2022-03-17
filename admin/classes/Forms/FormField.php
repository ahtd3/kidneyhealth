<?php
	namespace Forms;

	use Core\Elements\Checkbox;
	use Core\Elements\FormOption;
	use Core\Elements\Group;
	use Core\Elements\Select;
	use Core\Elements\Text;
	use Core\Elements\Textarea;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Error;
	use Slugging\Slugging;
	
	/**
	 * A single field that belongs to a Form
	 */
	class FormField extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		/** @var	string	The table where the Form Fields are stored */
		const TABLE = "form_fields";

		/** @var	string	The field name for the primary key of the Form Fields */
		const ID_FIELD = "form_field_id";

		/** @var	string	The text to display to the user when the context of an operation is a single object */
		const SINGULAR = "Form Field";

		/** @var	string	The text to display to the user when the context of an operation is multiple objects */
		const PLURAL = "Fields";

		/** @var	string	The property to display to the user when operations are performed  */
		const LABEL_PROPERTY = "label";

		/** @var	bool	Whether Form Fields have a position */
		const HAS_POSITION = true;

		const PARENT_PROPERTY = "form";
		
		// FormField

		/** @var	string	Enum identifier for a text type of Form Field */
		const TEXT = "TEXT";

		/** @var	string	Enum identifier for an email type of Form Field */
		const EMAIL = "EMAIL";

		/** @var	string	Enum identifier for a phone type of Form Field */
		const PHONE = "PHONE";

		/** @var	string	Enum identifier for a textarea type of Form Field */
		const TEXTAREA = "TEXTAREA";

		/** @var	string	Enum identifier for a checkbox group type of Form Field */
		const CHECKBOXES = "CHECKBOXES";

		/** @var	string	Enum identifier for a radio button group type of Form Field */
		const RADIO = "RADIO";

		/** @var	string	Enum identifier for a select box type of Form Field */
		const SELECT = "SELECT";

		/** @var	string	Enum identifier for a date type of Form Field */
		const DATE = "DATE";

		/** @var	string	Enum identifier for a password type of Form Field */
		const PASSWORD = "PASSWORD";

		/** @var	string	Enum identifier for a file type of Form Field */
		const UPLOAD = "UPLOAD";

		/** @var	string	Enum identifier for a general heading */
		const HEADING = "HEADING";

		/** @var	string	Enum identifier for a hidden field */
		const HIDDEN = "HIDDEN";

		/** @var	string	The separator between a field's name and a junk unique identifier */
		const NAME_SEPARATOR = "^";

		/** @var	string	The type of Form Field this Form Field is */
		public $type = self::TEXT;

		/** @var	string	The class to wrap this field in for styling/script hooks */
		public $classes = '';

		/** @var	string	The label to display next to this Form Field */
		public $label = "";

		/** @var	string	The default value to give this Form Field */
		public $defaultValue = "";

		/** @var	string	The possible values for this Form Field */
		public $values = "";

		/** @var	bool	Whether this Form Field is required */
		public $required = false;

		/** @var	Form	The Form this Form Field belongs to */
		public $form = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * The properties that define how this object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new LinkToProperty("form", "form_id", Form::class));
			static::addProperty(new Property("type", "type", "string"));
			static::addProperty(new Property("label", "label", "string"));
			static::addProperty(new Property("defaultValue", "default_value", "string"));
			static::addProperty(new Property("values", "values", "string"));
			static::addProperty(new Property("position", "position", "int"));
			static::addProperty(new Property("required", "required", "bool"));
		}

		/**
		 * Outputs the types of form field this field could be
		 * @return	string[]	Said fields
		 */
		public static function outputAllTypes()
		{
			return
			[
				new FormOption("Text", self::TEXT),
				new FormOption("Email", self::EMAIL),
				new FormOption("Phone", self::PHONE),
				new FormOption("Date", self::DATE),
				new FormOption("Text Box", self::TEXTAREA),
				new FormOption("Checkboxes", self::CHECKBOXES),
				new FormOption("Radio Buttons", self::RADIO),
				new FormOption("Dropdown Menu", self::SELECT),
				new FormOption("Upload File", self::UPLOAD),
				new FormOption("Section Heading", self::HEADING)
			];
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$columns = (new Group("columns"))->addClass("columns");
			
			$left = (new Group("left"))->addClass("column");
			$right = (new Group("right"))->addClass("column");
			
			$heading = json_encode(self::HEADING);
			$upload = json_encode(self::UPLOAD);
			$checkboxes = json_encode(self::CHECKBOXES);
			$radio = json_encode(self::RADIO);
			$select = json_encode(self::SELECT);
			
			$left->add((new Text("label", "Label"))->addValidation(Text::REQUIRED));
			$left->add((new Text("defaultValue", "Default Value"))->setDescription("Comma separated for checkboxes")->setConditional("return type !== {$heading} && type !== {$upload}"));
			$left->add((new Checkbox("required", "Required"))->setConditional("return type !== {$heading}"));
			
			$right->add(new Select("type", "Type", static::outputAllTypes()));
			$right->add((new Textarea("values", "Options"))->setHint("One per line")->setConditional("return type === {$checkboxes} || type === {$radio} || type === {$select}"));
			
			$columns->add($left);
			$columns->add($right);
			$this->addElement($columns);
		}

		/**
		 * Formats the name of this Form Field so that it doesn't conflict with any other
		 * @return	string	The formatted name
		 */
		public function formatName()
		{
			return Slugging::slug(trim($this->label)) . self::NAME_SEPARATOR . $this->id;
		}
		
		/**
		 * Gets the current value in $_POST for this field
		 */
		public function getPostValue()
		{
			if($this->type === static::HEADING)
			{
				return null;
			}
			else if($this->type === static::UPLOAD)
			{
				return $_FILES[$this->formatName()] ?? null;
			}
			else
			{
				return $_POST[$this->formatName()] ?? null;
			}
		}
		
		/**
		 * Gets the stored value for this field from the session
		 * @return	string|string[]		The stored value
		 */
		public function getStoredValue()
		{
			$value = $this->defaultValue;
			$name = $this->formatName();
			$cleanName = Slugging::slug($name);

			if(isset($_SESSION['formdata']['form' . $this->form->id][$name]))
			{
				$value = $_SESSION['formdata']['form' . $this->form->id][$name];
			}
			elseif(isset($_GET[$cleanName]))
			{
				$value = $_GET[$cleanName];
			}

			return $value;
		}
		
		/**
		 * Gets the template for this field
		 * @return	string	The path to the template for this field
		 */
		public function getTemplate()
		{
			$base = "forms/";

			switch($this->type)
			{
				case self::TEXT:
					return $base . "text-field.twig";

				case self::EMAIL:
					return $base . "email-field.twig";

				case self::PHONE:
					return $base . "phone-field.twig";

				case self::TEXTAREA:
					return $base . "textarea.twig";

				case self::CHECKBOXES:
					return $base . "checkboxes.twig";

				case self::RADIO:
					return $base . "radio-buttons.twig";

				case self::SELECT:
					return $base . "select-box.twig";

				case self::DATE:
					return $base . "date-picker.twig";

				case self::PASSWORD:
					return $base . "password-field.twig";

				case self::UPLOAD:
					return $base . "file-picker.twig";

				case self::HEADING:
					return $base . "heading.twig";

				case self::HIDDEN:
					return $base . "hidden-field.twig";

				default:
					throw new Error("No template defined for this type of form field");
			}
		}

		/**
		 * Gets the variables for this field
		 * @return	array	The variables for this field
		 */
		public function getVariables()
		{
			$variables = [];

			switch($this->type)
			{
				case self::TEXT:
				case self::EMAIL:
				case self::PHONE:
				case self::TEXTAREA:
				case self::DATE:
				case self::PASSWORD:
				case self::HIDDEN:
					$variables["label"] = $this->label;
					$variables["usePlaceholder"] = $this->form::PLACEHOLDERS;
					$variables["name"] = $this->formatName();
					$variables["value"] = $this->getStoredValue();
					$variables["required"] = $this->required;
				break;

				case self::CHECKBOXES:
				case self::RADIO:
				case self::SELECT:
					$options = [];

					if (trim($this->values) !== '') 
					{
						foreach(explode("\n", $this->values) as $option)
						{
							$options[$option] = trim($option);
						}
					}

					$variables["label"] = $this->label;
					$variables["values"] = $options;
					$variables["usePlaceholder"] = $this->form::PLACEHOLDERS;
					$variables["name"] = $this->formatName();
					$variables["selected"] = $this->getStoredValue();
					$variables["required"] = $this->required;
				break;

				case self::UPLOAD:
					$accepts = [];

					if (trim($this->values) !== '') 
					{
						foreach(explode("\n", $this->values) as $option)
						{
							$option = (strpos($option, '.') === false) ? '.' . $option : $option;
							$accepts[$option] = trim($option);
						}
					}

					$variables["label"] = $this->label;
					$variables["name"] = $this->formatName();
					$variables["required"] = $this->required;
					$variables["usePlaceholder"] = $this->form::PLACEHOLDERS;
					$variables["accepts"] = $accepts;
					
				break;

				case self::HEADING:
					$variables["heading"] = $this->label;
					$variables["content"] = $this->values;
					$variables["name"] = $this->formatName();
				break;
			}

			return $variables;
		}

		/**
		* Validate this field from $_POST
		*
		* @return String $message 	any error messages to display to the user
		*/
		public function validateField()
		{
			// No validation for headings
			if($this->type == static::HEADING)
			{
				return null;
			}
			
			$result = $this->getPostValue();
			
			if($this->type !== static::UPLOAD)
			{
				if($this->required)
				{
					if($result === "" || $result === null)
					{
						return "The {$this->label} field is required.";
					}
					else if($this->type === FormField::EMAIL && !isEmail($result))
					{
						return "Please enter a valid email address.";
					}
				}
			}
			else
			{
				if($this->required && ($result === null || $result['tmp_name'] === ""))
				{
					return "The {$this->label} field is required.";
				}
			}

			return null;
		}
	}
