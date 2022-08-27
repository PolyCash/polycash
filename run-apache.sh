#!/bin/bash

CONF_FNAME=/root/.datacoin/datacoin.conf

if ! [ -f "$CONF_FNAME" ]; then
	cp /var/www/html/.dockerize/datachain/datacoin.conf "$CONF_FNAME"
fi

/var/www/datachain/datacoind &
cron
a2enmod headers rewrite
apache2-foreground
