FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql

RUN a2enmod rewrite

WORKDIR /var/www/html

EXPOSE 80