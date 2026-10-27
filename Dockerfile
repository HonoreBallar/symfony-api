FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# var/ (cache, logs) lives in a Docker volume: file locks are unreliable and slow on a Windows bind mount.
# The volume inherits this ownership when it is first created.
RUN mkdir -p var && chown www-data:www-data var

# php-fpm and "docker compose exec" both run as www-data, so cache files are always writable by both
USER www-data
