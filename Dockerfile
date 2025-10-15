# Use the official PHP 8.3 image with Apache
FROM php:8.3-apache

WORKDIR /var/www/html

# Install system dependencies, PHP extensions, Python, and ffmpeg
RUN apt-get update -o Acquire::Retries=3 && \
    apt-get install -y --no-install-recommends \
    libpng-dev \
    zip \
    unzip \
    git \
    python3 \
    python3-pip \
    ffmpeg \
    build-essential \
    python3-dev \
    netcat-traditional || apt-get install -y netcat-openbsd && \
    docker-php-ext-install pdo pdo_mysql mysqli && \
    a2enmod rewrite && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
