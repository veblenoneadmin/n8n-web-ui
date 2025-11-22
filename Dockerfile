# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Enable PHP extensions for MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy your project files into the web root
COPY . /var/www/html/

# Set proper permissions (optional but recommended)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
