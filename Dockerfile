# Use the official PHP image with Apache
FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy your project into the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Set proper permissions (optional)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
