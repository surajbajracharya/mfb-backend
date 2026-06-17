#!/bin/sh
set -e

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
      echo "${var}=${val}" >> .env
    fi
  done
}

generate_env

# Clear caches (non-fatal)
php artisan config:clear || true
php artisan cache:clear  || true
php artisan route:clear  || true
php artisan view:clear   || true

php artisan storage:link --force 2>/dev/null || true

# Rebuild caches (non-fatal)
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# Wait up to 60s for DB to be reachable before migrating
echo "Waiting for database..."
DB_READY=0
DB_HOST_VAL=$(printenv DB_HOST 2>/dev/null || echo "")
DB_PORT_VAL=$(printenv DB_PORT 2>/dev/null || echo "3306")
for i in $(seq 1 30); do
  if php -r "
    \$c = @fsockopen('${DB_HOST_VAL}', ${DB_PORT_VAL}, \$e, \$m, 2);
    if (\$c) { fclose(\$c); exit(0); }
    exit(1);
  " 2>/dev/null; then
    DB_READY=1
    echo "Database is ready."
    break
  fi
  echo "  attempt $i/30 - not ready yet, retrying in 2s..."
  sleep 2
done

if [ "$DB_READY" = "1" ]; then
  php artisan migrate --force || echo "WARNING: Migration failed - app will start but DB may not be initialized."
else
  echo "WARNING: Database unreachable after 60s - skipping migration. Check DB_HOST/DB_PORT in Coolify env vars."
fi

mkdir -p /var/log/supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
