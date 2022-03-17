<?php
	namespace Template\ShortCodes;

	use Configuration\Registry;
	use Exception;

	/**
	 * Handles short codes
	 */
	class ShortCode
	{
		// Note: %% is equivalent to a single % character
		private const FORMAT = "%%%%%s-%s%%%%";

		/**
		 * @var string[] $processed Codes which have already been processed this session.
		 *                    Protects against infinite loops
		 */
		static $processed = [];

		/**
		 * Converts a shortcode to the appropriate string
		 * @param	string		$classIdentifier	Identifies the shortcode generating class
		 * @param	string		$objectIdentifier	Identifies the shortcode generating object
		 * @return	string							The expanded code
		 * @throws	Exception						If something goes wrong during rendering
		 */
		public static function expandCode($classIdentifier, $objectIdentifier)
		{
			$code = $classIdentifier . "-" . $objectIdentifier;

			if(!isset(static::$processed[$code]))
			{
				// Set this to an empty string early, in case of loops
				static::$processed[$code] = "";

				foreach(Registry::SHORTCODE_CLASSES as $shortcodeSupportingClass)
				{
					if($shortcodeSupportingClass::getClassShortcodeIdentifier() === $classIdentifier)
					{
						$object = $shortcodeSupportingClass::loadForShortcodeIdentifier($objectIdentifier);

						if($object !== null)
						{
							static::$processed[$code] = $object->getShortcodeContent();
						}

						break;
					}
				}
			}

			return static::$processed[$code];
		}

		/**
		 * find and replace shortcodes in html
		 * @param	string	$content	The content to replace shortcodes in
		 * @return	string				The updated HTML
		 */
		public static function expandHtml($content)
		{
			// Searches for patterns resembling <p>%%Form-1%%</p>, %%Gallery-Five%%, <p> %%Page-Open%% </p>, etc.
			$content = preg_replace_callback('/(<p[^>]*?>)?.*?%%([A-Za-z0-9]*?)-([A-Za-z0-9]*?)%%.*?(<\/p[^>]*?>)?/', function($matches)
			{
				return static::expandCode($matches[2], $matches[3]);
			}, $content);

			return $content;
		}
		
		/**
		 * Generates a shortcode from an object
		 * @param    ShortCodeSupport $object The object to generate the shortcode for
		 * @return    string                            The shortcode for that object
		 */
		public static function generate(ShortCodeSupport $object)
		{
			return sprintf(self::FORMAT, $object::getClassShortcodeIdentifier(), $object->getShortcodeIdentifier());
		}
	}
