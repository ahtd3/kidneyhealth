<?php
	namespace Core\Attributes;
	
	use Attribute;
	use Core\Properties\FileProperty;
	
	/**
	 * The attribute for a file property
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class FileValue extends PropertyAttribute
	{
		/**
		 * Creates a new file value
		 * @param	string	$databaseName	The name of the associated field in the database
		 * @param	string	$location		The folder where the files should be stored
		 */
		public function __construct(public string $databaseName, public string $location){}
		
		/**
		 * @inheritDoc
		 */
		public function getProperty(string $variableName, string $variableType): FileProperty
		{
			return new FileProperty($variableName, $this->databaseName, $this->location);
		}
	}