<?php
	namespace Admin;

	use Controller\JsonController;
	use Controller\UrlController;
	use Core\Elements\Base\Element;
	use Core\Elements\Base\ResultElement;
	use Core\Generator;
	use Users\User;
	
	/**
	 * Handles add and edit forms
	 */
	class EditController extends AdminController
	{
		/** @var class-string<Generator> */
		public $class = null;
		public $id = null;
		public $parentId = null;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/element/$element/' => self::class,
				'/json/' => self::class,
				'/$parentId/*' => self::class
			];
		}

		/**
		 * Gets the JSON value for an array of elements
		 * @param	Element[]	$elements	The elements to search through
		 * @return	array					The JSON value for those elements
		 */
		public static function getJsonValue(array $elements)
		{
			$output = [];
			
			foreach($elements as $element)
			{
				if(!$element instanceof ResultElement)
				{
					continue;
				}
				
			
				$output[$element->name] = $element->getJsonValue();
			}
			
			return $output;
		}
		
		/**
		 * Converts shorthand ini byte values to full byte values
		 * @param	string	$setting	The ini setting to convert
		 * @return	int					The full byte value
		 */
		private static function getBytesFromShorthand(string $setting): int
		{
			switch(substr($setting, -1))
			{
				// Kilobytes
				case "K":
				case "k":
					return (int) $setting * 1024;
				
				// Megabytes
				case "M":
				case "m":
					return (int) $setting * 1024 * 1024;
				
				// Gigabytes
				case "G":
				case "g":
					return (int) $setting * 1024 * 1024 * 1024;
			}
			
			return (int) $setting;
		}
		
		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param UrlController $parent  The parent to the Page Child Controller
		 * @param string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param string        $pattern The pattern that was matched
		 * @return    UrlController                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			if(isset($matches["class"]))
			{
				// Unsubstitute '-' character
				return new self(Generator::getActualClassName($matches["class"]), $matches["id"] ?? null);
			}
			else if(!$parent instanceof EditController)
			{
				return null;
			}
			else if(isset($matches['element']))
			{
				$generator = ($parent->class)::load($parent->id);
				$element = $generator->findElement($matches['element']);
				return new JsonController(['data' => $element->getJson()]);
			}
			else if(isset($matches["parentId"]))
			{
				$parent->parentId = $matches["parentId"];
				return $parent;
			}
			// JSON
			else
			{
				if($parent->id !== null)
				{
					$generator = $parent->class::load($parent->id);
					$heading = "Edit {$generator->{$generator::LABEL_PROPERTY}}";
				}
				else if(isset($_GET["copy"]))
				{
					$class = $parent->class;
					$original = $class::load($_GET["copy"]);

					$generator = $original->copy();
					$heading = "Copy of {$original->{$original::LABEL_PROPERTY}}";
				}
				else
				{
					$class = $parent->class;
					$generator = new $class;
					$heading = "Add " . $class::SINGULAR;
				}

				if($parent->parentId !== null && $parent->class::PARENT_PROPERTY !== null)
				{
					$generator->{$parent->class::PARENT_PROPERTY} = $parent->parentId;
				}

				$json =
				[
					"heading" => $heading,
					"class" => $parent->class::normalisedClassName(),
					"navId" => $parent->class,
					"singular" => $parent->class::SINGULAR,
					"id" => $generator->id,
					"previousPageDetails" => $generator->getPreviousPageDetails(),
					"previousPageLink" => $generator->previousPageLink(),
					"hasAdd" => $generator::canAdd(User::get()),
					"values" => static::getJsonValue($generator->getElements()),
					"uploadLimit" => (int) (min(self::getBytesFromShorthand(ini_get("upload_max_filesize")), self::getBytesFromShorthand(ini_get("post_max_size"))) / 2),
					"elements" => [],
					"message" => outputMessage()
				];

				foreach($generator->getElements() as $element)
				{
					$json["elements"][] = $element->getJson();
				}

				if($parent->class::HAS_ACTIVE)
				{
					$json["active"] = $generator->active;
				}

				return new JsonController($json);
			}
		}

		/**
		 * Creates a new Form Controller
		 * @param	string	$class	The class
		 * @param	int		$id		The identifier for the object (optional)
		 */
		public function __construct(string $class, ?int $id = null)
		{
			$this->class = $class;
			$this->id = $id;
		}

		/**
		 * Retrieves the location of the template to display to the user
		 * @return    string    The location of the template
		 */
		protected function getTemplateLocation()
		{
			return "vue-page.twig";
		}
	}