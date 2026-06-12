# Roadmap de desarrollo

Lista de tareas pendientes. Al trabajar con Claude Code, pídele que tome la
siguiente tarea sin marcar de la sección de mayor prioridad, y marca `[x]`
cuando esté hecha y verificada (tests + revisión en navegador).

## 1. Ajustes inmediatos

- [x] Configurar `APP_NAME` en `.env` y `.env.example` (hoy el título dice "Laravel")
- [ ] Cargar identidad real en Admin → Configuración: nombre, logo, favicon, WhatsApp, email de contacto
- [ ] Reemplazar datos demo por el catálogo real (usar import CSV en Admin → Productos)
- [x] Probar flujo completo como cliente: buscar → carrito → cupón → checkout → consulta visible en panel y email enviado

## 2. Puesta en producción (ver DEPLOY.md para el detalle)

- [ ] VPS/hosting con Docker, dominio y DNS
- [ ] `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, SMTP real, Redis, contraseñas fuertes
- [ ] Reverse proxy con TLS, workers de cola activos, healthcheck `/up` en verde
- [ ] Backup manual probado y copiado fuera del servidor
- [ ] Usuario propietario real creado, login + 2FA verificados en producción

## 3. Funcionalidades siguientes

- [x] Gestión de consultas más rica: notas internas, historial de contacto, aviso al staff de consulta nueva
- [ ] Variantes de producto (tallas, colores, presentaciones) — evaluar si el rubro lo necesita
- [x] Favoritos/wishlist en el catálogo público (productos relacionados ya existe en el detalle)
- [ ] Búsqueda con Laravel Scout + Meilisearch (solo si el catálogo supera ~miles de productos)
- [ ] DECISIÓN: cuentas de cliente (el registro público está deshabilitado a propósito)
- [ ] DECISIÓN: pagos online (pasarela, stock transaccional, términos legales) — el mayor salto de alcance

## 4. Calidad y operación continua

- [ ] Tests E2E (Playwright o Dusk): checkout, login con 2FA, CRUD de producto con imagen
- [ ] Monitoreo de errores en producción (Sentry o similar) + alerta de uptime
- [ ] `composer audit` y `npm audit` en el CI existente (`.github/workflows/`)
- [ ] Auditoría Lighthouse del catálogo público con contenido real
