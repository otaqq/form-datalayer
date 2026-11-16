# PHP for development and tests: the lowest supported version
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-alpine
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
