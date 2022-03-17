<?php
	namespace Controller;
	
	use Cart\CartController;
	use Forms\FormController;
	use Pages\Page;
	use Pages\PageController;
	use Payments\PaymentController;
	use Search\SearchController;
	use Users\AccountController;
	
	/**
	 * Handles root level URL segments
	 */
	class RootController extends UrlController
	{
		/**
		 * @inheritDoc
		 */
		protected static function getChildPatterns()
		{
			return
			[
				AccountController::BASE_PATH . "*" => AccountController::class,
				CartController::BASE_PATH . "*" => CartController::class,
				FormController::BASE_PATH . "*" => FormController::class,
				PaymentController::BASE_PATH . "*" => PaymentController::class,
				SearchController::BASE_PATH => SearchController::class,
				'/$page/*' => PageController::class
			];
		}
		
		/**
		 * @inheritDoc
		 */
		protected function getTemplateLocation()
		{
			return "under-construction-page.twig";
		}
		
		/**
		 * @inheritDoc
		 */
		protected static function getControllerFromPattern(UrlController $parent = null, array $matches = [], $pattern = "")
		{
			return null;
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			$page = Page::loadForMultiple(
			[
				"isHomepage" => true,
				"active" => true
			]);
			
			if($page->isNull())
			{
				parent::output();
			}
			else
			{
				$page->getController()->output();
			}
		}
	}