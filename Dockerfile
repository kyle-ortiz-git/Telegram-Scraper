# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# ------------------------------------------------------------
# 1️ Install system dependencies & PHP extensions
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
# 2️ Install Composer
# ------------------------------------------------------------
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# ------------------------------------------------------------
# 3️ Copy ONLY composer manifests FIRST
# This ensures composer install can run BEFORE app code is copied
# ------------------------------------------------------------
COPY ./app/composer.json /var/www/html/composer.json
COPY ./app/composer.lock /var/www/html/composer.lock

# ------------------------------------------------------------
# 4️ Install PHP dependencies BEFORE copying the full app
# (better build caching, vendor will not be overwritten)
# ------------------------------------------------------------
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ------------------------------------------------------------
# 5️ Now copy application source code
# (vendor already exists, app code will NOT overwrite it)
# ------------------------------------------------------------
COPY ./app /var/www/html

# Copy Python scraper
COPY ./telegram_scraping /opt/telegram_scraping

# Python requirements
COPY ./requirements.txt /opt/requirements.txt

# ------------------------------------------------------------
# 6️ Install Python dependencies
# ------------------------------------------------------------
WORKDIR /opt
RUN --mount=type=cache,target=/root/.cache/pip \
    pip3 install --no-cache-dir --break-system-packages audioop-lts && \
    pip3 install --no-cache-dir --break-system-packages -r /opt/requirements.txt

# ------------------------------------------------------------
# 7️ Permissions
# ------------------------------------------------------------
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# ------------------------------------------------------------
# 8️ Copy environment file
# ------------------------------------------------------------
COPY .env /var/www/html/.env

# ------------------------------------------------------------
# 9️ Expose Apache port
# ------------------------------------------------------------
EXPOSE 80

# ------------------------------------------------------------
# 10 Start Apache + Python scraper
# ------------------------------------------------------------
CMD ["bash", "-c", "python3 /opt/telegram_scraping/scraping.py & apache2-foreground"]
