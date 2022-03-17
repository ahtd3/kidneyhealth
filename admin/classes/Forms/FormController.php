<?php
	namespace Forms;

	use Configuration\Configuration;
	use Controller\JsonController;
	use Controller\RedirectController;
	use Controller\Twig;
	use Controller\UrlController;
	use Forms\Submissions\FormSubmission;
	use Mailer;
	use Pages\Page;
	use Pages\PageController;
	
	/**
	 * Handles the submission of forms
	 */
	class FormController extends PageController
	{
		/** @var string */
		const BASE_PATH = "/form/";

		/** @var string[] */
		// These fields will not be output to emails
		const IGNORED_FIELDS = ['id', 'g-recaptcha-response', 'security_code', 'auth', 'submit'];
		
		/** @var bool */
		// for SEO integrations which require a specific thank you page
		const REDIRECT_ON_SUCCESS = false;
		
		/** @var bool */
		private $isSuccessRedirect = false;

		/** @var Form */
		private $form;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return	UrlController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/submit/$form/' => self::class,
				'/success/$form/' => self::class
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
			$controller = new self(new Page);

			if(isset($matches["form"]))
			{
				$form = Form::load($matches["form"]);
				
				if($form->isNull())
				{
					return null;
				}
				elseif(strpos($pattern, '/success/') !== false)
				{
					$controller->page->name = $form->name;
					$controller->page->title = 'Thank you for contacting ' . Configuration::getSiteName();
					$controller->isSuccessRedirect = true;
				}
			}
			else
			{
				$form = Form::makeNull();
			}

			$controller->form = $form;

			return $controller;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		protected function getTemplateLocation()
		{
			if($this->isSuccessRedirect)
			{
				return 'forms/form-success.twig'; 
			}
			else
			{
				return null;
			}
		}

		/**
		 * Cleans up POST data
		 * @param	array	$data	The data to cleanup
		 * @return	array			The cleaned up POST data
		 */
		private function cleanUpPost(array $data)
		{
			$fields = [];

			foreach($data as $key => $value)
			{
				$name = explode(FormField::NAME_SEPARATOR, $key)[0];
				$name = str_replace(["-", "_"], " ", $name);

				// Ignore any fields in the $doNotIncludeFields array
				if(in_array(strtolower($name), static::IGNORED_FIELDS))
				{
					continue;
				}

				$outputValue = $value;

				if(is_array($value))
				{
					$outputValue = $this->cleanUpPost($value);
				}

				$fields[is_int($key) ? "_{$key}" : $name]  = $outputValue;
			}

			return $fields;
		}

		/**
		 * Gets any extra POST values that are not part of the stored form
		 * @return	string[]	Key/value pairs for orphaned values
		 */
		private function getOrphanedValues()
		{
			$explicitFields = static::IGNORED_FIELDS;

			foreach($this->form->fields as $field)
			{
				$explicitFields[] = $field->formatName();
			}

			$orphanedValues = [];

			foreach($_POST as $key => $value)
			{
				if(!in_array($key, $explicitFields))
				{
					$orphanedValues[$key] = $value;
				}
			}

			return $this->cleanUpPost($orphanedValues);
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			if($this->form === null)
			{
				return parent::getTemplateVariables() + ["fields" => $this->cleanUpPost($_POST)];
			}
			else
			{
				return parent::getTemplateVariables() +
				[
					"form" => $this->form,
					"orphanedValues" => $this->isSuccessRedirect ? [] : $this->getOrphanedValues()
				];
			}
		}

		/**
		 * Gets the body for the email
		 * @return	string	The email body
		 */
		protected function getEmailBody()
		{
			$variables = $this->getTemplateVariables();

			if($this->form === null)
			{
				$template = "forms/emails/general-email.twig";
			}
			else
			{
				$template = "forms/emails/forms-module-email.twig";
			}

			return Twig::render($template, $variables);
		}

		/**
		 * Gets the subject for the email
		 * @return	string	The email subject
		 */
		protected function getEmailSubject()
		{
			$config = Configuration::acquire();
			return "Enquiry from {$config::getSiteName()} website";
		}

		/**
		 * Gets the recipient of the email
		 * @return	string	The email recipient
		 */
		protected function getEmailRecipient()
		{
			return $this->form->recipient;
		}

		/**
		 * Gets the reply address for the email
		 * @return	string	The reply address
		 */
		protected function getReplyAddress()
		{
			foreach($_POST as $key => $value)
			{
				if(stripos($key,'email') !== false && isEmail($value))
				{
					return $value;
				}
			}

			// An empty string will be replaced with the default email address on send
			return "";
		}

		/**
		 * Gets the (best guess) name of the sender
		 * @return	string	The name
		 */
		protected function getSenderName()
		{
			foreach($_POST as $key => $value)
			{
				if(stripos($key,'name') !== false && !is_array($value))
				{
					return $value;
				}
			}
			// no fields which might be a name?
			return "";
		}

		/**
		 * Gets the attachments for the email
		 * @return	string[]	The attachments to add to the email
		 */
		protected function getEmailAttachments()
		{
			$attachments = [];

			foreach($_FILES as $file)
			{
				$attachments[$file['tmp_name']] = $file['name'];
			}

			return $attachments;
		}

		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			if($this->isSuccessRedirect)
			{
				parent::output();
			}
			else
			{
				// Store post data, in case we need to repopulate the form later
				$_SESSION["formdata"]["form{$this->form->id}"] = $_POST;

				$messages = [];

				if(!Form::passedCaptcha())
				{
					$messages[] = "Verification failed, please try again.";
				}

				$messages = array_merge($messages, $this->form->validateForm());

				// Errors, send failure message back to form
				if(count($messages) > 0)
				{
					$this->outputVerificationFailure($messages);
				}
				// Valid form submission, send email
				else
				{
					$sendSuccess = $this->sendEmail();

					if($sendSuccess)
					{
						$this->recordSubmission();
						$this->outputSendSuccess();
					}
					else
					{
						$this->outputSendFailure();
					}
				}
			}
		}

		/**
		 * Checks if the POST data was submitted via AJAX
		 * @return	bool	Whether it's an AJAX request
		 */
		public function isAjaxRequest()
		{
			return isset($_GET['ajaxed']);
		}

		/**
		 * Verification failed, display error messages
		 * @param	string[]	$messages	The messages to output to the user
		 */
		protected function outputVerificationFailure(array $messages)
		{
			$message = implode("<br />\n", $messages);

			// Form was submitted via AJAX
			if($this->isAjaxRequest())
			{
				$data =
				[
					"success" => false,
					"message" => $message
				];

				(new JsonController($data))->output();
			}
			// Form was submitted via normal browser action
			else
			{
				$this->form->storeUserMessage($message);
				(new RedirectController(null))->output();
			}
		}

		/**
		 * Sends the email to the appropriate location
		 * @return	bool	Whether the email was sent successfully
		 */
		protected function sendEmail()
		{
			$html = $this->getEmailBody();
			$subject = $this->getEmailSubject();
			$recipient = $this->getEmailRecipient();
			$replyAddress = $this->getReplyAddress();
			$attachments = $this->getEmailAttachments();

			return Mailer::sendEmail($html, $subject, $recipient, $replyAddress, $attachments, true);
		}

		/**
		 * Logs the submission to the database
		 */
		public function recordSubmission()
		{
			$log = new FormSubmission;
			$log->form = $this->form;
			$log->formName = $this->form->name;
			$log->subject = $this->getEmailSubject();
			$log->senderEmail = $this->getReplyAddress();
			$log->senderName = $this->getSenderName();
			$log->recipient = $this->getEmailRecipient();
			$log->content = $this->getEmailBody();
			$log->files = implode("\n", $this->getEmailAttachments());
			// timestamp is taken care of by the default values
			$log->save();
		}

		/**
		 * Outputs send email success
		 */
		public function outputSendSuccess()
		{
			$this->form->setSubmitted(true);

			if($this->isAjaxRequest())
			{
				$data =
				[
					"success" => true,
					"message" => $this->form->outputForm()
				];
				
				if(static::REDIRECT_ON_SUCCESS)
				{
					$data['redirect'] = "/Form/Success/{$this->form->id}/";
				}

				(new JsonController($data))->output();
			}
			else
			{
				(new RedirectController(static::REDIRECT_ON_SUCCESS ? "/Form/Success/{$this->form->id}/" : null))->output();
			}
		}

		/**
		 * Outputs send email failure
		 */
		public function outputSendFailure()
		{
			$message = "Sorry, a technical issue prevented the email from being sent. Please try again shortly.";

			if($this->isAjaxRequest())
			{
				$data =
				[
					"success" => false,
					"message" => $message
				];

				(new JsonController($data))->output();
			}
			else
			{
				$this->form->storeUserMessage($message);
				(new RedirectController(null))->output();
			}
		}
	}