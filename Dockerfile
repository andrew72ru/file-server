FROM composer:latest as composer
FROM php:8.1-fpm-alpine3.15 as php

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

ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

RUN pecl install redis && \
    docker-php-ext-enable redis && \
    pecl install uuid && \
    docker-php-ext-enable uuid && \
    pecl install pcov && \
    docker-php-ext-enable pcov

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php
ENV APP_ENV=prod
ENV APP_DEBUG=false

ARG UID
ARG GID
ENV TARGET_UID ${UID:-1000}
ENV TARGET_GID ${GID:-1000}

RUN usermod -u ${TARGET_UID} www-data && groupmod -g ${TARGET_UID} www-data
WORKDIR /var/www/app

RUN mkdir -p /var/www/app/var/cache/prod/images && mkdir -p /var/www/app/var/cache/prod/video && \
    chown -R www-data:www-data /var/www/app
COPY --chown=www-data:www-data . /var/www/app

USER ${TARGET_UID}:${TARGET_GID}
