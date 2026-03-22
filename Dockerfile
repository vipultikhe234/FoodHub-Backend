FROM php:8.2-cli

WORKDIR /app

# Install system dependencies needed for Laravel
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev

# Clear apt cache to keep image size small
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Postgres PDO and other essential Laravel extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy all application files
COPY . .

# Install dependencies without dev requirements
RUN composer install --no-dev --optimize-autoloader

# Create required directories if they don't exist
RUN mkdir -p storage bootstrap/cache

# Set proper permissions for Laravel storage and cache directories
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 10000

# Cache config/routes/views, run migrations, and start server
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=10000