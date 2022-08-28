#!/bin/bash

if ! [ -f "/root/.datacoin" ]; then
	mkdir /root/.datacoin
fi

CONF_FNAME=/root/.datacoin/datacoin.conf
if ! [ -f "$CONF_FNAME" ]; then
	cp /var/www/html/.dockerize/datachain/datacoin.conf "$CONF_FNAME"
fi

POLYCASH_CONF_FNAME=/var/www/html/src/config/config.json
if ! [ -f "$POLYCASH_CONF_FNAME" ]; then
	cp /var/www/html/.dockerize/polycash/example-config.json "$POLYCASH_CONF_FNAME"
fi

if ! [ -f "/var/www/html/datacoind" ]; then
	wget https://poly.cash/binaries/debian/datacoind -P /var/www/html
	chmod 755 /var/www/html/datacoind
fi

if ! [ -f "/var/www/html/datacoin-cli" ]; then
	wget https://poly.cash/binaries/debian/datacoin-cli -P /var/www/html
	chmod 755 /var/www/html/datacoin-cli
fi

/var/www/html/datacoind &
cron
a2enmod headers rewrite
apache2-foreground
