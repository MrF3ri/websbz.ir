FROM node:22-bookworm-slim AS node-runtime

FROM php:8.5-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libonig-dev libpq-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql mbstring intl zip bcmath gd \
    && php -r 'if (! extension_loaded("Zend OPcache")) { fwrite(STDERR, "PHP 8.5 image must provide built-in Zend OPcache.\\n"); exit(1); }' \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --create-home --shell /usr/sbin/nologin app \
    && mkdir -p storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache node_modules \
    && chown -R app:app storage bootstrap/cache node_modules \
    && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's#^;*pid = .*#pid = /tmp/php-fpm.pid#' /usr/local/etc/php-fpm.conf \
    && sed -i 's#^;*error_log = .*#error_log = /proc/self/fd/2#' /usr/local/etc/php-fpm.conf

USER app

EXPOSE 9000

CMD ["php-fpm", "-F"]

# The dedicated Vite image also contains PHP CLI so Laravel's Wayfinder Vite
# plugin can invoke artisan while generating route/action declarations.
FROM app AS vite

COPY --from=node-runtime /usr/local/ /usr/local/

CMD ["node", "--version"]
