# Use official PHP image with Apache
FROM php:8.4-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Laravel application
COPY . /var/www/html

# Configure Apache to serve Laravel public directory
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set Laravel permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose Apache port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

# Use official PHP image with Apache
FROM php:8.4-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Laravel application
COPY . /var/www/html

# Configure Apache to serve Laravel public directory
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set Laravel permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose Apache port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
