<?php
	namespace Core\Properties;

	use Core\Entity;
	use Core\ValueWrappers\FileWrapper;
	use Core\ValueWrappers\ValueWrapper;
	
	/**
	 * A File Property handles files attached to an Entity
	 */
	class FileProperty extends Property
	{
		public $location;

		/**
		 * Creates a new Property
		 * @param	string	$propertyName	The name of the property
		 * @param	string	$databaseName	The name of the database field
		 * @param	string	$location		The location to store the files
		 */
		public function __construct($propertyName, $databaseName, $location)
		{
			parent::__construct($propertyName, $databaseName, null);

			$this->location = rtrim($location, "/");
		}

		/**
		 * Gets an appropriate value wrapper for this Property
		 * @param	Entity						$entity		The owner of this Property
		 * @return	ValueWrapper							The value wrapper for this Property
		 */
		public function getValueWrapper(Entity $entity)
		{
			return new FileWrapper($entity, $this);
		}
	}