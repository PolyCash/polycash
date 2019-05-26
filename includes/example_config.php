<?php
$is_dev = false;
$GLOBALS['coin_brand_name'] = "PolyCash";
$GLOBALS['site_name_short'] = "PolyCash";
$GLOBALS['site_name'] = "PolyCash";
$site_domain = "";			 		// Enter your domain name, IP or "localhost" here
$dev_site_domain = ""; 				// Enter your dev domain name here if you have one
if (!empty($dev_site_domain) && $_SERVER['SERVER_NAME'] == $dev_site_domain) {
	$site_domain = $dev_site_domain;
	$is_dev = true;
}
else $site_domain = $site_domain;
$GLOBALS['base_url'] = "http://".$site_domain;

$GLOBALS['mysql_server'] = "localhost";
$GLOBALS['mysql_user'] = ""; 				// Enter your mysql username here
$GLOBALS['mysql_password'] = ""; 			// Enter your mysql password here
$database = "";			 					// Enter your mysql database name here
$dev_database = "";							// Enter your dev db name here if you have one
if ($is_dev) $GLOBALS['mysql_database'] = $dev_database;
else $GLOBALS['mysql_database'] = $database;

$GLOBALS['signup_captcha_required'] = false;
$GLOBALS['recaptcha_publickey'] = "";
$GLOBALS['recaptcha_privatekey'] = "";

$GLOBALS['outbound_email_enabled'] = false;
$GLOBALS['sendgrid_user'] = "";
$GLOBALS['sendgrid_pass'] = "";

$GLOBALS['show_query_errors'] = true;
$GLOBALS['cron_key_string'] = ""; // Enter a random string / password here

$GLOBALS['process_lock_method'] = "db";

$GLOBALS['identifier_case_sensitive'] = 1;
$GLOBALS['identifier_first_char'] = 2;

$GLOBALS['pageview_tracking_enabled'] = false;

$GLOBALS['currency_price_refresh_seconds'] = 30;
$GLOBALS['invoice_expiration_seconds'] = 60*60*48;

$GLOBALS['mine_private_blocks_when_offline'] = false;
$GLOBALS['new_games_per_user'] = "unlimited";
$GLOBALS['homepage_fname'] = "default.php";
$GLOBALS['navbar_icon_path'] = "";

$GLOBALS['default_timezone'] = 'UTC';

$GLOBALS['rsa_keyholder_email'] = "";
$GLOBALS['rsa_pub_key'] = "";
$GLOBALS['profit_btc_address'] = "";

$GLOBALS['api_proxy_url'] = "";

$GLOBALS['default_server_api_access_key'] = false;
$GLOBALS['cachebuster'] = 1001;
?>
