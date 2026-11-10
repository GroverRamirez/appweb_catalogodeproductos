#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/html"

log() { echo "[entrypoint] $*"; }

# ── Storage directories ────────────────────────────────────────────────────────
log "Ensuring storage directories..."
mkdir -p \
    "$APP_DIR/storage/app/public" \
    "$APP_DIR/storage/logs" \
    "$APP_DIR/storage/framework/cache/data" \
    "$APP_DIR/storage/framework/sessions" \
    "$APP_DIR/storage/framework/views" \
    "$APP_DIR/bootstrap/cache"

chown -R www-data:www-data \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"

chmod -R 775 \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"

# ── Wait for MySQL ─────────────────────────────────────────────────────────────
if [[ -n "${DB_HOST:-}" ]]; then
    log "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    MAX_TRIES=30
    COUNT=0
    # Con PDO (pdo_mysql), que es la extension que trae la imagen: mysqli no
    # esta instalada y con ella la espera fallaba siempre. Los datos se leen
    # con getenv para no meter la contrasena dentro del codigo PHP.
    until php -r '
        try {
            new PDO(
                "mysql:host=" . getenv("DB_HOST") . ";port=" . (getenv("DB_PORT") ?: "3306"),
                getenv("DB_USERNAME") ?: "root",
                getenv("DB_PASSWORD") ?: "",
                [PDO::ATTR_TIMEOUT => 2]
            );
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    ' 2>/dev/null; do
        COUNT=$((COUNT+1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            log "ERROR: MySQL not available after ${MAX_TRIES} attempts. Aborting."
            exit 1
        fi
        log "  attempt $COUNT/$MAX_TRIES — retrying in 2s..."
        sleep 2
    done
    log "MySQL is ready."
fi

# ── Wait for Redis (optional) ──────────────────────────────────────────────────
if [[ -n "${REDIS_HOST:-}" ]]; then
    log "Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT:-6379}..."
    MAX_TRIES=15
    COUNT=0
    until php -r "
        \$s = @fsockopen('${REDIS_HOST}', ${REDIS_PORT:-6379}, \$e, \$m, 2);
        exit(\$s ? 0 : 1);
    " 2>/dev/null; do
        COUNT=$((COUNT+1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            log "WARN: Redis not available after ${MAX_TRIES} attempts — continuing anyway."
            break
        fi
        sleep 2
    done
    log "Redis is ready."
fi

# ── Laravel bootstrap ──────────────────────────────────────────────────────────
cd "$APP_DIR"

log "Caching configuration..."
php artisan config:cache

log "Caching routes..."
php artisan route:cache

log "Caching views..."
php artisan view:cache

log "Caching events..."
php artisan event:cache

log "Running migrations..."
php artisan migrate --force --no-interaction

log "Linking storage..."
php artisan storage:link --no-interaction || true   # idempotent

# ── Hand off to supervisord ────────────────────────────────────────────────────
log "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
