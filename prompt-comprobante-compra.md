# Tarea: comprobante imprimible de compra a proveedor

Trabajás en `C:\laragon\www\appweb_catalogodeproductos` (proyecto "PC Store Pro").
Leé `CLAUDE.md` y `AGENTS.md` antes de empezar: tienen las convenciones del proyecto.

## Objetivo

Agregar una vista imprimible del **comprobante de ingreso de mercadería** para una
compra a proveedor ya registrada. Hoy existe el detalle en pantalla
(`/admin/purchases/{id}`) pero no hay forma de imprimirlo ni de archivarlo en papel.

**Importante — no es un ticket de venta.** Es un documento interno de almacén: se
imprime cuando llega el pedido del proveedor, se contrasta contra el remito físico y
se archiva firmado. No lleva "Gracias por su compra" ni "cambio/vuelto"; lleva
espacio de firmas (Recibido por / Entregado por) y el número de remito del proveedor.

## Stack (respetalo, no lo cambies)

- Laravel 13 + Inertia v3 + Vue 3 (`<script setup lang="ts">`) + Tailwind CSS 4
- Tests con Pest v4
- **No instales dependencias nuevas.** Nada de dompdf/snappy/mpdf: el comprobante se
  genera con CSS de impresión (`@media print` / variantes `print:` de Tailwind 4) y
  `window.print()`. El usuario imprime a impresora o usa "Guardar como PDF" del navegador.

## Datos que ya existen (no inventes campos)

`App\Models\Purchase` (tabla `compras`) expone estos alias en inglés vía el trait
`HasSpanishAliases` — las columnas reales están en español:

```
id, reference_number (remito/factura del proveedor), status ('confirmada'|'anulada'),
total_cost, notes, created_at, voided_at
relaciones: supplier (id, name), items[], creator (id, name), voider (id, name)
```

`App\Models\PurchaseItem` (tabla `compra_items`):

```
id, product_id, product_name_snapshot, product_code_snapshot,
quantity, unit_cost, subtotal (accessor ya en $appends)
```

Los datos de la tienda llegan como prop compartida de Inertia en `page.props.store`
(tipo `StoreSettings` en `resources/js/lib/catalog.ts`):

```
name, tagline, address, whatsapp, email, hours,
currency, currency_symbol, logo_url, favicon_url
```

Para formatear montos usá `formatPrice(valor, store.currency_symbol)` de
`@/lib/catalog` — ya existe, no escribas otro formateador. La moneda es BOB (`Bs/`).

## Qué construir

1. **Ruta nueva** en `routes/admin.php`, dentro del grupo `admin.*` existente:
   `GET admin/purchases/{purchase}/print` → `PurchaseController@print`,
   con `->middleware('permission:inventory.view')` y nombre `purchases.print`.
   Registrala **junto al resource de purchases** respetando el orden que ya usa el
   archivo (la ruta `purchases/{purchase}/void` está antes del resource por el
   wildcard; seguí ese mismo criterio).

2. **Método `print()`** en `app/Http/Controllers/Admin/PurchaseController.php`:
   carga las mismas relaciones que `show()` y renderiza
   `Inertia::render('admin/purchases/Print', [...])`.

3. **Página `resources/js/pages/admin/purchases/Print.vue`** sin el chrome del admin
   (sin sidebar, sin header, sin breadcrumbs):
   - **Ojo, acá hay una trampa:** `defineOptions({ layout: null })` **NO funciona** en
     este proyecto. Inertia resuelve el layout con
     `layoutValue ?? defaultLayout(...)`, así que un `layout: null` es nullish y cae
     igual en el layout por defecto (`AppLayout`, con sidebar).
   - La forma correcta es agregar un caso en el resolver de layouts de
     `resources/js/app.ts`, que es donde se decide de verdad. Hoy tiene:

     ```ts
     switch (true) {
         case name === 'Welcome':
         case name === 'Error':
             return null;
         case name.startsWith('catalog/'):
             return null;
         case name.startsWith('auth/'):
             return AuthLayout;
         ...
         default:
             return AppLayout;
     }
     ```

     Agregá un caso que devuelva `null` para la página de impresión (por ejemplo
     `case name.endsWith('/Print'):`, ubicado **antes** del `default`).
   - No intentes esconder el sidebar con `print:hidden`: es más frágil y el layout
     igual monta lógica que no necesitás.
   - Llama `window.print()` al montar (`onMounted`), y muestra en pantalla una barra
     superior con botones "Imprimir" y "Volver" que se ocultan al imprimir
     (`print:hidden`).

