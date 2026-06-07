# Setup — Catálogo de Productos

Laravel 13 + Inertia + Vue 3 + MySQL + Tailwind + Spatie Permission.
Iteración 1: base de datos · Iteración 2: panel admin completo.
Pendiente iteración 3: catálogo público + reportes avanzados.

---

## 1. Instalar dependencias

```bash
composer require spatie/laravel-permission:^7.4
```

> v7 es la que soporta Laravel 13 (la v6 sólo llega hasta Laravel 11).

## 2. Configurar `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=catalogodeproductos
DB_USERNAME=root
DB_PASSWORD=
```

Crea la base de datos si no existe:

```sql
CREATE DATABASE catalogodeproductos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 3. Migrar, sembrar, link de storage y assets

```bash
php artisan migrate:fresh --seed
php artisan storage:link
npm run dev          # o npm run build
```

`storage:link` es necesario para que las imágenes de productos subidas por el admin (en `storage/app/public/products/...`) se vean en `/storage/...`.

## 4. Usuarios demo

| Rol      | Email                    | Password   |
| -------- | ------------------------ | ---------- |
| admin    | admin@catalogo.test      | `password` |
| vendedor | vendedor@catalogo.test   | `password` |

El registro público asigna automáticamente el rol `cliente`.

## 5. URLs

| URL                          | Descripción                                              |
| ---------------------------- | -------------------------------------------------------- |
| `/`                          | Landing (catálogo público pendiente, iter 3)             |
| `/login` · `/register`       | Fortify                                                  |
| `/admin`                     | Dashboard con KPIs                                       |
| `/admin/products`            | Listado con búsqueda, filtros (categoría/marca/estado/stock bajo), paginación |
| `/admin/products/create`     | Alta de producto con imágenes (drag, hasta 8) y atributos |
| `/admin/products/{id}/edit`  | Edición; permite quitar imágenes existentes              |
| `/admin/categories`          | CRUD con padre/hijo, orden, activo                       |
| `/admin/brands`              | CRUD                                                     |
| `/admin/inquiries`           | Listado de consultas con filtro por estado               |
| `/admin/inquiries/{id}`      | Detalle: items, mensaje, botón WhatsApp, cambio de estado y notas |
| `/admin/settings`            | Editar `whatsapp_number`, moneda, etc. (sólo admin)      |
| `/settings/profile`          | Perfil del usuario logueado                              |

## 6. Roles y permisos (Spatie)

- **admin** — acceso total
- **vendedor** — ver/actualizar productos, gestionar consultas, ajustar inventario, ver reportes
- **cliente** — sólo navega catálogo público

Permisos: `categories.*`, `brands.*`, `products.*`, `inquiries.*`, `inventory.*`, `users.*`, `settings.*`, `reports.view`.

Las rutas admin están protegidas con `role:admin|vendedor`. Los Form Requests validan permisos con `$this->user()->can('products.update')`.

## 7. Datos sembrados

- 5 categorías raíz con subcategorías
- 15 marcas
- 60 productos con 3 imágenes (de `picsum.photos`) y 2 atributos
- 12 settings (WhatsApp, moneda, plantilla de mensaje, etc.)

## 8. Archivos creados/modificados — iteración 2 (panel admin)

**Controllers** (`app/Http/Controllers/Admin/`)
`DashboardController`, `CategoryController`, `BrandController`, `ProductController`, `InquiryController`, `SettingsController`

**Form Requests** (`app/Http/Requests/Admin/`)
`StoreCategoryRequest`, `UpdateCategoryRequest`, `StoreBrandRequest`, `UpdateBrandRequest`, `StoreProductRequest`, `UpdateProductRequest`

**Rutas**
`routes/admin.php` con grupo `prefix('admin') name('admin.') middleware('role:admin|vendedor')` + resource routes.

**Páginas Vue** (`resources/js/pages/admin/`)
- `Dashboard.vue` (KPIs, top productos, stock bajo, últimas consultas)
- `categories/Index.vue` + `categories/Form.vue`
- `brands/Index.vue` + `brands/Form.vue`
- `products/Index.vue` (con filtros, búsqueda, thumbnails)
- `products/Form.vue` (datos, atributos repetibles, upload de imágenes, eliminación selectiva)
- `inquiries/Index.vue` + `inquiries/Show.vue` (con botón directo a WhatsApp)
- `settings/Edit.vue` (agrupado por sección)

