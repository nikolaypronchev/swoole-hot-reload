ARG PHP_VERSION=8

FROM php:${PHP_VERSION}-cli

COPY --from=composer:lts /usr/bin/composer /usr/local/bin/composer

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
  inotify \
  swoole \
  zip

# Hide Composer fundraising message
ENV COMPOSER_FUND=0