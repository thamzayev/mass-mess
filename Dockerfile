FROM php:8.3-fpm
RUN apt-get update
RUN apt-get install -y git curl zip unzip libonig-dev libxml2-dev libzip-dev libpng-dev libmcrypt-dev libpq-dev mariadb-client supervisor netcat-traditional
RUN docker-php-ext-install pdo pdo_mysql zip exif pcntl intl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www && chmod -R 777 /var/www/storage && chmod -R 777 /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
