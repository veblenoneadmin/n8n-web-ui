# Use the official PHP image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Copy all project files to the container
COPY . /var/www/html/

# Enable Apache mod_rewrite (required for .htaccess)
RUN a2enmod rewrite

# Allow .htaccess overrides in Apache config
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set permissions (good practice)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Expose Apache port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
