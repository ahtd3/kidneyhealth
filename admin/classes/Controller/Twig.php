<?php
	namespace Controller;

	use Configuration\Configuration;
	use Configuration\Registry;
	use Core\Generator;
	use Payments\PaymentGateway;
	use Template\ShortCodes\ShortCode;
	use Twig\Environment;
	use Twig\Error\Error;
	use Twig\Extension\DebugExtension;
	use Twig\Extra\Markdown\DefaultMarkdown;
	use Twig\Extra\Markdown\MarkdownExtension;
	use Twig\Extra\Markdown\MarkdownRuntime;
	use Twig\Loader\FilesystemLoader;
	use Twig\RuntimeLoader\RuntimeLoaderInterface;
	use Twig\TwigFilter;
	use Twig\TwigTest;
	
	/**
	 * Handles outputting Twig templates
	 */
	class Twig implements RuntimeLoaderInterface
	{
		const TWIG_ROOT = DOC_ROOT . "/theme/twig/";
		const TWIG_CACHE_LOCATION = DOC_ROOT . "/resources/cache/twig/";

		/**
		 * Renders a template and returns the results
		 * @param	string	$path		The path to the template, relative to the twig root
		 * @param	array	$context	The variables to pass to the template
		 * @return	string				The rendered template
		 * @throws	Error				If something goes wrong while rendering the template
		 */
		public static function render($path, array $context = [])
		{
			$loader = new FilesystemLoader(static::TWIG_ROOT);
			
			$twig = new Environment($loader,
			[
				"cache" => static::TWIG_CACHE_LOCATION,
				"debug" => true
			]);
			
			$twig->addExtension(new DebugExtension());
			$twig->addExtension(new MarkdownExtension());
			$twig->enableAutoReload();
			$twig->addRuntimeLoader(new self());

			$twig->addFilter(new TwigFilter("formatPrice", "formatPrice"));
			
			$twig->addTest(new TwigTest("enabled", function($module)
			{
				return constant(Registry::class . "::{$module}");
			}));

			$twig->addFilter(new TwigFilter("gatewayEnabled", function($gateway)
			{
				return PaymentGateway::CLASSES[$gateway] ?? false;
			}));

			$twig->addFilter(new TwigFilter("getDigits", function($numberString)
			{
				return Configuration::getDigits($numberString);
			}));

			$twig->addFilter(new TwigFilter("expandHtml", function($string)
			{
				return ShortCode::expandHtml($string);
			}, ["is_safe" => ["html"]]));

			/*
			 * Filters out all inactive items in an array
			 */
			$twig->addFilter(new TwigFilter("active", function($items)
			{
				return array_filter($items, function(Generator $item)
				{
					return $item->active;
				});
			}));
			
			$twig->addFilter(new TwigFilter("formatInternationalPhone", function($numberString)
			{
				return Configuration::formatAsInternationalPhone($numberString);
			}));
			
			$twig->addFilter(new TwigFilter("source", function($path)
			{
				return file_get_contents(DOC_ROOT . $path);
			}, ["is_safe" => ["html"]]));

			$template = $twig->load($path);
			return $template->render($context);
		}
		
		//region RuntimeLoaderInterface
		
		/**
		 * @inheritDoc
		 */
		public function load(string $class)
		{
			if(MarkdownRuntime::class === $class)
			{
				return new MarkdownRuntime(new DefaultMarkdown());
			}
			
			return null;
		}
		
		//endregion
	}
