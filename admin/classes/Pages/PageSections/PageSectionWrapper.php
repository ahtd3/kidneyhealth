<?php
	namespace Pages\PageSections;
	
	use Core\Attributes\Data;
	use Core\Attributes\LinkTo;
	use Core\Elements\FormOption;
	use Core\Elements\GeneratorElement;
	use Core\Elements\Select;
	use Core\Elements\Text;
	use Core\Entity;
	use Core\Generator;
	use Core\Properties\Property;
	use Error;
	use Pages\Page;
	use Pages\PageSections\BigSlideshow\BigSlideshow;
	use Slugging\Slugging;
	
	/**
	 * An extra section of the page that handles various things. This wraps around the actual page section
	 */
	class PageSectionWrapper extends Generator
	{
		const TABLE = "page_section_wrappers";
		const ID_FIELD = "page_section_wrapper_id";
		const SINGULAR = "Section";
		const PLURAL = "Sections";
		const LABEL_PROPERTY = "label";
		const HAS_POSITION = true;
		const HAS_ACTIVE = true;
		const PARENT_PROPERTY = "page";
		
		const TYPES =
		[
			"Big Slideshow" => BigSlideshow::class,
			"Extra Content" => ExtraContent::class,
			"Image Block" => ImageBlock::class,
			"Embed Code" => EmbedCode::class,
		];
		
		public bool $active = true;
		
		#[Data("label")]
		public string $label = "";
		
		#[Data("type")]
		public string $type = "";
		
		#[Data("section_id")]
		public ?int $sectionId = null;
		
		#[LinkTo("page_id")]
		public Page $page;
		
		/** @var PageSection */
		public PageSection $pageSection;
		private ?PageSection $cachedPageSection = null;
		
		/** @var Generator[] */
		private array $toDelete = [];
		
		protected static function properties()
		{
			parent::properties();
			
			foreach(static::TYPES as $type)
			{
				if(!is_a($type, PageSection::class, true))
				{
					throw new Error("{$type} should implement PageSection");
				}
			}
			
			static::addProperty((new Property("pageSection"))->setGetter(function(self $wrapper)
			{
				if($wrapper->cachedPageSection !== null)
				{
					return $wrapper->cachedPageSection;
				}
				
				/** @var class-string<Generator> $type */
				$type = static::TYPES[$wrapper->type ?: array_key_first(static::TYPES)];
				$section = is_a($type, Generator::class, true) ? $type::load($wrapper->sectionId) : new $type;
				$wrapper->cachedPageSection = $section;
				
				return $section;
			}));
		}
		
		protected function elements()
		{
			parent::elements();
			
			$options = array_map(fn(string $label) => new FormOption($label, $label), array_keys(static::TYPES));
			
			$this->addElement(new Text("label", "Label"));
			$this->addElement((new Select("type", "Type", $options))->addValidation(Select::REQUIRED));
			
			foreach(static::TYPES as $label => $type)
			{
				if(!is_a($type, Generator::class, true))
				{
					continue;
				}
				
				// We'll want to pass in the existing object, or create a null object if this is a different type
				$value = ($label === $this->type) ? $this->pageSection : $type::makeNull();
				$jsonType = json_encode($label);
				
				$this->addElement((new GeneratorElement(Slugging::slug($label), null, $type, $value))->setConditional("return type === {$jsonType}")->setResultHandler(function(Generator|PageSection $section) use($label)
				{
					// $this->type will have been set to the new type at this point
					if($this->type === $label)
					{
						if($this->pageSection instanceof Generator && $this->pageSection !== $section)
						{
							$this->toDelete[] = $this->pageSection;
						}
						
						$this->cachedPageSection = $section;
					}
				}));
			}
		}
		
		public function beforeSave(bool $isCreate)
		{
			parent::beforeSave($isCreate);
			
			if($this->pageSection instanceof Entity)
			{
				// Make sure we save any changes to our page section
				$this->pageSection->save();
			}
		}
		
		public function afterSave(bool $isCreate)
		{
			parent::afterSave($isCreate);
			
			// We're storing the section ID separately, so we'll update that now
			if($this->pageSection instanceof Entity)
			{
				if($this->pageSection->id !== $this->sectionId)
				{
					$this->sectionId = $this->pageSection->id;
					$this->save();
				}
			}
			else if($this->sectionId !== null)
			{
				$this->sectionId = null;
				$this->save();
			}
			
			// If we've changed types, we'll want to delete lingering traces
			foreach($this->toDelete as $toDelete)
			{
				if($toDelete === $this->pageSection)
				{
					continue;
				}
				
				$toDelete->delete();
			}
		}
		
		public function afterDelete()
		{
			parent::afterDelete();
			
			if($this->pageSection instanceof Entity)
			{
				$this->pageSection->delete();
			}
			
			foreach($this->toDelete as $toDelete)
			{
				$toDelete->delete();
			}
		}
	}