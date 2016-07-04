<?php
if (is_file(realpath(dirname(__FILE__))."/config.php")) {
	include("config.php");
}
else die("Please create the file includes/config.php");

if ($GLOBALS['enforce_domain'] != "") {
	$domain_parts = explode(".", $_SERVER['HTTP_HOST']);
	$domain = $domain_parts[count($domain_parts)-2].".".$domain_parts[count($domain_parts)-1];
	if ($domain != $GLOBALS['enforce_domain']) {
		header("Location: http://".$GLOBALS['enforce_domain']);
		die();
	}
}

date_default_timezone_set($GLOBALS['default_timezone']);

if ($GLOBALS['pageview_tracking_enabled']) include("pageview_functions.php");

mysql_connect($GLOBALS['mysql_server'], $GLOBALS['mysql_user'], $GLOBALS['mysql_password']) or die("The server is unreachable.");
if (!$skip_select_db) {
	mysql_select_db($GLOBALS['mysql_database']) or die ( "Please <a href=\"/install.php\">install the database</a>");
}
mysql_set_charset('utf8');
header('Content-Type: text/html; charset=UTF-8');

include("functions.php");
include("classes.php");
?>