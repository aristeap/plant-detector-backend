# Use the official PHP image as a base
FROM php:8.2-apache

# Install Apache's mod_rewrite module
RUN a2enmod rewrite

# Copy your application code into the container's web root
COPY . /var/www/html/

# Expose port 80 to the outside world
EXPOSE 80