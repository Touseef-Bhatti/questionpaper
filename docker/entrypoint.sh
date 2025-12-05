#!/bin/sh
set -e

# Ensure permissions for mounted project files (best-effort)
if [ "$(id -u)" = '0' ]; then
  # change ownership to www-data so Apache/PHP can write when needed
  chown -R www-data:www-data /var/www/html || true
fi

# If the app doesn't have a .env but we ship .env.example, copy it (non-destructive)
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
  cp /var/www/html/.env.example /var/www/html/.env || true
  chown www-data:www-data /var/www/html/.env || true
fi

exec "$@"
