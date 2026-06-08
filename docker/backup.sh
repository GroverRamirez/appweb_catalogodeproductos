#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Backup de base de datos + archivos subidos.
#
# Genera:
#   - storage/backups/db_<db>_<timestamp>.sql.gz       (volcado MySQL)
#   - storage/backups/storage_<timestamp>.tar.gz        (storage/app/public)
#
# Rota los backups con más de BACKUP_RETENTION_DAYS días (por defecto 14).
#
# Uso:
#   docker compose exec app /usr/local/bin/backup.sh        # manual
#   (también se ejecuta a diario vía el scheduler de Laravel — ver routes/console.php)
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/www/html/storage/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

mkdir -p "$BACKUP_DIR"

# ── Base de datos ──────────────────────────────────────────────────────────────
DB_FILE="$BACKUP_DIR/db_${DB_DATABASE}_${TIMESTAMP}.sql.gz"
echo "[backup] Volcando base de datos '${DB_DATABASE}'..."
mysqldump \
    --host="${DB_HOST:-mysql}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME}" \
    --password="${DB_PASSWORD}" \
    --single-transaction --quick --no-tablespaces \
    "${DB_DATABASE}" | gzip > "$DB_FILE"
echo "[backup]   -> ${DB_FILE} ($(du -h "$DB_FILE" | cut -f1))"

# ── Archivos subidos (storage/app/public) ──────────────────────────────────────
STORAGE_FILE="$BACKUP_DIR/storage_${TIMESTAMP}.tar.gz"
echo "[backup] Empaquetando archivos subidos..."
tar -czf "$STORAGE_FILE" -C /var/www/html/storage/app public 2>/dev/null || true
echo "[backup]   -> ${STORAGE_FILE} ($(du -h "$STORAGE_FILE" | cut -f1))"

# ── Rotación ────────────────────────────────────────────────────────────────────
echo "[backup] Eliminando backups con más de ${RETENTION_DAYS} días..."
find "$BACKUP_DIR" -type f -name '*.gz' -mtime "+${RETENTION_DAYS}" -delete

echo "[backup] Completado: $(ls -1 "$BACKUP_DIR" | wc -l) archivos en ${BACKUP_DIR}."
