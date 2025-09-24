FROM php:8.2-apache
RUN a2enmod rewrite

# This copies all files from the current directory (which is now 'api')
COPY . /var/www/html/

EXPOSE 80