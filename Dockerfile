FROM php:8.3-apache

RUN docker-php-ext-install pdo_sqlite sqlite3

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80