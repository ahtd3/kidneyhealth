<?php
	namespace Orders;
	
	/**
	 * Displays details about a cart / order
	 */
	interface OrderDetails
	{
		/**
		 * Gets the shipping address for the order
		 * @return	Address		The shipping address
		 */
		public function getOrderDetailsShippingAddress(): Address;
		
		/**
		 * Gets the billing address for the order
		 * @return	Address		The billing address
		 */
		public function getOrderDetailsBillingAddress(): Address;
		
		/**
		 * Gets the payment gateway
		 * @return	string	The payment gateway identifier
		 */
		public function getOrderDetailsPaymentGateway(): string;
		
		/**
		 * Gets the normal line items in the order
		 * @return	LineItem[]	The normal line items to display
		 */
		public function getOrderDetailsNormalLineItems(): array;
		
		/**
		 * Gets the special line items in the order
		 * @return	LineItem[]	The special line items to display
		 */
		public function getOrderDetailsSpecialLineItems(): array;
		
		/**
		 * Gets the subtotal for the order
		 * @return	float	The subtotal
		 */
		public function getOrderDetailsSubtotal(): float;
		
		/**
		 * Gets the total for the order
		 * @return	float	The total
		 */
		public function getOrderDetailsTotal(): float;
	}