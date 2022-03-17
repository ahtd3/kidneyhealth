<?php
	namespace Slugging;
	
	use Configuration\Registry;
	use Core\Entity;
	use Symfony\Component\String\Slugger\AsciiSlugger;
	
	/**
	 * Handles tracking possible slug conflicts
	 */
	class Slugging
	{
		/**
		 * Generates a slug from an arbitrary string
		 * @param	string	$string		The string to slug
		 * @return	string				The slugged string
		 */
		public static function slug(string $string): string
		{
			$slugger = new AsciiSlugger("en",
			[
				"en" =>
				[
					"@" => "at",
					"%" => "percent",
					"&" => "and"
				]
			]);
			
			return $slugger->slug(strtolower($string));
		}
		
		/**
		 * Tests a specific object to see if it conflicts with any other object
		 * @param	Entity	$entity		The object to test
		 * @return	bool				Whether there is an existing object with a matching slug to the object being tested
		 */
		public static function conflictExists(Entity $entity)
		{
			/** @var Entity[] $comparisons */
			$comparisons = [];
			
			if(in_array(get_class($entity), Registry::SLUGGED_CLASSES))
			{
				/** @var string|Entity $sluggedClass */
				foreach(Registry::SLUGGED_CLASSES as $sluggedClass)
				{
					$comparisons = array_merge($comparisons, $sluggedClass::loadAllForSlug($entity->slug));
				}
			}
			else
			{
				$comparisons = $entity::loadAllForSlug($entity->slug);
			}
			
			foreach($comparisons as $comparison)
			{
				if($comparison === $entity)
				{
					continue;
				}
				
				foreach($comparison->getSlugParents() as $comparisonParent)
				{
					foreach($entity->getSlugParents() as $objectParent)
					{
						if($comparisonParent === $objectParent || ($comparisonParent->isNull() && $objectParent->isNull()))
						{
							return true;
						}
					}
				}
			}
			
			return false;
		}
	}