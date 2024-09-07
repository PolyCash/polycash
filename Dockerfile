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
RUN mv ./.dockerize/cron/polycash-crontab-example /etc/cron.d
RUN chmod 644 /etc/cron.d/polycash-crontab-example
RUN mv ./.dockerize/cron/polycash-crontab /etc/cron.d
RUN chmod 644 /etc/cron.d/polycash-crontab

ENTRYPOINT [ "./run-apache.sh" ]
