# Dockerfile for PHP FPM
# Purpose: Run PHP application with necessary extensions
# Author: repo automation / commit: docker: add Dockerfile

FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql zip

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
