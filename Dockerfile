
FROM php:8.2-apache

# ติดตั้ง GD และ dependencies
RUN apt-get update \
	&& apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libzip-dev unzip git \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install gd zip pdo pdo_mysql

WORKDIR /var/www/html

# คัดลอกไฟล์ทั้งหมด
COPY . .

# ติดตั้ง Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# ติดตั้ง dependencies
RUN composer install --no-dev --optimize-autoloader

COPY .env /var/www/html/.env

EXPOSE 80
