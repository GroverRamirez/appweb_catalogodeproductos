# Despliegue a producción — Catálogo de Productos

Guía y checklist para poner el catálogo en producción con Docker.

Stack del contenedor: Nginx + PHP-FPM 8.3 (Alpine) + Supervisor (php-fpm, nginx,
2 workers de cola, scheduler) · MySQL 8 · Redis 7.

---

## 1. Requisitos del servidor

- Docker Engine 24+ y Docker Compose v2
- Un dominio apuntando al servidor (registro A)
- Puertos 80/443 abiertos (443 si pones HTTPS con un reverse proxy)

---

## 2. Configurar variables de entorno

```bash
cp .env.production.example .env
php artisan key:generate --show   # copia el valor a APP_KEY del .env (o genéralo dentro del contenedor)
```

Edita `.env` y revisa **como mínimo**:

| Variable | Valor |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://tu-dominio.com` |
| `APP_KEY` | generado (no vacío) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | credenciales propias |
| `DB_ROOT_PASSWORD` | contraseña root fuerte |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | `redis` |
| `MAIL_*` | SMTP real (si quieres recibir los emails de consultas) |

> Sin SMTP configurado, los emails de nuevas consultas se encolan pero no se
> envían. El driver por defecto en local es `log`.

---

## 3. Construir y levantar

```bash
docker compose build
docker compose up -d
```

Construye siempre desde un clon limpio del repositorio en el servidor, no
desde la carpeta de desarrollo. El build necesita internet: instala las
dependencias y baja la fuente desde fonts.bunny.net.

El `entrypoint.sh` se encarga automáticamente de:
cachear config/rutas/vistas/eventos, correr `migrate --force` y `storage:link`.

### Primera instalación: roles, configuración y propietario

```bash
docker compose exec -u www-data app php artisan app:instalar
```

Carga los roles con sus permisos y la configuración inicial, y pregunta los
datos del propietario (nombre, correo, contraseña), el WhatsApp de la tienda
y el correo donde llegan los avisos de consultas. La contraseña no se ve al
escribirla ni queda en el historial. Es seguro repetirlo: si ya hay datos,
solo aplica las migraciones pendientes y no pisa lo que el propietario
cambió. Se corre como `www-data` para que los archivos que deja en
`storage` y `bootstrap/cache` sean del mismo usuario que PHP-FPM.

> **No uses `db:seed` en producción.** El seeder completo es para
> desarrollo: carga productos de ejemplo y usuarios de demostración con la
> contraseña `password` (en producción `AdminUserSeeder` se salta solo).

Después, en **Configuración**, revisa el nombre, la dirección, el logo y los
datos de contacto antes de abrir la tienda.

---

## 4. HTTPS

El contenedor sirve HTTP en el puerto 80. Para HTTPS, ponlo detrás de un
reverse proxy con TLS (recomendado): **Caddy**, **Traefik** o **Nginx + certbot**.
Ejemplo mínimo con Caddy en el host:

```
tu-dominio.com {
    reverse_proxy localhost:8080
}
```

Con `APP_PORT=127.0.0.1:8080` el contenedor solo escucha en el host y el
proxy se queda con los puertos 80 y 443 (los necesita para el certificado).

Con `APP_URL=https://...` y `URL::forceScheme('https')` (ya activo en
producción), Laravel genera todas las URLs en https.

**Proxies de confianza.** `TRUSTED_PROXIES` (ver `config/trustedproxy.php`)
le dice a Laravel que crea en las cabeceras `X-Forwarded-*` del proxy. Sin
ella, todos los visitantes comparten la IP del proxy (los límites de login y
checkout se vuelven uno solo para todos), no se envía HSTS y la página de
gracias del checkout, que usa una URL firmada, no valida. Con el proxy en el
host y Docker, `TRUSTED_PROXIES=172.16.0.0/12`; usa `*` solo si el puerto de
la app no es accesible desde internet. Agrega también
`SESSION_SECURE_COOKIE=true`.

---

## 5. Checklist de go-live

- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] `APP_KEY` generado (no vacío)
- [ ] `APP_URL` con el dominio real y HTTPS
- [ ] Contraseñas fuertes en `DB_PASSWORD` y `DB_ROOT_PASSWORD`
- [ ] `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` en `redis`
- [ ] SMTP real configurado y probado (consulta de prueba → llega el email)
- [ ] Migraciones aplicadas (`docker compose exec app php artisan migrate:status`)
- [ ] `storage:link` hecho (las imágenes de productos cargan en `/storage/...`)
- [ ] Propietario creado con `app:instalar` y login funciona
- [ ] WhatsApp, correo de contacto y dirección reales en Configuración (una consulta de prueba abre WhatsApp y llega el email)
- [ ] `APP_TIMEZONE=America/La_Paz` (el "hoy" del panel y la hora de las tareas programadas)
- [ ] Healthcheck en verde (`docker compose ps` → `healthy`)
- [ ] `GET /up` responde 200
- [ ] El catálogo público carga con productos e imágenes
- [ ] Workers de cola activos (`docker compose exec app supervisorctl status`)
- [ ] Backup manual probado y restaurable (ver §6)
- [ ] Reverse proxy con TLS en marcha (candado en el navegador)
- [ ] `TRUSTED_PROXIES` y `SESSION_SECURE_COOKIE=true` configurados (la respuesta trae `Strict-Transport-Security`)
- [ ] DNS apuntando correctamente

### Verificación rápida post-deploy

```bash
docker compose ps                                   # todos healthy
curl -sf https://tu-dominio.com/up && echo OK       # healthcheck
docker compose exec app supervisorctl status        # php-fpm, nginx, workers, scheduler
docker compose logs -f app                          # revisar errores de arranque
```

---

## 6. Backups y restauración

Backups automáticos **diarios a las 03:00** (BD + archivos subidos), vía el
scheduler. Se guardan en el volumen `storage_backups`
(`storage/backups/` dentro del contenedor) y se rotan a los 14 días
(`BACKUP_RETENTION_DAYS`).

### Backup manual

```bash
docker compose exec app /usr/local/bin/backup.sh
docker compose exec app ls -lh storage/backups
```

### Backup desde el panel

El propietario (o un rol con el permiso `backups.manage`) puede generar,
descargar y eliminar copias de la base en **Configuración → Copias de
seguridad** (`/admin/settings/backups`). Usa `mysqldump` y deja el archivo en
la misma carpeta privada `storage/backups`, con el mismo formato
`db_<base>_<fecha>.sql.gz`, así que se restaura igual que abajo. Restaurar desde
la web no está permitido a propósito.

En Laragon (Windows) `mysqldump.exe` se detecta solo; si no, indicar la ruta en
`.env` con `MYSQLDUMP_PATH`.

### Copiar los backups fuera del servidor

```bash
docker compose cp app:/var/www/html/storage/backups ./backups-$(date +%F)
```

> Recomendado: sincronizar `storage/backups` a almacenamiento externo
> (S3, otro servidor) con un cron del host.

### Restaurar la base de datos

```bash
# Descomprime y carga el dump elegido
gunzip < db_catalogo_AAAAMMDD_HHMMSS.sql.gz \
  | docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"
```

### Restaurar archivos subidos

```bash
docker compose exec -T app tar -xzf - -C storage/app < storage_AAAAMMDD_HHMMSS.tar.gz
```

---

## 7. Operación

```bash
# Actualizar a una versión nueva
git pull
docker compose build
docker compose up -d            # el entrypoint re-cachea y migra

# Reiniciar solo la app
docker compose restart app

# Ver colas / reintentar fallidos
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all

# Limpiar caché de la aplicación (settings, etc.)
docker compose exec app php artisan cache:clear
```

---

## 8. Notas de seguridad (ya incluidas)

- Cabeceras de seguridad + CSP con nonce (`AddSecurityHeaders`)
- Panel admin con `noindex` y protegido por rol/permiso (Spatie)
- Rutas públicas con throttling; URL de "gracias" firmada por token
- `APP_DEBUG=false` oculta trazas; logs a `stderr` (visibles con `docker compose logs`)
- Reglas de contraseña fuerte en producción (12+, mixta, no comprometida)
