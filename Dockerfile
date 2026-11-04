FROM php:8.3-apache

RUN docker-php-ext-install pdo_pgsql pgsql

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 10000

CMD ["apache2-foreground"]

