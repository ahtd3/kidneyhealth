<?php
	namespace Core\Elements;
	
	/**
	 * Contains a group of child options
	 */
	class FormOptionGroup
	{
		public $label;
		public $children;
		
		/**
		 * Creates a new option group
		 * @param	string				$label		The label for the group
		 * @param	FormOptionGroup[]	$children	Any child options
		 */
		public function __construct(string $label, array $children = [])
		{
			$this->label = $label;
			$this->children = $children;
		}
		
		/**
		 * Adds a child option
		 * @param	FormOptionGroup		$child	The child to add
		 * @return	FormOptionGroup				The added child
		 */
		public function addChild(FormOptionGroup $child)
		{
			$this->children[] = $child;
			return $child;
		}
		
		/**
		 * Specify data which should be serialized to JSON
		 * @link  https://php.net/manual/en/jsonserializable.jsonserialize.php
		 * @return mixed data which can be serialized by <b>json_encode</b>,
		 * which is a value of any type other than a resource.
		 * @since 5.4.0
		 */
		public function jsonSerialize()
		{
			return
			[
				"label" => $this->label,
				"children" => $this->children,
				"unique" => uniqid()
			];
		}
	}