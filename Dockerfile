# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

# Set working directories
WORKDIR /var/www/html

# ------------------------------------------------------------
# 1️⃣ Install system dependencies & PHP extensions
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
# 2️⃣ Install Composer (cached globally)
# ------------------------------------------------------------
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ------------------------------------------------------------
# 3️⃣ Copy dependency manifests *only* first (for cache)
# ------------------------------------------------------------
COPY ./app/composer.json ./app/composer.lock* /var/www/html/
COPY ./requirements.txt /opt/requirements.txt

# ------------------------------------------------------------
# 4️⃣ Install PHP & Python dependencies (cached)
# ------------------------------------------------------------
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

WORKDIR /opt
RUN --mount=type=cache,target=/root/.cache/pip \
    pip3 install --no-cache-dir --break-system-packages audioop-lts && \
    pip3 install --no-cache-dir --break-system-packages -r /opt/requirements.txt

# ------------------------------------------------------------
# 5️⃣ Copy application source code (invalidate cache only here)
# ------------------------------------------------------------
COPY ./app /var/www/html
COPY ./telegram_scraping /opt/telegram_scraping

# ------------------------------------------------------------
# 6️⃣ Permissions
# ------------------------------------------------------------
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# ------------------------------------------------------------
# 7️⃣ Expose Apache port
# ------------------------------------------------------------
EXPOSE 80

# ------------------------------------------------------------
# 8️⃣ Start Apache + Python scraper
# ------------------------------------------------------------
CMD ["bash", "-c", "python3 /opt/telegram_scraping/scraping.py & apache2-foreground"]
