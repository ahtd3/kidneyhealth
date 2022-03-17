<?php
	namespace Orders;

	use Core\Attributes\Data;
	use Core\Attributes\LinkTo;
	use Core\Elements\Output;
	use Core\Entity;
	
	/**
	 * Mapping between orders and line items
	 */
	class OrderLineItemLink extends Entity
	{
		const TABLE = "order_line_item_links";
		const ID_FIELD = "order_line_item_link_id";

		#[Data("is_normal")]
		public bool $isNormal = true;
		
		#[LinkTo("order_id")]
		public Order $order;
		
		#[LinkTo("line_item_id")]
		public LineItem $lineItem;

		/**
		 * Generates some Output elements to display in the admin panel
		 * @param	int			$index	The index of this order line item
		 * @return	Output[]			Various outputs to display
		 */
		public function getOutputs($index)
		{
			$outputs = [];
			$outputs[] = new Output("item" . $index, "Item", sprintf("%d x %s", $this->lineItem->quantity, $this->lineItem->title));
			$outputs[] = new Output("price" . $index, "Price", formatPrice($this->lineItem->price));

			return $outputs;
		}

		/**
		 * Runs after this entity is deleted
		 */
		public function afterDelete()
		{
			parent::afterDelete();

			if($this->lineItem->cartLineItemLink->isNull())
			{
				$this->lineItem->delete();
			}
		}

		/**
		 * Runs after this entity is saved
		 * makes sure both ends of the link actually have this assigned, because for some reason ORM is not updating the cached versions of the line items automatically
		 * necessary to stop CartLineItemLink deleting the line items for orders placed through a PaymentGateway which does not do immediate payment of the order eg On Account
		 * @param	bool	$isCreate	Whether this was a new entity, or not
		 */
		public function afterSave(bool $isCreate)
		{
			if($isCreate)
			{
				$this->lineItem->orderLineItemLink = $this;
			}
		}
	}