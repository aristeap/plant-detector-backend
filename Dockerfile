FROM php:8.2-apache

# 1. Install Composer globally inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 2. Enable Apache's rewrite module for .htaccess files
RUN a2enmod rewrite

# 3. Copy all project files into the Apache web root
COPY . /var/www/html/

# 4. Set the working directory to the web root
WORKDIR /var/www/html/

# 5. Install PHP dependencies (creates the vendor folder)
RUN composer install --no-dev --optimize-autoloader

# 6. Expose the standard web port
EXPOSE 80