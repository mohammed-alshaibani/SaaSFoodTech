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
    supervisor \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory permissions
COPY --chown=www:www . /var/www/html

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Run composer install
RUN composer install --no-dev --optimize-autoloader

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set up cron job for Laravel scheduler
RUN crontab -l | { cat; echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1"; } | crontab -

# Start supervisor to run both PHP-FPM and the queue worker
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
