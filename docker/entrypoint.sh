#!/bin/sh
set -e

cd /var/www/html

# Write production env defaults — any value already set in the environment
# (via Coolify) wins; these are fallbacks so the live server always works correctly.
write_env() {
  KEY=$1
  DEFAULT=$2
  CURRENT=$(grep -E "^${KEY}=" .env 2>/dev/null | cut -d= -f2- | tr -d '"' || true)
  ENV_VAL=$(printenv "$KEY" || true)
  # If the OS env var is set (from Coolify), write it to .env so artisan picks it up
  if [ -n "$ENV_VAL" ]; then
    if grep -qE "^${KEY}=" .env 2>/dev/null; then
      sed -i "s|^${KEY}=.*|${KEY}=${ENV_VAL}|" .env
    else
      echo "${KEY}=${ENV_VAL}" >> .env
    fi
  # If not set anywhere, write the default
  elif [ -z "$CURRENT" ]; then
    echo "${KEY}=${DEFAULT}" >> .env
  fi
}

write_env APP_URL          "https://api.meditationforbeginners.com"
write_env STORAGE_URL      "https://api.meditationforbeginners.com/storage"
write_env APP_ENV          "production"
write_env APP_TIMEZONE     "Australia/Sydney"
write_env FRONTEND_URL     "https://meditationforbeginners.com"
write_env ADMIN_URL        "https://back.meditationforbeginners.com"

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan storage:link --force 2>/dev/null || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force

mkdir -p /var/log/supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
