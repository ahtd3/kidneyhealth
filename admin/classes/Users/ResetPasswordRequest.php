<?php
	namespace Users;

	use Core\Entity;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Database\Database;
	use DateTimeImmutable;
	
	/**
	 * A request that a user has made to reset their password
	 */
	class ResetPasswordRequest extends Entity
	{
		const TABLE = "reset_password_requests";
		const ID_FIELD = "reset_password_request_id";
		const PARENT_PROPERTY = "user";

		const EXPIRY_LENGTH = "+1 day";

		public $tokenHash = "";
		public $token = "";

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
		 * Flushes the requests that have expired
		 */
		public static function flushExpired()
		{
			$query = "DELETE FROM ~TABLE "
				   . "WHERE ~expires < ?";

			Database::query(static::processQuery($query), [date("Y-m-d H:i:s")]);
		}
		
		/**
		 * Creates a new Reset Password Request
		 */
		public function __construct()
		{
			parent::__construct();

			$this->token = bin2hex(openssl_random_pseudo_bytes(16));
			$this->tokenHash = password_hash($this->token, PASSWORD_DEFAULT);
			$this->expires = new DateTimeImmutable(static::EXPIRY_LENGTH);
		}
	}

	ResetPasswordRequest::flushExpired();
