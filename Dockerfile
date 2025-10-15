# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

# Set working directory for PHP app
WORKDIR /var/www/html

# Install system dependencies, PHP extensions, Python, and ffmpeg
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
    netcat-traditional \           # <-- allows healthcheck/wait-for-db
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application code and scraper
COPY ./app /var/www/html
COPY ./telegram_scraping /opt/telegram_scraping
COPY requirements.txt /opt/requirements.txt

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Python dependencies
# `audioop-lts` acts as the drop-in replacement for `audioop` in Python 3.13+
RUN pip3 install --no-cache-dir --break-system-packages audioop-lts && \
    pip3 install --no-cache-dir --break-system-packages -r /opt/requirements.txt

# Copy in a wait script to delay startup until DB is ready
COPY wait-for-db.sh /usr/local/bin/wait-for-db.sh
RUN chmod +x /usr/local/bin/wait-for-db.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expose Apache port
EXPOSE 80

# Run wait-for-db, then Python scraper and Apache
CMD ["bash", "-c", "/usr/local/bin/wait-for-db.sh db 3306 && python3 /opt/telegram_scraping/scraping.py & apache2-foreground"]
