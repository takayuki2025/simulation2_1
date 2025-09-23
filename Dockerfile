FROM php:8.4-fpm

RUN apt-get update \
&& apt-get install -y default-mysql-client zlib1g-dev libzip-dev unzip \
&& docker-php-ext-install pdo_mysql zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer