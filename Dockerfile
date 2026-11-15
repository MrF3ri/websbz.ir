FROM php:8.5-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libonig-dev libpq-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql mbstring intl zip bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . .

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --create-home --shell /usr/sbin/nologin app \
    && mkdir -p storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's#^;*pid = .*#pid = /tmp/php-fpm.pid#' /usr/local/etc/php-fpm.conf \
    && sed -i 's#^;*error_log = .*#error_log = /proc/self/fd/2#' /usr/local/etc/php-fpm.conf

USER app

EXPOSE 9000

CMD ["php-fpm", "-F"]
