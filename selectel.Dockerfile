ARG BASE_APP_IMAGE
FROM ${BASE_IMAGE:-registry.gitlab.com/dtr-projects/main-project/php/base:latest} as php

WORKDIR /var/www/app
COPY --chown=www-data:www-data . /var/www/app
