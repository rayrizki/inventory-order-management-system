FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

# PCOV: driver coverage untuk PHPUnit, dibutuhkan agar laporan coverage bisa
# dibaca SonarQube. Dipilih dibanding Xdebug karena jauh lebih ringan dan
# memang hanya untuk menghitung baris yang tereksekusi - bukan debugger.
# Dimatikan secara default (lihat docker/pcov.ini) sehingga tidak menambah
# beban saat aplikasi melayani request; diaktifkan per-perintah saat
# menjalankan coverage.
RUN pecl install pcov && docker-php-ext-enable pcov

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . .

EXPOSE 8000

COPY docker/errors.ini /usr/local/etc/php/conf.d/zz-errors.ini
COPY docker/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/pcov.ini /usr/local/etc/php/conf.d/zz-pcov.ini

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
