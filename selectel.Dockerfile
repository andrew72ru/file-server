ARG BASE_APP_IMAGE
FROM ${BASE_IMAGE:-git.crtweb.ru:4567/rostelecom/docker-images/php/base:latest} as php

WORKDIR /var/www/app
COPY --chown=www-data:www-data . /var/www/app
