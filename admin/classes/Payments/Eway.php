<?php
	namespace Payments;

	use Controller\Controller;
	use Controller\RedirectController;
	use Eway\Rapid;
	use Eway\Rapid\Client;
	use Eway\Rapid\Enum\ApiMethod;
	use Eway\Rapid\Enum\TransactionType;
	use Exception;
	
	/**
	 * eWAY payment gateway class
	 */
	class Eway extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging

		// Client's details
		private const API_ENDPOINT = self::TEST_MODE ? Client::MODE_SANDBOX : Client::MODE_PRODUCTION;
		// Test details here are Activate Design account credentials
		private const API_KEY = self::TEST_MODE ? '44DD7CStnf/p0d6JxVX0GIRlOU6dSBZeBtlK9/iJSc1EgyyXAUfkoymzvieIMU7GYzKCmP' : '';
		private const API_PASSWORD = self::TEST_MODE ?  '90K0rKkw' : '';
		private const CURRENCY = "NZD";

		private static $client = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		
		/**
		 * Gets the API client
		 * @return	Client
		 */
		public static function getClient()
		{
			if(self::$client === null)
			{
				self::$client = Rapid::createClient(self::API_KEY, self::API_PASSWORD, self::API_ENDPOINT);
			}

			return self::$client;
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "eWAY";
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return "Credit Card";
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    Controller    The controller that will handle the user
		 * @throws    Exception    If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$client = static::getClient();

			$transaction =
			[
				"Payment" =>
				[
					"TotalAmount" => $this->payment->amount * 100,
					"CurrencyCode" => self::CURRENCY
				],

				"RedirectUrl" => $this->getSuccessUrl(),
				"CustomerIP" => $_SERVER["REMOTE_ADDR"],
				"TransactionType" => TransactionType::PURCHASE,
				"Capture" => true
			];

			$response = $client->createTransaction(ApiMethod::RESPONSIVE_SHARED, $transaction);
			$errors = $response->getErrors();

			if(count($errors) > 0)
			{
				throw new Exception(Rapid::getMessage($errors[0]));
			}
			else
			{
				/* @phpstan-ignore-next-line */
				return new RedirectController($response->SharedPaymentUrl);
			}
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			// san check
			if(!isset($_GET["AccessCode"]))
			{
				return new PaymentResult(false, "eWAY data missing from URL");
			}
			//still here?
			
			$client = static::getClient();
			$response = $client->queryTransaction($_GET["AccessCode"]);
			$transactionResponse = $response->Transactions[0];
			
			/* @phpstan-ignore-next-line */
			if($transactionResponse->TransactionStatus)
			{
				return new PaymentResult(true);
			}
			else
			{
				/* @phpstan-ignore-next-line */
				$errors = explode(", ", $transactionResponse->ResponseMessage);
				return new PaymentResult(false, Rapid::getMessage($errors[0]));
			}
		}
	}