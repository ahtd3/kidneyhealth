<?php

namespace Payments\BillPayments;

use Controller\RedirectController;
use Controller\UrlController;
use Exception;
use Forms\Form;
use Pages\Page;
use Pages\PageController;
use Payments\Payment;
use Payments\PaymentGateway;
use Payments\Stripe;
use Users\User;

/**
 * Handles URLs relating to bill payments
 */
class BillPaymentController extends PageController
{
	public $reference = null;

	/**
	 * Retrieves the child patterns that can belong to this controller
	 * Nested objects not supported (eg categories with sub Categories)
	 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
	 */
	protected static function getChildPatterns()
	{
		// The third pattern (/$page/*) is to hand sub pages back to PageController
		return
		[
			'/action/pay/' => self::class,
			'/success/$reference/' => self::class,
			'/$page/*' => PageController::class
		];
	}
	
	/**
	 * Creates a new Page Controller
	 * @param	Page	$page		The Page to output
	 * @param	string	$reference	The bill payment to show info for
	 */
	public function __construct($page, $reference = null)
	{
		parent::__construct($page);
		$this->reference = $reference;
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
		if (isset($matches['reference']) && $parent instanceof PageController)
		{
			return new self($parent->page, $matches['reference']);
		}

		$payment = new BillPayment;
		$payment->name = $_SESSION['payments']['name'] = $_POST['name'];
		$payment->email = $_SESSION['payments']['email'] = $_POST['email'];
		$payment->phone = $_SESSION['payments']['phone'] = $_POST['phone'];
		$payment->amount = $_SESSION['payments']['amount'] = $_POST['amount'];
		$payment->invoiceNumber = $_SESSION['payments']['invoice'] = $_POST['invoice'];
		$payment->paymentMethod = $_SESSION['payments']['payment'] = $_POST['payment'];
		$payment->user = User::get();

		//get gateway and redirect

		$gatewayClass = null;
		
		if(count(PaymentGateway::getActivePaymentGatewayClasses()) === 1)
		{
			$gatewayClass = PaymentGateway::getActivePaymentGatewayClasses()[0];
		}
		else if(isset($_POST["payment"]))
		{
			$gatewayClass = PaymentGateway::getGatewayClassForIdentifier($_POST["payment"]);
		}
		
		if($gatewayClass === null)
		{
			addMessage("Please select a payment method");
			return new RedirectController($payment->getFailureRedirect());
		}
		
		$gateway = new $gatewayClass($payment);
		assert($gateway instanceof PaymentGateway);

		try
		{
			$redirectController = $gateway->getRedirectController();
		}
		catch (Exception $e)
		{
			addMessage('An error has occured: ' . $e->getMessage() . '. Please try again.');
			return new RedirectController($payment->getFailureRedirect());
		}

		$payment->save();
		return $redirectController;
	}

	/**
	 * Sets the variables that the template has access to
	 * @return	array	An array of [string => mixed] variables that the template has access to
	 */
	protected function getTemplateVariables()
	{
		$variables = parent::getTemplateVariables();

		$paymentGateways = [];
		
		/** @var class-string<PaymentGateway> $paymentGatewayClass */
		foreach(PaymentGateway::getActivePaymentGatewayClasses() as $paymentGatewayClass)
		{
			if ($paymentGatewayClass::DOES_PAYMENT)
			{
				$paymentGateways[$paymentGatewayClass::getUserLabel()] = $paymentGatewayClass::getClassIdentifier();
			}
		}

		$variables['paymentGateways'] = $paymentGateways;
		$variables["stripeOnly"] = count($paymentGateways) === 1 && ($paymentGateways[Stripe::getUserLabel()] ?? "") === Stripe::getClassIdentifier();

		if ($this->reference !== null)
		{
			$payment = Payment::loadFor('localReference', $this->reference);
			$variables['payment'] = $payment;
			$variables['paymentSingular'] = BillPayment::SINGULAR;
			$variables['paymentPlural'] = BillPayment::PLURAL;
		}

		$refill = [];
		$refill['name'] = $_SESSION["payments"]["name"] ?? User::get()->name;
		$refill['email'] = $_SESSION["payments"]["email"] ?? User::get()->email;
		$refill['phone'] = $_SESSION["payments"]["phone"] ?? User::get()->address->phone;
		$refill['amount'] = $_SESSION["payments"]["amount"] ?? '';
		$refill['invoice'] = $_SESSION["payments"]["invoice"] ?? '';
		$refill['payment'] = $_SESSION["payments"]["payment"] ?? '';
		$variables['refill'] = $refill;
		$variables['usePlaceholder'] = Form::PLACEHOLDERS;

		return $variables;
	}
}
