FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git curl zip unzip icu-dev oniguruma-dev libzip-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl zip bcmath gd opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini     /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
