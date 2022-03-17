<?php
	use Files\File;
	
	/**
	 * Class for generating PDFs using installed Chrome
	 */
	class ChromePdf
	{
		/**
		 * Generates a PDF via headless Chrome from a path or URL
		 * @param	string	$path	The path or URL to the document to generate the PDF from
		 * @return	File			A File containing the PDF
		 */
		public static function generateFromFile($path)
		{
			$pdfFile = tempnam(sys_get_temp_dir(), "temp-pdf");
			exec("/opt/google/chrome/google-chrome --headless --no-sandbox --print-to-pdf=" . $pdfFile . " " . $path);
			
			return new File($pdfFile);
		}
		
		/**
		 * Generates a PDF via headless Chrome from an HTML string
		 * @param	string	$html	The HTML to create the PDF from
		 * @return	File			A File containing the PDF
		 */
		public static function generateFromHtml($html)
		{
			$htmlFile = tempnam(sys_get_temp_dir(), 'temp-html') . ".html";
			file_put_contents($htmlFile, $html);
			
			$pdf = static::generateFromFile($htmlFile);
			unlink($htmlFile);
			
			return $pdf;
		}
	}