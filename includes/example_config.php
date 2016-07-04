<?php
$GLOBALS['mysql_server'] = "localhost";
$GLOBALS['mysql_user'] = ""; // Required
$GLOBALS['mysql_password'] = ""; // Required
$GLOBALS['mysql_database'] = "empirecoin";

$GLOBALS['signup_captcha_required'] = false;
$GLOBALS['recaptcha_publickey'] = "";
$GLOBALS['recaptcha_privatekey'] = "";

$GLOBALS['sendgrid_user'] = "";
$GLOBALS['sendgrid_pass'] = "";

$GLOBALS['show_query_errors'] = true;
$GLOBALS['cron_key_string'] = ""; // Required (Please enter a random string)

$GLOBALS['coin_port'] = 23347;
$GLOBALS['coin_testnet_port'] = 23345;
$GLOBALS['coin_rpc_user'] = "EmpireCoinrpc";
$GLOBALS['coin_rpc_password'] = ""; // Required

$GLOBALS['always_generate_coins'] = false;
$GLOBALS['restart_generation_seconds'] = 60;

$GLOBALS['walletnotify_by_cron'] = true;
$GLOBALS['pageview_tracking_enabled'] = false;

$GLOBALS['enforce_domain'] = "";
?>