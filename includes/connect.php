<?php
$pageload_start_time = microtime(true);

if (is_file(realpath(dirname(__FILE__))."/config.php")) {
	include(realpath(dirname(__FILE__))."/config.php");
}
else die("Please create the file includes/config.php");

if (empty($argv) && !empty($GLOBALS['restrict_ip_address'])) {
	if ($_SERVER['REMOTE_ADDR'] != $GLOBALS['restrict_ip_address']) die("This website is closed for maintenance.\n");
}

if (empty($GLOBALS['coin_brand_name'])) die('Please add this line to your includes/config.php: $GLOBALS[\'coin_brand_name\'] = \'CoinBlock\';');

if (empty($GLOBALS['process_lock_method'])) {
	if (PHP_OS == "WINNT") $GLOBALS['process_lock_method'] = "db";
	else $GLOBALS['process_lock_method'] = "grep";
}

include("global_functions.php");
include(realpath(dirname(dirname(__FILE__)))."/lib/bitcoin-sci/common.lib.php");

if (!empty($allow_no_https)) {}
else if ($GLOBALS['base_url'] && (!isset($host_not_required) || !$host_not_required)) {
	$b_url = $_SERVER['HTTP_HOST'];
	if (isset($_SERVER['HTTPS'])) $b_url = "https://".$b_url;
	else $b_url = "http://".$b_url;
	
	if ($b_url != $GLOBALS['base_url']) {
		header("Location: ".$GLOBALS['base_url'].$_SERVER['REQUEST_URI']);
		die();
	}
}

date_default_timezone_set($GLOBALS['default_timezone']);
if (empty($skip_select_db)) $skip_select_db = false;
try {
	$dbh = new_db_conn($skip_select_db);
	$dbh->query("SET sql_mode='';");
}
catch (Exception $e) {
	die("Error, database connection failed. Make sure MySQL is running and check mysql parameters in includes/config.php");
}
header('Content-Type: text/html; charset=UTF-8');

include(realpath(dirname(dirname(__FILE__)))."/classes/Api.php");
include(realpath(dirname(dirname(__FILE__)))."/classes/App.php");
include(realpath(dirname(dirname(__FILE__)))."/classes/JsonRPCClient.php");
include(realpath(dirname(dirname(__FILE__)))."/classes/Blockchain.php");
include(realpath(dirname(dirname(__FILE__)))."/classes/Game.php");
include(realpath(dirname(dirname(__FILE__)))."/classes/Event.php");
if ($GLOBALS['pageview_tracking_enabled']) include(realpath(dirname(dirname(__FILE__)))."/classes/PageviewController.php");
include(realpath(dirname(dirname(__FILE__)))."/classes/User.php");

$app = new App($dbh);
try {
	$app->set_db($GLOBALS['mysql_database']);
	
	$r = $app->run_query("SHOW DATABASES;");
	if ($r->rowCount() > 0) {
		try {
			$module_q = "SELECT * FROM modules ORDER BY module_id ASC;";
			$module_r = $app->run_query($module_q);
			while ($module = $module_r->fetch()) {
				include(realpath(dirname(dirname(__FILE__)))."/modules/".$module['module_name']."/".$module['module_name']."GameDefinition.php");	
			}
		}
		catch(Exception $ee) {}
	}	
}
catch (Exception $e) {}

if ($GLOBALS['pageview_tracking_enabled']) $pageview_controller = new PageviewController($app);
?>