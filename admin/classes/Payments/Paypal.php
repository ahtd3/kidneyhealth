<?php
	namespace Payments;

	use Controller\RedirectController;
	use Exception;
	use Orders\OrderPayment;
	use PayPal\Api\Amount;
	use PayPal\Api\Item;
	use PayPal\Api\ItemList;
	use PayPal\Api\Payer;
	use PayPal\Api\Payment as PaypalPayment;
	use PayPal\Api\PaymentExecution;
	use PayPal\Api\RedirectUrls;
	use PayPal\Api\Transaction;
	use PayPal\Auth\OAuthTokenCredential;
	use PayPal\Rest\ApiContext;
	
	/**
	 * Handles payments using PayPal
	 */
	class Paypal extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging
		private const API_MODE = self::TEST_MODE ? 'sandbox' : 'live';
		
		// Our account details or Client's account details
		private const API_KEY = self::TEST_MODE ? 'AQWR-nGF5JDDHYeE8OX59rSyNFngl6kpKY0OYGxNu637QlnEFnWstRQBCxMCZcG_xC39tmGxaVyxoJDt' : '';
		private const API_SECRET = self::TEST_MODE ? 'EF3Q5Ksd9u5SoDAdlm_zdLsqdAoFZz2cLGq36VxJjGewPaKeEBHzq5E7-v7R7eOkCbCfXiJ5m5LcJajy' : '';

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Retrieves the PayPayl API context
		 * @return	ApiContext	The API context, containing PayPal keys and which mode we're currently using
		 */
		private static function getApiContext(): ApiContext
		{
			$context = new ApiContext(new OAuthTokenCredential(self::API_KEY, self::API_SECRET));
			$context->setConfig(["mode" => self::API_MODE]);

			return $context;
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "PayPal";
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	unused
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return "PayPal";
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    RedirectController    The controller that will handle the user
		 * @throws    Exception            If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$transaction = new Transaction();
			$transaction->setInvoiceNumber($this->payment->localReference);

			$payer = new Payer();
			$payer->setPaymentMethod("paypal");

			// Might as well itemise things
			if($this->payment instanceof OrderPayment)
			{
				$items = [];

				foreach($this->payment->order->lineItems as $lineItem)
				{
					$item = new Item();
					$item->setName($lineItem->title);
					$item->setCurrency("NZD");
					$item->setQuantity($lineItem->quantity);
					$item->setPrice($lineItem->price);
					$items[] = $item;
				}

				$itemList = new ItemList();
				$itemList->setItems($items);

				$transaction->setItemList($itemList);
				$transaction->setInvoiceNumber($this->payment->order->reference);
			}

			$amount = new Amount();
			$amount->setCurrency("NZD");
			$amount->setTotal($this->payment->amount);

			$transaction->setAmount($amount);

			$redirectUrls = new RedirectUrls();
			$redirectUrls->setReturnUrl($this->getSuccessUrl());
			$redirectUrls->setCancelUrl($this->getFailureUrl());

			$payment = new PaypalPayment();
			$payment->setIntent("sale");
			$payment->setPayer($payer);
			$payment->setRedirectUrls($redirectUrls);
			$payment->setTransactions([$transaction]);

			try
			{
				$payment->create(self::getApiContext());
			}
			catch(Exception $exception)
			{
				return new RedirectController($this->getFailureUrl());
			}

			return new RedirectController($payment->getApprovalLink());
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			if($this->payment->status === Payment::SUCCESS)
			{
				return new PaymentResult(true);
			}
			elseif($this->payment->status === Payment::FAILURE)
			{
				return new PaymentResult(false, "This payment has already failed");
			}
			elseif(!isset($_GET["paymentId"], $_GET["PayerID"]))
			{
				return new PaymentResult(false, "PayPal data missing from URL");
			}
			// still here?

			$apiContext = self::getApiContext();

			$paymentId = $_GET["paymentId"];
			$this->payment->remoteReference = $paymentId;
			$payment = PaypalPayment::get($paymentId, $apiContext);

			$execution = new PaymentExecution();
			$execution->setPayerId($_GET["PayerID"]);

			try
			{
				$payment->execute($execution, $apiContext);
			}
			catch(Exception $exception)
			{
				return new PaymentResult(false, $exception->getMessage());
			}

			return new PaymentResult(true);
		}
	}