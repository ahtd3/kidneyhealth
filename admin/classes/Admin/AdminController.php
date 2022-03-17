<?php
	namespace Admin;
	
	use Assets\Picker\PickerController;
	use Configuration\Configuration;
	use Configuration\ConfigurationController;
	use Configuration\Registry;
	use Controller\UrlController;
	use Users\AccountController;
	use Users\Administrator;
	use Users\User;
	
	/**
	 * Abstract class for admin controllers
	 */
	abstract class AdminController extends UrlController
	{
		/**
		 * Retrieves the child patterns that can belong to this controller
		 * Nested objects not supported (eg categories with sub Categories)
		 * @return    UrlController[]|string[]    Pattern to controller class names, example: ['/$category/' => CategoryController::class, '/$category/$tour/' => TourController::class]
		 */
		protected static function getChildPatterns()
		{
			return
			[
				'/action/new/$class/' => ProcessController::class,
				'/action/edit/$class/$id/' => ProcessController::class,
				'/action/delete/$class/$id/' => ProcessController::class,
				'/action/export/$class/' => ProcessController::class,
				'/action/reorder/' => ProcessController::class,
				'/action/upload/' => ProcessController::class,
				'/action/switch/$class/$id/$property/' => ProcessController::class,
				'/picker/$type/*' => PickerController::class,
				'/$class/new/*' => EditController::class,
				'/$class/edit/$id/*' => EditController::class,
				'/$class/*' => ListController::class,
				'/action/edit-config/' => ConfigurationController::class,
				'/action/update-property/$class/$id/$property/' => ProcessController::class
			];
		}
		
		/**
		 * Sets the variables that the template has access to
		 * @return	array	An array of [string => mixed] variables that the template has access to
		 */
		protected function getTemplateVariables()
		{
			Registry::checkModuleDependencies();

			switch($_SERVER["SERVER_NAME"])
			{
				case "www.werewolf.activatedev.co.nz":
					$powerSource = "Fenrir";
					$powerUrl = "http://www.artofvfx.com/ThorRagnarok/ThorRagnarok_Framestore_ITW_05.jpg";
				break;
				
				default:
					$powerSource = "Activate Design";
					$powerUrl = "https://www.activatedesign.co.nz/";
				break;
			}
			
			return
			[
				"_get" => $_GET,
				"_post" => $_POST,
				"adminNavItems" => Registry::getAdminNavItems(),
				"config" => Configuration::acquire(),
				"controller" => $this,
				"templateUsesVueRouter" => $this->getTemplateLocation() === "vue-page.twig",
				"powerSource" => $powerSource,
				"powerUrl" => $powerUrl,
				"templateDir" => DOC_ROOT . "/admin/theme/",
				"user" => User::get()
			];
		}
		
		/**
		 * Obtains a URL Controller for a URL
		 * @param	string[]		$segments	A list of URL segments to parse
		 * @param	UrlController	$parent		The previous Page Controller, unless this is the first segment
		 * @return	UrlController						A new Page Controller object
		 */
		public static function getControllerFor(array $segments, UrlController $parent = null)
		{
			$user = User::get();
			
			if($user->isNull())
			{
				AccountController::$registrationEnabled = false;
				return new LoginController();
			}
			
			if(!$user instanceof Administrator)
			{
				return new AccessController("You do not have permission to access this area");
			}
			
			return parent::getControllerFor($segments, $parent);
		}
		
		/**
		 * Sets the template variables and loads the template
		 */
		public function output()
		{
			echo AdminTwig::render($this->getTemplateLocation(), $this->getTemplateVariables());
		}
	}