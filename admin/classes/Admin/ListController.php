<?php
	namespace Admin;

	use Controller\JsonController;
	use Controller\UrlController;
	use Core\Columns\Column;
	use Core\CreatesTable;
	use Core\Exportable;
	use Core\Filterable;
	use Core\Generator;
	use Pagination;
	use Users\User;
	
	/**
	 * Handles object lists
	 */
	class ListController extends AdminController
	{
		/** @var string|Generator  */
		private $class = null;
		private $page = 1;

		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/json/' => self::class,
				'/$page/*' => self::class
			];
		}

		/**
		 * Generates the JSON array for an array of objects
		 * @param	Generator[]|Pagination	$objects	The objects to create the array for
		 * @param	string|CreatesTable		$class		The class to retrieve the columns from
		 * @param	Generator|null			$parent		The parent object, used for defining the expected singular value
		 * @return	array								The JSON array to pass to the system
		 */
		public static function generateJsonArray($objects, $class, ?Generator $parent = null)
		{
			/** @var Column[] $columns */
			$columns = $class::getColumns();
			$columnJson = [];

			foreach($columns as $column)
			{
				$columnJson[] =
				[
					"heading" => $column->getHeading(),
					"alignment" => $column->getAlignment()
				];
			}

			$json =
			[
				"classes" => [],
				"navId" => $class,
				"heading" => $class::tableHeading(),
				"beforeTable" => $class::beforeTable(),
				"afterTable" => $class::afterTable(),
				"columns" => $columnJson,
				"rows" => [],
				"totalPages" => null,
				"filterable" => is_a($class, Filterable::class, true),
				"message" => outputMessage(),
				"exportable" => is_a($class, Exportable::class, true) ? $class::getExportableFormats() : false,
			];

			$classNames = $class::getNormalisedClassNames();
			$singulars = $class::getSingulars();
			$canAdds = $class::getCanAdds(User::get());

			for($i = 0; $i < count($classNames); $i += 1)
			{
				if($parent !== null)
				{
					$actualClassName = Generator::getActualClassName($classNames[$i]);
					$singular = $parent::getSubitemSingular($actualClassName) ?? $singulars[$i];
				}
				else
				{
					$singular = $singulars[$i];
				}

				$json["classes"][] =
				[
					"name" => $classNames[$i],
					"singular" => $singular,
					"canAdd" => $canAdds[$i]
				];
			}

			if($objects instanceof Pagination)
			{
				$json["totalPages"] = $objects->totalPages();
				$objects = $objects->results;
			}

			foreach($objects as $generator)
			{
				$row =
				[
					"id" => $generator->id,
					"class" => $generator::normalisedClassName(),
					"singular" => $generator::SINGULAR,
					"subitemSingular" => $generator::getSubitemSingular($generator->getChildClass()),
					"subitemPlural" => $generator::getSubitemPlural(),
					"cells" => [],
					"state" => "closed"
				];

				if($class::hasPositioning())
				{
					$row["position"] = $generator->position;
				}

				foreach($columns as $column)
				{
					$row["cells"][] = $generator->getValueForColumn($column);
				}

				$childRows = $generator->getChildRows();
				$childClass = $generator->getChildClass();

				if($childRows !== null && $childClass !== null)
				{
					$row["children"] = static::generateJsonArray($childRows, $childClass, $generator);
				}

				$json["rows"][] = $row;
			}

			return $json;
		}

		/**
		 * Retrieves a Page Child Controller that matches a pattern, or returns null otherwise
		 * @param    UrlController $parent  The parent to the Page Child Controller
		 * @param    string[]      $matches An array of name to string values, so a pattern '/$category/$product/$size/' matching "/pets/dog/small/" would give ["category" => "pets", "product" => "dog", "size" => "small"]
		 * @param    string        $pattern The pattern that was matched
		 * @return    UrlController                        An object of this type, or null if one can't be found
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			if(isset($matches["class"]))
			{
				/** @var string|CreatesTable $className */
				$className = Generator::getActualClassName($matches["class"]); // Unsubstitute '-' character

				if(!is_a($className, CreatesTable::class, true))
				{
					// Should be ignored, can't create a table
					return null;
				}
				else if(!$className::canView(User::get()))
				{
					return new AccessController("You do not have access to view this page.");
				}

				return new self($className);
			}
			else if(!$parent instanceof ListController)
			{
				return null;
			}
			else if(isset($matches["page"]))
			{
				$parent->page = $matches["page"];
				return $parent;
			}
			// JSON
			else
			{
				if(is_a($parent->class, Filterable::class, true) && ($_GET["filter"] ?? "") !== "")
				{
					/** @var Filterable|string $class */
					$class = $parent->class;
					$contents = $class::loadForFilter($_GET["filter"]);
				}
				else
				{

					/** @var Generator[]|Pagination $contents */
					$contents = $parent->class::loadAllForTable($parent->page);
				}

				$json = static::generateJsonArray($contents, $parent->class);

				return new JsonController($json);
			}
		}

		/**
		 * Creates a new List Controller
		 * @param	string	$class	The class
		 */
		public function __construct(string $class)
		{
			$this->class = $class;
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