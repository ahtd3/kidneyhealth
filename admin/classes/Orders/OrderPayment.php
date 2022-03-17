<?php
	namespace Orders;

	use Cart\Cart;
	use Configuration\Configuration;
	use Controller\Twig;
	use Core\Attributes\LinkFrom;
	use Exception;
	use Mailer;
	use Payments\BankDeposit;
	use Payments\OnAccount;
	use Payments\Payment;
	use Twig\Error\Error;
	use Users\User;
	
	/**
	 * A Payment generated by an Order
	 */
	class OrderPayment extends Payment
	{
		#[LinkFrom("payment")]
		public Order $order;
		
		/**
		 * Outputs the reference
		 * @return	string	The reference to the payment
		 */
		public function outputLocalReference()
		{
			return parent::outputLocalReference() . " (" . $this->order->reference . ")";
		}

		/**
		 * Outputs text for the Type column
		 * 
		 * @return String 	Text to be displayed in the Type column
		 */
		public function getTypeColumnContent() 
		{
			return  "<a href='" . $this->order->getEditLink() . "'>Order</a>\n";
		}
		
		/**
		 * Sends order emails to the user and site admin
		 * @throws	Error	If something goes wrong while generating the email content
		 */
		public function sendEmails()
		{
			// should these come from methods in the payment type class?
			// could return '' for a value not to send that email
			if($this->paymentMethod === BankDeposit::getClassIdentifier())
			{
				$adminEmail = "orders/admin-bank-deposit-email.twig";
				$userEmail = "orders/user-bank-deposit-email.twig";
			}
			elseif($this->paymentMethod === OnAccount::getClassIdentifier())
			{
				$adminEmail = "orders/admin-on-account-email.twig";
				$userEmail = "orders/user-on-account-email.twig";
			}
			else
			{
				$adminEmail = "orders/admin-payment-email.twig";
				$userEmail = "orders/user-payment-email.twig";
			}
			
			// Send an email to the site admin
			$emailContent = Twig::render($adminEmail, ["config" => Configuration::acquire(), "order" => $this->order]);
			Mailer::sendEmail($emailContent, "A customer has placed an order at " . Configuration::getSiteName(), Configuration::getAdminEmail(), $this->order->billingAddress->email);

			// Send an email to the customer
			$emailContent = Twig::render($userEmail, ["config" => Configuration::acquire(), "order" => $this->order]);
			Mailer::sendEmail($emailContent, "Your order with " . Configuration::getSiteName(), $this->order->billingAddress->email, Configuration::getAdminEmail());
		}

		/**
		 * Handles this payment succeeding
		 * @throws	Exception	If something goes wrong while generating the emails
		 */
		public function handleSuccess()
		{
			if(!$this->gatewayDoesPayment())
			{
				// make sure OrderLineItemLinks are saved, or line items will be deleted with cart
				$this->order->save();
			}

			parent::handleSuccess();
			
			$this->order->cart->delete();
			$this->order->cart = Cart::makeNull();

			if($this->gatewayDoesPayment())
			{
				//update order status
				$this->order->status = Order::PAID;
			}
			
			$this->save();
			$this->sendEmails();

			foreach($this->order->lineItems as $lineItem)
			{
				$lineItem->onPurchase($this);
			}
		}

		/**
		 * Handles this payment failing
		 */
		public function handleFailure()
		{
			//update order status
			$this->order->status = Order::PENDING;

			parent::handleFailure();

			$cart = $this->order->cart;
			$cart->awaitingPayment = false;
			$user = User::get();
			
			// If you're not redirected (i.e. Stripe), then you won't have a new blank cart created that needs deleting
			if($user->cart !== $cart)
			{
				// Free up the user_id unique index in the database
				$user->cart->delete();
			}
			
			$cart->user = $user;
			$cart->save();
			$this->order->delete();
		}
	}
