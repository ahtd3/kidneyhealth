<?php
	namespace Forms\Submissions;

	use Controller\DownloadController;

	use Core\Columns\CustomColumn;
	use Core\Columns\WidgetColumn;
	use Core\Elements\Output;
	use Core\Exportable;
	use Core\Filterable;
	use Core\Generator;
	use Core\Properties\LinkToProperty;
	use Core\Properties\Property;

	use Database\Database;
	use DateTimeImmutable;
	
	use Forms\Form;
	use Pagination;
	use Users\User;
	
	/**
	 * Logs a successful submission through a site form.
	 */
	class FormSubmission extends Generator implements Exportable, Filterable
	{
		/*~~~~~
		 * setup
		 **/
		// Entity/Generator
		const TABLE = 'form_submissions';
		const ID_FIELD = 'id';
		const LABEL_PROPERTY = 'subject';
		const SINGULAR = 'Submission';
		const PLURAL = 'Submissions';
		const PARENT_PROPERTY = 'form';

		// FormSubmission
		/** @var string */
		public $formName = ''; //snapshot or identfication if Form is null
		public $subject = '';
		public $senderEmail = '';
		public $senderName = '';
		public $replyTo = '';
		public $recipient = '';
		public $cc = '';
		public $bcc = '';
		public $content = '';
		public $files = '';

		/** @var DateTimeImmutable */
		public $when = null;

		/** @var Form */
		public $form = null;

		/*~~~~~
		 * static methods excluding interface methods
		 **/
		/**
		 * Gets the array of Properties that determine how this Database Object Generator Category interacts with the database
		 */
		protected static function properties()
		{
			parent::properties();

			static::addProperty(new LinkToProperty('form', 'form_id', Form::class));
			static::addProperty(new Property('formName', 'form_name', 'string'));
			static::addProperty(new Property('subject', 'subject', 'string'));
			static::addProperty(new Property('senderEmail', 'sender_email', 'string'));
			static::addProperty(new Property('senderName', 'sender_name', 'string'));
			static::addProperty(new Property('recipient', 'recipient', 'string'));
			static::addProperty(new Property('cc', 'cc', 'string'));
			static::addProperty(new Property('bcc', 'bcc', 'string'));
			static::addProperty(new Property('content', 'content', 'html'));
			static::addProperty(new Property('files', 'files', 'string'));
			static::addProperty(new Property('when', 'when', 'datetime'));
		}

		/*
		 * We don't want manual additions to this table
		 */
		public static function canAdd(User $user): bool
		{
			return false;
		}

		protected static function columns()
		{
			static::addColumn(new CustomColumn("subject", 'Subject/Form', function(self $generator)
			{
				return $generator->subject . '<br />[' . $generator->formName . ']';
			}));

			static::addColumn(new CustomColumn("sender", 'Sender', function(self $generator)
			{
				return ($generator->senderName === '' ? '' : $generator->senderName . '<br />' ) . $generator->senderEmail;
			}));

			static::addColumn(new CustomColumn("to", 'To', function(self $generator)
			{
				$cc = $generator->cc;
				$bcc = $generator->bcc;
				return "To {$generator->recipient}"
					. ($cc ? "\nCc: {$cc}" : '')
					. ($bcc ? "\nBcc: {$bcc}" : '')
					;
			}));

			static::addColumn(new CustomColumn("submitted", 'Submitted', function(self $generator)
			{
				return $generator->when->format('Y-m-d g:i a');
			}));

			parent::columns();
			
			$editColumn = static::getColumns()["edit"];
			assert($editColumn instanceof WidgetColumn);
			$editColumn->heading = 'View';
		}

		/**
		 * *@param string $filter the search term
		 * @return string[] the parameters used with the query when filtering for display or export
		 */
		protected static function getFilterParams(string $filter = '') : array
		{
			$param = '%' . strtolower(trim($filter)) . '%';
			return [$param, $param, $param, $param];
		}

		/**
		 * @return string the query used when filtering for display or export
		 */
		protected static function getFilterQuery() : string
		{
			return 'SELECT ~PROPERTIES FROM ~TABLE WHERE LOWER(~subject) LIKE ? OR LOWER(~senderEmail) LIKE ? OR LOWER(~senderName) LIKE ? OR LOWER(~recipient) LIKE ? ORDER BY ~when DESC, ~id DESC';
		}

		/**
		 * utility method
		 * nothing shows up how much whitespace is in a twig template like converting it back to a string...
		 * @return string
		 */
		public static function htmlToPlainText($string)
		{
			// remove html from template
			$string = cleanHtmlData($string);
			// clean up excess white space from template
			$string = preg_replace('/(\s*\r?\n\s*){2,}/', "\n", $string);

			return $string;
		}

		/**
		 * Loads all the Generators to be displayed in the table
		 * @param	int						$page	The page to load, if handling pagination
		 * @return	static[]|Pagination				The array/Pagination of Generators
		 */
		public static function loadAllForTable(int $page = 1)
		{
			return static::loadPagesForMultiple([], ['when'=>false, 'id' => false], $page, 25);
		}

		/*~~~~~
		 * non-static methods excluding interface methods
		 **/
		/**
		 * Sets the Form Elements for this Generator
		 */
		protected function elements()
		{
			parent::elements();

			$this->addElement((new Output("formName", "Form")));

			$this->addElement((new Output("sender", "Sender", $this->senderName . ' &lt;' . $this->senderEmail . '&gt;')));

			$this->addElement((new Output("recipient", "To")));

			if($this->cc !== '')
			{
				$this->addElement((new Output("cc", "Cc")));
			}

			if($this->bcc !== '')
			{
				$this->addElement((new Output("bcc", "Bcc")));
			}

			$this->addElement((new Output("when", "Sent", $this->when->format('g:i:s a, l jS F T'))));

			$this->addElement(new Output("content", "Contents", "<div class='form-submission'>{$this->content}</div>"));

			if($this->files !== '')
			{
				$this->addElement(new Output("files", "Attached Files <span>(Files are not stored)</span>", nl2br($this->files)));
			}
		}

		/*~~~~~
		 * interface methods
		 */
		// region Exportable
		/**
		 * Exports all objects that match a specific filter to a given format
		 * @param	string			$filter		The filter to apply
		 * @param	string			$format		The format to export (unused, format is always csv)
		 * @return	Generator[]					The filtered objects
		 */
		public static function export(?string $filter = '', ?string $format = null)
		{
			$rows = [[
				'Subject'
				, 'From Name'
				//, 'First Name'
				//, 'Last Name'
				, 'From Email'
				, 'To'
				, 'Cc'
				, 'Bcc'
				, 'Submitted'
				, 'Content'
				, 'Files'
				]];

			$query = static::getFilterQuery();
			$params = static::getFilterParams($filter);

			foreach(static::makeMany($query, $params) as $generator)
			{
				// $names = explode(' ', $submission->senderName, 2);

				$rows[] = [
					$generator->subject
					, $generator->senderName
					//, reset($names)
					//, (count($names) > 1 ? end($names) : '')
					, $generator->senderEmail
					, $generator->recipient
					, $generator->cc
					, $generator->bcc
					, $generator->when->format('Y-m-d g:i a')
					, $generator->content
					, $generator->files
					];
			}

			DownloadController::outputDownloadHeaders('Form-Submissions-' . date('Y-m-d') . '.csv', 'text/csv');

			$file = fopen('php://output', 'w');

			foreach($rows as $arr)
			{
				fputcsv($file, $arr);
			}

			fclose($file);
			exit;
		}

		/**
		 * gets the formats this object can export to, for generating buttons or select options
		 * this can conditionally return an empty array to en/disable exporting on a permissions basis
		 *
		 * @return string[] values which will be displayed to the user and passed into the $format parameter of export() eg ['CSV', 'PDF']
		 */
		public static function getExportableFormats()
		{
			return ['CSV'];
		}

		// endregion

		// region Filterable
		/**
		 * Loads all objects that match a specific filter
		 * @param	string			$filter		The filter to apply
		 * @return	Generator[]					The filtered objects
		 */
		public static function loadForFilter(string $filter): array
		{
			$query = static::getFilterQuery();
			$params = static::getFilterParams($filter);

			return static::makeMany($query, $params);
		}
		// endregion
	}
