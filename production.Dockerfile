ARG CI_REGISTRY_IMAGE
FROM ${CI_REGISTRY_IMAGE}/composer:latest

RUN composer install \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --no-ansi \
    --no-dev \
    --no-scripts