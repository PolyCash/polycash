<?php
$GLOBALS['mysql_server'] = "localhost";
$GLOBALS['mysql_user'] = "root";
$GLOBALS['mysql_password'] = "";
$GLOBALS['mysql_database'] = "empirecoin";

$GLOBALS['signup_captcha_required'] = false;
$GLOBALS['recaptcha_publickey'] = false;
$GLOBALS['recaptcha_privatekey'] = false;

$GLOBALS['outbound_email_enabled'] = false;
$GLOBALS['sendgrid_user'] = "";
$GLOBALS['sendgrid_pass'] = "";

$GLOBALS['show_query_errors'] = false;
$GLOBALS['cron_key_string'] = "";

$GLOBALS['coin_port'] = 23347;
$GLOBALS['coin_testnet_port'] = 23345;
$GLOBALS['coin_rpc_user'] = "EmpireCoinrpc";
$GLOBALS['coin_rpc_password'] = "";

$GLOBALS['always_generate_coins'] = false;
$GLOBALS['restart_generation_seconds'] = 60;

$GLOBALS['walletnotify_by_cron'] = true;
$GLOBALS['pageview_tracking_enabled'] = false;
$GLOBALS['min_unallocated_addresses'] = 40;

$GLOBALS['site_name_short'] = "EmpireCoin";
$GLOBALS['site_name'] = "EmpireCoin.org";
$GLOBALS['site_domain'] = strtolower($GLOBALS['site_name']);
$GLOBALS['base_url'] = "http://".$GLOBALS['site_domain'];

$GLOBALS['default_timezone'] = 'America/Chicago';

$GLOBALS['default_server_api_access_key'] = false;
$GLOBALS['api_proxy_url'] = false;
?>