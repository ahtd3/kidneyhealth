<?php
	namespace Core\SaveHandling;
	
	use Core\Entity;
	
	/**
	 * Keeps track of prerequisites for saving a specific entity
	 */
	class EntityTracker
	{
		const ACTION_SAVE = "save";
		const ACTION_DELETE = "delete";
		
		public string $action;
		public Entity $entity;
		
		/** @var EntityTracker[] */
		public array $prerequisites = [];
		
		/**
		 * Creates a new Entity Tracker
		 * @param	Entity	$entity		The entity to change in the database
		 * @param	string	$action		One of the ACTION_ constants to perform
		 */
		public function __construct(Entity $entity, string $action)
		{
			$this->action = $action;
			$this->entity = $entity;
		}
		
		/**
		 * An identifier that uniquely identifies this object and the action being performed to it
		 * @return	string	The identifier
		 */
		public function getId(): string
		{
			$class = get_class($this->entity);
			$id = spl_object_hash($this->entity);
			
			return "{$this->action}_{$class}_{$id}";
		}
		
		/**
		 * Gets the highest number of prerequisite links in this chain
		 * @param	EntityTracker[]		$ignore		Trackers to ignore so that we don't accidentally end up in an infinite loop
		 * @return	int								The number of links in the child chain
		 */
		public function getPrerequisiteLength(array $ignore = []): int
		{
			$toCount = array_filter($this->prerequisites, fn(EntityTracker $tracker) => !in_array($tracker, $ignore));
			
			if(count($toCount) === 0)
			{
				return 0;
			}
			
			$childCounts = array_map(fn(EntityTracker $tracker) => $tracker->getPrerequisiteLength(array_merge($ignore, [$this])), $toCount);
			
			return max($childCounts) + 1;
		}
	}