#!/bin/sh
set -eu

attempt=0
until php -r '
$dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));
new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
' >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Database did not become ready after 30 attempts" >&2
        exit 1
    fi
    sleep 1
done

php artisan migrate --force
php artisan config:cache

exec "$@"
