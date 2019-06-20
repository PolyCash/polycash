To get started, first please install and secure Apache, MySQL and PHP.  Set your Apache web root to the "public" folder of this repository.  Then create a file src/config/config.json by copying and pasting src/config/example_config.json.

Make sure to set the following params in your config.json to something like the following:
```
"site_domain": "localhost",
"mysql_server": "localhost",
"mysql_user": "root",
"mysql_password": "somesecurepass",
"database": "polycash",
"cron_key_string": "anothersecurepass"
```
"cron_key_string" is a parameter which allows a site administrator to perform certain actions like updating the application.  If you are installing PolyCash on a public facing server, be sure to set a secure value for this parameter.

If you want to allow users to log in with an email address, enter your sendgrid credentials into these variables in your config file:
```
"sendgrid_user": "",
"sendgrid_pass": ""
```

Next, configure cron to poll PolyCash every minute. This keeps PolyCash in sync at all times. Add this line to your /etc/crontab:
```
* * * * * root /usr/bin/php /var/www/html/polycash/src/cron/minutely.php
```

Set "pageview_tracking_enabled" = true in your config.json if you want to track all pageviews.  This may help you to detect malicious activity on your server.  If you don't set this parameter, no IP addresses or pageviews will be tracked.

Next, point your browser to http://localhost/install.php?key=<cron_key_string> where <cron_key_string> is the random string that you generated above.  If Apache, MySQL and PHP are all installed correctly, PolyCash should automatically install.

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