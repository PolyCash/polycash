#!/bin/bash

/var/www/datachain/datacoind &
cron
a2enmod headers rewrite
apache2-foreground
