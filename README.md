To get started, first install and secure Apache, MySQL and PHP (at least version 7).  Set your Apache web root to the "public" folder of this repository.  Then create a file src/config/config.json by copying and pasting src/config/example_config.json.

Make sure to set the following params in your config.json to something like the following:
```
"site_domain": "localhost",
"mysql_server": "localhost",
"mysql_user": "root",
"mysql_password": "somesecurepass",
"database": "polycash",
"operator_key": "anothersecurepass"
```
"operator_key" is a parameter which allows a site administrator to perform certain actions like updating the application.  If you are installing PolyCash on a public facing server, be sure to set a secure value for this parameter.

If you want to allow users to log in with an email address, enter your sendgrid credentials into these variables in your config file:
```
"sendgrid_user": "",
"sendgrid_pass": ""
```

Next, configure cron to poll PolyCash every minute. This keeps PolyCash in sync at all times. Add this line to your /etc/crontab:
```
* * * * * root /usr/bin/php /var/www/html/polycash/src/cron/minutely.php
```

Set "pageview_tracking_enabled": true in your config.json if you want to track all pageviews.  If you don't set this parameter, no IP addresses or pageviews will be tracked.

Next, point your browser to http://localhost/install.php?key=<operator_key> where <operator_key> is the random string that you generated above.  If Apache, MySQL and PHP are all installed correctly, PolyCash should automatically install.

Make sure you have curl installed:
```
apt-get install php-curl
```

If the home page doesn't load, it's possible that mod_rewrite needs to be enabled.  To enable mod_rewrite, edit your httpd.conf and make sure this line is uncommented:

```
#!php

LoadModule rewrite_module modules/mod_rewrite.so
```
Or run this command:
```
a2enmod rewrite
```

For faster page loads, make sure that browser caching is enabled
```
a2enmod expires
```

The user account you set up when installing has special permissions.  Use this account to import any game definitions for crypto assets that you want to run on your node.  Any time you update PolyCash from github, make sure to visit the install page and any new database migrations will automatically be applied.

Use the install page to enter the RPC parameters for any blockchains that you want to use.  Install and start your blockchains as full nodes before entering the RPC parameters.  To install full nodes, make sure to set txindex=1 in bitcoin.conf, litecoin.conf etc.  After entering blockchain RPC parameters, use the "reset & synchronize" link on the install page to quickly insert initial empty blocks.

You need to set the right value for "first required block" for any blockchains that you install.  You should set the first required block for each blockchain at least as early as the lowest starting block for any games that you plan to install on that blockchain.  You should try to avoid ever changing the first required block to a lower value because this will cause the entire blockchain to re-sync with PolyCash which can take hours or days.
