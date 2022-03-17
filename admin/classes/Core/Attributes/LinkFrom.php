<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\Properties\LinkFromProperty;
	
	/**
	 * The attribute for a link from property
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class LinkFrom extends PropertyAttribute
	{
		/**
		 * Creates a new link from
		 * @param	string	$linkPropertyName	The name of the property that links back to this entity
		 */
		public function __construct(public string $linkPropertyName){}
		
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): LinkFromProperty
		{
			return new LinkFromProperty($variableName, $variableType, $this->linkPropertyName);
		}
	}