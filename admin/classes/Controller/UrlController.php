<?php
	namespace Controller;
	
	/**
	 * Handles complex URLs
	 */
	abstract class UrlController extends Controller
	{
		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return	UrlController[]|string[]	Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		abstract protected static function getChildPatterns();
		
		/**
		 * Matches against all possible child patterns
		 * @param	string[]	$segments	The segments to match against
		 * @return	string[][]				Groups of pattern number to segment arrays
		 */
		protected static function getPatternResults(array $segments)
		{
			$resolvedPatterns = [];
			
			foreach(static::getChildPatterns() as $pattern => $class)
			{
				$unlimitedPattern = $pattern[strlen($pattern) - 1] === "*";
				
				$trimmed = trim($pattern, "/*");
				$exploded = explode("/", $trimmed);
				
				if(!$unlimitedPattern && count($exploded) !== count($segments))
				{
					// Ignore limited patterns that don't have the same number of segments as the URL
					continue;
				}
				
				if($unlimitedPattern && count($segments) < count($exploded))
				{
					// Ignore unlimited patterns that have more segments than the URL
					continue;
				}
				
				$doesMatch = true;
				$resolvedPattern = [];
				
				for($i = 0; $i < count($exploded); $i += 1)
				{
					//Note: Match first, on the off-chance someone enters the same variable name
					$segmentDoesMatch = preg_match("/^\\\$([a-zA-Z0-9]+)\$/", $exploded[$i], $matches);
					
					if($segmentDoesMatch === 1)
					{
						$resolvedPattern[$matches[1]] = $segments[$i];
						continue;
					}
					
					if(strtolower($segments[$i]) === strtolower($exploded[$i]))
					{
						continue;
					}
					
					$doesMatch = false;
					break;
				}
				
				// In the case of an unlimited pattern, retrieve all the extra segments
				for($j = count($exploded); $j < count($segments); $j += 1)
				{
					$resolvedPattern["*"][] = $segments[$j];
				}
				
				if(!$doesMatch)
				{
					continue;
				}
				
				$resolvedPatterns[] =
				[
					"class" => $class,
					"resolvedPattern" => $resolvedPattern,
					"pattern" => $pattern
				];
			}
			
			return $resolvedPatterns;
		}
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param	UrlController	$parent		The parent to the Page Child Controller
		 * @param	string[]		$matches	An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param	string			$pattern	The pattern that was matched
		 * @return	self						An object of this type, or null if one can't be found
		 */
		abstract protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "");
		
		/**
		 * Obtains a URL Controller for a URL
		 * @param	string[]		$segments	A list of URL segments to parse
		 * @param	UrlController	$parent		The previous Page Controller, unless this is the first segment
		 * @return	static						A new Page Controller object
		 */
		public static function getControllerFor(array $segments, UrlController $parent = null)
		{
			$patternMatchGroups = static::getPatternResults($segments);
			
			foreach($patternMatchGroups as $patternMatchGroup)
			{
				/** @var UrlController $class */
				$class = $patternMatchGroup["class"];
				
				/** @var array $resolvedPattern */
				$resolvedPattern = $patternMatchGroup["resolvedPattern"];
				$pattern = $patternMatchGroup["pattern"];
				
				$controller = $class::getControllerFromPattern($parent, $resolvedPattern, $pattern);
				
				// An unlimited pattern, let's see if we can get any child controllers
				if($controller !== null && isset($resolvedPattern["*"]))
				{
					$controller = $controller::getControllerFor($resolvedPattern["*"], $controller);
				}
				
				if($controller !== null)
				{
					return $controller;
				}
			}
			
			return null;
		}
	}