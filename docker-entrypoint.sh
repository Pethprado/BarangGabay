#!/bin/sh
set -e

PORT="${PORT:-80}"
export APACHE_PORT="$PORT"

# Update Apache port to match the port assigned by Render ($PORT)
sed -i "s/Listen [0-9]*/Listen ${PORT}/" /etc/apache2/ports.conf

# Ensure required upload and storage directories exist
mkdir -p /var/www/html/public/uploads/announcements \
         /var/www/html/public/uploads/avatars \
         /var/www/html/public/uploads/branding \
         /var/www/html/public/uploads/events \
         /var/www/html/public/uploads/id-photos \
         /var/www/html/public/uploads/manobo-audio \
         /var/www/html/public/uploads/ordinances \
         /var/www/html/public/uploads/voice \
         /var/www/html/storage/logs \
         /var/www/html/storage/backups

# Set write permissions for web server
chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/storage
chmod -R 775 /var/www/html/public/uploads /var/www/html/storage

exec "$@"
