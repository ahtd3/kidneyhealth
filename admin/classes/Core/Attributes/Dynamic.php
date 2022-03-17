<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\Properties\Property;
	
	/**
	 * Handles properties that are meant to hook into getters and setters
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class Dynamic extends PropertyAttribute
	{
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): Property
		{
			return new Property($variableName);
		}
	}