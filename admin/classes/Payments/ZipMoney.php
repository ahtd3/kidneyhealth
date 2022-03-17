<?php
	namespace Payments;

	use Controller\RedirectController;
	use Error;
	use Exception;
	use Orders\OrderPayment;
	use Products\Product;
	use stdClass;
	use zipMoney\Api\ChargesApi;
	use zipMoney\Api\CheckoutsApi;
	use zipMoney\Configuration;
	use zipMoney\Model\Address;
	use zipMoney\Model\CaptureChargeRequest;
	use zipMoney\Model\CheckoutConfiguration;
	use zipMoney\Model\CheckoutOrder;
	use zipMoney\Model\CreateChargeRequest;
	use zipMoney\Model\CreateCheckoutRequest;
	use zipMoney\Model\OrderItem;
	use zipMoney\Model\OrderShipping;
	use zipMoney\Model\Shopper;
	
	// via Composer
	
	/**
	 * Handles requests to Zip AU (also supports NZ, but that is mostly handled by Zip NZ integration)
	 * This integration is untested and likely incomplete, but should save some time if/when we actually need to implement 
	 *
	 * Zip integrations require sign-off by Zip before going live
	 * @see https://zip-online.api-docs.io/v1/ for the API docs
	 * @see https://zip-online.api-docs.io/v1/conversion-optimisation/ for additional requirements for site
	 * @see https://zip-online.api-docs.io/v1/certification/testing for certification checklist
	 * @see https://github.com/zipMoney/merchantapi-php/ for the PHP SDK
	 */
	class ZipMoney extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging
		/** @var string API mode */
		private const API_ENVIRONMENT = self::TEST_MODE ? 'sandbox' : 'production';

		/** @var string API authentication details */
		private const API_KEY = self::TEST_MODE ? '' : '';

		/** @var stdClass|null */
		private static $configuration = null;

		// countries which ZIP services and common variations which might be entered in the 'Country' field at checkout
		// we check these before displaying ZIP as an available option @see isEligibleCountry()
		// and trust this holds through to order generation @see getRedirectController()
		/** @var string[] */ 
		private const COUNTRIES = [
			'australia' => 'AU',
			'new zealand' => 'NZ',
			'nz' => 'NZ'
			];

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Zip does not currently expose this information
		 * @param float $orderValue value of the order
		 * @return bool  is this gateway available given this order value?
		 */
		public static function availableForOrderValue($orderValue)
		{
			return true;
		}
		
		/**
		 * check if purchase is being made from a country which is eligible to use Zip
		 * method not implemented in code (@see CartController::getAvialablePaymentgateways()) 
		 * unless/until it is needed for a site
		 *
		 * @return bool
		 */
		public static function isEligibleCountry($country)
		{
			return isset(self::COUNTRIES[strtolower($country)]);
		}

		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			throw new Error("Caution: ZipMoney has NOT been full tested or completed, and largely does not apply to New Zealand. Remove this error to use this class.");
			
			/** @noinspection PhpUnreachableStatementInspection */
			return "ZipMoney";
		}
		
		/**
		 * Zip doesn't expose their installment calcuation, so we're approximating it
		 *
		 * @param float $total
		 * @return float probabaly slightly under 1/4th of the value
		 */
		public static function getInstallment($total)
		{
			// always rounding to the lowest cent;
			return formatPrice(round(($total / 4) - 0.005 ,2));
		}
		
		/**
		 * Gets a label to describe this payment gateway to the user after purchase.
		 * @param 	float 	$total 	Order total
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getPaymentLabel($total = null)
		{
			return 'Zip | four payments from ' . static::getInstallment($total);
		}

		/**
		 * Gets a label to describe this payment gateway to the user
		 * @param 	float 	$total 	Rather than assuming the global cart is the correct
		 * 		one at the time this method is called better to pass in the total
		 * @return	string			A label to describe this payment gateway
		 */
		public static function getUserLabel($total = null)
		{
			return '<img src="https://static.zipmoney.com.au/assets/default/footer-tile/footer-tile-new.png" style="height:30px;" alt="Zip /> | four payments from ' . static::getInstallment($total);
		}

		/*~~~~~
		 * non-static methods
		 **/
		/**
		 * Creates a new payment for a specific Payment Action
		 * @param	Payment $payment The payment to create
		 */
		public function __construct(Payment $payment)
		{
			parent::__construct($payment);
			
			// from https://github.com/zipMoney/merchantapi-php
			// Configure API key authorization: Authorization
			$configuration = Configuration::getDefaultConfiguration();
			$configuration->setApiKey('Authorization', self::API_KEY);
			$configuration->setApiKeyPrefix('Authorization', 'Bearer');
			$configuration->setEnvironment(self::API_ENVIRONMENT); // Allowed values are  ( sandbox | production )
			$configuration->setPlatform('WEP/4'); // E.g. Magento/1.9.1.2

			$configuration->setDebug(true);
			// $configuration->setTempFolderPath(__DIR__);
			$configuration->setDebugFile(static::getLogDestination());
		}
		
		/**
		 * Returns a controller that will send the user to the payment screen, etc.
		 * @return    RedirectController    The controller that will handle the user
		 * @throws    Exception            If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
			$create = new CreateCheckoutRequest;
			$payment = $this->payment;
			
			if($payment instanceOf OrderPayment)
			{
				$wepOrder = $payment->order;

				// Zip is very intrusive and demands a lot of information
				$shopper = new Shopper;
					$customer = $wepOrder->getOrderDetailsBillingAddress();
					$names = explode(' ', $customer->name, 2);
					$shopper->setFirstName($names[0] ?? '');
					$shopper->setLastName($names[1] ?? '');
					$shopper->setEmail($customer->email);

					$billingAddress = new Address;
						$billingAddress->setLine1($customer->address);
						$billingAddress->setLine2($customer->suburb);
						$billingAddress->setCity($customer->city);
						// $billingAddress->setState();
						$billingAddress->setPostalCode($customer->postCode);
						$billingAddress->setCountry(self::COUNTRIES[strtolower($customer->country)]);

					$shopper->setBillingAddress($billingAddress);
				
				$create->setShopper($shopper);

				$order = new CheckoutOrder;

					$order->setAmount($wepOrder->getOrderDetailsTotal());
					$order->setCurrency('NZD');
					$order->setReference($wepOrder->reference);

						$shipping = new OrderShipping;
							$recipient = $wepOrder->getOrderDetailsShippingAddress();
							$pickup = false;
							$shipping->setPickup($pickup);
							if(!$pickup)
							{
								$deliveryAddress = new Address;
									$deliveryAddress->setLine1($recipient->address);
									$deliveryAddress->setLine2($recipient->suburb);
									$deliveryAddress->setCity($recipient->city);
									// $deliveryAddress->setState();
									$deliveryAddress->setPostalCode($recipient->postCode);
									$deliveryAddress->setCountry(self::COUNTRIES[strtolower($recipient->country)]);

								$shipping->setAddress($deliveryAddress);
							}
											
					$order->setShipping($shipping);

					$items = [];

					foreach($wepOrder->getOrderDetailsNormalLineItems() as $lineItem)
					{
						/** @var Product $product */
						$product = $lineItem->getGenerator();

						$item = new OrderItem;
						$item->setName($lineItem->title);
						$item->setAmount($lineItem->price);
						$item->setQuantity($lineItem->quantity);
						$item->setType(OrderItem::TYPE_SKU);
						$item->setReference($product->code);
						$item->setItemUri(PROTOCOL . SITE_ROOT . $product->getCanonicalLink());
						$item->setImageUri($product->getVisibleImages()[0]->thumbnail->getFullLink());

						$items[] = $item;
					}
					
					foreach($wepOrder->getOrderDetailsSpecialLineItems() as $lineItem)
					{
						$item = new OrderItem;
						$item->setName($lineItem->title);
						$item->setAmount($lineItem->price);
						$item->setQuantity($lineItem->quantity);
						
						if(strpos($lineItem->title, 'Discount') === 0)
						{
							$item->setType(OrderItem::TYPE_DISCOUNT);
						}
						else if(strpos($lineItem->title, 'Shipping') === 0)
						{
							$item->setType(OrderItem::TYPE_SHIPPING);
						}
						else
						{
							continue;
						}
						
						$items[] = $item;
					}

					$order->setItems($items);
			}
			else
			{
				throw new Exception(self::getClassIdentifier() . ' gateway implementation does not support ' . get_class($this->payment));
			}
			
			$create->setOrder($order);

			$config = new CheckoutConfiguration;
			$config->setRedirectUri($this->getSuccessUrl());

			$create->setConfig($config);

			$result = (new CheckoutsApi)->checkoutsCreate($create);
			return new RedirectController($result->getUri());
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			try
			{
				$success = false;

				switch(strtolower($_GET['result']))
				{
					case 'approved':
						$checkout = (new CheckoutsApi)->checkoutsGet($_GET['checkoutId']);

						$instance = new ChargesApi;
						$create = new CreateChargeRequest;

						$charge = $instance->chargesCreate($create);

						$capture = new CaptureChargeRequest;
						$result = $instance->chargesCapture($charge->getId(), $capture);
						
						// if the charge failed to capture an Exception should have been thrown
						// but we may need to recheck the charge state ($result->getState()) to be sure
						// possible values authorised, captured, cancelled, declined, refunded, approved
						// a problem for future programmer
						
						return new PaymentResult(true);

					case 'cancelled':
						return new PaymentResult(false, 'Payment was cancelled');

					case 'declined':
						return new PaymentResult(false, 'Zip declined your application. Please select a different method of payment.');
						// possible enhancement: some sort of flag remove zip from the list of available payment gateways for the duration of the order

					case 'referred':
						return new PaymentResult(false, 'We are sorry but we cannot process this payment until your Zip account is approved. <br />Please try again in 10 minutes or select a different method of payment.');

					default:
						return new PaymentResult(false, 'Invalid result parameter');
				}
			}
			catch(Exception $exception)
			{
				return new PaymentResult(false, $exception->getMessage());
			}
		}
	}