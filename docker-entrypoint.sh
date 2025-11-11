#!/bin/sh

# Attendre que la base de données soit prête
echo "Waiting for database to be ready..."
while ! pg_isready -h ${DB_HOST:-db} -p ${DB_PORT:-5432} -U ${DB_USERNAME:-laravel}; do
  echo "Database is unavailable - sleeping"
  sleep 1
done

echo "Database is up - executing migrations"
php artisan migrate --force

echo "Running database seeders"
php artisan db:seed --force

echo "Checking Passport keys..."
if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    echo "Generating Passport keys..."
    php artisan passport:keys --force
    php artisan passport:install --uuids --force
else
    echo "Passport keys already exist."
fi

echo "Generating Swagger documentation..."
php artisan l5-swagger:generate

echo "Starting Laravel application..."
exec "$@"