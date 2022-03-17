<?php
	namespace Users;
	
	use Core\Entity;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Database\Database;
	use DateTimeImmutable;
	
	/**
	 * A User Session is a session that a user has logged in with
	 */
	class UserSession extends Entity
	{
		const TABLE = "user_sessions";
		const ID_FIELD = "user_session_id";
		const PARENT_PROPERTY = "user";
		
		const EXPIRY_LENGTH = "+1 month";
		const SESSION_ID_COOKIE = "alice_session_id";
		const SESSION_TOKEN_COOKIE = "alice_session_token";
		
		private static $current = null;
		
		public $tokenHash = "";
		private $token = "";
		
		/** @var DateTimeImmutable */
		public $expires = null;
		
		/** @var User */
		public $user = null;
		
		/**
		 * Sets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new Property("tokenHash", "token_hash", "string"));
			static::addProperty(new Property("expires", "expires", "datetime"));
			static::addProperty(new LinkToProperty("user", "user_id", User::class));
		}
		
		/**
		 * Attempts to get the currently user session
		 * @return	self	The current session, or a null object
		 */
		public static function get(): self
		{
			if(self::$current === null)
			{
				self::$current = static::loginFromCookies();
			}
			
			return self::$current ?? static::makeNull();
		}
		
		/**
		 * Logs a user in with an email and password
		 * @param	string	$email		The user's email address
		 * @param	string	$password	The user's password
		 * @return	static				A User Session for this login
		 */
		public static function loginWithEmailAndPassword(string $email, string $password): self
		{
			$user = User::loadForEmailAndPassword($email, $password);
			
			if($user->isNull())
			{
				return static::makeNull();
			}
			
			$session = new self();
			$session->user = $user;
			$session->save();
			self::$current = $session;
			User::set($user);
			
			return $session;
		}
		
		/**
		 * Logs a user in with a session identifier and a token
		 * @param	int		$sessionId	The identifier for the session
		 * @param	string	$token		The token to log the user in with
		 * @return	static				The user session for that token
		 */
		public static function loginWithSessionIdAndToken(int $sessionId, string $token): self
		{
			$session = static::load($sessionId);
			
			if($session->isNull())
			{
				// Waste a little bit of time, to slow down attacks
				password_hash($token, PASSWORD_DEFAULT);
				return $session;
			}
			
			if(password_verify($token, $session->tokenHash) && $session->user->active)
			{
				if(password_needs_rehash($session->tokenHash, PASSWORD_DEFAULT))
				{
					$session->tokenHash = password_hash($token, PASSWORD_DEFAULT);
				}
				
				$session->expires = new DateTimeImmutable(static::EXPIRY_LENGTH);
				$session->user->lastActive = new DateTimeImmutable();
				$session->token = $token;
				$session->save();
				self::$current = $session;
				
				return $session;
			}
			else
			{
				return static::makeNull();
			}
		}
		
		/**
		 * Logs in the user from their cookies
		 * @return	static	The session for the logged in user
		 */
		public static function loginFromCookies(): self
		{
			if(isset($_SESSION["sessionId"]))
			{
				return static::load($_SESSION["sessionId"]);
			}
			
			$session = static::loginWithSessionIdAndToken((int) ($_COOKIE[static::SESSION_ID_COOKIE] ?? 0), $_COOKIE[static::SESSION_TOKEN_COOKIE] ?? "");
			$session->saveCookie();
			
			return $session;
		}
		
		/**
		 * Flushes the sessions that have expired
		 */
		public static function flushExpired()
		{
			$query = "DELETE FROM ~TABLE "
				   . "WHERE ~expires < ?";
			
			Database::query(static::processQuery($query), [date("Y-m-d H:i:s")]);
		}
		
		/**
		 * Creates a new User Session
		 */
		public function __construct()
		{
			parent::__construct();
			
			$this->token = bin2hex(openssl_random_pseudo_bytes(16));
			$this->tokenHash = password_hash($this->token, PASSWORD_DEFAULT);
			$this->expires = new DateTimeImmutable(static::EXPIRY_LENGTH);
		}
		
		/**
		 * Saves or updates a cookie for use with this session
		 */
		public function saveCookie()
		{
			if($this->isNull())
			{
				return;
			}
			
			if($this->id === null)
			{
				$this->save();
			}
			
			$_SESSION["sessionId"] = $this->id;
			
			if(PHP_SAPI === "cli")
			{
				return;
			}
			
			setcookie(static::SESSION_ID_COOKIE, $this->id, $this->expires->format("U"), "/");
			setcookie(static::SESSION_TOKEN_COOKIE, $this->token, $this->expires->format("U"), "/");
		}
	}
	
	UserSession::flushExpired();