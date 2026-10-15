FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql
COPY uploads/.htaccess /var/www/html/uploads/.htaccess
