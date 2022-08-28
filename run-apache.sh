#!/bin/bash

CONF_FNAME=/root/.datacoin/datacoin.conf
if ! [ -f "$CONF_FNAME" ]; then
	cp /var/www/html/.dockerize/datachain/datacoin.conf "$CONF_FNAME"
fi

POLYCASH_CONF_FNAME=/var/www/html/src/config/config.json
if ! [ -f "$POLYCASH_CONF_FNAME" ]; then
	cp /var/www/html/.dockerize/polycash/example-config.json "$POLYCASH_CONF_FNAME"
fi

/var/www/html/datacoind &
cron
a2enmod headers rewrite
apache2-foreground
