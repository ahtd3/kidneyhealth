<?php
	namespace Pages\Linking;
	
	use Core\Elements\FileElement;
	use Core\Elements\ImageElement;
	use Core\Elements\Select;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\FileProperty;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Files\File;
	use Files\Image;
	use Pages\Page;
	use Core\Elements\FormOption;
	use Pages\Linking\GroupLinks;
	use Core\Attributes\LinkTo;

	use JsonSerializable;

	class Link extends Generator implements JsonSerializable
	{
		const TABLE = "links";
		const ID_FIELD = "link_id";
		const SINGULAR = "Link";
		const PLURAL = "Links";
		const HAS_POSITION = true;
		const LABEL_PROPERTY = "label";
		const PARENT_PROPERTY = "groupLink";

		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/links/";
		const IMAGE_WIDTH = 325;
		const IMAGE_HEIGHT = 239;
		const IMAGE_RESIZE_TYPE = ImageProperty::CROP;
		
		const FILE_LOCATION = DOC_ROOT . "/resources/files/links/";
		
		public $label = "";
		
		/** @var File|null */
		public $file = null;

		// public $link_type  = "";
		
		#[LinkTo("group_link_id")]
		public GroupLinks $groupLink;

		/** @var Page */
		public $linkedPage;

		
		protected static function properties()
		{
			parent::properties();
			
			static::addProperty(new Property("label", "label", "string"));
			// static::addProperty(new Property("link_type", "link_type", "string"));
			// static::addProperty(new Property("group_link", "group_link", "string"));
			static::addProperty(new FileProperty("file", "file", static::FILE_LOCATION));
			static::addProperty(new LinkToProperty("linkedPage", "linked_page_id", Page::class));
		}
		
		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("label", "Label"));
			// $this->addElement(new Select("link_type", "Type of link", self::outputAllTypes()));
			// $this->addElement(new Text("linkedPage", "Linked page"));
			$this->addElement(new FileElement("file", "Linked file"));
		}
		
		public function getLink()
		{
			if($this->file !== null)
			{
				return $this->file->getLink();
			}
			else if(!$this->linkedPage->isNull())
			{
				return $this->linkedPage->path;
			}
			else
			{
				return "#";
			}
		}
		
		public function isDownload()
		{
			return $this->file !== null;
		}

		// public static function outputAllTypes()
		// {
		// 	return
		// 	[
		// 		new FormOption("file", 'Donwload File'),
		// 		new FormOption("link", 'Link to website')
		// 	];
		// }

		public function jsonSerialize(): array
		{
			return
			[
				"file" => $this->file,
			];
		}
	}