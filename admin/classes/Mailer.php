<?php
	use Configuration\Configuration;
	use PHPMailer\PHPMailer\Exception as MailerException;
	use PHPMailer\PHPMailer\PHPMailer;
	
	/**
	 * Extended PHPMailer class to send via SMTP by default, using defaults from Configuration
	 */
	class Mailer extends PHPMailer
	{
		private const USERNAME = "webmailer@activehost.co.nz";
		private const PASSWORD = "NTR7iJ,DMq4d";

		/**
		 * set properties to send via SMTP by default
		 */
		public $Mailer = 'smtp';
		public $Host = 'activehost.co.nz';
		public $Port = 465;
		public $SMTPSecure = 'ssl';
		public $SMTPAuth = true;
		public $Username = self::USERNAME;
		public $Password = self::PASSWORD;
		public $CharSet = "UTF-8";
		
		/**
		 * Generates a PHPMailer object with default site settings
		 * Note: This will automatically generate a non-HTML alternate body if $html is true
		 *
		 * @param	string				$body			The content to send
		 * @param	string				$subject		The subject of the email
		 * @param	string				$toAddress		The recipient of the email
		 * @param	string				$replyAddress	The ostensible sender of the email
		 * @param	bool				$html			Whether the content is HTML or plain text
		 * @return	Mailer								An instance of this class
		 * @throws	MailerException						If PHPMailer doesn't accept one of the addresses being passed to it
		 */
		public static function generateEmail(string $body, string $subject, string $toAddress = "", string $replyAddress = "", bool $html = true): self
		{
			// First parameter is whether to throw exceptions, assume we do if it's from an office IP address
			$mailer = new self(IS_DEBUG_IP);
			$mailer->Subject = $subject;
			$mailer->Body = $body;
			
			if($html)
			{
				$mailer->isHtml(true);
				
				// Need to provide an alternative non-HTML body for non-HTML clients if this is an HTML email
				$mailer->AltBody = strip_tags($body);
			}
			
			$defaultEmailAddress = Configuration::getAdminEmail();
			$mailer->addAddress(isEmail($toAddress) ? $toAddress : $defaultEmailAddress);
			$mailer->addReplyTo(isEmail($replyAddress) ? $replyAddress : $defaultEmailAddress);
			
			return $mailer;
		}
		
		/**
		 * Sends an email using default site settings
		 * @param	string				$body			The content to send
		 * @param	string				$subject		The subject of the email
		 * @param	string				$toAddress		The recipient of the email
		 * @param	string				$replyAddress	The ostensible sender of the email
		 * @param	string[]			$attachments	An associative array of attachments, path => outputted filename
		 * @param	bool				$html			Whether the content is HTML or plain text
		 * @return	bool                        		Whether the email successfully made it to the SMTP server
		 * @throws	MailerException						If generateEmail fails or adding an attachment fails
		 */
		public static function sendEmail(string $body, string $subject, string $toAddress = '', string $replyAddress = '', array $attachments = [], bool $html = true)
		{
			$mailer = static::generateEmail($body, $subject, $toAddress, $replyAddress, $html);
			
			foreach($attachments as $path => $filename)
			{
				$mailer->addAttachment($path, $filename);
			}
			
			try
			{
				// for debugging. Mailer only does exceptions if IS_DEBUG_IP is true
				// @see https://github.com/PHPMailer/PHPMailer/wiki/SMTP-Debugging
				// $mailer->SMTPDebug = 2; $mailer->send(); exit;
				return $mailer->send(); // boolean
			}
			catch(Exception $e)
			{
				// for debugging
				// echo $e->getMessage(); exit;
				addMessage($e->getMessage());
				
				return false;
			}
		}
		
		/**
		 * Constructor.
		 *
		 * @param bool $exceptions Should we throw external exceptions?
		 */
		public function __construct(bool $exceptions = null)
		{
			parent::__construct($exceptions);
			
			// set default From email and name
			// can't call methods in property setup
			$this->From = Configuration::getAdminEmail();
			$this->FromName = Configuration::getSiteName();
		}
	}