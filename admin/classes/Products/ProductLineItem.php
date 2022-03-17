<?php
	namespace Products;
	
	use Core\Properties\LinkFromMultipleProperty;
	use Orders\LineItem;
	use Payments\Payment;
	use Products\Options\LineItemOption;
	use Products\Options\PricedProductOption;
	
	/**
	 * A Product Line Item contains references to products
	 */
	class ProductLineItem extends LineItem
	{
		/** @var LineItemOption[] */
		public $options = null;
		
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new LinkFromMultipleProperty("options", LineItemOption::class, "lineItem"));
		}
		
		/**
		 * Specifies what data should be serialised when json_encode is called
		 * @return    array    Name/data pairs for the data in this object
		 */
		public function jsonSerialize(): array
		{
			$options = [];
			
			foreach($this->options as $option)
			{
				$options[] = $option->optionGroupName . ": " . $option->optionName;
			}
			
			$json = parent::jsonSerialize();
			$json["options"] = implode("<br />\n", $options);
			
			return $json;
		}

		/**
		 * Some functionality (eg stock update) to happen after a transaction has been completed, eg Order has been placed, BillPayment has been made.
		 * 
		 * @param Payment	$payment	Some functionality may depend on the payment status
		 */
		public function onPurchase(Payment $payment) 
		{
			if ($payment->status === Payment::SUCCESS)
			{
				$generator = $this->getGenerator();

				if(Product::DO_STOCK)
				{
					if($generator instanceOf Product)
					{
						$generator->stock -= $this->quantity;
						$generator->save();
					}
					else if ($generator instanceOf PricedProductOption)
					{
						$generator->group->product->stock -= $this->quantity;
						$generator->group->product->save();
					}
				}	
			}
		}
	}