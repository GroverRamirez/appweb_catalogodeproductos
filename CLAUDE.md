# Catálogo de Productos — contexto para Claude Code

Catálogo público de productos con carrito de consulta (sin pagos online): el
cliente arma su pedido y lo envía como consulta; el staff lo atiende por
WhatsApp/email. Panel admin con roles y permisos granulares.

## Stack y convenciones técnicas

Ver `AGENTS.md` (guías de Laravel Boost: versiones exactas, convenciones de
código, skills). Resumen: Laravel 13 + Inertia v3 + Vue 3 + Tailwind 4,
Fortify (login, passkeys; registro público DESHABILITADO a propósito),
Spatie Permission, Pest v4.

## Documentos clave

- `TAREAS.md` — roadmap de desarrollo; tomar la siguiente tarea sin marcar
- `SETUP_CATALOGO.md` — setup local e historial de iteraciones
- `DEPLOY.md` — despliegue a producción, checklist go-live, backups

## Comandos

```bash
php artisan test --compact   # suite completa (Pest), debe quedar en verde
npm run build                # build de producción (Vite)
npx eslint <archivo>         # lint frontend
vendor/bin/pint --dirty      # formatear PHP modificado
```

Servidor local del usuario: `php artisan serve` en el puerto 8000 (suele estar
corriendo). Para previews usar otro puerto (`.claude/launch.json` usa 8010).

## Reglas del proyecto

- Commits en español, sin acentos, estilo `feat(ambito): descripcion` (ver `git log`).
- Comentarios de código en español.
- Roles del sistema: `propietario` (super-admin), `encargado`, `vendedor`, `cliente`.
  El acceso al panel se decide por permisos (`App\Support\AdminGuard`), cada ruta
  admin exige su permiso en `routes/admin.php`.
- Precios y totales SIEMPRE se calculan en el servidor desde la BD, nunca se
  confía en montos del cliente.
- Imágenes subidas siempre pasan por `App\Services\ImageProcessor` (WebP, sin EXIF).
- En `<script setup>` de archivos .vue nunca escribir la secuencia literal de
  cierre de script en comentarios (rompe el parser SFC).
- Inertia `<Head>` serializa props como atributos HTML: contenido de tags
  (p. ej. JSON-LD) va como hijo de texto, no como `:innerHTML`.
