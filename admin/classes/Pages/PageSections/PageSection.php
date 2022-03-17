<?php
	namespace Pages\PageSections;
	
	/**
	 * A page section is an area of distinct content. If this is implemented by a Generator, it will be stored by the PageSectionWrapper. Otherwise, it will just create a new instance of this object when needed.
	 */
	interface PageSection
	{
		/**
		 * For automation reasons, we need a no argument constructor
		 */
		function __construct();
		
		/**
		 * Gets the location (relative to the twig folder) for the template for this section.
		 * Note that this object will be passed into the template under a variable named "section"
		 * @return	string	The template location
		 */
		function getTemplateLocation(): string;
	}