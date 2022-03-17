<?php
	namespace Admin;
	
	use Controller\Twig;
	
	/**
	 * Twig rendering for the admin panel
	 */
	class AdminTwig extends Twig
	{
		const TWIG_ROOT = [DOC_ROOT . "/admin/theme/twig/", Twig::TWIG_ROOT];
		const TWIG_CACHE_LOCATION = DOC_ROOT . "/resources/cache/twig-admin/";
	}