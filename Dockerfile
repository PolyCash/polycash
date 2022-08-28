FROM php:8.0-apache

WORKDIR /var/www/html

RUN apt-get update
RUN apt-get install -y software-properties-common

RUN apt-get install -y \
    mariadb-client \
    curl \
    cron \
	wget \
	libboost-all-dev \
	libdb5.3++ \
	libdb5.3++-dev \
	libpng-dev

RUN docker-php-ext-install pdo_mysql \
    && docker-php-ext-install mysqli \
	&& docker-php-ext-install gd \
    && docker-php-source delete

COPY ./ ./
RUN mv ./.dockerize/apache/000-default.conf /etc/apache2/sites-enabled/000-default.conf
RUN mv ./.dockerize/cron/crontab /etc/crontab
RUN mv ./.dockerize/polycash/example-config.json /var/www/html/src/config/config.json
RUN mkdir /root/.datacoin

RUN wget https://poly.cash/binaries/debian/datacoin-cli -P /var/www/html
RUN wget https://poly.cash/binaries/debian/datacoind -P /var/www/html
RUN chmod 755 /var/www/html/datacoind
RUN chmod 755 /var/www/html/datacoin-cli

RUN php /var/www/html/src/scripts/set_blockchain_parameters.php blockchain_identifier=datachain rpc_username=datacoinuser rpc_password=datacoinpass

ENTRYPOINT [ "./run-apache.sh" ]
