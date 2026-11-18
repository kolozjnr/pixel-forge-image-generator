
# ==================================================
# STAGE 1: Build Laravel and frontend assets
# ==================================================
FROM php:8.4-cli AS build

WORKDIR /var/www/html

# Install PHP dependencies
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
    ca-certificates \
    gnupg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 22
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && node --version \
    && npm --version

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application source
COPY . .

# Install Composer dependencies, including build-time dependencies
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Install frontend dependencies
RUN npm ci

# Clear any stale Laravel route cache before Wayfinder generation
RUN php artisan route:clear

# Build Vue, Inertia and Tailwind assets using Vite Plus
RUN npm run build

# Verify frontend build
RUN test -f public/build/manifest.json \
    && echo "Vite manifest generated successfully"


# ==================================================
# STAGE 2: Production Apache image
# ==================================================
FROM php:8.4-apache AS production

WORKDIR /var/www/html

# Install required PHP dependencies
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

# Enable Apache rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Laravel application
COPY . .

# Copy generated frontend assets
COPY --from=build \
    /var/www/html/public/build \
    /var/www/html/public/build

# Configure Apache
COPY docker/000-default.conf \
    /etc/apache2/sites-available/000-default.conf

# Install production Composer dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Set Laravel permissions
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Verify production assets
RUN test -f public/build/manifest.json

EXPOSE 80

CMD ["apache2-foreground"]
