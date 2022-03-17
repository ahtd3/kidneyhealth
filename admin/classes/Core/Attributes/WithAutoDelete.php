<?php
	namespace Core\Attributes;
	
	use Attribute;
	
	/**
	 * Handles link properties that need to change their default auto delete
	 * Note: LinkToProperties and LinkFromProperties default to false, LinkFromMultipleProperties default to true
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class WithAutoDelete
	{
		public function __construct(public bool $hasAutoDelete){}
	}