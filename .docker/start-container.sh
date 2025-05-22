#!/bin/sh
# /.docker/start-container.sh
set -e

# Wait for the database to be ready (optional, depends_on helps but doesn't guarantee DB service readiness)
# Add a loop here to check DB connection if needed, e.g., using nc or psql/mysql client

# Run Laravel optimizations and migrations
# These are often run once per deployment.
# For frequent starts in dev, you might comment some of these out or run them manually.
if [ "$APP_ENV" != 'local' ] && [ "$APP_ENV" != 'development' ]; then
    echo "Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Always run migrations (or use --seed for development)
echo "Running database migrations..."
php artisan migrate --force

# Execute the CMD from the Dockerfile (e.g., php-fpm)
echo "Starting PHP-FPM..."
exec "$@"
