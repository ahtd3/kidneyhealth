<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\LabelledResultElement;
	use DateTimeImmutable;
	use DateTimeInterface;
	use Exception;
	
	/**
	 * An input for a date (but not a time)
	 */
	class Date extends LabelledResultElement
	{
		public $allowNull = false;
		
		/**
		 * Sets whether to allow this element to be set to null, in the case of there not being a date set yet
		 * @param	bool	$allowNull	Whether to allow it
		 * @return	$this				This object, for chaining
		 */
		public function setAllowNull(bool $allowNull): self
		{
			$this->allowNull = $allowNull;
			
			return $this;
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "datetime/DateElement.js";
		}
		
		/**
		 * Gets the value for this element, pulling from the generator if such hasn't been set
		 * @return	mixed	The value
		 */
		public function getValue()
		{
			$value = parent::getValue();
			
			if($value instanceof DateTimeInterface)
			{
				return $value->format("Y-m-d");
			}
			else
			{
				return null;
			}
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			return parent::getJson() +
			[
				"allowNull" => $this->allowNull
			];
		}
		
		/**
		 * Gets the result of this element
		 * @param	mixed	$json	The JSON to retrieve the result from
		 * @return	mixed            The result that will be passed to the result handler
		 */
		public function getResult($json)
		{
			if($json === null)
			{
				return null;
			}
			
			try
			{
				return new DateTimeImmutable($json);
			}
			catch(Exception $exception)
			{
				return new DateTimeImmutable();
			}
		}
	}