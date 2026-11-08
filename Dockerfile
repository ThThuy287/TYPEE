FROM wordpress:latest

# Cài đặt và bật extension Redis cho PHP
RUN pecl install redis && docker-php-ext-enable redis