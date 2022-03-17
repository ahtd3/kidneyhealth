<?php
	namespace Payments;

	use Controller\NotFoundController;
	use Controller\RedirectController;
	use Controller\UrlController;
	use Orders\Order;
	use Pages\Page;
	use Pages\PageController;
	use Pages\PageType;
	use Payments\BillPayments\BillPaymentController;
	
	/**
	 * Handles requests from payment gateways
	 */
	class PaymentController extends PageController
	{
		const BASE_PATH = "/payment/";

		private $payment = null;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/invoice/$localReference/' => self::class,
				'/$localReference/$type/' => self::class
			];
		}

		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param    UrlController $parent  The parent to the Page Child Controller
		 * @param    string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param    string        $pattern The pattern that was matched
		 * @return    UrlController                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			if($pattern === static::BASE_PATH . "*")
			{
				//check for top level page that happens to have the same slug as BASE_PATH
				//BASE_PATH is never used on it's own, so it's ok to have a page with that as it's slug and not break everything
				$page = Page::loadForSlug(trim(static::BASE_PATH, "/"));

				if (!$page->isNull() && count($matches) === 0) 
				{
					if ($page->pageType === PageType::BILL_PAYMENTS)
					{
						return new BillPaymentController($page);
					}
					else 
					{
						return new PageController($page);
					}
				}

				return new self();
			}

			$payment = Payment::loadFor("localReference", $matches["localReference"]);

			if($payment->isNull())
			{
				addMessage("No record of payment found in database");
				return null;
			}

			if(!isset($matches["type"]))
			{
				return new self($payment);
			}

			switch($matches["type"])
			{
				case "success":
					return self::successfulPayment($payment);

				case "failure":
					return self::failedPayment($payment);

				case "notification":
					self::paymentNotification($payment);
					// Payment gateways should not expect a response to notifications
					exit;
			}

			return null;
		}

		/**
		 * Handles successful payments
		 * @param	Payment				$payment	The payment that was marked as successful
		 * @return	RedirectController				Redirects the user to the successful payment page
		 */
		private static function successfulPayment(Payment $payment)
		{
			$paymentGateway = PaymentGateway::getGatewayForPayment($payment);

			if($paymentGateway === null)
			{
				$payment->handleFailure();
				addMessage("Invalid payment gateway, please try again");
				return new RedirectController($payment->getFailureRedirect());
			}

			$result = $paymentGateway->getResult();

			if($result->success)
			{
				if($payment->status === Payment::PENDING)
				{
					$payment->handleSuccess();
				}

				return new RedirectController($payment->getSuccessRedirect());
			}
			else
			{
				$payment->handleFailure();
				addMessage($result->error);
				return new RedirectController($payment->getFailureRedirect());
			}
		}

		/**
		 * Handles failed payments
		 * @param	Payment				$payment	The payment that was marked as failed
		 * @return	RedirectController				Redirects the user to the cart page
		 */
		private static function failedPayment(Payment $payment)
		{
			$payment->handleFailure();
			addMessage("Transaction failed");
			return new RedirectController($payment->getFailureRedirect());
		}

		/**
		 * Handles payment notifications
		 * Ie, any request that the gateway sends us about the transaction, even if the user has abandoned us.
		 *
		 * @param	Payment		$payment	The payment that is probably successful
		 */
		private static function paymentNotification(Payment $payment)
		{
			$paymentGateway = PaymentGateway::getGatewayForPayment($payment);

			if($paymentGateway === null)
			{
				return;
			}

			$result = $paymentGateway->getResult();

			if($result->success)
			{
				$payment->handleSuccess();
			}
			else
			{
				//Note: Don't call handleFailure, as that will probably contain logic relevant to the specific user
				$payment->status = Payment::FAILURE;
				$payment->save();
			}
		}

		/**
		 * Creates a new Page Controller
		 * @param	Payment		$payment	The payment to display
		 */
		public function __construct(Payment $payment = null)
		{
			$page = new Page;
			$order = Order::loadFor('payment', $payment);
			$page->name = "Invoice for order #" . ($order->isNull() ? new Order() : $order)->reference;
			$page->pageType = "Invoice";
			$page->slug = trim(static::BASE_PATH, "/");

			parent::__construct($page);

			$this->payment = $payment;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return	string	The location of the template
		 */
		public function getTemplateLocation()
		{
			return "orders/invoice-page.twig";
		}

		/**
		 * Gets the class identifier for a payment type
		 * @param	string|PaymentGateway	$class	The name of the class
		 * @return	string							The identifier for that class
		 */
		public function getPaymentName($class)
		{
			assert(is_a($class, PaymentGateway::class, true), $class . " must be a type of payment gateway");

			return $class::getClassIdentifier();
		}

		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		public function getTemplateVariables()
		{
			$variables = parent::getTemplateVariables();
			
			$payment = $this->payment;

			$variables["payment"] = $payment;
			$paymentGateway = PaymentGateway::getGatewayClassForIdentifier($payment->paymentMethod);
			$variables["paymentGatewayHtml"] = $paymentGateway::getInvoiceHtml($payment);

			return $variables;
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			//if for some reason the user gets here without a valid payment, we don't want to display a blank invoice
			if($this->payment === null)
			{
				(new NotFoundController)->output();
			}

			parent::output();
		}
	}
