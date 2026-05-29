FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    libpq-dev \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . .

RUN composer install --optimize-autoloader

RUN touch var/data_dev.db && chown -R www-data:www-data var/ && chmod -R 777 var/

EXPOSE 80