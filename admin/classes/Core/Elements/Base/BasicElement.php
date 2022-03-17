<?php
	namespace Core\Elements\Base;
	
	/**
	 * A basic element that does not modify the input or output JSON
	 */
	abstract class BasicElement extends LabelledResultElement
	{
		/**
		 * Gets the result of this element
		 * @param	mixed	$json	The JSON to retrieve the result from
		 * @return	mixed			The result that will be passed to the result handler
		 */
		public function getResult($json)
		{
			return $json;
		}
	}