FROM php:8.3-cli

WORKDIR /app

# Install system dependencies needed for Laravel and clear cache in a single layer to reduce image size
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libmariadb-dev-compat \
    libmariadb-dev \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install MySQL and PostgreSQL PDO, plus other essential extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock ./

# Install dependencies without dev requirements and avoid running scripts
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy the rest of the application files
COPY . .

# Generate optimized autoload files and run post-install scripts
RUN composer dump-autoload --optimize

# Create required directories and set permissions
RUN mkdir -p storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Cache configurations, run migrations safely, and start the development server
CMD php artisan optimize:clear && \
    php artisan optimize && \
    php artisan migrate --force && \
    (php artisan db:seed --class=DatabaseSeeder --force || true) && \
    php artisan serve --host=0.0.0.0 --port=8000