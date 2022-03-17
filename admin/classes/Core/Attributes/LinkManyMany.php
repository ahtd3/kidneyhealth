<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\EntityLink;
	use Core\Properties\LinkManyManyProperty;
	
	/**
	 * The attribute for a link many many property
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class LinkManyMany extends PropertyAttribute
	{
		/**
		 * Creates a new link many many
		 * @param	class-string<EntityLink>	$linkingClassName	The name of the class that links this class with the other class
		 * @param	string						$linkPropertyName	The name of the property that links the linking class back to this class
		 * @param	array<string, bool>			$orderBy			An associative array of [property => direction] pairs, where true is ascending and false is descending
		 */
		public function __construct(public string $linkingClassName, public string $linkPropertyName, public array $orderBy = []){}
		
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): LinkManyManyProperty
		{
			return new LinkManyManyProperty($variableName, $this->linkingClassName, $this->linkPropertyName, $this->orderBy);
		}
	}