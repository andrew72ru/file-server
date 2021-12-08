FROM composer:latest as composer
FROM php:8.1-fpm-alpine as php

RUN set -xe && apk update && apk upgrade

RUN set -xe \
    && apk add --no-cache \
       ${PHPIZE_DEPS} \
       shadow \
       libzip-dev \
       libintl \
       icu \
       icu-dev \
       curl \
       libmcrypt \
       libmcrypt-dev \
       libxml2-dev \
       pcre-dev \
       git \
       openssh \
       util-linux-dev \
       libuuid \
       gnu-libiconv \
    && docker-php-ext-install -j$(nproc) \
        zip \
        sockets \
        opcache \
        pcntl \
        sockets \
        iconv \
        intl

RUN pecl install redis && \
    docker-php-ext-enable redis && \
    pecl install uuid && \
    docker-php-ext-enable uuid

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/app
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

RUN echo 'memory_limit=1536M' > /usr/local/etc/php/conf.d/memory.ini
RUN echo 'upload_max_filesize = 10M' > /usr/local/etc/php/conf.d/upload-size.ini
RUN echo 'post_max_size = 10M' >> /usr/local/etc/php/conf.d/upload-size.ini

WORKDIR /var/www/app
COPY --chown=www-data:www-data . /var/www/app
