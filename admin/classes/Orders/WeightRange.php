<?php
	namespace Orders;

	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Core\Elements\Text;
	
	/**
	 * The weight range for a shipping region
	 */
	class WeightRange extends Generator
	{
		// Object
		const TABLE = "shipping_weight_ranges";
		const ID_FIELD = "weight_range_id";

		// Generator
		const SINGULAR = "Weight range";
		const PLURAL = "Weight ranges";
		//const LABEL_PROPERTY = 'name';

		const HAS_ACTIVE = true;
		//const HAS_POSITION = true;

		const PARENT_PROPERTY = 'shippingRegion';

		public $shippingRegion = null;
		//public $from = '';
		public $upTo = 0;
		public $cost = 0;

		/**
		 * Gets the array of Database Object Properties that determine how an Image interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new LinkToProperty("shippingRegion", "shipping_region_id", ShippingRegion::class));
			//static::addProperty(new Property('from', 'from_weight', 'float'));
			static::addProperty(new Property("upTo", "to_weight", "float"));
			static::addProperty(new Property("cost", "cost", "float"));
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();
			
			//$this->addElement((new Text('from', 'From'))->addClass('half first')->setHint("kg"));
			$this->addElement((new Text('upTo', 'Up to'))->addClass('')->setHint("kg"));
			$this->addElement((new Text('cost', 'Cost'))->addClass('currency'));
		}
		
		/**
		 * Gets the dynamic label script to use for this generator
		 * @return	string	The dynamic label
		 */
		public function getDynamicLabelScript()
		{
			return 'return `Up to ${upTo}kg`';
		}

	}
