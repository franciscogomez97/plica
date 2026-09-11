#!/bin/bash
# Despliega Plica en el VPS desde el Mac: assets compilados aquí, código por rsync,
# dependencias y cachés en el servidor. Uso: deploy/desplegar.sh [alias-ssh] [ruta]
set -euo pipefail

SERVIDOR="${1:-plica}"
RUTA="${2:-/var/www/plica}"
cd "$(dirname "$0")/.."

echo "→ Tests"
php artisan test --compact

echo "→ Assets"
npm run build --silent

echo "→ Código a $SERVIDOR:$RUTA"
rsync -az --delete \
  --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
  --exclude '.env' --exclude '.env.backup' --exclude '.env.production' \
  --exclude 'storage/app/**' --exclude 'storage/logs/**' --exclude 'storage/framework/**' --exclude 'storage/*.key' \
  --exclude 'database/database.sqlite' --exclude 'public/storage' --exclude 'public/hot' \
  --exclude '.phpunit.cache' --exclude '.phpunit.result.cache' --exclude '.DS_Store' \
  --exclude '.vscode' --exclude '.idea' --exclude 'tests' \
  ./ "$SERVIDOR:$RUTA/"

echo "→ Dependencias, migraciones y cachés en el servidor"
ssh "$SERVIDOR" bash -s "$RUTA" <<'REMOTO'
set -euo pipefail
RUTA="$1"; cd "$RUTA"
export COMPOSER_ALLOW_SUPERUSER=1
mkdir -p storage/app/public storage/app/private storage/logs \
         storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
php artisan down --retry=5 >/dev/null 2>&1 || true
composer deploy --no-interaction --quiet
php artisan storage:link --no-interaction --quiet || true
php artisan up >/dev/null
chown -R root:root "$RUTA"
chown -R www-data:www-data storage bootstrap/cache
chown root:www-data .env && chmod 640 .env
chmod -R u=rwX,go=rX "$RUTA"; chmod -R ug+rwX storage bootstrap/cache
systemctl reload php8.4-fpm
php artisan about --only=environment | grep -E "Environment|Debug|URL"
REMOTO

echo "→ Comprobación"
URL=$(ssh "$SERVIDOR" "sed -n 's|^APP_URL=||p' $RUTA/.env")
for u in / /app/login /manifest.webmanifest; do
  printf '   %-24s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "$URL$u")"
done
echo "✓ Desplegado"
