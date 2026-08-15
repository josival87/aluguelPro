FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev libpq-dev oniguruma-dev linux-headers sqlite-dev \
    && docker-php-ext-install bcmath intl opcache pcntl pdo_pgsql pdo_sqlite \
    && rm -rf /tmp/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/php/entrypoint.sh /usr/local/bin/alugapro-entrypoint
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN chmod +x /usr/local/bin/alugapro-entrypoint
ENTRYPOINT ["alugapro-entrypoint"]
CMD ["php-fpm"]
