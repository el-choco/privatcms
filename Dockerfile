FROM php:8.2-apache

# Enable mod_rewrite for pretty URLs later
RUN a2enmod rewrite

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libicu-dev libjpeg62-turbo-dev libpng-dev libzip-dev unzip \
  && docker-php-ext-configure gd --with-jpeg \
  && docker-php-ext-install intl gd zip pdo pdo_mysql

# Upload limits
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/admin-alias.conf /etc/apache2/conf-available/admin-alias.conf
RUN a2enconf admin-alias

# Set Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
