<?php
/**
 * An object with this interface Publishes content to (usually) the front end of the website
 */
interface Publishable
{
	/**
	 * Format and output properties as a coherent string of HTML
	 * @return	string		The HTML to publish
	 * @throws	Exception	If something goes wrong during rendering
	 */
	public function output();
}