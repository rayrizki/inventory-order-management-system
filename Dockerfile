FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . .

EXPOSE 8000

COPY docker/errors.ini /usr/local/etc/php/conf.d/zz-errors.ini

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
