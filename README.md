Empirecoin Web can act as front end web wallet for the decentralized EmpireCoin currency. It can also run as a standalone program for hosting centralized EmpireCoin private games.

To use Empirecoin Web as a wallet for the Empirecoin currency, first install empirecoind and ensure that RPC calls can be made.  If you are only using Empirecoin Web to host private games, you don't need to install empirecoind.

To get started, first please install and secure Apache, MySQL and PHP.  Then create a new file: includes/config.php and paste the following code into this file.  You can also find an example config file in includes/example_config.php

```
#!php

<?php
error_reporting(0);

$GLOBALS['mysql_server'] = "localhost";
$GLOBALS['mysql_user'] = "root"; // Enter your mysql username here
$GLOBALS['mysql_password'] = ""; // Enter your mysql password here
$GLOBALS['mysql_database'] = "empirecoin";

$GLOBALS['signup_captcha_required'] = false;
$GLOBALS['recaptcha_publickey'] = "";
$GLOBALS['recaptcha_privatekey'] = "";

$GLOBALS['outbound_email_enabled'] = false;
$GLOBALS['sendgrid_user'] = "";
$GLOBALS['sendgrid_pass'] = "";

$GLOBALS['show_query_errors'] = true;
$GLOBALS['cron_key_string'] = ""; // Enter a random string / password here

$GLOBALS['bitcoin_port'] = 8332;
$GLOBALS['bitcoin_rpc_user'] = "bitcoinrpc";
$GLOBALS['bitcoin_rpc_password'] = ""; // Enter your bitcoin RPC password here

// After installing, you'll create a user account.
$GLOBALS['default_coin_winner'] = 'your_username'; // Enter that username here

$GLOBALS['pageview_tracking_enabled'] = false;

$GLOBALS['currency_price_refresh_seconds'] = 2*60;
$GLOBALS['invoice_expiration_seconds'] = 15*60;
$GLOBALS['cron_interval_seconds'] = 5;

$GLOBALS['new_games_per_user'] = "unlimited";

$GLOBALS['coin_brand_name'] = "EmpireCoin";
$GLOBALS['site_name_short'] = "EmpireCoin";
$GLOBALS['site_name'] = "EmpireCoin.org";
$GLOBALS['site_domain'] = "localhost"; // Enter localhost or your public IP/domain here
$GLOBALS['base_url'] = "http://".$GLOBALS['site_domain'];

$GLOBALS['default_timezone'] = 'America/Chicago';

$GLOBALS['rsa_keyholder_email'] = "";
$GLOBALS['rsa_pub_key'] = "";
$GLOBALS['profit_btc_address'] = "";

$GLOBALS['api_proxy_url'] = "";
?>

```

Enter the username, password and database name for your MySQL database into your includes/config.php.

Next, configure cron to poll empirecoin every minute. This keeps empirecoin-web in sync with empirecoind. Add these lines to your /etc/crontab:
```
* * * * * root /usr/bin/php /var/www/html/empirecoin-web/cron/minutely.php <CRON_KEY_STRING>
```

You can configure outbound emails by setting $GLOBALS['outbound_email_enabled'] = true, and then entering your sendgrid credentials in the following 2 parameters.

Set $GLOBALS['pageview_tracking_enabled'] = true if you want to track all user's pageviews.  This may help you to detect malicious activity on your server.  If you set $GLOBALS['pageview_tracking_enabled'] = false; no IP addresses or pageviews from users will be tracked.

Set $GLOBALS['base_url'] to the URL for your server.  If you are running locally, this should be "http://localhost".  If you are using a domain, it should be something like "https://mydomain.com".
Also enter values for site_name_short, site_name, and site_domain.

Next, use a password generator or otherwise generate a secure random string of at least 10 characters, and enter it into the config file as $GLOBALS['cron_key_string'].  Certain actions such as installing the application should only be accessible by the site administrator; this secret string protects all of these actions.

Next, point your browser to http://localhost/install.php?key=<cron_key_string> where <cron_key_string> is the random string that you generated above.  If Apache, MySQL and PHP are all installed correctly, Empirecoin Web should automatically install.

Follow the instructions on install.php to configure your server for accepting Bitcoin payments and resolving any other potential issues.

After completing this step, visit the home page in your browser, log in and create an account.  After logging in you can try creating a private via the "My Games" tab.

If the home page doesn't load, it's possible that mod_rewrite needs to be enabled.  To enable mod_rewrite, edit your httpd.conf and make sure this line is uncommented:

```
#!php

LoadModule rewrite_module modules/mod_rewrite.so
```
