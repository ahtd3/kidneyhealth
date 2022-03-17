<?php
	namespace Cart;
	
	use Core\Attributes\LinkTo;
	use Core\Entity;
	use Core\Properties\LinkToProperty;
	use Orders\LineItem;
	
	/**
	 * Mapping table between carts and line items
	 */
	class CartLineItemLink extends Entity
	{
		const TABLE = "cart_line_item_links";
		const ID_FIELD = "cart_line_item_link_id";
		
		#[LinkTo("cart_id")]
		public Cart $cart;
		
		#[LinkTo("line_item_id")]
		public LineItem $lineItem;
		
		/**
		 * Runs after this entity is deleted
		 */
		public function afterDelete()
		{
			parent::afterDelete();

			
			if($this->lineItem->orderLineItemLink->isNull())
			{
				$this->lineItem->delete();
			}
		}
	}