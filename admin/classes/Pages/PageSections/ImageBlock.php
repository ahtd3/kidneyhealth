<?php
	namespace Pages\PageSections;
	
	use Core\Attributes\Data;
	use Core\Attributes\ImageValue;
	use Core\Elements\Checkbox;
	use Core\Elements\Editor;
	use Core\Elements\Group;
	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Elements\Url;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Files\Image;
	use Media\StreamingVideo;
	use Core\Elements\Select;
	use Core\Elements\FormOption;
	/**
	 * Displays an image or video next to some content
	 */
	class ImageBlock extends Generator implements PageSection
	{
		const TABLE = "image_blocks";
		const ID_FIELD = "image_block_id";
		
		#[Data("content", "html")]
		public string $content = "";
		
		#[Data("video_url")]
		public string $videoUrl = "";
		
		#[Data("image_description")]
		public string $imageDescription = "";

		#[Data("code")]
		public string $code = "";
		
		#[ImageValue("image", DOC_ROOT . "/resources/images/page/", 924, null, ImageProperty::SCALE)]
		public ?Image $image = null;
		
		#[Data("custom_type")]
		public $custom_type = "";

		protected function elements()
		{
			parent::elements();
			
			$this->addElement(new Text("code", "Class"));
			$this->addElement(new Select("custom_type", "custom_Type", static::outputAllTypes()));
			$this->addElement(new Editor("content", "Content"));
			$this->addElement((new Url("videoUrl", "Video URL"))->setConditional("return image === null"));
			
			$group = $this->addElement(new Group("imageItems"))->setConditional("return videoUrl === ''");
			$group->add(new ImageElement("image", "Image"));
			$group->add(new Text("imageDescription", "Image Description"));
		}
		
		public function getMedia(): ?StreamingVideo
		{
			if($this->videoUrl === "")
			{
				return null;
			}
			
			return StreamingVideo::get($this->videoUrl);
		}
		
		//region PageSection
		
		function getTemplateLocation(): string
		{
			return "pages/sections/image-block.twig";
		}
		
		//endregion

				
		public static function outputAllTypes()
		{
			return
			[
				new FormOption("Normal", ''),
				new FormOption("Image right content", 'image_right'),
				new FormOption("Image left and content above image", 'content_above_image_left'),
				new FormOption("Image right and content above image", 'content_above_image_right'),
			];
		}
	}