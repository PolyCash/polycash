<?php
if (is_file(realpath(dirname(__FILE__))."/config.php")) {
	include("config.php");
}
else die("Please create the file includes/config.php");

include("lib/bitcoin-sci/common.lib.php");

if ($GLOBALS['base_url'] && !$host_not_required) {
	$b_url = $_SERVER['HTTP_HOST'];
	if (isset($_SERVER['HTTPS'])) $b_url = "https://".$b_url;
	else $b_url = "http://".$b_url;
	
	if ($GLOBALS['b_url'] != $base_url) {
		header("Location: ".$GLOBALS['base_url'].$_SERVER['REQUEST_URI']);
		die();
	}
}

date_default_timezone_set($GLOBALS['default_timezone']);

if ($GLOBALS['pageview_tracking_enabled']) include("pageview_functions.php");

mysql_connect($GLOBALS['mysql_server'], $GLOBALS['mysql_user'], $GLOBALS['mysql_password']) or die("The server is unreachable.");
if (!$skip_select_db) {
	mysql_select_db($GLOBALS['mysql_database']) or die ( "Please <a href=\"/install.php?key=\">install the database</a>");
}
mysql_set_charset('utf8');
header('Content-Type: text/html; charset=UTF-8');

include("functions.php");
include("classes.php");
?>