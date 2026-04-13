# Use the official PHP 8.2 FPM image as base
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libgd-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    nginx \
    supervisor \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory permissions
COPY --chown=www:www . /var/www/html

# Copy PHP configuration (as root, before USER switch)
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy Nginx configuration (as root, before USER switch)
COPY nginx.conf /etc/nginx/sites-available/default

# Copy supervisor configuration (as root, before USER switch)
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Change current user to www
USER www

# Expose port 9000 (PHP-FPM) and 8080 (Nginx HTTP)
EXPOSE 9000 8080

# Run composer install
RUN composer install --optimize-autoloader

# Set up cron job for Laravel scheduler
RUN crontab -l | { cat; echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1"; } | crontab -

# Start supervisor to run both PHP-FPM and the queue worker
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
