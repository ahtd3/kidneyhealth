<?php
	namespace Core\Elements;
	
	use Core\Generator;
	
	/**
	 * A generator element, but displayed as a grid of images and containing bulk upload capabilities
	 */
	class GridElement extends GeneratorElement
	{
		/**
		 * Creates a new instance of this object
		 * @param	string				$name		The name of the element
		 * @param	string|null			$label		An optional heading for the generator block (particularly multi-generators)
		 * @param	string|Generator	$class		The class to create new objects with
		 * @param	mixed				$value		The value to use for this element
		 */
		protected function createSubItem(string $name, ?string $label = null, $class = null, $value = self::EMPTY_VALUE)
		{
			return new GridElement($name, $label, $class, $value);
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "grid/Grid.js";
		}
	}