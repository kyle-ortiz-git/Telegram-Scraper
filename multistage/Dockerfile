# ============================
# 1) Build stage for Composer
# ============================
FROM php:8.3-cli AS builder

RUN apt-get update && apt-get install -y git unzip

WORKDIR /app

# Copy only composer files
COPY app/composer.json app/composer.lock* ./

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader


# ============================
# 2) Production stage
# ============================
FROM php:8.3-apache

WORKDIR /var/www/html

# Enable PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy vendor from builder
COPY --from=builder /app/vendor /var/www/html/vendor

# Copy backend PHP files ONLY
COPY app/process.php /var/www/html/
COPY app/script.js /var/www/html/
COPY app/.env /var/www/html/

# OPTIONAL: if you expect more PHP files, add them here
# COPY app/somefile.php /var/www/html/

# Remove ANY existing index.html (old PHP frontend)
RUN rm -f /var/www/html/index.html

# ==========================================
# COPY THE REACT BUILD (THIS MUST BE LAST)
# ==========================================
COPY react/dist/ /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
