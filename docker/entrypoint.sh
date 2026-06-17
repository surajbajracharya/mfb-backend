#!/bin/sh
set -e

cd /var/www/html

# Generate .env from environment variables injected by Coolify.
# Each variable is written only if it is actually set in the OS environment,
# so nothing is ever hardcoded here — Coolify is the single source of truth.
generate_env() {
  > .env
  for var in \
    APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL APP_TIMEZONE \
    STORAGE_URL FRONTEND_URL ADMIN_URL \
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    CACHE_STORE QUEUE_CONNECTION SESSION_DRIVER SESSION_LIFETIME \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION MAIL_FROM_ADDRESS MAIL_FROM_NAME \
    STRIPE_KEY STRIPE_SECRET STRIPE_WEBHOOK_SECRET \
    GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET GOOGLE_REDIRECT_URI \
    FILESYSTEM_DISK LOG_CHANNEL LOG_LEVEL \
  ; do
    val=$(printenv "$var" 2>/dev/null || true)
    if [ -n "$val" ]; then
      echo "${var}=${val}" >> .env
    fi
  done
}

generate_env

# Clear caches (non-fatal — continue even if these fail)
php artisan config:clear  || true
php artisan cache:clear   || true
php artisan route:clear   || true
php artisan view:clear    || true

php artisan storage:link --force 2>/dev/null || true

# Rebuild caches (non-fatal — app still works without cached config)
php artisan config:cache  || true
php artisan route:cache   || true
php artisan view:cache    || true

# Run migrations (fatal if DB is unreachable — container should restart)
php artisan migrate --force

mkdir -p /var/log/supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
