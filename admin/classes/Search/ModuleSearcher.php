<?php
	namespace Search;

	use Configuration\Registry;
	
	/**
	 * Searches all Searchable modules
	 */
	class ModuleSearcher
	{
		/** @var SearchResult[] */
		private $results = [];

		/**
		 * Searches all Searchable Entities for a particular term
		 * @param	string	$term		The term to search for
		 * @param 	array	$classes	The classes to search
		 */
		public function __construct(string $term, array $classes = Registry::SEARCHABLE_CLASSES)
		{
			$searchResults = [];

			/** @var string|Searchable|SearchResultGenerator $searchableClass */
			foreach($classes as $searchableClass)
			{
				assert(is_a($searchableClass, Searchable::class, true), "{$searchableClass} must be searchable");

				foreach($searchableClass::search($term) as $object)
				{
					assert($object instanceof SearchResultGenerator, get_class($object) . " must be a search result generator");
					$searchResults[] = $object->generateSearchResult();
				}
			}

			usort($searchResults, function(SearchResult $first, SearchResult $second)
			{
				return $second->relevance <=> $first->relevance;
			});

			$searchResults = array_unique($searchResults);

			$this->results = $searchResults;
		}

		/**
		 * Gets the search results
		 * @return	SearchResult[]	The search results
		 */
		public function getResults()
		{
			return $this->results;
		}
	}