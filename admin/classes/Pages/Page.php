<?php
	namespace Pages;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;

	use Configuration\Configuration;
	use Configuration\Registry;
	
	use Core\Attributes\LinkFromMultiple;
	use Core\Columns\CustomColumn;
	use Core\Columns\PropertyColumn;
	use Core\Columns\ToggleColumn;

	use Core\Elements\Base\ResultElement;
	use Core\Elements\Checkbox;
	use Core\Elements\Editor;
	use Core\Elements\FormOption;
	use Core\Elements\GeneratorElement;
	use Core\Elements\GridElement;
	use Core\Elements\ImageElement;
	use Core\Elements\Radio;
	use Core\Elements\Select;
	use Core\Elements\Text;
	use Core\Elements\Textarea;

	use Core\Entity;
	use Core\Generator;

	use Core\Properties\ImageProperty;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;

	use Database\Database;

	use Error;
	use Exception;

	use Files\Image;
	
	use Pages\PageSections\PageSection;
	use Pages\PageSections\PageSectionWrapper;
	use Search\SearchResult;
	use Search\SearchResultGenerator;

	use SitemapItem;

	use Template\Banners\BannerSource;
	use Template\PageItem;
	use Template\NavItem;
	use Template\ShortCodes\ShortCode;
	use Template\ShortCodes\ShortCodeSupport;

	/**
	 * Basic Page module
	 */
	class Page extends Generator implements AdminNavItemGenerator, BannerSource, NavItem, PageItem, SearchResultGenerator, ShortCodeSupport, SitemapItem
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = 'pages';
		const ID_FIELD = 'page_id';
		const PATH_PARENT = 'parent';

		const HAS_ACTIVE = true;
		const HAS_POSITION = true;

		const SINGULAR = 'Page';
		const PLURAL = 'Pages';

		const LABEL_PROPERTY = "name";
		const PARENT_PROPERTY = 'parent';
		const SLUG_PROPERTY = 'name';
		const SLUG_TAB = "Content";

		const SUBITEM_PROPERTY = 'children';
		const SUBITEM_SINGULAR = 'Subpage';
		const SUBITEM_PLURAL = "Subpages";

		// Page
		const DO_BANNER = true;
		const BANNER_CLASS = PageBanner::class;

		const DO_IMAGE = false;
		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/page/";
		const IMAGE_WIDTH = 980;
		const IMAGE_HEIGHT = 0;
		const IMAGE_RESIZE_TYPE = ImageProperty::SCALE;

		const SITE_SECONDARY_NAV = false;

		/** @var bool */
		public $isDuplicate = false;
		public $isErrorPage = false;
		public $isExternalRedirect = false;
		public $isHomepage = false;
		public $isInternalRedirect = false;
		public $isRedirect = false;
		public $noBanner = false;
		public $onNav = false;
		public $onMenuHeader = false;
		public $onSecondaryNav = false;
		public $showSubpageLinks = false;
		public $useNewWindow = false;

		/** @var string */
		public $content = '';
		public $mainHeading = '';
		public $imageDescription = '';
		public $name = '';
		public $metaDescription = '';
		public $pageTitle = '';
		public $pageType = 'Page';
		public $path = '';
		public $redirect = '';

		/** @var Image */
		public $image;

		/** @var Page */
		public $original;
		public $parent;

		/** @var Page[] */
		public $children;

		/** @var PageBanner[] */
		public $banners;

		// where there can only be one page of a given type in the site - checked in static::afterSave()
		/** @var bool */
		protected $wasErrorPage = false;
		protected $wasHomepage = false;

		/** @var bool|null */
		private $isSelected = null;
		
		/** @var PageSectionWrapper[] */
		#[LinkFromMultiple(PageSectionWrapper::class, "page")]
		public array $pageSectionWrappers = [];

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Sets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty((new Property('name','nav_text','string'))->setIsSearchable(true));
			static::addProperty((new Property('pageTitle','page_title','string'))->setIsSearchable(true));
			static::addProperty((new Property('mainHeading', 'main_heading', 'string'))->setIsSearchable(true));
			static::addProperty((new Property('content','content','html'))->setIsSearchable(true));
			static::addProperty(new Property('noBanner','no_banner','bool'));
			static::addProperty((new LinkFromMultipleProperty("banners", static::BANNER_CLASS, (static::BANNER_CLASS)::PARENT_PROPERTY)));
			static::addProperty((new Property('metaDescription','meta_description','string'))->setIsSearchable(true));
			static::addProperty(new Property('pageType','page_type','string'));
			static::addProperty(new LinkToProperty("parent", "parent_id", self::class));
			static::addProperty((new LinkFromMultipleProperty("children", self::class, "parent")));
			static::addProperty(new Property("isRedirect"));
			static::addProperty(new Property('isExternalRedirect','external_redirect','bool'));
			static::addProperty(new Property('redirect','redirect_path','string'));
			static::addProperty(new Property('isInternalRedirect','internal_redirect','bool'));
			static::addProperty(new Property('isDuplicate','duplicate','bool'));
			static::addProperty(new LinkToProperty('original','original_id', self::class));
			static::addProperty(new Property('onNav','display_on_nav','bool'));
			static::addProperty(new Property('onMenuHeader','display_on_menu_header','bool'));
			static::addProperty(new Property('onSecondaryNav','display_on_secondary_nav','bool'));
			static::addProperty(new Property('useNewWindow','new_window','bool'));
			static::addProperty(new Property('isHomepage','is_homepage','bool'));
			static::addProperty(new Property('isErrorPage','is_error_page','bool'));
			static::addProperty(new Property('path'));
			static::addProperty(new ImageProperty('image', 'image', static::IMAGE_LOCATION, static::IMAGE_WIDTH, static::IMAGE_HEIGHT, static::IMAGE_RESIZE_TYPE));
			static::addProperty(new Property('imageDescription', 'image_description', 'string'));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("name", "Name", function(Page $page)
			{
				$html = "<a href='" . $page->path . "' target='_blank'>" . $page->name . "</a>";

				$html .= $page->getValue('isHomepage') ? ' [Homepage]' : '';
				$html .= $page->getValue('isErrorPage') ? ' [404 error page]' : '';
				$html .= $page->isRedirect ? ' [Redirect]' : '';
				$html .= $page->isDuplicate ? ' [Duplicate]' : '';

				return $html;
			}));

			static::addColumn(new PropertyColumn("pageType", "Type"));
			static::addColumn(new ToggleColumn("onNav", "On Menu"));
			static::addColumn(new ToggleColumn("onMenuHeader", "On Top Menu"));

			if(static::SITE_SECONDARY_NAV)
			{
				static::addColumn(new ToggleColumn("onSecondaryNav", "In Quicklinks"));
			}

			parent::columns();
		}

		/**
		 * Gets the class a database row should be cast to
		 * Replaces Entity::getClassNameForRow()
		 * @param    array $row The database row as an associative array
		 * @return    string|Entity            The class to cast to
		 */
		protected static function getClassNameForRow(array $row)
		{
			$pageType = $row[static::getProperties()['pageType']->getDatabaseName()];
			$pageTypes = PageType::get();

			if(isset($pageTypes[$pageType]))
			{
				return $pageTypes[$pageType]->class;
			}

			return static::class;
		}

		/**
		 * The path for the current site homepage
		 *
		 * @return string
		 */
		public static function getHomepagePath()
		{
			$page = static::loadFor('isHomepage', true);

			//fallback assume page id 1 is homepage
			if($page->isNull() || !$page->active)
			{
				$page = static::load(1);
			}

			// fallback go to root
			if($page->isNull() || !$page->active)
			{
					return '/';
			}
			//else
			return $page->getNavPath();
		}

		/**
		 * Returns an array of options.
		 * @param	bool		$includeNone	Whether to include a "None" option, with a value of null
		 * @param	Page		$parent			A page to get the child options for
		 * @param	int			$level			The current level (used to work out how many dashes to insert in front of the label)
		 * @return	FormOption[]					The options to choose from
		 */
		public static function loadOptions(bool $includeNone = false, $parent = null, $level = 0): array
		{
			$options = [];

			if($includeNone)
			{
				$options[] = new FormOption("None", null);
			}

			// This needs to be Page or else only pages of the same type will be loaded by subclasses which tends to lead to complications when you want subpages which are not the same type as the parent
			// 'self' cannot be used here because late static binding is passed through 'self', so deeper calls to 'static' are passed to the child class
			foreach(Page::loadAllFor("parent", $parent, ["position" => true]) as $page)
			{
				$options[] = new FormOption(str_repeat("-", $level) . " " . $page->name, $page);
				$options = array_merge($options, static::loadOptions(false, $page, $level + 1));
			}

			return $options;
		}

		/**
		 * Removes a page and its descendants from a selectable list
		 * @param	FormOption[] $possibleParents The list to remove from
		 * @param	Page         $page            The page to remove
		 * @return	FormOption[]						The list with the page and its children removed
		 */
		private static function removePageAndDescendantsFromList($possibleParents, $page)
		{
			// remove instances of  this page
			foreach($possibleParents as $index => $option)
			{
				if($option->value === FormOption::sanitiseValue($page->id))
				{
					unset($possibleParents[$index]);
				}
			}

			// remove any resulting gaps in numeric index
			$possibleParents = array_values($possibleParents);

			// remove children of this page (recursive)
			foreach($page->children as $child)
			{
				$possibleParents = self::removePageAndDescendantsFromList($possibleParents, $child);
			}

			return $possibleParents;
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Runs on clone
		 */
		public function __clone()
		{
			parent::__clone();

			$this->active = false;
		}

		/**
		 * Runs after the entity is saved
		 * @param bool $isCreate
		 */
		public function afterSave(bool $isCreate)
		{
			parent::afterSave($isCreate);

			// Make sure no other page is marked as the homepage
			if($this->isHomepage && !$this->wasHomepage)
			{
				Database::query(static::processQuery("UPDATE ~TABLE SET ~isHomepage = FALSE WHERE ~id != ?"), [$this->id]);
			}

			// Make sure no other page is marked as the error page
			if($this->isErrorPage && !$this->wasErrorPage)
			{
				Database::query(static::processQuery("UPDATE ~TABLE SET ~isErrorPage = FALSE WHERE ~id != ?"), [$this->id]);
			}
		}

		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			$this->addElement((new Text("name", 'Menu text'))->setHint("Required")->addClasses(['half'])->addValidation(Text::REQUIRED), "Content");

			parent::elements();
			$this->addElement((new Text("mainHeading", 'Main heading'))->setHint('if different to menu text'), "Content");
			$this->addElement(new Editor("content", 'Content'), "Content");
			$this->addElement(new GeneratorElement("pageSectionWrappers", "Page Sections"), "Content");

			if(static::DO_IMAGE)
			{
				$this->addElement(new ImageElement('image', 'Image'), "Content");
				$this->addElement((new Text('imageDescription', "Image Description"))->setHint("for SEO and non-visual browsers"), "Content");
			}

			if(static::DO_BANNER)
			{
				if(Configuration::SITE_HAS_DEFAULT_BANNER)
				{
					$this->addElement((new Checkbox("noBanner", 'No banner'))->setHint('Do not display any banners including the default from Configuration'), "Banners");
				}

				$this->addElement(new GridElement('banners', 'Banners (slideshow)'), "Banners");
			}

			$this->addElement((new Text("pageTitle", 'Page title'))->setHint('if different to content title'), "Metadata / SEO");
			$this->addElement(new Textarea("metaDescription", 'Search result text'), "Metadata / SEO");

			$this->addElement(new Select("pageType", 'Page type', $this->getPageTypeOptions()), "Setup");
			$this->addElement(new Select("parent", "Parent", $this->getPossibleParents()), "Setup");

			$pageType = "normalPage";

			if($this->isExternalRedirect)
			{
				$pageType = "externalLink";
			}
			else if($this->isInternalRedirect)
			{
				$pageType = "internalLink";
			}
			else if($this->isDuplicate)
			{
				$pageType = "duplicate";
			}

			$this->addElement(new Radio("actsAs", "Acts as",
			[
				new FormOption("Normal Page", "normalPage"),
				new FormOption("External Link (enter URL below)", "externalLink"),
				new FormOption("Internal Link (select page below)", "internalLink"),
				new FormOption("Duplicate of (select page below)", "duplicate")
			], $pageType), "Setup");
			
			$this->addElement((new Text("redirect", "Redirect URL", $this->redirect))->setConditional("return actsAs === 'externalLink'"), "Setup");
			$this->addElement((new Select("original", "Link to / duplicate of", $this->getPossibleLinks()))->setConditional("return actsAs === 'internalLink' || actsAs === 'duplicate'"), "Setup");
			$this->addElement(new Checkbox("onNav", 'Display in main menu'), "Setup");

			$this->addElement(new Checkbox("onMenuHeader", 'Display in top menu'), "Setup");

			if(static::SITE_SECONDARY_NAV)
			{
				$this->addElement(new Checkbox("onSecondaryNav", 'Display in footer menu (Quicklinks)'), "Setup");
			}

			$this->addElement(new Checkbox('useNewWindow', 'Open in new browser tab'), "Setup");
			$this->addElement(new Checkbox('isHomepage', 'Set as home page'), "Setup");
			$this->addElement(new Checkbox("isErrorPage", 'Set as error page'), "Setup");

			
		}
		
		/**
		 * Gets the visible page sections
		 * @return	PageSection[]	The visible page sections
		 */
		public function getVisiblePageSections(): array
		{
			return array_map(fn(PageSectionWrapper $wrapper) => $wrapper->pageSection, filterActive($this->pageSectionWrappers));
		}

		/**
		 * pseudo property isRedirect
		 * 		does this page redirect to another
		 *
		 * @return bool
		 */
		public function get_isRedirect()
		{
			return $this->isExternalRedirect || $this->isInternalRedirect;
		}

		/**
		 * pseudo property isNormalPage
		 * 		does this page display it's own content
		 *
		 * @return bool
		 */
		public function get_isNormalPage()
		{
			return !($this->isRedirect || $this->isDuplicate);
		}

		/**
		 * Gets the path to this page
		 * @return	string	The path to this page
		 */
		public function get_path()
		{
			$parentPath = $this->parent->isNull() ? "/" : $this->parent->path;
			$path = "{$parentPath}{$this->slug}/";

			if($this->isHomepage)
			{
				$path = "/";
			}

			return $path;
		}

		/**
		 * Gets the controller for this page
		 * @return	PageController	The requested controller
		 */
		public function getController()
		{
			$pageTypes = PageType::get();

			if(isset($pageTypes[$this->pageType]))
			{
				$controllerClass = $pageTypes[$this->pageType]->controller;

				return new $controllerClass($this);
			}
			else
			{
				return new PageController($this);
			}
		}

		/**
		 * build an array of page (pageType) type => page type name for feeding to 'pageType' property Select
		 *
		 * @return FormOption[]
		 */
		public function getPageTypeOptions()
		{
			$options = [];

			foreach(array_keys(PageType::get()) as $pageType)
			{
				$options[] = new FormOption($pageType, $pageType);
			}

			return $options;
		}

		/**
		 * Gets Options for possible parent
		 * @return FormOption[] 	List of possible parent options
		 */
		public function getPossibleParents()
		{
			$possibleParents = static::loadOptions();
			$possibleParents = self::removePageAndDescendantsFromList($possibleParents, $this);
			$possibleParents = array_merge([new FormOption("None (top level)", null)], $possibleParents);

			return $possibleParents;
		}

		/**
		 * Gets Options for possible Links
		 * @return FormOption[] 	List of possible link options
		 */
		public function getPossibleLinks()
		{
			$possibleLinks = static::loadOptions();

			// disable this page as an option, but leave children
			foreach($possibleLinks as $index => $option)
			{
				if($option->value === FormOption::sanitiseValue($this))
				{
					$possibleLinks[$index]->disabled = true;
				}
			}

			$possibleLinks = array_merge([new FormOption("None", null)], $possibleLinks);
			return $possibleLinks;
		}

		/**
		 * Handles setting the functionality for this page
		 * @param	string	$value	Specific value provided from the actsAs form element
		 */
		public function set_actsAs($value)
		{
			$this->isExternalRedirect = false;
			$this->isInternalRedirect = false;
			$this->isDuplicate = false;

			switch($value)
			{
				case "externalLink":
					$this->isExternalRedirect = true;
				break;

				case "internalLink":
					$this->isInternalRedirect = true;
				break;

				case "duplicate":
					$this->isDuplicate = true;
				break;
			}
		}

		/**
		 * set property and flag to determine if homepage has changed
		 * 		only one page can be set as the homepage at a time
		 *
		 * @param bool $bool
		 */
		public function set_isHomepage($bool)
		{
			// in save() we check wasHomepage so we can unset any other instances
			$this->wasHomepage = $this->isHomepage;
			$this->setValue('isHomepage', $bool);
		}

		/**
		 * set property and flag to determine if error page has changed
		 * 		only one page can be set as the error page at a time
		 *
		 * @param bool $bool
		 */
		public function set_isErrorPage($bool)
		{
			// in save() we check wasErrorPpage so we can unset any other instances
			$this->wasErrorPage = $this->isErrorPage;
			$this->setValue('isErrorPage', $bool);
		}

		/**
		 * validate and set the page this one copies or redirects to
		 *
		 * @param int|static $id
		 */
		public function set_original($id)
		{
			// if we have an object reduce to id
			// null converts to '';
			$id = FormOption::sanitiseValue($id);

			if($id === '')
			{
				$this->setValue('original', null);
			}
			else
			{
				// this needs to be Page or else only pages of the same type with the id will be loaded by subclasses
				// which tends to lead to "the selected page no longer exists"
				// 'self' continues to not be viable (see comment in loadOptions())
				$newParent = Page::load($id);

				if($newParent->id === $this->original->id)
				{
					// no change
					return;
				}
				//else

				// san checks

				if($newParent->isNull())
				{
					addMessage('The selected page to duplicate or redirect to no longer exists.');
					$this->setValue('original', null);
					return;
				}

				// skip this check if this is a new page
				// getPossibleLinks() should have prevented this option being selectable by the user
				if($this->id && $newParent->id === $this->id)
				{
					addMessage('The page can not be it\'s own copy or redirect.');
					$this->setValue('original', null);
					return;
				}
				// good
				$this->setValue('original', $newParent);
			}
		}

		/**
		 * validate and set parent
		 *
		 * @param int|static $id
		 */
		public function set_parent($id)
		{
			// if we have an object reduce to id
			// null converts to '';
			$id = FormOption::sanitiseValue($id);

			if($id === '')
			{
				$this->setValue('parent', null);
			}
			else
			{
				// this needs to be Page or else only pages of the same type with the id will be loaded by subclasses
				// which tends to lead to "the selected parent no longer exists"
				// 'self' continues to not be viable (see comment in loadOptions())
				$newParent = Page::load($id);

				if($newParent->id === $this->parent->id)
				{
					// no change
					return;
				}
				// else

				// san checks

				if($newParent->isNull())
				{
					addMessage('The selected parent ' . static::SINGULAR . ' no longer exists.');
					$this->setValue('parent', null);
					return;
				}

				// skip these checks if this is a new page
				// getPossibleParents() should have prevented these options being presented to the user
				if($this->id && in_array($this,$newParent->getNavItemChain()))
				{
					addMessage('The ' . static::SINGULAR . ' can not be moved below itself.');
					return;
				}

				if($this->id && $newParent->id === $this->id)
				{
					addMessage('The ' . static::SINGULAR . ' can not be it\'s own parent.');
					return;
				}
				// good
				$this->setValue('parent',$newParent);
			}
		}

		/*~~~~~
		 * Interface methods
		 **/

		//region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			$identifiers = [];

			foreach(PageType::get() as $pageType)
			{
				$identifiers[] = $pageType->class;
			}

			return new AdminNavItem(static::getAdminNavLink(), "Pages", $identifiers, Registry::PAGES);
		}

		//endregion

		// region BannerSource

		/**
		 * Get the items to display as the page banner/slideshow
		 *
		 * return SiteBanner[] the active slides for the page, the default banner for the site
		 *		or  empty if specifically disabled for this page
		 */
		public function getVisibleBanners()
		{
			if(!static::DO_BANNER || $this->noBanner)
			{
				return [];
			}
			// still here?

			$banners = array_filter($this->banners, function($item)
			{
				return $item->active;
			});

			if(empty($banners))
			{
				if($this->parent->isNull())
				{
					$banner = Configuration::acquire()->defaultBanner;
					if(!$banner->isNull())
					{
						$banner->setContentParent($this);
						$banners = [$banner];
					}
				}
				else
				{
					$banners = $this->parent->getVisibleBanners();
				}
			}

			return $banners;
		}

		// endregion

		// region NavItem

		/**
		 * Gets any children this item has
		 * @return	NavItem[]	The children this item has
		 */
		public function getChildNavItems()
		{
			$query = "SELECT ~PROPERTIES "
				   . "FROM ~TABLE "
				   . "WHERE ~parent = ? "
				   . "AND ~active = TRUE "
				   . "AND ~onNav = TRUE "
				   . "ORDER BY ~position ASC";

			// This needs to be Page or else only pages of the same type will be loaded by subclasses which tends to lead to complications when you want subpages which are not the same type as the parent
			// 'self' cannot be used here because late static binding is passed through 'self', so deeper calls to 'static' are passed to the child class
			return Page::makeMany($query, [$this->id]);
		}

		/**
		 * Gets the complete chain of Nav Items from parent to child, including the current Nav Item
		 * @return	NavItem[]	The chain of Nav Items
		 */
		public function getNavItemChain()
		{
			$parent = $this->getParentNavItem();
			$return = [$this];
			return ($parent !== null) ? array_merge($parent->getNavItemChain(), $return) : $return;
		}

		/**
		 * Gets the label for this item
		 * @return	string	The label for this item
		 */
		public function getNavLabel()
		{
			return $this->name;
		}

		/**
		 * Gets the path to this item
		 * @return	string	The path to this item
		 */
		public function getNavPath()
		{
			// must be part of getNavPath() rather than get_path() otherwise it can break child paths when the child calls {page}->path
			if($this->isRedirect)
			{
				if($this->isExternalRedirect)
				{
					return $this->redirect;
				}
				else
				{
					return $this->original->getNavPath();
				}
			}
			else
			{
				return $this->path;
			}
		}

		/**
		 * Gets the parent of this page
		 * @return	Page	The parent item
		 */
		public function getParentNavItem()
		{
			return ($this->parent->isNull()) ? null : $this->parent;
		}

		/**
		 * Gets whether this item is the homepage
		 * @return	bool	Whether this item is the homepage
		 */
		public function isHomepage()
		{
			return $this->isHomepage;
		}

		/**
		 * Gets whether this item is currently selected
		 * @param	NavItem $currentNavItem The current nav item
		 * @return	bool	Whether this item is currently selected
		 */
		public function isNavSelected(NavItem $currentNavItem = null)
		{
			if($this->isSelected === null)
			{
				$this->isSelected = isGeneratorSelected($this, $currentNavItem);
			}

			return $this->isSelected;
		}

		/**
		 * Gets whether this item opens in a new window
		 * @return	bool	Whether this item opens in a new window
		 */
		public function isOpenedInNewWindow()
		{
			return $this->useNewWindow;
		}

		// region PageItem

		/**
		* Get the page that should be used in the template, ie, if this page is the duplicate of another
		* @return  Page 	$page 	the page to use as content
		*/
		public function getContentPage()
		{
			if ($this->isDuplicate)
			{
				return $this->original;
			}

			return $this;
		}

		/**
		 * Gets the content title for the item (where this is not laid out by the user in an Editor element)
		 * don't do splitting and html wrapping here because getPageTitle() falls back to use this logic, set up a filter in Controller\Twig.php
		 * @return	string	Usually the contents of the page h1.
		 */
		public function getMainHeading()
		{
			$title = $this->mainHeading;
			if($title === '')
			{
				$title = $this->name;
			}

			return $title;
		}

		/**
		 * Gets the <meta name="description" /> for the item
		 * @return	string	The text to use in the metadata description
		 */
		public function getMetaDescription()
		{
			return $this->metaDescription;
		}

		/**
		 * Gets the page <title> for the item
		 * @return	string	The text to use for the page title
		 */
		public function getPageTitle()
		{
			$title = $this->pageTitle;

			if($title === '')
			{
				$title = $this->getMainHeading();
			}

			return $title;
		}

		/**
		 * Gets the page content. This will usually just return a property, but might be eg a rendered twig template.
		 * Most templates will probably never bother with this method, calling the property directly.
		 * @return	string	The html to output in the template
		 */
		public function getPageContent()
		{
			return $this->content;
		}
		// endregion

		//region SearchResult

		/**
		 * Generates a Search Result for this object
		 * @return	SearchResult	The Search Result
		 */
		public function generateSearchResult()
		{
			return (new SearchResult($this->path, $this->getPageTitle(), $this->getMetaDescription()))->setRelevance($this->relevance);
		}

		//endregion SearchResult

		//region ShortCodeSupport

		/**
		 * Gets a unique identifier for this class
		 * @return    string    An identifier that uniquely identifies this class
		 */
		public static function getClassShortcodeIdentifier()
		{
			return "Page";
		}

		/**
		 * Format and output properties as a coherent string of HTML
		 * @return	string	The HTML for the gallery
		 * @throws	Error	If something goes wrong while rendering the gallery
		 */
		public function getShortcodeContent()
		{
			return ShortCode::expandHtml($this->content);
		}

		/**
		 * Gets a unique identifier for this object
		 * @return    string    An identifier that uniquely identifies this object
		 */
		public function getShortcodeIdentifier()
		{
			return $this->id;
		}

		/**
		 * Loads an object for this class, given an identifier
		 * @param    string $identifier The identifier to load from
		 * @return    PageItem                    An object that can be outputted to the page, or null if the correct one cannot be found
		 */
		public static function loadForShortcodeIdentifier($identifier)
		{
			$page = static::loadForMultiple(
			[
				"id" => $identifier,
				"active" => true
			]);

			return $page->isNull() ? null : $page;
		}

		//endregion

		// region SitemapItem

		/**
		 * return paths to active pages for inclusion in sitemap
		 *
		 *	@param Page $parent
		 *
		 * @return string[]
		 */
		public static function getSitemapUrls($parent = null)
		{
			$paths = [];

			// completely disabling a parent page can be expected to remove all sub pages from the site.
			$pages = static::loadAllForMultiple(['parent' => $parent, 'active' => true], []);

			foreach($pages as $page)
			{
				if($page->isHomepage)
				{
					$paths[] = '/';
				}
				elseif($page->isExternalRedirect)
				{
					// do not include in sitemap
					continue;
				}
				else
				{
					$paths[] = $page->path;
				}

				// sub pages
				$paths = array_merge($paths, static::getSitemapUrls($page));
			}

			return $paths;
		}

		// endregion
	}
