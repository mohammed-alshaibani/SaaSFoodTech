#!/bin/bash

# Set permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

# Wait for database to be ready
echo "Waiting for MySQL to be ready..."
while ! mysqladmin ping -h mysql -u foodtech_user -p${DB_PASSWORD} --silent; do
    echo "MySQL is unavailable - sleeping..."
    sleep 2
done

echo "MySQL is ready!"

# Run Laravel migrations
echo "Running database migrations..."
php artisan migrate --force

# Run database seeders
echo "Running database seeders..."
php artisan db:seed --force

# Generate Swagger documentation
echo "Generating API documentation..."
php artisan l5-swagger:generate

# Clear and cache configurations
echo "Optimizing application..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Set proper permissions again
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

echo "Application is ready!"

# Execute the original command
exec "$@"
