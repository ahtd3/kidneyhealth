<?php
	/**
	 * Class for interacting with paginated results
	 */
	class Pagination
	{
		/** @var	array[]		The results of the query */
		public $results = [];

		/** @var	int		The total number of rows of results */
		public $totalRows = 0;

		/** @var	int		How far offset to display results for */
		public $offset = 0;

		/** @var	int		The number of results to show per page */
		public $perPage = 0;

        /**
         * Creates a new Pagination object
         * @param    array $results Page of results received from pagination
         * @param    int $totalRows Total number of rows
         * @param    int $offset Current offset
         * @param   int $perPage    Number of items to display per page
         */
		public function __construct(array $results, $totalRows, $offset, $perPage)
		{
			$this->results = $results;
			$this->totalRows = $totalRows;
			$this->offset = $offset;
			$this->perPage = $perPage;
		}

		/**
		 * Gets the total number of pages
		 * @return	int		Total number of pages from this Pagination set
		 */
		public function totalPages()
		{
			return max(1, ceil($this->totalRows / $this->perPage));
		}

		/**
		 * Gets the current page
		 * @return	int		The current page, 1 indexed
		 */
		public function currentPage()
		{
			return ceil($this->offset / $this->perPage) + 1;
		}

		/**
		 * Generates pagination links, maximum of 15, clustered around the start, finish and middle
		 * @param	string	$url	Url to add the page number to the end of
		 * @return	string	HTML for the pagination links
		 */
		public function generatePageLinks($url)
		{
			$links = [];

			for($i = 1; $i <= $this->totalPages(); $i += 1)
			{
				if($i === (int) $this->currentPage())
				{
					$links[$i] = "<span class='current'>" . $i . "</span>";
				}
				else if
				(
					($i <= 3) ||
					($i >= $this->currentPage() - 2 && $i <= $this->currentPage() + 2) ||
					($i > $this->totalPages() - 3)
				)
				{
					$links[$i] = "<a href='" . $url . $i . "'>" . $i . "</a>";
				}
			}

			$previousIndex = 0;

			foreach($links as $index => $link)
			{
				if($index - $previousIndex > 1)
				{
					//cast to a string, because otherwise this will be case to an int, and we want this between things
					$links[(string) ($index - 0.5)] = "<span>...</span>";
				}

				$previousIndex = $index;
			}

			ksort($links);

			return implode("", $links);
		}

		/**
		 * Generates links (usually to be styled as arrows) for previous and next pages with text between
		 *		if there is no previous or next page the link is replaced with a span
		 * @param	string	$url	Url to add the page number to the end of
		 * @param	string	$text	Text to include between the arrows (defaults to 'i of n')
		 *
		 * @return	string	HTML for the navigation links
		 */
		public function generatePageArrows($url, $text='')
		{
			$currentPage = (int) $this->currentPage();
			$lastPage = (int) $this->totalPages();

			$html = ($currentPage <= 1) ? '<span class="previous">Previous</span>'
				: '<a class="previous" href="' . $url . ($currentPage - 1) . '">Previous</a>';
			$html .=  ' ' . ($text === '' ? $currentPage . ' of ' . $lastPage : $text) . ' ';
			$html .= ($currentPage >= $lastPage) ? '<span class="next">Next</span>'
				: '<a class="next" href="' . $url . ($currentPage + 1) . '">Next</a>';

			return $html;
		}
	}
