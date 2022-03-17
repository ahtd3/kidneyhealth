<?php
	namespace Admin;
	
	use Core\Generator;
	use JsonSerializable;
	
	/**
	 * Contains details about the previous page, for use in breadcrumbs and in the cancel button
	 */
	class PreviousPageDetails implements JsonSerializable
	{
		public string $label;
		public ?string $link;
		public ?Generator $previous;
		
		/**
		 * Creates a new Previous Page Details from a specific object
		 * @param	Generator		$previous	The previous generator that is being linked to
		 * @param	string|null		$link		A specific link to override the default one (getEditLink())
		 * @param	string|null		$label		A specific label to override the default one (LABEL_PROPERTY)
		 * @return	static						A new return link for that generator
		 */
		public static function makeForGenerator(Generator $previous, ?string $link = null, ?string $label = null): self
		{
			return new self($label ?? $previous->{$previous::LABEL_PROPERTY}, $link ?? $previous->getEditLink(), $previous);
		}
		
		/**
		 * Creates a new Previous Page Details from a Generator class (to link to the table)
		 * @param	class-string<Generator>		$generatorClass		The class to link to
		 * @param	string|null					$link				A specific link to override the default one (getAdminNavLink())
		 * @param	string|null					$label				A specific label to override the default one (PLURAL)
		 * @return	static											A new return link for that class
		 */
		public static function makeForTableClass(string $generatorClass, ?string $link = null, ?string $label = null): self
		{
			/** @var string|Generator $generatorClass */
			return new self($label ?? $generatorClass::PLURAL, $link ?? $generatorClass::getAdminNavLink());
		}
		
		/**
		 * Creates a new Previous Page Details
		 * @param	string			$label		The label for the previous item in the breadcrumbs
		 * @param	string|null		$link		A link to the previous item in the breadcrumbs, or null if there should be no link
		 * @param	Generator|null	$previous	The previous generator that is being linked to, which will provide further breadcrumbs in the chain, or null if the item is a table
		 */
		public function __construct(string $label, ?string $link = null, ?Generator $previous = null)
		{
			$this->label = $label;
			$this->link = $link;
			$this->previous = $previous;
		}
		
		//region JsonSerializable
		
		/**
		 * @inheritDoc
		 */
		public function jsonSerialize(): array
		{
			$previous = null;
			
			if($this->previous !== null)
			{
				$previous = $this->previous->getPreviousPageDetails();
			}
			
			return
			[
				"identifier" => uniqid(),
				"label" => $this->label,
				"link" => $this->link,
				"previous" => $previous
			];
		}
		
		//endregion
	}