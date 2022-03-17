<?php
	namespace Search;
	
	use Files\Image;
	
	/**
	 * SearchResult provides a common format for objects to return from a search method
	 */
	class SearchResult
	{
		public $unique = "";
		public $path = "";
		public $title = "";
		public $description = "";
		public $relevance = 0.5;
		
		/** @var Image|null */
		public $image = null;
		
		/**
		 * Creates a new Search Result
		 * @param	string	$path			The path to the item
		 * @param	string	$title			The title given to the item
		 * @param	string	$description	A description of the item, in HTML
		 */
		public function __construct($path, $title, $description)
		{
			$this->path = $path;
			$this->title = $title;
			$this->description = $description;
			$this->unique = $path;
		}
		
		/**
		 * Sets the image to use for this result
		 * @param	Image	$image	The image to use
		 * @return	$this			This object, for chaining
		 */
		public function setImage(?Image $image = null): self
		{
			$this->image = $image;
			return $this;
		}
		
		/**
		 * Sets the relevance of this result
		 * @param	float	$relevance	The relevance for this result
		 * @return	$this				This object, for chaining
		 */
		public function setRelevance(float $relevance): self
		{
			$this->relevance = $relevance;
			return $this;
		}
		
		/**
		 * Sets a string that uniquely identifies this result. Any extra results with same unique string will be stripped out of the search results.
		 * @param	string	$unique		The unique string
		 * @return	$this				This object, for chaining
		 */
		public function setUnique(string $unique): self
		{
			$this->unique = $unique;
			return $this;
		}
		
		/**
		 * Converts to a string, used for checking that this search result is unique
		 * @return	string	The path to the result
		 */
		public function __toString()
		{
			return $this->unique;
		}
	}
