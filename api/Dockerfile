FROM php:8.5.12-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends unzip
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

COPY public ./public
COPY bin ./bin
COPY routes ./routes
COPY src ./src
RUN mkdir -p var/rate-limit && chown -R www-data:www-data var

EXPOSE 9000
CMD ["php-fpm"]
