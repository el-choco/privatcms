# Explizit Debian Bookworm als Basis nutzen
FROM php:8.2-apache-bookworm

# Apache mod_rewrite aktivieren
RUN a2enmod rewrite

# System-Abhängigkeiten und PHP-Zip installieren
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    zip \
  && docker-php-ext-configure gd --with-jpeg \
  && docker-php-ext-install intl gd zip pdo pdo_mysql

# Arbeitsverzeichnis festlegen
WORKDIR /var/www/html

# Den gesamten Code kopieren
COPY . .

# Konfigurationen kopieren und Alias aktivieren
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/admin-alias.conf /etc/apache2/conf-available/admin-alias.conf
RUN a2enconf admin-alias

# DocumentRoot auf public setzen
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# Ordner vorbereiten und Berechtigungen für den Webserver setzen
RUN mkdir -p /var/www/html/backups /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/backups /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/backups /var/www/html/public/uploads