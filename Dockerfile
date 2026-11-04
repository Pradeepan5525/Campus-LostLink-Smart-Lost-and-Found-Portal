FROM php:8.3-apache

# Install PostgreSQL development libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Change Apache port for Render
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf

# Copy project files into web root
COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 10000

CMD ["apache2-foreground"]
