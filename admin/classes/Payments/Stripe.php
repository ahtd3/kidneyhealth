<?php
	namespace Payments;

    use Cart\Discount;
	use Configuration\Configuration;
	use Controller\RedirectController;
	use Exception;
	use Orders\OrderPayment;
	use Stripe\Checkout\Session;
	use Stripe\PaymentIntent;
	use Stripe\Stripe as StripeApi;
    use Stripe\Coupon;
	
	/**
	 * Handles payments using Stripe
	 */
	class Stripe extends PaymentGateway
	{
		/*~~~~~
		 * setup
		 **/
		// public const TEST_MODE = true; // override for debugging
		 
		// Client's test details or live details
		// Test details here are Activate Design account credentials, switch for client's credentials if available (and comment appropriately)
		private const API_KEY = self::TEST_MODE ? 'sk_test_lZqHSFdzUCvzhZPH8afJcL6N' : '';
		private const PUBLIC_KEY = self::TEST_MODE ?  'pk_test_1PocB3nEQbfasrFvzDiUuQjE' : '';

		/*~~~~~
		 * static methods
		 **/
		/**
		 * Gets the class identifier for this class
		 * @return    string    A string that uniquely identifies this class among all payment gateway classes
		 */
		public static function getClassIdentifier()
		{
			return "Stripe";
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
		 * @return    RedirectController    The controller that will handle the user
		 * @throws    Exception            If something goes wrong setting up the request
		 */
		public function getRedirectController()
		{
            $discountValue = 0;
            $discountTitles = [];
            $options =
                [
                    "payment_method_types" => ["card"],
                    "mode" => "payment",
                    "success_url" => $this->getSuccessUrl(),
                    "cancel_url" => $this->getFailureUrl()
                ];

            if($this->payment instanceof OrderPayment)
            {
                $options["line_items"] = [];

                foreach($this->payment->order->lineItems as $lineItem)
                {
                    if ($lineItem->generatorClassIdentifier === Discount::getClassLineItemGeneratorIdentifier())
                    {
                        $discountValue += abs($lineItem->price);
                        $discountTitles[] = $lineItem->title;

                        continue;
                    }
                    else if ($lineItem->price < 0)
                    {
                        $discountValue += $lineItem->quantity * abs($lineItem->price);
                        $discountTitles[] = $lineItem->quantity . ' x ' . $lineItem->title;

                        continue;
                    }

                    $lineItemOption =
                        [
                            "quantity" => $lineItem->quantity,
                            "price_data" =>
                                [
                                    "currency" => "nzd",
                                    "unit_amount" => $lineItem->price * 100,
                                    "product_data" => ["name" => $lineItem->title]
                                ]
                        ];

                    if($lineItem->image !== null)
                    {
                        $lineItemOption["price_data"]["product_data"]["images"] = [$lineItem->image->getFullLink()];
                    }

                    $options["line_items"][] = $lineItemOption;
                }
            }
            else
            {
                $options["line_items"] = [
                    [
                        "quantity" => 1,
                        "price_data" =>
                            [
                                "currency" => "nzd",
                                "unit_amount" => $this->payment->amount * 100,
                                "product_data" => ["name" => formatPrice($this->payment->amount) . " payment to " . Configuration::getSiteName()]
                            ]
                    ]];
            }

            StripeApi::setApiKey(self::API_KEY);

            if ($discountValue > 0)
            {
                $coupon = Coupon::create([
                    'currency' => 'nzd',
                    'amount_off' => ($discountValue * 100),
                    'duration' => 'once',
                    'name' => substr(implode(', ', $discountTitles), 0, 40)
                ]);

                $options['discounts'] = [[
                    'coupon' => "{$coupon->id}"
                ]];
            }

            $session = Session::create($options);

            $this->payment->remoteReference = $session->id;
            $publicKey = self::PUBLIC_KEY;

            // Retrieving the redirect requires the Stripe JavaScript library, so we'll do that on a page with minimal HTML
            return new RedirectController("/processes/stripe-redirect.html?{$publicKey}+{$this->payment->remoteReference}");
		}

		/**
		 * Gets the result of the transaction
		 * Note: This will be called from the return page, so if necessary you can access GET and POST data, but for security reasons, it's better to rely on direct calls.
		 * @return    PaymentResult    The result of the payment
		 */
		public function getResult()
		{
			StripeApi::setApiKey(self::API_KEY);
			$session = Session::retrieve($this->payment->remoteReference);
			$paymentIntent = PaymentIntent::retrieve($session->payment_intent);

			if($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED)
			{
				return new PaymentResult(true);
			}
			else
			{
				return new PaymentResult(false, $paymentIntent->last_payment_error);
			}
		}
	}