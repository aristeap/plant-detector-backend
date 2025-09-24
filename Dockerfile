# Use the official PHP image as a base
FROM php:8.2-apache

# Copy the contents of your 'api' folder into the container's web root
COPY api/ /var/www/html/

# Expose port 80 to the outside world
EXPOSE 80