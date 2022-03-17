<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\Properties\LinkToProperty;
	
	/**
	 * The attribute for a link to property
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class LinkTo extends PropertyAttribute
	{
		/**
		 * Creates a new link to
		 * @param	string	$databaseName	The name of the associated field in the database
		 */
		public function __construct(public string $databaseName){}
		
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): LinkToProperty
		{
			return new LinkToProperty($variableName, $this->databaseName, $variableType);
		}
	}