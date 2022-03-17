<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\Element;
	
	/**
	 * Outputs some HTML
	 */
	class Html extends Element
	{
		public $html = "";
		
		/**
		 * Creates a new element
		 * @param	string	$name	The name of the element
		 * @param	string	$html	The HTML to display
		 */
		public function __construct(string $name, string $html)
		{
			parent::__construct($name);
			
			$this->html = $html;
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "HtmlElement.js";
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() +
			[
				"html" => $this->html
			];
		}
	}