<?php
	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Core\Attributes\Data;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Html;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\Property;
	use Users\Administrator;
	use Users\User;
	
	/**
	 * Handles server side redirects instead of having to put them in .htaccess
	 */
	class Redirect extends Generator implements AdminNavItemGenerator
	{
		const TABLE = "redirects";
		const ID_FIELD = "redirect_id";
		const LABEL_PROPERTY = "from";
		const SINGULAR = "Redirect";
		const PLURAL = "Redirects";
		const HAS_ACTIVE = true;
		
		#[Data("from")]
		public string $from = "";
		
		#[Data("to")]
		public string $to = "";
		
		public bool $active = true;
		
		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		public static function columns()
		{
			static::addColumn(new PropertyColumn("from", "From"));
			static::addColumn(new PropertyColumn("to", "To"));
			
			parent::columns();
		}
		
		/**
		 * Loads a redirect that matches a specific URL
		 * @param	string		$url	The URL to compare with
		 * @return	Redirect			The first redirect that matches a specific URL
		 */
		public static function loadForUrl($url)
		{
			if($url === "")
			{
				return static::makeNull();
			}
			
			$segments = explode("/", trim(strtolower($url), "/"));
			$possibilities = [];
			$combinations = ["/"];
			$fullSegment = "/";
			
			// Work out what wildcards could match the URL
			foreach($segments as $segment)
			{
				$newCombinations = [];
				$possibilities[] = $fullSegment . $segment . "/*";
				$fullSegment .= $segment . "/";
				
				foreach($combinations as $combination)
				{
					$newCombinations[] = $combination . $segment . "/";
					$newCombinations[] = $combination . "*/";
				}
				
				$combinations = $newCombinations;
			}
			
			foreach($combinations as $combination)
			{
				$possibilities[] = $combination;
				$possibilities[] = $combination . "*";
			}
			
			$placeholders = array_fill(0, count($possibilities), "?");
			
			// Search the database for all redirects that might match the URL
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~from IN (" . implode(", ", $placeholders) . ") "
				. "AND ~active = TRUE";
			
			$redirects = static::makeMany($query, $possibilities);
			
			if(count($redirects) === 0)
			{
				return static::makeNull();
			}
			
			usort($redirects, [static::class, "sortRedirects"]);
			
			// Return the first URL that might match, ordered by priority
			return $redirects[0];
		}
		
		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	static[]|Pagination				The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			$redirects = static::loadAll();
			usort($redirects, [static::class, "sortRedirects"]);
			
			return $redirects;
		}
		
		/**
		 * Compares two redirects for priority
		 * @param	Redirect	$first		The redirect to compare
		 * @param	Redirect	$second		The redirect to compare to
		 * @return	int						The comparison result
		 */
		private static function sortRedirects(Redirect $first, Redirect $second)
		{
			// No wildcards, just use alphabetical order
			if(strpos($first->from, "*") === false && strpos($second->from, "*") === false)
			{
				return strcmp(trim($first->from, "/"), trim($second->from, "/"));
			}
			
			$firstSegments = explode("/", trim($first->from, "/"));
			$secondSegments = explode("/", trim($second->from, "/"));
			
			$firstHasFinalWildcard = false;
			$secondHasFinalWildcard = false;
			
			// Keep track of the final wildcard, but don't include it in comparisons
			if($first->from[strlen($first->from) - 1] === "*")
			{
				$firstHasFinalWildcard = true;
				unset($firstSegments[count($firstSegments) - 1]);
			}
			
			if($second->from[strlen($second->from) - 1] === "*")
			{
				$secondHasFinalWildcard = true;
				unset($secondSegments[count($secondSegments) - 1]);
			}
			
			for($i = 0; $i < max(count($firstSegments), count($secondSegments)); $i += 1)
			{
				// The second redirect is longer, prefer it
				if(!isset($firstSegments[$i]))
				{
					return 1;
				}
				
				// The first redirect is longer, prefer it
				if(!isset($secondSegments[$i]))
				{
					return -1;
				}
				
				// The first redirect wildcards first, second redirect should come first
				if($firstSegments[0] === "*" && $secondSegments[0] !== "*")
				{
					return 1;
				}
				
				// The second redirect wildcards first, first redirect should come first
				if($firstSegments[0] !== "*" && $secondSegments[0] === "*")
				{
					return -1;
				}
			}
			
			// The second redirect has no final wildcard, display the second redirect first
			if($firstHasFinalWildcard && !$secondHasFinalWildcard)
			{
				return 1;
			}
			
			// The first redirect has no final wildcard, display the first redirect first
			if(!$firstHasFinalWildcard && $secondHasFinalWildcard)
			{
				return -1;
			}
			
			// Similar wildcard usage, just use alphabetical order
			return strcmp(trim($first->from, "/"), trim($second->from, "/"));
		}
		
		/**
		 * Gets the instructions
		 * @return	string	The HTML instructions
		 */
		private function getInstructions()
		{
			$html = "";
			
			$html .= "<p>\n";
				$html .= "Use the * wildcard to indicate a <strong>From</strong> URL segment that can be replaced with anything.<br />\n";
				$html .= "<code>/products/*/normal/</code> will match <code>/products/nails/normal/</code> and <code>/products/staples/normal/</code>, etc.";
			$html .= "</p>\n";
			$html .= "<p>\n";
				$html .= "Use the * wildcard at the end of the <strong>From</strong> URL to indicate that anything following the URL should redirect as well.<br />\n";
				$html .= "<code>/products/nails/*</code> will match <code>/products/nails/</code> and <code>/products/nails/normal/</code>, etc.";
			$html .= "</p>\n";
			$html .= "<p>\n";
				$html .= "Use the * wildcard at the end of the <strong>To</strong> URL to indicate that anything following the <strong>From</strong> URL wildcard should be appended onto the end of the <strong>To</strong> URL.<br />\n";
				$html .= "Redirecting from <code>/products/nails/*</code> to <code>/nails/*</code> will send <code>/products/nails/normal/</code> to <code>/nails/normal/</code><br />\n";
				$html .= "<strong>Note:</strong> Omitting the wildcard from the end of the <strong>From</strong> URL, will cause the <strong>To</strong> wildcard to be ignored";
			$html .= "</p>\n";
			
			return $html;
		}
		
		/**
		 * Sets the Form Elements for this object
		 */
		public function elements()
		{
			parent::elements();
			
			$this->addElement(new Html("instructions", $this->getInstructions()));
			
			$this->addElement((new Text("from", "From (case insensitive)"))->setResultHandler(function($from)
			{
				if($from[0] !== "/")
				{
					$from = "/" . $from;
				}
				
				if($from[strlen($from) - 1] !== "*" && $from[strlen($from) - 1] !== "/")
				{
					$from .= "/";
				}
				
				$this->from = strtolower($from);
			}));
			
			$this->addElement((new Text("to", "To"))->setResultHandler(function($to)
			{
				if($to[0] !== "/")
				{
					$to = "/" . $to;
				}
				
				if($to[strlen($to) - 1] !== "*" && $to[strlen($to) - 1] !== "/")
				{
					$to .= "/";
				}
				
				$this->to = $to;
			}));
		}
		
		/**
		 * Works out the new URL from the current URL
		 * @param	string	$url	The URL to redirect from
		 * @return	string			The URL to redirect to
		 */
		public function getRedirectUrl($url)
		{
			$lastFromIndex = strlen($this->from) - 1;
			$lastToIndex = strlen($this->to) - 1;
			
			// The To field lacks a wildcard, just go ahead and redirect to it
			if($this->to[$lastToIndex] !== "*")
			{
				return $this->to;
			}
			
			$newTo = rtrim($this->to, "*");
			
			// The From field lacks a wildcard, redirect to the To field with the wildcard lopped off
			if($this->from[$lastFromIndex] !== "*")
			{
				return $newTo;
			}
			
			$newFrom = rtrim($this->from, "*");
			$fromSegments = explode("/", trim($newFrom, "/"));
			$urlSegments = explode("/", trim($url, "/"));
			$newUrl = $newTo;
			
			for($i = count($fromSegments); $i < count($urlSegments); $i += 1)
			{
				$newUrl .= $urlSegments[$i] . "/";
			}
			
			return $newUrl;
		}
		
		//region AdminNavItemGenerator
		
		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(static::getAdminNavLink(), "Redirects", [static::class], User::get()->id === Administrator::ACTIVATE_ID);
		}
		
		//endregion
	}