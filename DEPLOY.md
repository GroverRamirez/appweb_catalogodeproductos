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

El `entrypoint.sh` se encarga automáticamente de:
cachear config/rutas/vistas/eventos, correr `migrate --force` y `storage:link`.

### Sembrar datos iniciales (solo la primera vez)

```bash
docker compose exec app php artisan db:seed --force
```

Crea roles/permisos, settings, categorías, marcas y productos demo. **En una
tienda real**, en vez del seeder de demo siembra solo lo imprescindible:

```bash
docker compose exec app php artisan db:seed --class=RoleSeeder --force
docker compose exec app php artisan db:seed --class=SettingsSeeder --force
```

Luego crea tu usuario admin (Tinker):

```bash
docker compose exec app php artisan tinker
>>> $u = App\Models\User::create(['name'=>'Admin','email'=>'admin@tudominio.com','password'=>Hash::make('CAMBIA_ESTO')]);
>>> $u->assignRole('admin');
```

---

## 4. HTTPS

El contenedor sirve HTTP en el puerto 80. Para HTTPS, ponlo detrás de un
reverse proxy con TLS (recomendado): **Caddy**, **Traefik** o **Nginx + certbot**.
Ejemplo mínimo con Caddy en el host:

```
tu-dominio.com {
    reverse_proxy localhost:80
}
```

Con `APP_URL=https://...` y `URL::forceScheme('https')` (ya activo en
producción), Laravel genera todas las URLs en https.

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
- [ ] Usuario admin creado y login funciona
- [ ] Healthcheck en verde (`docker compose ps` → `healthy`)
- [ ] `GET /up` responde 200
- [ ] El catálogo público carga con productos e imágenes
- [ ] Workers de cola activos (`docker compose exec app supervisorctl status`)
- [ ] Backup manual probado y restaurable (ver §6)
- [ ] Reverse proxy con TLS en marcha (candado en el navegador)
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
