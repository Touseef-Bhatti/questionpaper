#!/bin/sh
set -e
if [ "$(id -u)" = '0' ]; then
  chown -R www-data:www-data /var/www/html || true
fi
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
  cp /var/www/html/.env.example /var/www/html/.env || true
  chown www-data:www-data /var/www/html/.env || true
fi
exec "$@"
