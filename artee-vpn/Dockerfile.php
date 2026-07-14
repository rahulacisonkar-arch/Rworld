# ============================================================
#  ARTEE VPN — Custom PHP 7.0 + Apache Docker Image
# ============================================================
FROM php:7.0-apache

# Install required PHP extensions for MySQL + production use
RUN docker-php-ext-install pdo pdo_mysql mysqli opcache

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# Configure PHP for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini

# Configure Apache to serve from /var/www/html and allow .htaccess
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80
