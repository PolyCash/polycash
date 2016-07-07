<?php
if (is_file(realpath(dirname(__FILE__))."/config.php")) {
	include(realpath(dirname(__FILE__))."/config.php");
}
else die("Please create the file includes/config.php");

if ($GLOBALS['coin_brand_name'] != "") {}
else die('Please add this line to your includes/config.php: $GLOBALS[\'coin_brand_name\'] = \'EmpireCoin\';');

include("global_functions.php");
include(realpath(dirname(__FILE__))."/../lib/bitcoin-sci/common.lib.php");

if ($GLOBALS['base_url'] && (!isset($host_not_required) || !$host_not_required)) {
	$b_url = $_SERVER['HTTP_HOST'];
	if (isset($_SERVER['HTTPS'])) $b_url = "https://".$b_url;
	else $b_url = "http://".$b_url;
	
	if ($GLOBALS['b_url'] != $base_url) {
		header("Location: ".$GLOBALS['base_url'].$_SERVER['REQUEST_URI']);
		die();
	}
}

date_default_timezone_set($GLOBALS['default_timezone']);
$dbh = new_db_conn();
if (isset($skip_select_db) && $skip_select_db) {}
else {
	$dbh->query("USE ".$GLOBALS['mysql_database']) or die ("Please <a href=\"/install.php?key=\">install the database</a>");
}

$dbh->query("SET sql_mode='';");

header('Content-Type: text/html; charset=UTF-8');

include("classes/Api.php");
include("classes/App.php");
include("classes/JsonRPCClient.php");
include("classes/Game.php");
include("classes/Match.php");
if ($GLOBALS['pageview_tracking_enabled']) include("classes/PageviewController.php");
include("classes/User.php");

$app = new App($dbh);
if ($GLOBALS['pageview_tracking_enabled']) $pageview_controller = new PageviewController($app);
?>
