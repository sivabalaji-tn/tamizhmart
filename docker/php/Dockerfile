FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install mysqli pdo pdo_mysql gd

RUN a2enmod rewrite

WORKDIR /var/www/html

EXPOSE 80
