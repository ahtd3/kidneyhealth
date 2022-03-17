<?php
	namespace Pages\Faqs;

	use Core\Elements\GeneratorElement;
	use Core\Elements\Tab;
	use Core\Elements\TabGroup;
	use Core\Properties\LinkFromMultipleProperty;
	use Pages\Page;
	
	/**
	 * The class file for the FAQ (Frequently Asked Question) class; A simple module for a question and answer
	 *
	 */
	class FaqPage extends Page
	{
		/*~~~~~
		 * Setup
		 **/

		// FaqPage
		/** @var Faq[] $faqs */
		public $faqs;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Sets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty((new LinkFromMultipleProperty('faqs', Faq::class, 'page'))->setAutoDelete(true));
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
			$tabGroup = $this->getElements()['tab'];
			assert($tabGroup instanceof TabGroup);
			$tab = new Tab('faqs', 'FAQs');
			$tabGroup->add($tab, 'banners');
			$this->addElement(new GeneratorElement('faqs'), 'FAQs');
		}
		
		public function getVisibleFaqs()
		{
			return filterActive($this->faqs);
		}
	}
