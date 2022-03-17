<?php
	namespace Pages\Faqs;

	use Core\Elements\Editor;
	use Core\Elements\Text;

	use Core\Generator;

	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;

	use Search\SearchResult;
	use Search\SearchResultGenerator;

	/**
	 * A simple module for a question and answer
	 *
	 */
	class Faq extends Generator implements SearchResultGenerator
	{
		/*~~~~~
		 * setup
		 **/
		// Entity / Generator
		const TABLE = 'faqs';
		const ID_FIELD = 'faq_id';
		const SINGULAR = 'FAQ';
		const PLURAL = 'FAQs';
		const HAS_POSITION = true;
		const HAS_ACTIVE = true;
		const LABEL_PROPERTY = 'title';
		const PARENT_PROPERTY = 'page';

		// Faq
		/** @var bool */
		public bool $active = true;

		/** @var string */
		public $text = '';
		public $title = '';

		/** @var FaqPage $page */
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

			static::addProperty((new Property("title", "title", "string"))->setIsSearchable(true));
			static::addProperty((new Property("text", "text", "html"))->setIsSearchable(true));
			static::addProperty(new LinkToProperty('page', 'page_id', FaqPage::class));
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

			$this->addElement(new Text('title', 'Question'));
			$this->addElement(new Editor('text', 'Answer'));
		}

		/*~~~~~
		 * Interface methods
		 **/

		// region SearchResultGenerator

		/**
		 * create a SearchResult containing relevant information for the object
		 *
		 * @return SearchResult usually for output to a page alongside other SearchResults
		 */
		public function generateSearchResult()
		{
			return (new SearchResult($this->page->getNavPath(), $this->title, $this->text))->setRelevance($this->relevance);
		}

		// endregion
	}