**Componentes**
- `components/Pagination.vue` para los listados
- `components/admin/StatCard.vue` para las tarjetas del dashboard
- `components/AppSidebar.vue` actualizado con links del panel (oculta "Configuración" si no es admin)

## 9. Iteración 3 — Catálogo público (ya implementada)

Rutas:

| URL                          | Descripción                                                           |
| ---------------------------- | --------------------------------------------------------------------- |
| `/`                          | Home: hero, categorías, destacados, recién agregados                  |
| `/catalogo`                  | Listado con filtros (categoría, marca, precio, en stock), búsqueda, orden y paginación |
| `/catalogo/{slug}`           | Detalle: galería, descripción, características, botón WhatsApp y mini-form de consulta. Registra `ProductView` |
| `POST /consultas`            | Recibe consultas públicas (también dispara WhatsApp). Throttle 10/min |

Archivos:

**Controllers** (`app/Http/Controllers/Catalog/`)
`CatalogController.php` (home, index, show), `InquiryController.php` (store)

**Layout y helpers**
- `resources/js/layouts/PublicLayout.vue` — header con búsqueda, login/logout, footer con datos de contacto
- `resources/js/lib/catalog.ts` — `formatPrice`, `productMainImage`, `buildWhatsAppLink`, tipo `StoreSettings`

**Componentes**
- `components/catalog/ProductCard.vue` — tarjeta reutilizable con badge destacado/oferta/sin stock
- `components/catalog/Filters.vue` — sidebar de filtros con debounce

**Páginas Vue** (`resources/js/pages/catalog/`)
- `Home.vue`, `Index.vue`, `Show.vue`

**Cambios en compartidos**
- `HandleInertiaRequests` ahora expone `props.store` (nombre, whatsapp, plantilla mensaje, moneda, etc.) para todo el frontend
- `app.ts` deja que las páginas `catalog/*` usen su propio `PublicLayout`

### Cómo se conectan los settings

Edita `/admin/settings` para cambiar `whatsapp_number`, `whatsapp_message_template`, `currency_symbol`, `show_prices`, `show_stock`, etc. — los cambios aparecen inmediatamente en el catálogo público porque `Setting::get()` cachea por la app y `Setting::saved()` invalida el caché.

La plantilla de WhatsApp soporta `{producto}` y `{codigo}` como placeholders. Ejemplo:

```
Hola, me interesa el producto: {producto} (código {codigo}).
```

### Cómo se registran las visitas

Cuando un visitante abre `/catalogo/{slug}`, `CatalogController::show()` crea un registro en `product_views` con IP, user agent y referrer (sólo una vez por sesión por producto durante esa sesión) e incrementa `products.views_count`. El dashboard del admin usa esos datos para "más consultados" y la gráfica de visitas 7 días.

## 10. Iteración 4 — Mejoras del catálogo (ya implementada)

### Banners administrables

| URL                          | Descripción                                     |
| ---------------------------- | ----------------------------------------------- |
| `/admin/banners`             | Listado con thumbnail, orden, vigencia          |
| `/admin/banners/create`      | Crear: subir imagen o pasar URL externa         |
| `/admin/banners/{id}/edit`   | Editar banner                                   |

Migración: `2026_05_30_000001_create_banners_table.php` (título, subtítulo, imagen, link, CTA, orden, activo, `starts_at`, `ends_at`).

El controlador público (`CatalogController::home`) carga banners con scope `active()` que respeta las fechas y los pasa a `HeroCarousel.vue` (autoplay 6 s, navegación con flechas e indicadores, pausa al hover, transición fade).

Permisos nuevos en `RoleSeeder`: `banners.view`, `banners.create`, `banners.update`, `banners.delete` (solo admin por defecto).

`BannerSeeder` crea 3 banners demo de muestra con imágenes de `picsum.photos`.

### ProductCard mejorada

- Hover: tarjeta se eleva, imagen se hace zoom, aparece botón **Vista rápida**.
- Badges: `Destacado` (amarillo), `Nuevo` (productos < 30 días, celeste), `-X%` (descuento, rojo), `Sin stock` o `¡Últimas N!` cuando stock ≤ 5.
- **Modal de vista rápida** (`QuickViewModal.vue`) con imagen grande, precio, descripción corta, stock y botones "Consultar por WhatsApp" (registra `Inquiry` antes de abrir `wa.me`) y "Ver detalle completo".

