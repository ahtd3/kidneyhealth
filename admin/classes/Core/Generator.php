<?php
	namespace Core;
	
	use Admin\PreviousPageDetails;
	use Core\Columns\Column;
	use Core\Columns\ColumnCell;
	use Core\Columns\ToggleColumn;
	use Core\Columns\WidgetColumn;
	use Core\Elements\Base\Element;
	use Core\Elements\Base\ElementParent;
	use Core\Elements\Base\LabelledResultElement;
	use Core\Elements\Checkbox;
	use Core\Elements\FormOption;
	use Core\Elements\Group;
	use Core\Elements\Hidden;
	use Core\Elements\Output;
	use Core\Elements\Tab;
	use Core\Elements\TabGroup;
	use Core\Elements\Text;
	use Core\ValueWrappers\FileWrapper;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkManyManyProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Database\Database;
	use Error;
	use Exception;
	use Pagination;
	use Slugging\Slugging;
	use Users\Administrator;
	use Users\User;
	
	/**
	 * An Object that can automatically generate a table, generate a create/edit form and automatically process said form
	 */
	abstract class Generator extends Entity implements CreatesTable, ElementParent
	{
		/** @var	string	The text to display to the user when the context of an operation is a single object */
		const SINGULAR = "";
		
		/** @var	string	The text to display to the user when the context of an operation is multiple objects
		 * 					(because English plurals are inconsistent and it is more reliable just to define it here ) */
		const PLURAL = "";
		
		/** @var	string	The property to display to the user when operations are performed  */
		const LABEL_PROPERTY = "id";
		
		/** @var	string|null	The property that contains any child items to display in the table */
		const SUBITEM_PROPERTY = null;
		
		/** @var	string|null	A label to give single subitem class types */
		const SUBITEM_SINGULAR = null;
		
		/** @var	string|null	A label to give plural subitem class types */
		const SUBITEM_PLURAL = null;
		
		/** @var	bool	Whether to automatically insert a position column, there must be a position field in the database */
		const HAS_POSITION = false;
		
		/** @var	bool	Whether to automatically insert an active column, there must be an active field in the database */
		const HAS_ACTIVE = false;
		
		/** @var	bool	Whether to automatically insert a 'Copy' column */
		const CAN_MAKE_COPY = false;
		
		/** @var	bool	Whether to add fields so that the slug can be changed independently of the slug property if you want */
		const CUSTOMISABLE_SLUG = true;
		
		/** @var	string	The tab that the slug form elements should be placed into */
		const SLUG_TAB = "";
		
		private const DEFAULT_POSITION = 9999999;
		
		/** @var    Column[][]	The Columns that belong to this class */
		protected static array $columns = [];
		
		/** @var	Element[]	$elements	The Form Elements that belong to this object */
		protected array|null $elements = null;
		
		/** @var	int		The current position of this object */
		public int $position = self::DEFAULT_POSITION;
		
		/** @var	bool	Whether it's active */
		public bool $active = false;
		
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			
			if(static::HAS_POSITION)
			{
				static::addProperty(new Property("position", "position", "int"));
			}
			else
			{
				static::addProperty(new Property("position", null, "int"));
			}
			
			if(static::HAS_ACTIVE)
			{
				static::addProperty(new Property("active", "active", "bool"));
			}
		}
		
		/**
		 * Adds a Column to the class
		 * @param	Column			$column		The Column to add
		 * @param	string|null		$before		The name of the Column to insert this Column before, or null to insert it at the end
		 */
		protected static function addColumn(Column $column, string $before = null)
		{
			$className = static::class;
			
			if(!isset(static::$columns[$className]))
			{
				static::$columns[$className] = [];
			}
			
			try
			{
				static::$columns[$className] = mergeAssociative(static::$columns[$className], [$column->getName() => $column], $before);
			}
			catch(Exception $exception)
			{
				throw new Error($exception->getMessage());
			}
		}
		
		/**
		 * Removes a Column from the class
		 * @param	string	$name	The name of the Column to remove
		 */
		protected static function removeColumn(string $name)
		{
			$className = static::class;
			
			if(!isset(static::$columns[$className]))
			{
				static::$columns[$className] = [];
			}
			
			unset(static::$columns[$className][$name]);
		}
		
		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			if(static::HAS_ACTIVE)
			{
				static::addColumn(new ToggleColumn("active", "Active", function(Generator $generator)
				{
					return !$generator->canEdit(User::get());
				}));
			}
			
			static::addColumn(new WidgetColumn("edit", "Edit", function(Generator $object)
			{
				if($object->canEdit(User::get()))
				{
					return new ColumnCell("edit", ["to" => $object->getEditLink()], "Edit");
				}
				
				return new ColumnCell("html-cell");
			}));
			
			if(static::CAN_MAKE_COPY)
			{
				static::addColumn(new WidgetColumn("copy", "Copy", function(Generator $object)
				{
					if($object->canAdd(User::get()))
					{
						return new ColumnCell("copy", ["to" => $object->getMakeCopyLink()], "Copy");
					}
					
					return new ColumnCell("html-cell");
				}));
			}
			
			static::addColumn(new WidgetColumn("delete", "Delete", function(Generator $object)
			{
				if($object->canDelete(User::get()) && $object->hasDelete())
				{
					return new ColumnCell("remove",
						[
							"type" => $object::SINGULAR,
							"label" => $object->{$object::LABEL_PROPERTY},
							"confirm" => true
						]);
				}
				
				return new ColumnCell("html-cell");
			}));
		}
		
		/**
		 * Loads all the Generators in this class
		 * @param	bool[]	$orderBy	List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]    The array of Generators
		 */
		public static function loadAll(array $orderBy = [])
		{
			if(static::HAS_POSITION && $orderBy === [])
			{
				$query = "SELECT ~PROPERTIES "
					. "FROM ~TABLE "
					. "ORDER BY ~position ASC";
				
				return static::makeMany($query, []);
			}
			else
			{
				/** @var static[] $results */
				$results = parent::loadAll($orderBy);
				return $results;
			}
		}
		
		/**
		 * Loads all the Objects that link to a particular Object
		 * @param	string	$propertyName	The name of the property that the objects link through
		 * @param	mixed	$id				The value to load all for
		 * @param	bool[]	$orderBy	List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return    static[]        Array of Objects
		 */
		public static function loadAllFor($propertyName, $id, array $orderBy = [])
		{
			if(static::HAS_POSITION && count($orderBy) === 0)
			{
				$results = parent::loadAllFor($propertyName, $id, ["position" => true]);
			}
			elseif(count($orderBy) === 0)
			{
				$results = parent::loadAllFor($propertyName, $id, [static::LABEL_PROPERTY => true]);
			}
			else
			{
				$results = parent::loadAllFor($propertyName, $id, $orderBy);
			}
			
			/** @var static[] $results */
			return $results;
		}
		
		/**
		 * Creates pages from objects that have particular values
		 * @param    array  $values  Key/value pairs of property name => property value
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @param    int    $current The current page to display
		 * @param    int    $perPage The number of items to display per page
		 * @return	Pagination                The Pagination object
		 */
		public static function loadPagesForMultiple(array $values, array $orderBy = [], $current = 1, $perPage = 10)
		{
			if (count($orderBy) === 0 && static::HAS_POSITION)
			{
				$orderBy = ['position' => true];
			}
			return parent::loadPagesForMultiple($values, $orderBy, $current, $perPage);
		}
		
		/**
		 * Loads all the Objects that have particular values
		 * @param	array		$values		Key/value pairs of property name => property value
		 * @param	bool[]		$orderBy	List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @return	static[]				Array of Objects
		 */
		public static function loadAllForMultiple(array $values, array $orderBy = [])
		{
			if(static::HAS_POSITION && count($orderBy) === 0)
			{
				$results = parent::loadAllForMultiple($values, ["position" => true]);
			}
			elseif(count($orderBy) === 0)
			{
				$results = parent::loadAllForMultiple($values, [static::LABEL_PROPERTY => true]);
			}
			else
			{
				$results = parent::loadAllForMultiple($values, $orderBy);
			}
			
			/** @var static[] $results */
			return $results;
		}
		
		/**
		 * Creates pages from all the objects of this type
		 * @param    bool[] $orderBy List of property name / boolean pairs (ASC true, DESC false) to order results by
		 * @param    int    $current The current page to display
		 * @param    int    $perPage The number of items to display per page
		 * @return    Pagination                The Pagination object
		 */
		public static function loadAllPages(array $orderBy = [], $current = 1, $perPage = 10)
		{
			if (count($orderBy) === 0 && static::HAS_POSITION)
			{
				$orderBy = ['position' => true];
			}
			
			return parent::loadAllPages($orderBy, $current, $perPage);
		}
		
		/**
		 * Loads an object that matches a slug (case insensitive)
		 * @param	string	$slug			The slug to match against
		 * @param	Entity	$parent			The parent of the object matching that slug
		 * @param 	bool 	$checkActive	Do we care about active or not
		 * @return	static					The matching object
		 */
		public static function loadForSlug($slug, Entity $parent = null, bool $checkActive = true)
		{
			/** @var Generator $generator */
			$generator = parent::loadForSlug($slug, $parent);
			
			if($checkActive && static::HAS_ACTIVE && !$generator->active)
			{
				return static::makeNull();
			}
			
			return $generator;
		}
		
		
		/**
		 * Returns an array of options.
		 * @param	bool		$includeNone	Whether to include a "None" option, with a value of null
		 * @return	FormOption[]					The options to choose from
		 */
		public static function loadOptions(bool $includeNone = false): array
		{
			$options = [];
			
			if($includeNone)
			{
				$options[] = new FormOption("None", null);
			}
			
			foreach(static::loadAll() as $object)
			{
				$options[] = new FormOption($object->{static::LABEL_PROPERTY}, $object);
			}
			
			return $options;
		}
		
		/**
		 * Gets a normalised name for this class
		 * @return	string	The normalised name
		 */
		public static function normalisedClassName()
		{
			return str_replace("\\", "-", static::class);
		}
		
		/**
		 * Gets an actual class name from a string
		 * @param	string				$normalised		The current, normalised class name
		 * @return	class-string<self>					The actual class name
		 */
		public static function getActualClassName(string $normalised)
		{
			return str_replace("-", "\\", $normalised);
		}
		
		/**
		 * Retrieves the link to this Generator's table
		 * @return	string	The link to the table
		 */
		public static function getAdminNavLink()
		{
			return "/admin/" . static::normalisedClassName() . "/";
		}
		
		/**
		 * General permissions for a user for this object
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can view, add, edit or delete these objects
		 */
		public static function canDo(User $user)
		{
			return $user instanceof Administrator;
		}
		
		/**
		 * Whether a specific user can view the table of objects for this type
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can view these objects
		 */
		public static function canView(User $user): bool
		{
			return static::canDo($user);
		}
		
		/**
		 * Whether a specific user can create a new object of this type
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can add a new object
		 */
		public static function canAdd(User $user): bool
		{
			return static::canDo($user);
		}
		
		/**
		 * Gets the singular subitem name
		 * @param	string|Generator|null	$type	The class name for the type of subitem to get the name for
		 * @return	string|null						The singular subitem name, or null if there's no subitems
		 */
		public static function getSubitemSingular($type): ?string
		{
			if(static::SUBITEM_PROPERTY === null)
			{
				return null;
			}
			else if(static::SUBITEM_SINGULAR !== null)
			{
				return static::SUBITEM_SINGULAR;
			}
			else
			{
				$property = static::getProperties()[static::SUBITEM_PROPERTY];
				assert($property instanceof LinkFromMultipleProperty);
				$class = $type ?? $property->getType();
				
				if(is_a($class, Generator::class, true))
				{
					/** @var string|Generator $class */
					return $class::SINGULAR;
				}
			}
			
			return null;
		}
		
		/**
		 * Gets the plural subitem name
		 * @return	string|null		The plural subitem name, or null if there's no subitems
		 */
		public static function getSubitemPlural(): ?string
		{
			if(static::SUBITEM_PROPERTY === null)
			{
				return null;
			}
			else if(static::SUBITEM_PLURAL !== null)
			{
				return static::SUBITEM_PLURAL;
			}
			else
			{
				$property = static::getProperties()[static::SUBITEM_PROPERTY];
				assert($property instanceof LinkFromMultipleProperty);
				$class = $property->getType();
				
				if(is_a($class, Generator::class, true))
				{
					/** @var string|Generator $class */
					return $class::PLURAL;
				}
			}
			
			return null;
		}
		
		/**
		 * Adds a Form FormElement to this object
		 * @template	T of Element					The type of element being added
		 * @param		T				$element		The Form FormElement to add to this object
		 * @param		string			$tabName		The tab to add the Form FormElement to (optional)
		 * @param		string			$insertBefore	The name of the FormElement to insert this FormElement before, or null to insert it at the end
		 * @return		T								The element that was added
		 */
		protected function addElement(Element $element, $tabName = null, $insertBefore = null): Element
		{
			if($tabName !== null && $tabName !== "")
			{
				$tabGroup = null;
				
				foreach($this->elements as $existingElementName => $existingElement)
				{
					if($existingElementName === "tab" && $existingElement instanceof TabGroup)
					{
						$tabGroup = $existingElement;
						break;
					}
				}
				
				if($tabGroup === null)
				{
					$tabGroup = new TabGroup("tab");
					$this->addElement($tabGroup);
				}
				
				$tab = null;
				$cleanTabName = Slugging::slug($tabName);
				
				foreach($tabGroup->children as $existingTabName => $existingTab)
				{
					if($existingTabName === $cleanTabName && $existingTab instanceof Tab)
					{
						$tab = $existingTab;
						break;
					}
				}
				
				if($tab === null)
				{
					$tab = new Tab($cleanTabName, $tabName);
					$tabGroup->add($tab);
				}
				
				$tab->add($element, $insertBefore);
			}
			else
			{
				try
				{
					$this->elements = mergeAssociative($this->elements, [$element->name => $element], $insertBefore);
				}
				catch(Exception $exception)
				{
					throw new Error($exception->getMessage());
				}
				
				$element->afterAdd($this);
			}
			
			return $element;
		}
		
		/**
		 * Inserts a new tab before another tab
		 * @param	string	$tabName	The name of the tab to insert
		 * @param	string	$before		The name of the tab to insert it before
		 * @return	Tab					The inserted tab
		 */
		protected function insertTab(string $tabName, string $before): Tab
		{
			$tabs = $this->findElement("tab");
			
			if($tabs === null)
			{
				throw new Error("No tabs have been setup yet. Make sure you've called parent::elements().");
			}
			
			assert($tabs instanceof TabGroup);
			$cleanTabName = strtolower(Slugging::slug($tabName));
			$cleanBefore = strtolower(Slugging::slug($before));
			return $tabs->add(new Tab($cleanTabName, $tabName), $cleanBefore);
		}
		
		/**
		 * Removes a Form FormElement from this object
		 * @param	string	$propertyName	The name of the property the Form FormElement corresponds to
		 */
		protected function removeElement($propertyName)
		{
			unset($this->elements[$propertyName]);
			
			foreach($this->elements as $existingElementName => $existingElement)
			{
				if($existingElementName === "tab" && $existingElement instanceof TabGroup)
				{
					$tabGroup = $existingElement;
					break;
				}
			}
			
			if(isset($tabGroup))
			{
				$tabGroup->remove($propertyName);
				
				foreach($tabGroup->children as $tab)
				{
					assert($tab instanceof Tab);
					$tab->remove($propertyName);
					
					// If this tab no longer contains any children, remove it from the tab group
					if(count($tab->children) === 0)
					{
						$tabGroup->remove($tab->name);
					}
				}
			}
		}
		
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			if (static::SLUG_PROPERTY !== null && static::CUSTOMISABLE_SLUG)
			{
				$slugGroup = (new Group("slug"))->addClass("columns");
				
				$slugElement = new Text('slug', 'URL segment');
				$slugGroup->add($slugElement->addClass("column")->setHint("Optional")->setConditional("return !updateSlugFromProperty"));
				
				//if parent::elements() is called after defining the slug property's element, we can use the label from that.
				$slugPropertyNiceName = static::SLUG_PROPERTY;
				$slugPropertyElement = $this->findElement($slugPropertyNiceName);
				
				if($slugPropertyElement instanceof LabelledResultElement)
				{
					$slugPropertyNiceName = strtolower($slugPropertyElement->label);
				}
				
				$slugGroup->add((new Checkbox('updateSlugFromProperty', "Update URL segment from  {$slugPropertyNiceName}", $this->id === null))->addClass("column")->setResultHandler(function($doUpdate)
				{
					if(!$doUpdate)
					{
						return;
					}
					
					// Empty string slugs will be ignored until save
					$this->slug = "";
				}));
				
				$this->addElement($slugGroup, static::SLUG_TAB);
			}
		}
		
		/**
		 * Gets the Form Elements that belong to this object
		 * @return	Element[]	Said Form Elements
		 */
		public function getElements()
		{
			if($this->elements === null)
			{
				$this->elements = [];
				$this->elements();
			}
			
			return $this->elements;
		}
		
		/**
		 * Sets the slug property
		 * @param	string	$value	The value to set
		 */
		protected function setSlugProperty($value)
		{
			if(static::CUSTOMISABLE_SLUG)
			{
				return;
			}
			
			parent::setSlugProperty($value);
		}
		
		/**
		 * Sets the value of a property from a form element, handles casting to the correct format.
		 * @param	string	$propertyName	The name of the property to set
		 * @param	mixed	$value			The value to set
		 */
		public function setFromElement(string $propertyName, $value)
		{
			// Assume that the setter will call setProperty()
			if(method_exists($this, "set_" . $propertyName))
			{
				$this->{"set_" . $propertyName}($value);
				return;
			}
			
			$property = static::$properties[$propertyName] ?? null;
			
			// Fallback to just setting it and seeing what happens
			if($property === null)
			{
				$this->$propertyName = $value;
				return;
			}
			
			// Set property bypasses type checking
			$this->setValue($propertyName, $value);
		}
		
		/**
		 * get the path for the controller to redirect to after a custom action
		 * @param bool $addAnother was the "Seve and create another" button clicked?
		 *
		 * @return string|null the url or null for the controller to continue with the default action
		 */
		public function getCustomRedirect($addAnother = false): ?string
		{
			return null;
		}
		
		/**
		 * get the path to edit this Generator in the admin panel
		 * @param	string		$class	The class this is an example of
		 * @return	string				The edit link
		 */
		public function getEditLink(string $class = null)
		{
			/** @var string|Generator $class */
			$class = $class ?? static::class;
			
			return "/admin/" . $class::normalisedClassName() . "/edit/" . $this->id . "/";
		}
		
		/**
		 * get the path to make a copy of this Generator in the admin panel
		 * @param	string		$class	The class this is an example of
		 * @return	string				The copy link
		 */
		public function getMakeCopyLink(string $class = null)
		{
			/** @var string|Generator $class */
			$class = $class ?? static::class;
			
			return "/admin/" . $class::normalisedClassName() . "/new/?copy=" . $this->id;
		}
		
		/**
		 * Checks it this Generator is allowed to be deleted from the admin panel
		 * @return	bool	Whether it's allowed to be deleted
		 */
		public function hasDelete()
		{
			return true;
		}
		
		/**
		 * get the path to delete this Generator in the admin panel
		 *
		 * @return string
		 */
		public function getDeleteLink()
		{
			return "/admin/action/delete/" . static::class . "/" . $this->id . "/";
		}
		
		/**
		 * Gets the previous page details for this generator
		 * @return	PreviousPageDetails|null	The previous page details
		 */
		public function getPreviousPageDetails(): ?PreviousPageDetails
		{
			$parentProperty = static::getProperties()[static::PATH_PARENT] ?? null;
			
			if(static::PARENT_PROPERTY !== null && $parentProperty instanceof LinkToProperty)
			{
				$parent = $this->{static::PARENT_PROPERTY};
				
				if($parent->isNull())
				{
					return PreviousPageDetails::makeForTableClass($parentProperty->getType());
				}
				else
				{
					return PreviousPageDetails::makeForGenerator($parent);
				}
			}
			else
			{
				return PreviousPageDetails::makeForTableClass(static::class);
			}
		}
		
		/**
		 * Gets the path that the user will be sent to when they click the cancel button
		 * @return	string|null		The path to exit to
		 */
		public function previousPageLink(): ?string
		{
			if($this->getPreviousPageDetails() !== null)
			{
				return $this->getPreviousPageDetails()->link;
			}
			else if(static::PARENT_PROPERTY === null)
			{
				return static::getAdminNavLink();
			}
			else
			{
				//get the previousPageLink() for the parent property
				$parentProperty = static::PARENT_PROPERTY;
				$parentClassName = trim(static::getProperties()[$parentProperty]->getType(), "\\");
				
				//avoid endless recursion
				if($parentClassName === get_called_class())
				{
					return static::getAdminNavLink();
				}
				
				$parent = $this->$parentProperty;
				assert($parent instanceof Generator);
				return $parent->previousPageLink();
			}
		}
		
		/**
		 * Gets the dynamic label script to use for this generator
		 * @return	string	The dynamic label
		 */
		public function getDynamicLabelScript()
		{
			$labelProperty = $this::LABEL_PROPERTY;
			return "return {$labelProperty}";
		}
		
		/**
		 * Checks that this object can be edited by a user
		 * @param	User	$user	The user to check
		 * @return	bool			Whether the user can edit this object
		 */
		public function canEdit(User $user)
		{
			return static::canDo($user);
		}
		
		/**
		 * Checks that this object can be deleted by a user
		 * @param	User	$user	The user to check
		 * @return	bool			Whether that user can delete this object
		 */
		public function canDelete(User $user)
		{
			return static::canDo($user);
		}
		
		/**
		 * Delete process triggered by the user
		 * @param	User	$user	The user who triggered the delete process
		 * @param	int		$id		The id that was passed in (in cases where we want to delete something that doesn't exist in the database)
		 */
		public function removeForUser(User $user, int $id)
		{
			$this->delete();
		}
		
		/**
		 * Gets the class for the child rows for this object
		 * @return	CreatesTable|string|null	The class for the child rows
		 */
		public function getChildClass()
		{
			if(static::SUBITEM_PROPERTY === null)
			{
				return null;
			}
			else
			{
				return static::getProperties()[static::SUBITEM_PROPERTY]->getType();
			}
		}
		
		/**
		 * Gets the child rows for this object
		 * @return	self[]	Any child rows for this object
		 */
		public function getChildRows(): ?array
		{
			if(static::SUBITEM_PROPERTY === null)
			{
				return null;
			}
			else
			{
				return $this->{static::SUBITEM_PROPERTY};
			}
		}
		
		/**
		 * Retrieves the class name for this object from the database. This is mostly of use just after changing the class, when we need to know what class to redirect to.
		 * @return	string	The updated class name
		 */
		public function getClassNameFromDatabase()
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~id = ?";
			
			$result = Database::query(static::processQuery($query), [$this->id]);
			
			return static::getClassNameForRow($result[0]);
		}
		
		/**
		 * Creates and saves a copy (clone) of the object
		 * This method will almost certainly need to be extended to reset particular
		 * properties for any given Generator class which is making use of this
		 * functionality
		 * @return 	self	The new object or null if the copy failed
		 */
		public function copy()
		{
			// permissions check
			if(!$this->canAdd(User::get()))
			{
				throw new Error("You do not have permission to create a new " . static::SINGULAR);
			}
			// else
			$newObject = clone $this;
			
			foreach($newObject->valueWrappers as $valueWrapper)
			{
				if($valueWrapper instanceOf FileWrapper)
				{
					// create a new copy of the file and put it somewhere vue admin
					// can get it for display in the form for the not-yet-saved
					// generator and passing to the Generator creation script
					// @see Core/Elements/FileElement::getResult()
					$file = $valueWrapper->getForOutput();
					if($file !== null)
					{
						$file = $file->copy($file::TEMPORARY_UPLOADS_LOCATION . $file->getFilename());
						$valueWrapper->setFromDatabase($file->getLink());
					}
				}
			}
			
			return $newObject;
		}
		
		/**
		 * Runs before the entity is saved
		 * @param	bool	$isCreate	Whether this is a new entity or not
		 */
		public function beforeSave(bool $isCreate)
		{
			parent::beforeSave($isCreate);
			
			if($isCreate && static::HAS_POSITION && $this->position === self::DEFAULT_POSITION)
			{
				$query = "SELECT MAX(~position) AS max_position "
					   . "FROM ~TABLE";
				
				$rows = Database::query(static::processQuery($query));
				$this->position = $rows[0]["max_position"] + 1;
			}
		}
		
		//region CreatesTable
		
		/**
		 * Adds some HTML to display before the admin table
		 * @return	string	The HTML to display before the table
		 */
		public static function beforeTable(): string
		{
			return "";
		}
		
		/**
		 * Adds some HTML to display after the admin table
		 * @return	string	The HTML to display after the table
		 */
		public static function afterTable(): string
		{
			return "";
		}
		
		/**
		 * Gets the heading for the table
		 * @return	string	The heading for the table
		 */
		public static function tableHeading(): string
		{
			return static::PLURAL;
		}
		
		/**
		 * Handles serialisation
		 * @return array
		 */
		public function __serialize()
		{
			// Closures attached to elements will cause issues during serialisation
			$this->elements = null;
			return (Array) $this;
		}
		
		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	static[]|Pagination				The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			if(static::PARENT_PROPERTY === null)
			{
				return static::loadAll();
			}
			else
			{
				return static::loadAllFor(static::PARENT_PROPERTY, null);
			}
		}
		
		/**
		 * Gets the array of Columns that are displayed to the user for this object type
		 * @return    Column[]        The Columns to display
		 */
		public static function getColumns(): array
		{
			$className = static::class;
			
			if(!isset(static::$columns[$className]))
			{
				static::columns();
			}
			
			return static::$columns[$className];
		}
		
		/**
		 * Gets the singular terms for the class names that can be added for this type of object
		 * @return	string[]	The singular names
		 */
		public static function getSingulars(): array
		{
			return [static::SINGULAR];
		}
		
		/**
		 * Gets addableness for every class
		 * @param	User					$user	The user to check
		 * @return	bool[]							The addableness
		 */
		public static function getCanAdds(User $user): array
		{
			return [static::canAdd($user)];
		}
		
		/**
		 * Gets the normalised class names that can be added for this type of object
		 * @return	string[]	The classes that can be added
		 */
		public static function getNormalisedClassNames(): array
		{
			return [static::normalisedClassName()];
		}
		
		/**
		 * Whether this class supports positioning
		 * @return	bool	Whether it supports positioning
		 */
		public static function hasPositioning(): bool
		{
			return static::HAS_POSITION;
		}
		
		/**
		 * Gets the value for a particular column
		 * @param	Column $column The Column to get the value for
		 * @return	ColumnCell	The value for that Column
		 */
		public function getValueForColumn(Column $column): ColumnCell
		{
			if(in_array($column, static::getColumns()))
			{
				return $column->getValueFor($this);
			}
			
			if(isset(static::getColumns()[$column->getName()]))
			{
				return static::getColumns()[$column->getName()]->getValueFor($this);
			}
			
			return new ColumnCell("html-cell");
		}
		
		//endregion
		
		//region ElementParent
		
		/**
		 * Gets the Generator that any child elements should reference
		 * @return	Generator	$generator	Said generator
		 */
		public function getGenerator(): Generator
		{
			return $this;
		}
		
		/**
		 * Searches for a child element with a specific name
		 * @param	string			$name	The name of the element
		 * @return	Element|null			An element with that name, or null if one can't be found
		 */
		public function findElement(string $name): ?Element
		{
			foreach($this->getElements() as $element)
			{
				if($element->name === $name)
				{
					return $element;
				}
				else if($element instanceof ElementParent)
				{
					$childElement = $element->findElement($name);
					
					if($childElement !== null)
					{
						return $childElement;
					}
				}
			}
			
			return null;
		}
		
		/**
		 * Performs a search using the supplied string
		 * @param	string				$term	The term to search
		 * @return	static[]					Search Results for this Searchable
		 */
		public static function search($term)
		{
			$entities = parent::search($term);
			
			if(static::HAS_ACTIVE)
			{
				$entities = array_filter($entities, function($entity)
				{
					return $entity->active;
				});
			}
			
			return $entities;
		}
		
		//endregion
	}