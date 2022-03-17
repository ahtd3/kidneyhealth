<?php
	namespace Core\Elements;
	
	use Core\Elements\Base\Element;
	use Core\Elements\Base\ElementParent;
	use Core\Generator;
	use Core\Properties\ImageProperty;
	use Exception;
	use Files\File;
	use Files\Image;
	
	/**
	 * Allows the user to select an image
	 */
	class ImageElement extends FileElement implements ElementParent
	{
		public $accept = "image/jpeg,image/png,image/gif,.svg,.jpeg";
		
		public $width = self::EMPTY_VALUE;
		public $height = self::EMPTY_VALUE;
		public $resizeType = self::EMPTY_VALUE;
		
		/** @var ImageElement[] */
		public $children = [];
		
		/**
		 * Runs after this element has been added to its parent, will set the base generator and anything else that needs to be setup
		 * @param	ElementParent	$parent		The container that this element was added to
		 */
		public function afterAdd(ElementParent $parent)
		{
			parent::afterAdd($parent);
			
			if($this->width === self::EMPTY_VALUE)
			{
				$imageProperty = $this->generator::getProperties()[$this->name];
				assert($imageProperty instanceof ImageProperty);
				
				$this->width = $imageProperty->width;
				$this->height = $imageProperty->height;
				$this->resizeType = $imageProperty->resizeType;
			}
			
			foreach($this->children as $child)
			{
				$child->afterAdd($this);
			}
		}
		
		/**
		 * Sets the scaling for this element
		 * @param	int		$width			The maximum width to scale to, or the width to crop to
		 * @param	int		$height			The maximum height to scale to, or the height to crop to
		 * @param	string	$resizeType		One of the resize constants in ImageProperty
		 * @return	$this					This element, for chaining
		 */
		public function setScaling(?int $width = null, ?int $height = null, ?string $resizeType = ImageProperty::SCALE): self
		{
			assert($resizeType === ImageProperty::SCALE || ($width !== null && $height !== null), "Croppable images must have a set width and height");
			
			$this->width = $width;
			$this->height = $height;
			$this->resizeType = $resizeType;
			
			return $this;
		}
		
		/**
		 * Adds an associated image size
		 * @param	ImageElement	$element	Another image element, handles different image sizes for the same image
		 * @return	$this						This element, for chaining
		 */
		public function addChild(ImageElement $element): self
		{
			$this->children[$element->name] = $element;
			
			if($this->generator !== null)
			{
				$element->afterAdd($this);
			}
			
			return $this;
		}
		
		/**
		 * Removes an associated image size
		 * @param	string	$elementName	The name of the element to remove
		 * @return	$this					This element, for chaining
		 */
		public function removeChild(string $elementName): self
		{
			unset($this->children[$elementName]);
			
			return $this;
		}
		
		/**
		 * Gets the path to the component for this element, relative to /admin/theme/components/elements/
		 * @return    string    The vue template
		 */
		public function getTemplate(): string
		{
			return "image/ImageElement.js";
		}
		
		/**
		 * Gets the JSON encodable value for this element, often the same as the original value
		 * @return	mixed	The encodable value
		 */
		public function getJsonValue()
		{
			$values = [];
			
			foreach(array_merge([$this], $this->children) as $item)
			{
				$value = $item->getValue();
				$values[$item->name] = $value instanceof Image ? $value->getLink() : null;
			}
			
			return $values;
		}
		
		/**
		 * Gets the value to pass to the Vue component
		 * @return	mixed	The value to pass to the component (will be JSON encoded)
		 */
		public function getJson()
		{
			$values = parent::getJson();
			
			$values["elements"] = [$this->getChildJson()];
			
			foreach($this->children as $child)
			{
				$values["elements"][] = $child->getChildJson();
			}
			
			return $values;
		}
		
		/**
		 * Gets the JSON for a child element
		 * @return	array	The value to pass to the component (will be JSON encoded)
		 */
		public function getChildJson()
		{
			$json = parent::getJson();
			
			$json["width"] = $this->width;
			$json["height"] = $this->height;
			$json["crop"] = $this->resizeType === ImageProperty::CROP;
			
			return $json;
		}
		
		/**
		 * Gets the result of this element
		 * @param	mixed		$json	The JSON to retrieve the result from
		 * @return	Image[]				The result that will be passed to the result handler
		 * @throws	Exception			If something goes wrong while retrieving the child results
		 */
		public function getResult($json)
		{
			$results = [];
			
			$results[$this->name] = $this->getChildResult($json[$this->name] ?? null);
			
			foreach($this->children as $child)
			{
				$results[$child->name] = $child->getChildResult($json[$child->name] ?? null);
			}
			
			return $results;
		}
		
		/**
		 * Gets the result of a child element
		 * @param	string		$json	The JSON to retrieve the result from
		 * @return	Image				The result that will be passed to the result handler
		 * @throws	Exception			If something goes wrong for any reason
		 */
		public function getChildResult($json)
		{
			$file = parent::getResult($json);
			
			if($file === null)
			{
				return null;
			}
			else if($file instanceof Image)
			{
				return $file;
			}
			else if($file instanceof File)
			{
				$image = new Image($file->path);
				$image->originalName = $file->originalName;
				return $image;
			}
			else
			{
				return new Image($file);
			}
		}
		
		/**
		 * Passes the result of this element to the result handler
		 * @param	mixed	$result		The result to pass to the handler
		 */
		public function handleResult($result)
		{
			$this->handleChildResult($result[$this->name]);
			
			foreach($this->children as $child)
			{
				$child->handleChildResult($result[$child->name]);
			}
		}
		
		/**
		 * Passed the result of a child element to that element's result handler
		 * @param	Image	$result		The result to pass to the handler
		 */
		public function handleChildResult($result)
		{
			parent::handleResult($result);
		}
		
		//region ElementParent
		
		/**
		 * Gets the Generator that any child elements should reference
		 * @return    Generator    $generator    Said generator
		 */
		public function getGenerator(): Generator
		{
			return $this->generator;
		}
		
		/**
		 * Searches for a child element with a specific name
		 * @param	string			$name	The name of the element
		 * @return	Element|null			An element with that name, or null if one can't be found
		 */
		public function findElement(string $name): ?Element
		{
			foreach($this->children as $element)
			{
				if($element->name === $name)
				{
					return $element;
				}
				else if($element instanceof ElementParent)
				{
					$childElement = $element->findElement($name);
					
					if($childElement !== null)
					{
						return $childElement;
					}
				}
			}
			
			return null;
		}
		
		//endregion
	}