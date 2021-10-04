FROM php:8.0-cli
WORKDIR "/opt/php"

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && \
    pecl channel-update pecl.php.net && \
    pecl install xdebug && \
    docker-php-ext-install bcmath && \
    docker-php-source delete && \
    rm -r /tmp/* /var/cache/*

COPY ./.docker/php8.0-cli/php-ini-overrides.ini /usr/local/etc/php/conf.d/99-overrides.ini