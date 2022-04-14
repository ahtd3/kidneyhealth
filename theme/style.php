<?php
	use Sass\CustomCompiler;
	use Sass\MappingServer;
	
	require_once($_SERVER["DOCUMENT_ROOT"] . "/admin/scripts-includes/universal.php");

	$scss = new CustomCompiler;
	$server = new MappingServer(__DIR__, $scss);
	$server->serve();
