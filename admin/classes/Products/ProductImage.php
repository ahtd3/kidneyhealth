<?php
	namespace Products;

	use Core\Elements\ImageElement;
	use Core\Elements\Text;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;
	use Files\Image;
	
	/**
	 * A single image for a product
	 */
	class ProductImage extends Generator
	{
		/*~~~~~
		 * setup
		 **/
		// Generator / Entity
		const TABLE = "product_images";
		const ID_FIELD = "product_image_id";
		const SINGULAR = 'Image';
		const PLURAL = 'Images';
		const HAS_ACTIVE = true;
		const HAS_POSITION = true;
		const LABEL_PROPERTY = "thumbnail";
		const PARENT_PROPERTY = "product";
		
		// ProdcutImage
		const IMAGE_LOCATION = DOC_ROOT . "/resources/images/product/";
		const IMAGE_WIDTH = 680;
		const IMAGE_HEIGHT = 0;
		const IMAGE_RESIZE_TYPE = ImageProperty::SCALE;
		const THUMBNAIL_WIDTH = 150;
		const THUMBNAIL_HEIGHT = 150;
		const THUMBNAIL_RESIZE_TYPE = ImageProperty::CROP;

		/** @var bool */
		public bool $active = true;
		
		/** @var string */
		public $imageDescription = '';
		
		/** @var Image */
		public $image = null;
		
		/** @var Image */
		public $thumbnail = null;
		

		/** @var Product */
		public $product = null;
		
		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Object interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();
			static::addProperty(new LinkToProperty("product", "product_id", Product::class));
			static::addProperty(new ImageProperty('image', 'image', static::IMAGE_LOCATION, static::IMAGE_WIDTH, static::IMAGE_HEIGHT, static::IMAGE_RESIZE_TYPE));
			static::addProperty(new ImageProperty('thumbnail', 'thumbnail', static::IMAGE_LOCATION, static::THUMBNAIL_WIDTH, static::THUMBNAIL_HEIGHT, static::THUMBNAIL_RESIZE_TYPE));
			static::addProperty(new Property('imageDescription', 'image_description', 'string'));
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
			
			$this->addElement((new ImageElement("image", "Image"))->addChild(new ImageElement("thumbnail", "Thumbnail")));
			$this->addElement((new Text('imageDescription', 'Image description'))->setHint("for SEO and non-visual browsers"));
		}
	}
