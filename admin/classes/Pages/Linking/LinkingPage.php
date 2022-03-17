<?php
	namespace Pages\Linking;
	
	use Core\Elements\GeneratorElement;
	use Core\Properties\LinkFromMultipleProperty;
	use Pages\Page;
	use Core\Elements\Tab;
	use Core\Elements\TabGroup;
	use Pages\Linking\GroupLinks;

	class LinkingPage extends Page
	{
		const LINK_CLASS = GroupLinks::class;
		
		/** @var GroupLinks[] group_links */
		public $group_links;
		
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty((new LinkFromMultipleProperty("group_links", static::LINK_CLASS, "page"))->setAutoDelete(true));
		}

		protected function elements()
		{
			parent::elements();
			$tabGroup = $this->getElements()['tab'];
			assert($tabGroup instanceof TabGroup);
			$tab = new Tab('links', 'Links');
			$tabGroup->add($tab, 'banners');
			$this->addElement(new GeneratorElement('group_links'), 'Links');
		}
	}