### Vista grid / lista + ordenamientos extra

- Toggle de iconos en `/catalogo` cambia entre grid 4 cols y lista detallada. La preferencia se persiste en `localStorage`.
- `ProductRow.vue`: card horizontal con imagen 32–40 px, descripción corta, precio y stock.
- Ordenamientos nuevos: **Más visto** (usa `views_count`) y **Con descuento** (calcula `(1 - sale_price/price)` ordenado descendente).

## 11. Comandos para esta iteración

```bash
php artisan migrate                    # solo crea la tabla banners
php artisan db:seed --class=RoleSeeder # registra permisos banners.*
php artisan db:seed --class=BannerSeeder
php artisan cache:clear
```

O simplemente `php artisan migrate:fresh --seed` si no te importa perder los datos actuales (vuelven a sembrarse los 60 productos, 3 banners, etc.).

## 12. Iteración 5 — Usuarios, carrito y reportes (ya implementada)

### CRUD de usuarios (solo admin)

| URL                       | Descripción                                                |
| ------------------------- | ---------------------------------------------------------- |
| `/admin/users`            | Listado con búsqueda, filtro por rol, badges               |
| `/admin/users/create`     | Crear usuario; asigna rol al instante                      |
| `/admin/users/{id}/edit`  | Editar datos; password opcional (vacío = no cambia)        |

Protegido con `role:admin`. El usuario logueado no puede eliminarse a sí mismo. Email se marca verificado automáticamente al crearse desde admin.

### Carrito multi-producto

Frontend puro (localStorage, sin tabla extra):

- `resources/js/composables/useCart.ts` — composable singleton (count, subtotal, add/remove/clear), sincroniza entre pestañas.
- `resources/js/components/catalog/CartDrawer.vue` — drawer lateral con items, controles +/-, subtotal.
- Botón **carrito** en el header con badge de cantidad.
- Botón **"Agregar"** en hover de `ProductCard` y como CTA principal en `Show.vue`.

### Checkout

| URL                | Descripción                                                              |
| ------------------ | ------------------------------------------------------------------------ |
| `/carrito`         | Página completa con items, controles y formulario lateral                |
| `/carrito/gracias` | Confirmación con resumen del pedido                                      |
| `POST /checkout`   | Crea `Inquiry` + `InquiryItem`s para todos los items. Throttle 10/min     |

El form de checkout tiene dos botones:
- **Enviar por WhatsApp** — registra el pedido y abre `wa.me` con un mensaje formateado: lista de items, total estimado y nombre.
- **Enviar pedido** — solo registra en BD; admin verá la consulta en `/admin/inquiries`.

### Reportes

`/admin/reportes` (permiso `reports.view`, admin y vendedor).

Filtro por rango de fechas. Calcula:
- KPIs: consultas totales, vendidas, conversión %, ingresos estimados (suma `total_estimated` de las marcadas como vendido), pendientes, cerradas.
- Serie diaria de consultas + ingresos con barras horizontales comparativas.
- Top 10 productos más solicitados (cantidad e ingresos).
- Top 10 productos más vistos (vista del cliente, vía `product_views`).

## 13. Archivos clave — iteración 5

**Controllers**: `Admin/UserController`, `Admin/ReportController`, ampliación de `Catalog/InquiryController::checkout()`.
**Form Requests**: `Admin/StoreUserRequest`, `Admin/UpdateUserRequest`.
**Rutas**: `users.*` resource, `reportes`, `/carrito`, `/carrito/gracias`, `POST /checkout`.
**Páginas Vue**: `admin/users/Index`, `admin/users/Form`, `admin/reports/Index`, `catalog/Cart`, `catalog/CartThanks`.
**Componentes**: `catalog/CartDrawer`.
**Composables**: `useCart`.
**Sidebar**: items nuevos "Reportes" (admin+vendedor) y "Usuarios" (solo admin).

## 14. Comandos para esta iteración

Solo recargar permisos (no hay migraciones nuevas):

```bash
php artisan db:seed --class=RoleSeeder
php artisan cache:clear
```

## 15. Iteración 6 — Logo/favicon, CSV y emails (ya implementada)

### Logo y favicon

- Settings nuevos `logo_path` y `favicon_path` (tipo `image`).
- `/admin/settings` ahora soporta upload con preview, vista previa y botón "Quitar".
- Archivos van a `storage/app/public/settings/` (necesita `php artisan storage:link`).
- `PublicLayout` muestra el logo en el header (con fallback al texto si no hay logo) e inyecta el favicon en `<head>` cuando está configurado.

