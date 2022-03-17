<?php
	namespace Blog;

	use Admin\AdminNavItem;
	use Admin\AdminNavItemGenerator;
	use Configuration\Registry;
	use Core\Columns\CustomColumn;
	use Core\Columns\PropertyColumn;
	use Core\Elements\Date as DateElement;
	use Core\Elements\Editor;
	use Core\Elements\GeneratorElement;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Elements\Textarea;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkFromProperty;
	use Core\Properties\Property;
	use Database\Database;
	use DateTimeImmutable;
	use Files\Image;
	use Pages\Page;
	use Pages\PageType;
	use Pagination;
	use Search\SearchResult;
	use Search\SearchResultGenerator;
	use SitemapItem;
	use Template\Banners\BannerSource;
	use Template\PageItem;
	
	/**
	 * A single blog article
	 */
	class BlogArticle extends Generator implements AdminNavItemGenerator, BannerSource, PageItem, SearchResultGenerator, SitemapItem
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = 'blog_articles';
		const ID_FIELD = 'blog_article_id';
		const SINGULAR = 'Article';
		const PLURAL = 'Articles';
		const HAS_ACTIVE = true;
		const LABEL_PROPERTY = 'mainHeading';
		const PATH_PARENT = "page";
		const SLUG_PROPERTY = "mainHeading";
		const SLUG_TAB = "Content";

		// BlogArticle
		const DO_BANNER = Page::DO_BANNER;

		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/blog/";
		const IMAGE_WIDTH = Page::IMAGE_WIDTH;
		const IMAGE_HEIGHT = Page::IMAGE_HEIGHT;
		const IMAGE_RESIZE_TYPE = ImageProperty::SCALE;
		const THUMBNAIL_WIDTH = 446;
		const THUMBNAIL_HEIGHT = 296;
		const THUMBNAIL_RESIZE_TYPE = ImageProperty::SCALE;

		const PER_PAGE = 9;

		/** @var string */
		public $author = '';
		public $content = '';
		public $mainHeading = '';
		public $imageDescription = '';
		public $pageTitle = '';
		public $metaDescription = '';
		public $path = '';
		public $summary = '';

		/** @var ArticleBanner */
		public $banner = null;

		/** @var DateTimeImmutable */
		public $date = null;

		/** @var Image */
		public $image = null;
		public $thumbnail = null;

		/** @var Page */
		public $page = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			static::addProperty((new Property('mainHeading', 'main_heading', 'string'))->setIsSearchable(true));
			static::addProperty((new Property("author", "author", "string"))->setIsSearchable(true));
			static::addProperty(new Property('date', 'date', 'date'));
			static::addProperty((new Property('summary', 'summary', 'string'))->setIsSearchable(true));
			static::addProperty(new Property('pageTitle', 'page_title', 'string'));
			static::addProperty(new Property('metaDescription', 'meta_description', 'string'));
			static::addProperty(new LinkFromProperty('banner', ArticleBanner::class, 'parent'));
			static::addProperty((new Property('content', 'content', 'html'))->setIsSearchable(true));
			static::addProperty(new ImageProperty('image', 'image', static::IMAGE_LOCATION, static::IMAGE_WIDTH, static::IMAGE_HEIGHT, static::IMAGE_RESIZE_TYPE));
			static::addProperty(new ImageProperty('thumbnail', 'thumbnail', static::IMAGE_LOCATION, static::THUMBNAIL_WIDTH, static::THUMBNAIL_HEIGHT, static::THUMBNAIL_RESIZE_TYPE));
			static::addProperty(new Property('imageDescription', 'image_description', 'string'));

			static::addProperty(new Property("page"));
			static::addProperty(new Property("path"));
		}

		/**
		 * Sets the array of Columns that are displayed to the user for this object type
		 */
		protected static function columns()
		{
			static::addColumn(new PropertyColumn('mainHeading', 'Article'));

			static::addColumn(new CustomColumn("date", "Date", function(BlogArticle $generator)
			{
				return $generator->date->format('Y-m-d');
			}));

			parent::columns();
		}

		/**
		 * Gets an array of the most recent blog articles
		 * @param	int			$limit	The number of recent articles to get
		 * @return	static[]			Recent blog articles
		 */
		public static function getRecent($limit = 3)
		{
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~active = true "
				. "ORDER BY ~date DESC, ~id DESC  "
				. "LIMIT ?";

			return static::makeMany($query, [$limit]);
		}

		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	static[]|Pagination				The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			return static::loadAll(['date' => false, 'id' => false]);
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this object
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement((new Text('mainHeading', 'Article Title'))->setHint("Required")->addValidation(Text::REQUIRED), 'Content', 'slug');
			//$this->addElement(new Text('author', 'Author'), 'Content');
			$this->addElement(new DateElement('date', 'Date'), 'Content');
			$this->addElement(new Textarea('summary', 'Summary'), 'Content');

			if(static::DO_BANNER)
			{
				$this->addElement(new GeneratorElement('banner', 'Banner'), 'Content');
			}

			$this->addElement(new Editor('content', 'Content'), 'Content');
			$this->addElement((new ImageElement("image", "Image"))->addChild(new ImageElement("thumbnail", "Thumbnail")), "Content");
			$this->addElement((new Text('imageDescription', 'Image description'))->setHint("for SEO and non-visual browsers"), "Content");

			$this->addElement((new Text('pageTitle', 'Page Title'))->setHint('if different to article title'), 'Metadata / SEO');
			$this->addElement((new Textarea('metaDescription', 'Search result text'))->setHint('if different to summary'), 'Metadata / SEO');

		}

		/**
		 * Gets the blog module page
		 * @return Page The blog module page
		 */
		public function get_page()
		{
			return PageType::getPageOfType(PageType::BLOG);
		}

		/**
		 * Gets the path where this article can be found on the site
		 * @return string the path of this article
		 */
		public function get_path()
		{
			$parentPath = $this->{static::PATH_PARENT}->path;
			return "{$parentPath}{$this->slug}/";
		}

		/**
		 * Gets the article immediately after this one
		 *
		 * @return BlogArticle
		 */
		public function getNext()
		{
			$formatedDate = $this->date->format('Y-m-d H:i:s');
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~active = true "
				. "AND (~date < ? OR (~date = ? AND ~id < ?))"
				. "ORDER BY ~date DESC, ~id DESC "
				. "LIMIT 1";

			return static::makeOne($query, [$formatedDate, $formatedDate, $this->id]);
		}

		/**
		 * Gets the page number to append to a "back" link or breadcrumb
		 * sql based on http://www.tech-recipes.com/rx/17470/mysql-how-to-get-row-number-order-5/
		 *
		 * @todo this really needs to be made generic and go with the loadPages() methods in Entity
		 * @todo change get_path() so it can take an article as a parameter and append the appropriate ?page=n
		 *
		 * @return int
		 */
		public function getPageNumber()
		{
			// san check
			if(!$this->active)
			{
				// go to first page
				return 1;
			}
			// else
			// set up a temporary user defined variable
			Database::query('set @row_num = 0');
			// set up a temporary table containing the indexes of the active articles if they
			// were all on one page
			Database::query('CREATE TEMPORARY TABLE `blog_row_numbers` ' . static::processQuery(
					'SELECT @row_num := @row_num + 1 AS `row_number`, ~id AS `id` from ~TABLE '
					. 'WHERE ~active = true '
					. 'ORDER BY ~date DESC, ~id DESC'
				));

			// query the temporary table using a litle math to get the page number from the row number
			// for this article
			$result = Database::query(static::processQuery('SELECT '
				. 'CEIL(`row_number` / ' . static::PER_PAGE . ') as page_number '
				. 'FROM `blog_row_numbers` WHERE `id` = ? LIMIT 1'), [$this->id]
			);

			// return page number
			return $result[0]['page_number'];
		}

		/**
		 * Gets the article immediately before this one
		 *
		 * @return BlogArticle
		 */
		public function getPrevious()
		{
			$formatedDate = $this->date->format('Y-m-d H:i:s');
			$query = "SELECT ~PROPERTIES "
				. "FROM ~TABLE "
				. "WHERE ~active = true "
				. "AND (~date > ? OR (~date = ? AND ~id > ?))"
				. "ORDER BY ~date ASC, ~id ASC "
				. "LIMIT 1";

			return static::makeOne($query, [$formatedDate, $formatedDate, $this->id]);
		}

		/*~~~~~
		 * Interface methods
		 **/

		// region AdminNavItemGenerator

		/**
		 * Gets the nav item for this class
		 * @return    AdminNavItem        The admin nav item for this class
		 */
		public static function getAdminNavItem()
		{
			return new AdminNavItem(static::getAdminNavLink(), "Blog", [static::class], Registry::BLOG);
		}

		// endregion

		// region BannerSource

		/**
		 * Get the items to display as the page banner/slideshow
		 *
		 * return SiteBanner[] the active slides for the page, the default banner for the site
		 *		or  empty if specifically disabled for this page
		 */
		public function getVisibleBanners()
		{
			$banner = $this->banner;
			if($banner->image === null)
			{
				return $this->page->getVisibleBanners();
			}
			else
			{
				return [$banner];
			}
		}

		// endregion

		// region PageItem

		/**
		 * Gets the content title for the item (where this is not laid out by the user in an Editor element)
		 * don't do splitting and html wrapping here because getPageTitle() falls back to use this logic, set up a filter in Controller\Twig.php
		 * @return	string	Usually the contents of the page h1.
		 */
		public function getMainHeading()
		{
			return $this->mainHeading;
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
				$description = $this->summary;
			}

			return $description;
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

		//region SearchResultGenerator

		/**
		 * Generates a Search Result for this object
		 * @return	SearchResult	The Search Result
		 */
		public function generateSearchResult()
		{
			return (new SearchResult($this->path, $this->getMainHeading(), nl2br($this->getMetaDescription())))
						->setImage($this->image)
						->setRelevance($this->relevance);
		}

		//endregion

		// region SitemapItem

		/**
		 * return paths to active pages for inclusion in sitemap
		 *
		 *	@param null $parent not used
		 *
		 * @return string[]
		 */
		static function getSitemapUrls($parent = null)
		{
			// check if site should be returning items for this module
			if(!Registry::BLOG)
			{
				return [];
			}
			// else

			$paths = [];

			$items = static::loadAllFor('active', true, ['date' => false]);

			/** @var BlogArticle $obj */
			foreach($items as $obj)
			{
				$paths[] = $obj->path;
			}

			return $paths;
		}

		// region

		//region SearchResultGenerator
	}
