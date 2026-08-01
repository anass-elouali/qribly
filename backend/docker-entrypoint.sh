#!/bin/sh

set -e

echo "🚀 Starting Qribly backend..."

# Create .env if it doesn't exist
if [ ! -f .env ]; then
    echo "📄 Creating .env from .env.example..."
    cp .env.example .env
fi

# Install Composer dependencies
if [ ! -d vendor ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction
fi

# Generate application key if missing
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "🔑 Generating APP_KEY..."
    php artisan key:generate --force
fi

# # Wait for PostgreSQL
# echo "⏳ Waiting for PostgreSQL..."

# until php -r "
# try {
#     new PDO(
#         'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
#         getenv('DB_USERNAME'),
#         getenv('DB_PASSWORD')
#     );
#     exit(0);
# } catch (Exception \$e) {
#     exit(1);
# }
# "; do
#     sleep 2
# done

echo "✅ PostgreSQL is ready."

# Run migrations
echo "🗄 Running migrations..."
php artisan migrate --force

echo "🌐 Starting Laravel..."
exec php artisan serve --host=0.0.0.0 --port=8000
