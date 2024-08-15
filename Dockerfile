FROM composer:latest AS composer

FROM php:8.2-apache

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN docker-php-ext-install zip sodium && \
    service apache2 restart


COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY ./000-default.conf /etc/apache2/sites-enabled/000-default.conf

WORKDIR /src
COPY ./application /src

RUN composer install

EXPOSE 80