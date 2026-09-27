#!/bin/sh
set -e

PORT="${PORT:-80}"
export APACHE_PORT="$PORT"

# Update Apache port to match the port assigned by Render ($PORT)
sed -i "s/Listen [0-9]*/Listen ${PORT}/" /etc/apache2/ports.conf

# â”€â”€ Startup sanity check â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
# If DB_HOST is still the localhost default (or unset), the app CANNOT connect
# to any real database. Print a loud warning so it's visible in Render's
# deploy logs immediately, before any HTTP request is served.
DB_HOST_VAL="${DB_HOST:-127.0.0.1}"
DATABASE_URL_VAL="${DATABASE_URL:-}"

if [ -z "$DATABASE_URL_VAL" ] && [ "$DB_HOST_VAL" = "127.0.0.1" ]; then
    echo "============================================================"
    echo " WARNING: DB credentials are NOT configured!"
    echo " DB_HOST is still 127.0.0.1 (the localhost default)."
    echo " Set DB_HOST, DB_USER, DB_PASS, DB_NAME (and optionally"
    echo " DATABASE_URL) in the Render dashboard â†’ Environment tab."
    echo " APP_URL must also be set to https://baranggabay.onrender.com"
    echo "============================================================"
fi

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
