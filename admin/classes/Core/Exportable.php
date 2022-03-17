<?php
	namespace Core;
	
	/**
	 * Handles exporting objects to a file
	 */
	interface Exportable
	{
		/**
		 * Exports all objects that match a specific filter to a given format
		 * Typically this will generate and stream a file then exit;
		 *
		 * @param	string			$filter		The filter to apply
		 * @param	string			$format		The format to export
		 * @return	Generator[]					The filtered objects
		 */
		public static function export(?string $filter = null, ?string $format = null);
		
		/**
		 * gets the formats this object can export to, for generating buttons or select options
		 * this can conditionally return an empty array to en/disable exporting on a permissions basis
		 *
		 * @return string[] values which will be displayed to the user and passed into the $format parameter of export() eg ['CSV', 'PDF'] 
		 */
		public static function getExportableFormats();
	}