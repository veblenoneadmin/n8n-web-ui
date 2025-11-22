# Use the official PHP image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Copy all project files to the container
COPY . /var/www/html/

# Enable Apache mod_rewrite (needed for pretty URLs if you ever use them)
RUN a2enmod rewrite

# Set permissions (optional, but good practice)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Install any required PHP extensions (adjust as needed)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Expose port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
