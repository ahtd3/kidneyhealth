<?php
	namespace Menus;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Configuration\Registry;
	use Core\Columns\CustomColumn;
	use Core\Elements\GeneratorElement;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Elements\Textarea;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkFromMultipleProperty;
	use Core\Properties\Property;
	use Files\Image;
	use Pages\Page;
	use Pages\PageType;
	use SitemapItem;
	use Template\NavItem;
	use Template\PageItem;
	
	/**
	* This is a menu. Like, for food or drinks.
	*/
	class Menu extends Generator implements AdminNavItemGenerator, NavItem, PageItem, SitemapItem
	{
		const TABLE = 'menus';
		const ID_FIELD = 'menu_id';
		const SINGULAR = 'Menu';
		const PLURAL = 'Menus';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = 'name';
		const PATH_PARENT = 'page';
		const SLUG_PROPERTY = 'name';
		const SLUG_TAB = "Content";
		
		public string $name = "";
		public string $path = "";
		public string $pageTitle = "";
		public string $metaDescription = "";
		private ?bool $isSelected = null;
		public ?Image $image = null;
		public ?Image $thumbnail = null;
		public Page $page;
		
		/** @var MenuItem[] */
		public array $items;
		
		/** @var MenuItem[] */
		private array $visibleItems;
		
		/** @var MenuGroup[] */
		public array $groups;
		
		/** @var MenuGroup[] */
		private array $visibleGroups;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new Property('name', 'name', 'string'));
			static::addProperty(new Property('path'));
			static::addProperty(new Property('pageTitle', 'page_title', 'string'));
			static::addProperty(new Property('metaDescription', 'meta_description', 'string'));
			static::addProperty(new ImageProperty('image', 'image', DOC_ROOT . '/resources/images/menus', 594, 1000));
			static::addProperty(new ImageProperty('thumbnail', 'thumbnail', DOC_ROOT . '/resources/images/menus', 344, 419, ImageProperty::CROP));
			static::addProperty(new Property('page'));
			static::addProperty(new LinkFromMultipleProperty("items", MenuItem::class, "menu"));
			static::addProperty(new LinkFromMultipleProperty("groups", MenuGroup::class, "menu"));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new CustomColumn("name", "Name", function(Menu $menu)
			{
				return $menu->getLinkTag();
			}));

			parent::columns();
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			$this->addElement((new Text('name', 'Menu name'))->setHint("Required")->addValidation(Text::REQUIRED), 'Content');

			parent::elements(); //includes slug elements which go after name

			$this->addElement(new ImageElement('image', 'Image'), 'Content');
			$this->addElement((new ImageElement('thumbnail', "Thumbnail"))->addClass('half'), "Content");
			$this->addElement(new GeneratorElement("items", "Items"), "Menu");
			$this->addElement(new GeneratorElement("groups", "Item Groups"), "Menu");
			$this->addElement((new Text('pageTitle', "Page title"))->setHint('if different from menu name'), "Metadata / SEO");
			$this->addElement(new Textarea('metaDescription', 'Search result text'), "Metadata / SEO");
		}

		/**
		 * Gets the menu page
		 * @return	Page	The menus page
		 */
		public function get_page()
		{
			return PageType::getPageOfType(PageType::MENUS);
		}

		/**
		 * Gets the path to this menu
		 * @return	string	The path
		 */
		public function get_path()
		{
			$parentPath = $this->{static::PATH_PARENT}->path;
			return "{$parentPath}{$this->slug}/";
		}

		/**
		 * Gets a link to this Menu
		 * @return	string	The link to the menu
		 */
		public function getLinkTag()
		{
			return "<a href='" . $this->getNavPath() . "' target='_blank'>" . htmlentities($this->name) . "</a>";
		}
		
		/**
		 * @return MenuItem[] just the active menu items for display
		 */
		public function getVisibleMenuItems(): array
		{
			$this->visibleItems = $this->visibleItems ?? filterActive($this->items);
			
			return $this->visibleItems;
		}
		
		/**
		 * Gets the visible menu groups in this menu
		 * @return	MenuGroup[]		The visible menu groups
		 */
		public function getVisibleMenuGroups(): array
		{
			$this->visibleGroups = $this->visibleGroups ?? filterActive($this->groups);
			
			return $this->visibleGroups;
		}

		/*~~~~~
		 * Interface methods
		 **/

		// region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return	AdminNavItem		The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(Menu::getAdminNavLink(), "Menus", [Menu::class], Registry::MENUS,
			[
				new AdminNavItem(MenuAttribute::getAdminNavLink(), "Attributes", [MenuAttribute::class])
			]);
		}

		// endregion

		// region PageItem
		/**
		 * Gets the content title for the item (where this is not laid out by the user in an Editor element)
		 * @return	string	Usually the contents of the page h1.
		 */
		public function getMainHeading()
		{
			return $this->name;
		}
		
		/**
		 * Gets the <meta name="description" /> for the item
		 * @return	string	The text to use in the metadata description
		 */
		public function getMetaDescription()
		{
			$description = $this->metaDescription;
			
			if($description === '')
			{
				$description = $this->page->getMetaDescription();
			}
			
			return $description;
		}

		/**
		 * Gets the page <title> for the item
		 * @return	string	The text to use for the page title
		 */
		public function getPageTitle()
		{
			return $this->pageTitle !== '' ? $this->pageTitle : $this->page->getPageTitle();
		}
		
		/**
		 * Gets the page content. This will usually just return a property, but might be eg a rendered twig template.
		 * Most templates will probably never bother with this method, calling the property directly.
		 * @return	string	The html to output in the template
		 */
		public function getPageContent()
		{
			// unused
			return '';
		}
		// endregion

		// region NavItem

		/**
		 * Gets any children this item has
		 * @return	NavItem[]	The children this item has
		 */
		public function getChildNavItems()
		{
			return [];
		}

		/**
		 * Gets the complete chain of Nav Items from parent to child, including the current Nav Item
		 * @return	NavItem[]	The chain of Nav Items
		 */
		public function getNavItemChain()
		{
			$return = [$this];
			return array_merge($this->page->getNavItemChain(), $return);
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
			return $this->path;
		}
		
		/**
		 * Gets whether this item is the homepage
		 * @return	bool	Whether this item is the homepage
		 */
		public function isHomepage()
		{
			return false;
		}

		/**
		 * Gets whether this item is currently selected
		 * @param	NavItem $currentNavItem The current nav item
		 * @return	bool	Whether this item is currently selected
		 */
		public function isNavSelected(NavItem $currentNavItem = null): bool
		{
			$this->isSelected = $this->isSelected ?? isGeneratorSelected($this, $currentNavItem);
			return $this->isSelected;
		}

		/**
		 * Gets whether this item opens in a new window
		 * @return	bool	Whether this item opens in a new window
		 */
		public function isOpenedInNewWindow()
		{
			return false;
		}

		// endregion

		// region SitemapItem

		/**
		 * return paths to active pages for inclusion in sitemap
		 *
		 *	@param null $parent not used
		 *
		 * @return string[]
		 */
		public static function getSitemapUrls($parent = null)
		{
			// check if site should be returning items for this module
			if(!Registry::MENUS)
			{
				return [];
			}
			//else

			$paths = [];

			$menus = static::loadAllFor('active', true, []);

			foreach($menus as $menu)
			{
				$paths[] = $menu->path;
			}

			return $paths;
		}

		// endregion
	}