### Exportar CSV

| Endpoint                          | Contenido                                        |
| --------------------------------- | ------------------------------------------------ |
| `GET /admin/inquiries/export.csv` | Todas las consultas (respeta filtros q + status) |
| `GET /admin/reportes/export.csv`  | Serie diaria de consultas + ventas + ingresos    |

- Helper `App\Support\CsvDownload` con BOM UTF-8 para que Excel respete acentos.
- Botones "Exportar CSV" / "CSV" agregados en los headers de `admin/inquiries/Index.vue` y `admin/reports/Index.vue`.

### Notificaciones por email

- `App\Notifications\NewInquiryNotification` (canal `mail`) con plantilla legible: cliente, teléfono, items, total, link al panel.
- `Catalog\InquiryController::store()` y `::checkout()` envían la notificación al `email_contact` de Settings tras crear la consulta.
- Errores de envío van a `report()` (no bloquean al cliente).
- Driver por defecto `MAIL_MAILER=log` → revisa `storage/logs/laravel.log` para ver los emails generados. Para enviar realmente, configura SMTP en `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu@email.com
MAIL_PASSWORD=app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="contacto@tudominio.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## 16. Comandos para esta iteración

```bash
php artisan db:seed --class=SettingsSeeder   # agrega logo_path y favicon_path
php artisan storage:link                     # si no lo has hecho
php artisan cache:clear
```

## 17. Iteración 7 — Cupones, relacionados mejorados y multi-idioma (ya implementada)

### Sistema de cupones

- Migración `coupons` (code único, type percent/fixed, value, min_subtotal, max_uses, used_count, vigencia, is_active) + columnas en `inquiries` (`coupon_code`, `discount_amount`).
- Modelo `Coupon` con scope `active()` y métodos `isUsable($subtotal)` + `discountFor($subtotal)`.
- CRUD admin `/admin/coupons` (solo admin, permiso `coupons.*`).
- Endpoint público `POST /cupones/validar` para validar en vivo desde el carrito.
- En `/carrito`: campo "Código de descuento" con botón "Aplicar"; si es válido muestra badge verde + descuento + total final.
- En el checkout, si `coupon_code` se valida, se descuenta y se incrementa `used_count` del cupón.

### Productos relacionados mejorados

Algoritmo en `CatalogController::buildRelated()`:
1. Misma categoría + misma marca (mejor match)
2. Completar con misma categoría
3. Completar con misma marca

Bloque nuevo **"También te puede interesar"** que muestra los 4 productos más vistos en los últimos 30 días dentro de la misma categoría (con fallback a `views_count` total si no hay vistas recientes).

### Multi-idioma (es / en)

- Archivos `lang/es/catalog.php` y `lang/en/catalog.php` con ~70 claves de la UI pública.
- Middleware `App\Http\Middleware\SetLocale` lee cookie `locale` (excluida de cifrado en `bootstrap/app.php`).
- Controlador `LocaleController::set()` en ruta `GET /locale/{locale}` guarda la cookie.
- `HandleInertiaRequests` comparte `props.locale` y `props.translations`.
- Composable `useTranslations()` en frontend: `t('home')`, `t('low_stock', { count: 3 })` con interpolación de `:param`.
- Selector de idioma (icono globo) en `PublicLayout` header con español/english.
- Las páginas del catálogo público usan `t()` para textos clave; el panel admin queda en español (lang del staff).

## 18. Comandos para esta iteración

```bash
php artisan migrate                          # crea coupons + columnas en inquiries
php artisan db:seed --class=RoleSeeder       # añade permisos coupons.*
php artisan cache:clear
```

## 19. Iteración 8 — Rediseño UI espectacular (ya implementada)

### Paleta esmeralda + ámbar

- Nuevas variables CSS en `resources/css/app.css`: primario esmeralda profundo, acento ámbar cálido, fondo con tinte verdoso muy sutil. Versión dark con esmeralda brillante.
- Variables nuevas: `--brand`, `--brand-foreground`, `--accent2`, `--accent2-foreground` → usables como clases Tailwind `bg-brand`, `text-accent2`, etc.
- Tipografía display: **Plus Jakarta Sans** (cargada desde Google Fonts en el layout) con `letter-spacing` ajustado para titulares.

### Utilidades nuevas (Tailwind)

- `.gradient-brand` — gradiente diagonal esmeralda → ámbar para CTAs y banners.
- `.gradient-brand-soft` — versión semitransparente para fondos de sección.
- `.gradient-text` — texto con gradiente (background-clip).
- `.glow-brand` — sombra de luz esmeralda para botones principales.
- `.glass` — efecto glassmorphism con blur (header sticky).
- `.pattern-dots` — patrón decorativo de puntos.
- `.reveal` + `.reveal.in` + `.reveal-d1..4` — animación fade-up activada por IntersectionObserver.
- Animaciones: `animate-shimmer`, `animate-float`, `animate-pulse-soft`, `animate-fade-up`, `gradient-animated`.

### Composable

- `useReveal()` — IntersectionObserver que añade `.in` a cualquier `.reveal` cuando entra en viewport. Auto-cargado en `PublicLayout`.

### Componentes rediseñados

- **`PublicLayout.vue`** — topbar de contacto con fondo oscuro, header sticky glassy con altura 18, logo con icono Sparkles + tipografía display, navegación con píldoras hover esmeralda, búsqueda con input redondeado, badge del carrito con `animate-pulse-soft`, CTA "Login" con gradiente esmeralda, footer con fondo gradient-brand (esmeralda/ámbar) y links con ámbar en hover.
- **`HeroCarousel.vue`** — efecto Ken Burns (zoom lento) en imágenes, gradient overlay diagonal multi-stop, badge "Edición especial" con backdrop blur, botones de navegación más grandes con glass effect, indicador de progreso (barra ámbar al pie que avanza con el autoplay).
- **`ProductCard.vue`** — rounded-2xl, sombra brand al hover, imagen con `scale-115` al hover, efecto shine que cruza la card, badges flotantes con fondo translúcido y backdrop-blur, overlay de CTAs (Vista + Agregar) que sube desde abajo, línea inferior animada que crece desde el centro al hover.
- **`Filters.vue`** — card grande con padding generoso, header con borde inferior y icono brand, inputs redondeados con focus esmeralda, checkbox "Solo en stock" como label-card.
- **`Home.vue`** — hero con dots pattern, título con `gradient-text`, sección de perks (4 cards con iconos), categorías como cards con hover lift + rotate sutil + línea inferior, sección destacados con fondo gradient-brand-soft, sección CTA final fondo oscuro con dos blobs de glow (esmeralda + ámbar).
- **`Index.vue`** (catálogo) — sub-hero con tinte brand, toggle grid/list con píldora gradient cuando está activo, sidebar de filtros sticky en desktop, empty state con icono brand.

### Fondos decorativos globales

`PublicLayout` ahora monta dos blobs fijos detrás del contenido (esmeralda arriba-derecha, ámbar centro-izquierda) con `blur-[120px]` que dan profundidad al fondo de toda la app pública.

## 20. Comandos para esta iteración

```bash
npm run dev          # recompila CSS con las nuevas variables
```

(no requiere migrar ni resembrar — son solo cambios de estilos)

## 21. Pendiente — iteración 9 (opcional)

- Pasarela de pago real (Mercado Pago / Stripe / Culqi).
- Notificación WhatsApp (twilio / API oficial / chatbot).
- Wishlist / favoritos del cliente.
- SEO: sitemap.xml dinámico, meta-tags por producto, schema.org JSON-LD.
- Modo PWA con cache offline del catálogo.

## 10. Archivos huérfanos del starter (puedes borrarlos manualmente)

```
app/Models/Team.php, Membership.php, TeamInvitation.php
app/Concerns/HasTeams.php, GeneratesUniqueTeamSlugs.php
app/Enums/TeamRole.php, TeamPermission.php
app/Http/Controllers/Teams/   (carpeta)
app/Http/Middleware/EnsureTeamMembership.php, SetTeamUrlDefaults.php
app/Http/Requests/Teams/      (carpeta)
app/Http/Responses/Concerns/RedirectsToCurrentTeam.php
app/Notifications/Teams/      (carpeta)
app/Policies/TeamPolicy.php
app/Rules/TeamName.php, UniqueTeamInvitation.php, ValidTeamInvitation.php
app/Support/TeamPermissions.php, UserTeam.php
app/Actions/Teams/CreateTeam.php
database/factories/TeamFactory.php, TeamInvitationFactory.php
```
