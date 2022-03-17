<?php
	namespace Core\Elements;
	
	use Core\Entity;
	use JsonSerializable;
	
	/**
	 * An option contained in an options element
	 */
	class FormOption extends FormOptionGroup implements JsonSerializable
	{
		public $value;
		public $disabled = false;
		
		/**
		 * Converts a value into something usable by HTML
		 * @param	string|Entity	$value	The value to convert
		 * @return	string					A usable value
		 */
		public static function sanitiseValue($value): string
		{
			if($value instanceof Entity)
			{
				$value = $value->id;
			}
			
			if($value === null)
			{
				$value = "";
			}
			
			return $value;
		}
		
		/**
		 * Creates a new option
		 * @param	string				$label		The label for the option
		 * @param	string|Entity		$value		The value for the option
		 * @param	FormOptionGroup[]	$children	Any child options
		 */
		public function __construct(string $label, $value, array $children = [])
		{
			parent::__construct($label, $children);
			$this->value = static::sanitiseValue($value);
		}
		
		/**
		 * Sets if this option is disabled or not
		 * @param	bool	$disabled	Whether this is disabled or not
		 * @return	$this				This object, for chaining
		 */
		public function setDisabled(bool $disabled)
		{
			$this->disabled = $disabled;
			return $this;
		}
		
		/**
		 * Specify data which should be serialized to JSON
		 * @link  https://php.net/manual/en/jsonserializable.jsonserialize.php
		 * @return mixed data which can be serialized by <b>json_encode</b>,
		 * which is a value of any type other than a resource.
		 * @since 5.4.0
		 * @return	array	JSON option data
		 */
		public function jsonSerialize(): array
		{
			return parent::jsonSerialize() +
			[
				"value" => $this->value,
				"disabled" => $this->disabled
			];
		}
	}