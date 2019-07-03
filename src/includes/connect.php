<?php
$pageload_start_time = microtime(true);
require_once(dirname(dirname(__FILE__))."/classes/AppSettings.php");
AppSettings::load();

if (empty($argv) && !empty(AppSettings::getParam('restrict_ip_address'))) {
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	if ($_SERVER['REMOTE_ADDR'] != AppSettings::getParam('restrict_ip_address')) die("This website is closed for maintenance.\n");
}

if (empty(AppSettings::getParam('coin_brand_name'))) die("Please set the 'coin_brand_name' parameter in your config/config.json");

if (!empty($allow_no_https)) {}
else if (AppSettings::getParam('base_url') && !AppSettings::runningFromCommandline()) {
	if (isset($_SERVER['HTTPS'])) $requested_base_url = "https";
	else $requested_base_url = "http";
	$requested_base_url .= "://".$_SERVER['HTTP_HOST'];
	
	if ($requested_base_url != AppSettings::getParam('base_url')) {
		header("Location: ".AppSettings::getParam('base_url').$_SERVER['REQUEST_URI']);
		die();
	}
}

date_default_timezone_set(AppSettings::getParam('default_timezone'));

header('Content-Type: text/html; charset=UTF-8');

include(AppSettings::srcPath()."/classes/Api.php");
include(AppSettings::srcPath()."/classes/App.php");
include(AppSettings::srcPath()."/classes/JsonRPCClient.php");
include(AppSettings::srcPath()."/classes/Blockchain.php");
include(AppSettings::srcPath()."/classes/Game.php");
include(AppSettings::srcPath()."/classes/Event.php");
if (AppSettings::getParam('pageview_tracking_enabled')) include(AppSettings::srcPath()."/classes/PageviewController.php");
include(AppSettings::srcPath()."/classes/User.php");

if (empty($skip_select_db)) $skip_select_db = false;
$app = new App($skip_select_db);

if (!$skip_select_db) {
	$app->load_module_classes();
}

if (AppSettings::getParam('pageview_tracking_enabled')) $pageviewController = new PageviewController($app);
?>