4. **Botón "Imprimir"** en la vista de detalle existente
   `resources/js/pages/admin/purchases/Show.vue`, al lado de "Anular compra".

## Diseño del comprobante

Formato **ticket angosto tipo POS (80 mm)**, monoespaciado, en blanco y negro —
pensado para impresora térmica, pero legible también en A4. Estructura:

```
        [logo si store.logo_url existe]
              PC STORE PRO
      Eterazama, plaza principal acera oeste
        +59163853001 · correo@tienda.com
------------------------------------------------
COMPROBANTE DE INGRESO DE MERCADERIA
N.o interno:  #12
Remito prov.: FAC-1004
Fecha:        11/08/2026 20:31
Proveedor:    Expósito y Negrete e Hija
Registró:     Grover Ramirez
------------------------------------------------
Producto            Cant  C.Unit    Subtotal
IPC-HFW1239 Cámara     5   480.00   2,400.00
...
------------------------------------------------
                 TOTAL:  Bs/ 9,712.00
------------------------------------------------
[si status === 'anulada': banda visible "ANULADA"
 con fecha y quién la anuló]

Notas: ...

___________________      ___________________
  Recibido por            Entregado por
```

Reglas de estilo:
- Ancho fijo `80mm` en impresión (`@page { size: 80mm auto; margin: 4mm; }`), y
  centrado con ancho máximo cómodo en pantalla.
- Tipografía monoespaciada, tamaño chico (≈11–12px), sin colores de marca ni sombras:
  la impresora térmica es monocromática.
- Números alineados a la derecha con `font-variant-numeric: tabular-nums`.
- Si la compra está anulada tiene que **saltar a la vista** que no es válida.
- Debe verse bien en modo oscuro en pantalla, pero imprimirse siempre sobre fondo
  blanco con texto negro (forzalo en el bloque `@media print`).

## Restricciones del proyecto (están en CLAUDE.md, respetalas)

- **Comentarios de código en español.**
- Si hacés commit: mensaje en español, sin acentos, estilo `feat(ambito): descripcion`.
- En `<script setup>` de archivos `.vue` **nunca** escribas la secuencia literal de
  cierre de script dentro de un comentario: rompe el parser SFC.
- Los montos se toman de la base de datos, nunca se recalculan en el cliente ni se
  aceptan del request. El `total_cost` ya viene calculado por el servidor: mostralo,
  no lo vuelvas a sumar en el frontend.
- Cada ruta admin exige su permiso explícito en `routes/admin.php`.

## Verificación (hacela, no la asumas)

```bash
php artisan test --compact     # toda la suite tiene que quedar en verde
npx eslint resources/js/pages/admin/purchases/Print.vue
npx vue-tsc --noEmit
vendor/bin/pint --dirty
```

Además, agregá un test en `tests/Feature/Admin/` que cubra:
- que `GET /admin/purchases/{id}/print` responde 200 y renderiza el componente
  `admin/purchases/Print` con la compra y sus items;
- que un usuario **sin** el permiso `inventory.view` recibe 403.

Guiate por `tests/Feature/Admin/PurchaseManagementTest.php`, que ya existe y tiene el
esqueleto (`RefreshDatabase`, `beforeEach` con `RoleSeeder` y usuario `propietario`).

Hay factories listas: `Purchase::factory()`, `Supplier::factory()`, `Product::factory()`.

## Criterios de aceptación

- [ ] Desde `/admin/purchases/{id}` puedo abrir el comprobante e imprimirlo.
- [ ] La impresión no incluye sidebar, header, breadcrumbs ni botones
      (verificalo de verdad en el navegador, no lo asumas por el `defineOptions`).
- [ ] Los datos de la tienda salen de `settings`, no están hardcodeados.
- [ ] Una compra anulada se imprime marcada como anulada.
- [ ] Suite de tests en verde, lint y tipos limpios.
