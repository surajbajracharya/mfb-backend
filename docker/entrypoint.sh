#!/bin/sh

cd /var/www/html

# Generate .env from environment variables injected by Coolify.
generate_env() {
  > .env
  for var in \
    APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL APP_TIMEZONE \
    STORAGE_URL FRONTEND_URL ADMIN_URL \
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    CACHE_STORE CACHE_DRIVER QUEUE_CONNECTION SESSION_DRIVER SESSION_LIFETIME \
    SANCTUM_STATEFUL_DOMAINS \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION MAIL_FROM_ADDRESS MAIL_FROM_NAME \
    STRIPE_KEY STRIPE_SECRET STRIPE_WEBHOOK_SECRET \
    GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET GOOGLE_REDIRECT_URI \
    FILESYSTEM_DISK LOG_CHANNEL LOG_LEVEL \
  ; do
    val=$(printenv "$var" 2>/dev/null || true)
    if [ -n "$val" ]; then
      echo "${var}=\"${val}\"" >> .env
    fi
  done
}

generate_env

# Auto-derive STORAGE_URL from APP_URL if not explicitly set
if [ -z "$(printenv STORAGE_URL 2>/dev/null)" ] && [ -n "$(printenv APP_URL 2>/dev/null)" ]; then
  echo "STORAGE_URL=\"$(printenv APP_URL)/storage\"" >> .env
fi

php artisan config:clear  2>/dev/null || true
php artisan cache:clear   2>/dev/null || true
php artisan route:clear   2>/dev/null || true
php artisan view:clear    2>/dev/null || true

php artisan storage:link --force 2>/dev/null || true

# Seed uploads into the volume on first deploy (volume starts empty)
if [ ! -f /var/www/html/storage/app/public/.seeded ]; then
  cp -r /var/www/html/storage-seed/. /var/www/html/storage/app/public/ 2>/dev/null || true
  touch /var/www/html/storage/app/public/.seeded
fi

php artisan config:cache  2>/dev/null || true
php artisan route:cache   2>/dev/null || true
php artisan view:cache    2>/dev/null || true

php artisan migrate --force 2>/dev/null || true

mkdir -p /var/log/supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
