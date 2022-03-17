<?php
	namespace Orders;

	use Core\Attributes\Data;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\Property;
	
	/**
	 * A Courier delivers orders
	 */
	class Courier extends Generator
	{
		const TABLE = "couriers";
		const ID_FIELD = "courier_id";
		const LABEL_PROPERTY = "name";
		const SINGULAR = "Courier";
		const PLURAL = "Couriers";

		#[Data("name")]
		public string $name = "";
		
		#[Data("tracking_url")]
		public string $trackingUrl = "";

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn("name", "Name"));

			parent::columns();
		}

		/**
		 * Sets the Form Elements for this object
		 */
		public function elements()
		{
			parent::elements();

			$this->addElement(new Text("name", "Name"));
			$this->addElement((new Text("trackingUrl", "Tracking URL"))->setHint("enter %code% where the tracking code should go"));
		}

		/**
		 * Gets a URL for a specific tracking code
		 * @param	string	$trackingCode	The tracking code to use
		 * @return	string					The URL for that tracking code
		 */
		public function getUrl($trackingCode)
		{
			return str_replace("%code%", $trackingCode, $this->trackingUrl);
		}
	}
