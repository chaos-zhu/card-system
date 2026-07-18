FROM php:7.4-fpm-bullseye AS app

WORKDIR /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev libonig-dev libpng-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl exif gd mbstring mysqli pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY . .

RUN composer config --no-plugins allow-plugins.kylekatarnls/update-helper true \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]

FROM nginx:1.24-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/public /var/www/public
