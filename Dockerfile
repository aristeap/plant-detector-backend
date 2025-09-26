FROM php:8.2-apache

# 1. Install Composer globally inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 2. Enable Apache's rewrite module for .htaccess files
RUN a2enmod rewrite

# 3. Copy all project files into the Apache web root
COPY . /var/www/html/

# --- NEW: Change Apache's DocumentRoot to the 'api' folder ---
# The default document root is /var/www/html. We change it to /var/www/html/api
RUN sed -i 's!/var/www/html!/var/www/html/api!g' /etc/apache2/sites-available/000-default.conf
# 4. Set the working directory to the web root
WORKDIR /var/www/html/

# 5. Install PHP dependencies (creates the vendor folder)
RUN composer install --no-dev --optimize-autoloader

# 6. Expose the standard web port
EXPOSE 80