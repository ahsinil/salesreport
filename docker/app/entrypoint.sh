#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

mkdir -p \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

if [ -f .env ] && ! grep -Eq '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
    until php -r '
        $host = getenv("DB_HOST") ?: "db";
        $port = getenv("DB_PORT") ?: "5432";
        $database = getenv("DB_DATABASE") ?: "salesreport";
        $username = getenv("DB_USERNAME") ?: "salesreport";
        $password = getenv("DB_PASSWORD") ?: "secret";

        try {
            new PDO(
                "pgsql:host={$host};port={$port};dbname={$database}",
                $username,
                $password,
                [PDO::ATTR_TIMEOUT => 5],
            );
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage().PHP_EOL);
            exit(1);
        }
    '; do
        echo "Waiting for PostgreSQL..."
        sleep 2
    done
fi

exec "$@"
