<?php
$pageload_start_time = microtime(true);

require_once(dirname(dirname(__FILE__))."/classes/AppSettings.php");
AppSettings::load();

if (empty($argv) && !empty(AppSettings::getParam('restrict_ip_address'))) {
	if ($_SERVER['REMOTE_ADDR'] != AppSettings::getParam('restrict_ip_address')) die("This website is closed for maintenance.\n");
}

if (empty(AppSettings::getParam('coin_brand_name'))) die("Please set the 'coin_brand_name' parameter in your config/config.json");

include(AppSettings::srcPath()."/lib/bitcoin-sci/common.lib.php");

if (!empty($allow_no_https)) {}
else if (AppSettings::getParam('base_url') && (!isset($host_not_required) || !$host_not_required)) {
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

$app = new App();

try {
	$all_dbs = $app->run_query("SHOW DATABASES;");
	if ($all_dbs->rowCount() > 0) {
		try {
			$all_modules = $app->run_query("SELECT * FROM modules ORDER BY module_id ASC;");
			
			while ($module = $all_modules->fetch()) {
				include(AppSettings::srcPath()."/modules/".$module['module_name']."/".$module['module_name']."GameDefinition.php");
			}
		}
		catch(Exception $ee) {}
	}	
}
catch (Exception $e) {}

if (AppSettings::getParam('pageview_tracking_enabled')) $pageview_controller = new PageviewController($app);
?>