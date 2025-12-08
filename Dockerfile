# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

# Set working directory for the PHP app
WORKDIR /var/www/html

# ------------------------------------------------------------
# 1. Install system dependencies & PHP extensions
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y \
    libpng-dev \
    zip \
    unzip \
    git \
    python3 \
    python3-pip \
    ffmpeg \
    build-essential \
    python3-dev \
 && docker-php-ext-install pdo pdo_mysql mysqli \
 && a2enmod rewrite \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# 2. Install Composer
# ------------------------------------------------------------
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# ------------------------------------------------------------
# 3. Copy ONLY Composer manifests from ./app and install PHP deps
#    (this creates vendor/ in /var/www/html)
# ------------------------------------------------------------
COPY ./app/composer.json /var/www/html/composer.json
# composer.lock is optional; copy it if present
COPY ./app/composer.lock /var/www/html/composer.lock

RUN composer install --no-dev --optimize-autoloader --no-interaction

# ------------------------------------------------------------
# 4. Copy application source code from ./app into /var/www/html
#    (this will NOT overwrite vendor/, we are not copying vendor/)
# ------------------------------------------------------------
COPY ./app/ /var/www/html/

# ------------------------------------------------------------
# 5. Copy Python scraper and requirements
# ------------------------------------------------------------
WORKDIR /opt
COPY ./telegram_scraping /opt/telegram_scraping
COPY ./requirements.txt /opt/requirements.txt

RUN --mount=type=cache,target=/root/.cache/pip \
    pip3 install --no-cache-dir --break-system-packages audioop-lts && \
    pip3 install --no-cache-dir --break-system-packages -r /opt/requirements.txt

# ------------------------------------------------------------
# 6. Permissions
# ------------------------------------------------------------
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# ------------------------------------------------------------
# 7. Copy env file
# ------------------------------------------------------------
COPY .env /var/www/html/.env

# ------------------------------------------------------------
# 8. Expose Apache port
# ------------------------------------------------------------
EXPOSE 80

# ------------------------------------------------------------
# 9. Start Apache + Python scraper
# ------------------------------------------------------------
CMD ["bash", "-c", "python3 /opt/telegram_scraping/scraping.py & apache2-foreground"]
