#!/bin/sh

set -e

echo "🚀 Starting Qribly..."

if [ ! -f .env ]; then
    echo "📄 Creating .env from .env.example..."
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction
fi

if ! grep -q "^APP_KEY=base64:" .env; then
    echo "🔑 Generating APP_KEY..."
    php artisan key:generate --force
fi

if [ "$APP_ROLE" = "backend" ]; then
    echo "🗄 Running migrations..."
    php artisan migrate --force

    echo "🌱 Seeding database..."
    php artisan db:seed --force
fi

exec "$@"