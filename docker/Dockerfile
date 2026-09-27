ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        intl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/xdebug.ini

RUN git config --global --add safe.directory '*'

WORKDIR /app

COPY composer.json ./

ARG PIMCORE_CONSTRAINT=^12.0

RUN case "${PIMCORE_CONSTRAINT}" in \
        ^11.*) composer config --json policy.advisories.ignore '["pimcore/pimcore"]' ;; \
    esac \
    && composer require --no-interaction --no-progress --no-scripts \
        "pimcore/pimcore:${PIMCORE_CONSTRAINT}" \
    && composer install --prefer-dist --no-progress --no-interaction

COPY . .
