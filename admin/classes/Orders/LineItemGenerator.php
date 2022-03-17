<?php
	namespace Orders;

	use Exception;
	use Files\Image;
	
	/**
	 * A Line Item Generator can generate a line item
	 */
	interface LineItemGenerator
	{
		/**
		 * Gets a string that will identify this uniquely identify this class from other Line Item Generators
		 * @return	string	The identifier
		 */
		public static function getClassLineItemGeneratorIdentifier(): string;

		/**
		 * Loads an object for this class, given an identifier
		 * @param	string				$identifier		The identifier that will identify a Line Item Generator
		 * @return	LineItemGenerator					The original object that generated this Line Item, or null if such cannot be found
		 */
		public static function loadForLineItemGeneratorIdentifier($identifier): ?LineItemGenerator;

		/**
		 * Updates, replaces or deletes an existing Line Item
		 * Basically this is entended to check if the line item generator is still current (active, still exists etc)
		 * And update to whatever the current line item generator is like (price)
		 *
		 * @param	string		$identifier		The identifier that will identify the Line Item Generator
		 * @param	LineItem	$current		The line item to update
		 * @return	LineItem					The updated line item, or null if it's been removed
		 */
		public static function updateLineItem($identifier, LineItem $current): ?LineItem;

		/**
		 * Gets a unique identifier for this object
		 * @return	string	An identifier that uniquely identifies this object
		 */
		public function getLineItemGeneratorIdentifier(): string;

		/**
		 * Gets a Line Item from this object. The quantity, parentClassIdentifier and parentIdentifier will be filled in after you return the line item
		 * @return	LineItem	The generated line item
		 * @throws	Exception	If a line item cannot be generated, for whatever reason
		 */
		public function getLineItem(): LineItem;

		/**
		 * Gets a representative thumbnail image for this Line Item Generator, may return null
		 * @return	Image	The representative image
		 */
		public function getLineItemImage(): ?Image;

		/**
		 * Gets a link to this Line Item Generator on the site, may return null
		 * @return	string	A link to view this item on the site
		 */
		public function getLineItemLink(): ?string;

		/**
		 * Gets a link to edit this Line Item Generator in the admin, may return null
		 * @return	string	The link to edit this generator in the admin panel
		 */
		public function getLineItemEditLink(): ?string;
	}
