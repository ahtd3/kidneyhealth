<?php
	namespace Products\Options;
	
	use Core\Entity;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Products\ProductLineItem;
	
	/**
	 * Contains options for a single line item
	 */
	class LineItemOption extends Entity
	{
		/*~~~~~
		 * setup
		 **/
		// Entity
		const TABLE = "line_item_options";
		const ID_FIELD = "line_item_option_id";
		const PARENT_PROPERTY = "lineItem";
		
		// do not initialise until explicitly set
		public string $optionGroupName;
		public string $optionName;
		
		/** @var ProductLineItem */
		public $lineItem;
		
		/** @var OptionGroup */
		public $optionGroup;
		
		/** @var ProductOption */
		public $option;
		
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new Property("optionGroupName", "option_group_name", "string"));
			static::addProperty(new Property("optionName", "option_name", "string"));
			static::addProperty(new LinkToProperty("lineItem", "line_item_id", ProductLineItem::class));
			static::addProperty(new LinkToProperty("optionGroup", "option_group_id", OptionGroup::class));
			static::addProperty(new LinkToProperty("option", "option_id", ProductOption::class));
		}
		
		/**
		 * Sets the option group for this line item option
		 * @param	OptionGroup|int		$optionGroup	The option group to set
		 */
		public function set_optionGroup($optionGroup)
		{
			$this->setValue("optionGroup", $optionGroup);
			$this->optionGroupName = $this->optionGroup->name;
		}
		
		/**
		 * Sets the option for this line item option
		 * @param	ProductOption|int	$option		The option to set
		 */
		public function set_option($option)
		{
			$this->setValue("option", $option);
			$this->optionName = $this->option->name;
		}
	}