FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev && docker-php-ext-install -j$(nproc) pdo_sqlite && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80