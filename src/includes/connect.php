<?php
$pageload_start_time = microtime(true);
require_once(dirname(dirname(__FILE__))."/models/AppSettings.php");
AppSettings::load();

define('EXCHANGE_RATE_SIGFIGS', 6);

if (!AppSettings::runningFromCommandline() && !empty(AppSettings::getParam('restrict_ip_address'))) {
	if ($_SERVER['REMOTE_ADDR'] != AppSettings::getParam('restrict_ip_address')) {
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		die("This website is closed for maintenance.\n");
	}
}

if (AppSettings::runningFromCommandline()) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

if (!empty(AppSettings::getParam('memory_limit'))) ini_set('memory_limit', AppSettings::getParam('memory_limit'));

if ((string) AppSettings::getParam('options_begin_at_index') == "") die('Please add this line to your src/config/config.json: "options_begin_at_index": 10');

if (empty(AppSettings::getParam('site_name'))) die('Please set the "site_name" parameter in your src/config/config.json');

if (!empty($allow_no_https)) {}
else if (!AppSettings::runningFromCommandline()) {
	if (empty(AppSettings::getParam('site_domain'))) die('Please set "site_domain" in your configuration to a URL like "localhost"');
	else {
		if (isset($_SERVER['HTTPS'])) $requested_base_url = "https";
		else $requested_base_url = "http";
		$requested_base_url .= "://".$_SERVER['HTTP_HOST'];

		if ($requested_base_url != AppSettings::getParam('base_url')) {
			header("Location: ".AppSettings::getParam('base_url').$_SERVER['REQUEST_URI']);
			die();
		}
	}
}

date_default_timezone_set(AppSettings::getParam('default_timezone'));

header('Content-Type: text/html; charset=UTF-8');

include(AppSettings::srcPath()."/models/Api.php");
include(AppSettings::srcPath()."/models/App.php");
include(AppSettings::srcPath()."/models/CurrencyAccount.php");
include(AppSettings::srcPath()."/models/CurrencyOracle.php");
include(AppSettings::srcPath()."/models/Faucet.php");
include(AppSettings::srcPath()."/models/JsonRPCClient.php");
include(AppSettings::srcPath()."/models/Blockchain.php");
include(AppSettings::srcPath()."/models/BlockchainVerifier.php");
include(AppSettings::srcPath()."/models/EscrowAmount.php");
include(AppSettings::srcPath()."/models/Event.php");
include(AppSettings::srcPath()."/models/Game.php");
include(AppSettings::srcPath()."/models/GameDefinition.php");
include(AppSettings::srcPath()."/models/PeerVerifier.php");
include(AppSettings::srcPath()."/models/User.php");

if (AppSettings::getParam('pageview_tracking_enabled')) include(AppSettings::srcPath()."/models/PageviewController.php");

if (empty($skip_select_db)) $skip_select_db = false;
$app = new App($skip_select_db);

if (!$skip_select_db) {
	$app->load_module_classes();
}

if (AppSettings::getParam('pageview_tracking_enabled')) $pageviewController = new PageviewController($app);

if (!isset($argv) && !isset($_REQUEST['synchronizer_token'])) $_REQUEST['synchronizer_token'] = "";
?>