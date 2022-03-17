<?php

namespace Payments\BillPayments;

use Configuration\Configuration;
use Controller\Twig;
use Core\Columns\Column;
use Core\Columns\ColumnCell;
use Core\Columns\CustomColumn;
use Core\Columns\PropertyColumn;
use Core\Elements\Output;
use Core\Properties\Property;
use Mailer;
use Pages\PageType;
use Pagination;
use Payments\Payment;
use Users\User;

/**
 * A payment for a specific bill
 */
class BillPayment extends Payment
{
	const TABLE = 'bill_payments';
	const SINGULAR = 'Bill Payment';
	const PLURAL = 'Bill Payments';

	public $invoiceNumber = '';
	public $phone = '';
	
	/**
	 * Gets the array of Properties that determine how this Object interacts with the database
	 */
	public static function properties()
	{
		parent::properties();
		static::addProperty(new Property('invoiceNumber', 'invoice_number', 'string'));
		static::addProperty(new Property('phone', 'phone', 'string'));
	}
	
	/**
	 * Sets the array of Columns that are displayed to the user for this object type
	 */
	public static function columns()
	{
		static::addColumn(new CustomColumn("invoice", "Invoice", function(BillPayment $payment)
		{
			$html = "";
			$html .= "<div>\n";
				$html .= "<strong>Invoice #" . $payment->invoiceNumber . "</strong>\n";
			$html .= "</div>\n";
			$html .= "<div>\n";
				$html .= $payment->date->format("F j, Y") . "\n";
			$html .= "</div>\n";
			
			return $html;
		}));

		static::addColumn(new PropertyColumn('name', 'Name'));
		static::addColumn(new PropertyColumn('email', 'Email'));

		static::addColumn(new CustomColumn("total", "Total", function(BillPayment $payment)
		{
			$html = "";
			$html .= "<div>\n";
				$html .= "<strong>" . formatPrice($payment->amount) . "</strong>\n";
			$html .= "</div>\n";
			
			return $html;
		}));

		static::addColumn(new CustomColumn("paymentDetails", "Payment Details", function(BillPayment $payment)
		{
			$html = "";
			$html .= "<div>\n";
				$html .= "<a href='" . $payment->getEditLink() . "'>View</a>\n";
			$html .= "</div>\n";
			
			return $html;
		}));
	}

	/**
	 * Loads a page of payments for a particular user
	 * @param	User		$user			The user to load the orders for
	 * @param	int			$currentPage	The current page
	 * @param	int			$perPage		The number of orders per page
	 * @return	Pagination					The paginated orders
	 */
	public static function loadPageForUser(User $user, $currentPage, $perPage)
	{
		$query = "SELECT ~PROPERTIES "
			. "FROM ~TABLE "
			. "WHERE ~user = ? "
			. "AND ~status != ? "
			. "ORDER BY ~date DESC ";

		return static::makePages($query, [$user->id, Payment::FAILURE], $currentPage, $perPage);
	}
	
	/**
	 * Loads all the Generators to be displayed in the table
	 * @param	int						$page	The page to load, if handling pagination
	 * @return	Pagination				The array/Pagination of Generators
	 */
	public static function loadAllForTable(int $page = 1)
	{
		$page = $_GET["page"] ?? 1;

		$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~status != ? "
				. "ORDER BY ~date DESC ";
		$params = [static::FAILURE];

		return static::makePages($query, $params, $page, static::ACTIONS_PER_PAGE);
	}
	
	/**
	 * Sets the Form Elements for this object
	 */
	public function elements()
	{
		parent::elements();
		$this->addElement(new Output('name', 'Name'));
		$this->addElement(new Output('email', 'Email'));
		$this->addElement(new Output('phone', 'Phone'));
		$this->addElement(new Output('invoiceNumber', 'Invoice'));
		$this->addElement(new Output('amount', 'Amount'));
		$this->addElement(new Output('paymentMethod', 'Payment Method'));
	}

	/**
	 * Since BillPayment has it's own columns, but still needs to display like a Payment when it's in Payment's table, 
	 * we need to override this so that it doesn't return a blank column if it's doesn't find one in getColumns()
	 * 
	 * Gets the value for a particular column
	 * @param	Column $column The Column to get the value for
	 * @return	ColumnCell	The value for that Column
	 */
	public function getValueForColumn(Column $column): ColumnCell
	{		
		return $column->getValueFor($this);
	}

	/**
	 * Outputs text for the Type column
	 * 
	 * @return String 	Text to be displayed in the Type column
	 */
	public function getTypeColumnContent() 
	{
		return  "<a href='" . $this->getEditLink() . "'>Bill Payment</a>\n";
	}

	/**
	 * Gets a label for this Order
	 * @return	string	A label
	 */
	public function get_label()
	{
		return $this->name . " - " . $this->invoiceNumber;
	}

	/**
	 * Handles this payment succeeding
	 */
	public function handleSuccess()
	{
		parent::handleSuccess();

		unset($_SESSION['payments']);

		//send emails
		$emailContent = Twig::render("payments/user-email.twig", ["config" => Configuration::acquire(), 'payment' => $this]);
		Mailer::sendEmail($emailContent, "Your payment at " . Configuration::getSiteName(), $this->email, Configuration::getAdminEmail());

		$emailContent = Twig::render("payments/admin-email.twig", ["config" => Configuration::acquire(), 'payment' => $this]);
		Mailer::sendEmail($emailContent, "A customer has made a payment at " . Configuration::getSiteName(), Configuration::getAdminEmail(), $this->email);
	}
	
	/**
	 * Gets the redirect for successful payment handling
	 * @return	string	The successful payment path
	 */
	public function getSuccessRedirect()
	{
		return PageType::getPathForType('Payments') . "Success/" . $this->localReference . "/";
	}
	
	/**
	 * Gets the redirect for failed payment handling
	 * @return	string	The failed payment path
	 */
	public function getFailureRedirect()
	{
		return PageType::getPathForType('Payments');
	}
}
