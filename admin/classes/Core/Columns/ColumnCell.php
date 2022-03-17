<?php
	namespace Core\Columns;
	
	use JsonSerializable;
	
	/**
	 * A convenience class, allows for defining a widget type, props and a value
	 */
	class ColumnCell implements JsonSerializable
	{
		public $widgetType = "";
		public $value = "";
		public $props = [];
		
		/** @var string|null */
		public $alignment = null;
		
		/**
		 * Creates a new column cell
		 * @param	string	$widgetType		The type of widget to insert
		 * @param	array	$props			Any props to pass to the widget
		 * @param	string	$value			The HTML to insert into the widget
		 * @param	string	$alignment		The alignment for this cell
		 */
		public function __construct(string $widgetType, array $props = [], string $value = "", ?string $alignment = null)
		{
			$this->widgetType = $widgetType;
			$this->props = $props;
			$this->value = $value;
			$this->alignment = $alignment;
		}
		
		//region JsonSerializable
		
		/**
		 * Specify data which should be serialized to JSON
		 * @link  https://php.net/manual/en/jsonserializable.jsonserialize.php
		 * @return mixed data which can be serialized by <b>json_encode</b>,
		 * which is a value of any type other than a resource.
		 * @since 5.4.0
		 * @return	array	JSON column data
		 */
		public function jsonSerialize(): array
		{
			return
			[
				"widgetType" => $this->widgetType,
				"value" => $this->value,
				"props" => $this->props,
				"alignment" => $this->alignment ?? Column::LEFT
			];
		}
		
		//endregion
	}