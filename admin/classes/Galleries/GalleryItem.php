<?php

namespace Galleries;

use Core\Attributes\Data;
use Core\Attributes\ImageValue;
use Core\Attributes\LinkTo;
use Core\Elements\ImageElement;
use Core\Elements\Text;
use Core\Generator;
use Files\Image;

/**
 * A single item in a gallery
 */
class GalleryItem extends Generator
{
	const TABLE = 'gallery_items';
	const ID_FIELD = 'gallery_item_id';
	const SINGULAR = 'Image';
	const PLURAL = 'Images';
	const LABEL_PROPERTY = 'thumbnail';
	const HAS_ACTIVE = true;
	const HAS_POSITION = true;
	const PARENT_PROPERTY = 'gallery';
	
	const IMAGES_LOCATION = DOC_ROOT . "/resources/images/gallery/";
	const IMAGE_WIDTH = 1000;
	const IMAGE_HEIGHT = 800;
	const IMAGE_RESIZE_TYPE = ImageValue::SCALE;
	const THUMBNAIL_WIDTH = 500;
	const THUMBNAIL_HEIGHT = 500;
	const THUMBNAIL_RESIZE_TYPE = ImageValue::CROP;

	public bool $active = true;
	
	#[Data("title")]
	public string $title = '';
	
	#[ImageValue("image", self::IMAGES_LOCATION, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, self::IMAGE_RESIZE_TYPE)]
	public Image|null $image = null;
	
	#[ImageValue("thumbnail", self::IMAGES_LOCATION, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT, self::THUMBNAIL_RESIZE_TYPE)]
	public Image|null $thumbnail = null;
	
	#[LinkTo("gallery_id")]
	public Gallery $gallery;

	/**
	 * load all the images in one Gallery
	 *
	 * @param Gallery $parent
	 *
	 * @return Image[]
	 */
	public static function loadImagesForGallery(Gallery $parent)
	{
		$return = [];
		foreach(static::loadAllForMultiple(['gallery' => $parent], []) as $obj)
		{
			$return[] = $obj;
		}

		return $return;
	}

	/**
	 * load all the public images in one Gallery
	 *
	 * @param Gallery $parent
	 *
	 * @return Image[]
	 */
	public static function loadActiveImagesForGallery(Gallery $parent)
	{
		$return = [];
		foreach(static::loadAllForMultiple(['gallery' => $parent->id,'active' => true], []) as $obj)
		{
			$return[] = $obj;
		}
		return $return;
	}

	/**
	 * Sets the Form Elements for this object
	 */
	protected function elements()
	{
		parent::elements();
		$this->addElement(new Text('title', 'Title'));
		
		$imageElement = new ImageElement("image", "Image");
		$imageElement->addChild(new ImageElement("thumbnail", "Thumbnail"));
		$this->addElement($imageElement);
	}

	/**
	 * Gets the width of the item
	 * @return	int		The width
	 */
	public function get_width()
	{
		return ($this->image !== null) ? $this->image->width : 0;
	}

	/**
	 * Gets the height of the item
	 * @return	int		The height
	 */
	public function get_height()
	{
		return ($this->image !== null) ? $this->image->height : 0;
	}
}
