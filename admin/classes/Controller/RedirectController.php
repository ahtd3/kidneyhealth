<?php
	namespace Controller;
	
	/**
	 * A redirect controller redirects the user to a different URL
	 */
	class RedirectController extends UrlController
	{
		/** @var string */
		private $url;
		
		/**
		 * Redirect to a specific URL, or back to the previous URL passed by the HTTP referrer header
		 * @param	string|null		$url		URL to go to, null for back to previous page
		 * @param	bool			$temporary	Whether it should be a temporary 302 redirect, rather than a permanent 301 redirect
		 */
		public static function redirect(?string $url = null, bool $temporary = true)
		{
			if($url === null)
			{
				$url = getenv('HTTP_REFERER');
			}
			else if(strpos($url, '?') === 0)
			{
				$oldUrl = $_SERVER["HTTP_REFERER"];
				$oldQuery = parse_url($oldUrl, PHP_URL_QUERY);
				$severedUrl = explode("?", $oldUrl);
				$oldParameters = [];
				parse_str($oldQuery, $oldParameters);
				
				$newQuery = $url;
				$newQuery[0] = "&";
				$newParameters = [];
				parse_str($newQuery, $newParameters);
				
				$parameters = array_merge($oldParameters, $newParameters);
				$url = $severedUrl[0] . "?";
				
				foreach($parameters as $parameterKey => $parameterValue)
				{
					$url .= $parameterKey . "=" . $parameterValue . "&";
				}
				
				$url = rtrim($url, "&");
			}
			else if(strpos($url, '#') === 0)
			{
				$url = getenv('HTTP_REFERER') . $url;
			}
			
			if(isset($_GET["ajax"]) && $_GET["ajax"] === "redirect")
			{
				header('Content-Type: application/json');
				echo json_encode(["redirect" => $url]);
			}
			else
			{
				header("Location: $url", true, $temporary ? 302 : 301);
			}
			
			exit;
		}
		
		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return [];
		}
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param    UrlController $parent  The parent to the Page Child Controller
		 * @param    string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param    string        $pattern The pattern that was matched
		 * @return    self                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			return null;
		}
		
		/**
		 * Creates a new Redirect Controller
		 * @param	string	$url	The URL to redirect to
		 */
		public function __construct($url)
		{
			$this->url = $url;
		}
		
		/**
		 * Retrieves the location of the template to display to the user
		 * @return    string    The location of the template
		 */
		protected function getTemplateLocation()
		{
			return "";
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			static::redirect($this->url);
		}
	}