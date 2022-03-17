<?php
	namespace Core\Elements;
	
	/**
	 * Outputs a heading (h2)
	 *
	 * Before using this element consider if you would be better using 
	 * * a Group element to contain elements the heading is to refer to (optional heading is second parameter to the Group constuctor)
	 * * an Html or Output element, or setHint()/setDescription() methods (where supported) if the content is more of an informative nature than a semantic heading.
	 */
	class Heading extends Html
	{
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "HeadingElement.js";
		}
	}