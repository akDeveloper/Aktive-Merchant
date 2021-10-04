FROM php:7.4-cli
WORKDIR "/opt/php"

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && \
    pecl channel-update pecl.php.net && \
    pecl install xdebug && \
    docker-php-ext-install bcmath && \
    docker-php-source delete && \
    rm -r /tmp/* /var/cache/*

COPY ./.docker/php7.4-cli/php-ini-overrides.ini /usr/local/etc/php/conf.d/99-overrides.ini