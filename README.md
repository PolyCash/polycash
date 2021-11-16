## About PolyCash
PolyCash is an open source blockchain protocol for peer to peer betting currencies.  PolyCash integrates with blockchains including Bitcoin, Litecoin, Dogecoin & [Datachain](https://github.com/datachains/datachain). The PolyCash protocol powers Betcoin, a digital currency with a novel inflation model where coins are given out to players for betting on virtual basketball games in addition to being given to miners for securing the network.

## Install PolyCash
To get started, first install and secure Apache, MySQL and PHP (at least version 7).  Set your Apache web root to the "public" folder of this repository.  Then create a file src/config/config.json by copying and pasting src/config/example_config.json.

Make sure to set the following params in your config.json to something like the following:
```
"site_domain": "localhost",
"mysql_server": "127.0.0.1",
"mysql_user": "mysqluser",
"mysql_password": "somesecurepass",
"database": "polycash",
"operator_key": "anothersecurepass"
```
"operator_key" is a parameter which allows a site administrator to perform certain actions like updating the application.  If you are installing PolyCash on a public facing server, be sure to set a secure value for this parameter.

If you want to allow users to log in with an email address, enter your sendgrid API key in your config file:
```
"sendgrid_api_key": ""
```

Next, configure cron to poll PolyCash every minute. This keeps PolyCash in sync at all times. Add this line to your /etc/crontab:
```
* * * * * root /usr/bin/php /var/www/polycash/src/cron/minutely.php
```

Set "pageview_tracking_enabled": true in your config.json if you want to track all pageviews.  If you don't set this parameter, no IP addresses or pageviews will be tracked.

Next, point your browser to http://localhost/install.php?key=<operator_key> where <operator_key> is the random string that you generated above.  If Apache, MySQL and PHP are all installed correctly, PolyCash should automatically install.

Make sure you have curl installed:
```
apt-get install php-curl
```

Make sure QR codes are rendering correctly in the blockchain explorer. If not, ensure php-gd is installed.
```
apt-get install php-gd
```

Don't forget to restart apache after installing libraries like php-curl and php-gd.
```
service apache2 restart
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

## Install Blockchains & Games
By default, the Betcoin cryptocurrency is installed when you install PolyCash.  You can install other PolyCash-protocol cryptocurrencies by pasting their game definitions in via the "Import" link found in the left menu.

To get the betcoin cryptocurrency in sync, you'll need to install the Datachain blockchain as a full node.  For more information on that step, check out [Datachain](https://github.com/datachains/datachain) on github.

After installing Datachain, click the "Manage Blockchains" link in PolyCash, then select Datachain -> Set RPC credentials, and then enter the RPC username and password from your datacoin.conf.  Then run PolyCash either by setting up a cron job to run the src/cron/minutely.php every minute, or by visiting Install -> "Start process in new tab".
