<?php
	namespace Core\Attributes;
	
	use Attribute;
	
	/**
	 * Handles properties that should be searchable
	 */
	#[Attribute(Attribute::TARGET_PROPERTY)]
	class IsSearchable{}