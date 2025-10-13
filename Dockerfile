# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

# Set working directory for PHP app
WORKDIR /var/www/html

# Install dependencies (PHP + system tools + Python)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    zip \
    unzip \
    git \
    python3 \
    python3-pip \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy the PHP app
COPY ./app /var/www/html

# Copy the Telegram scraping directory into container
COPY ./telegram_scraping /opt/telegram_scraping

# Copy requirements (for Python + PHP)
COPY requirements.txt /opt/requirements.txt

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction


# Install Python dependencies for the scraper
RUN pip3 install --no-cache-dir --break-system-packages -r /opt/requirements.txt


# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose Apache port
EXPOSE 80

# Start both Apache and the Python scraper automatically
CMD ["bash", "-c", "python3 /opt/telegram_scraping/scraping.py & apache2-foreground"]