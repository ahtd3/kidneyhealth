<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\Entity;
	use Core\Properties\LinkFromMultipleProperty;
	
	/**
	 * The attribute for a link from multiple property
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class LinkFromMultiple extends PropertyAttribute
	{
		/**
		 * Creates a new link from multiple
		 * @param	class-string<Entity>	$type				The type of entity that this links to
		 * @param	string					$linkPropertyName	The name of the property that links back to this entity
		 * @param	array<string, bool>		$orderBy			An associative array of [property => direction] pairs, where true is ascending and false is descending
		 */
		public function __construct(public string $type, public string $linkPropertyName, public array $orderBy = []){}
		
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): LinkFromMultipleProperty
		{
			return new LinkFromMultipleProperty($variableName, $this->type, $this->linkPropertyName, $this->orderBy);
		}
	}