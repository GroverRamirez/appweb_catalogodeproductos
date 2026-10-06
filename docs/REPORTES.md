# Módulo de reportes y panel de indicadores

Reporte gerencial del catálogo: una sola pantalla con filtros por fecha, cinco
indicadores gráficos y tres formatos de exportación (PDF, Excel y CSV).

## Instalación (una sola vez)

Las dos librerías de reportes se instalan con Composer y Chart.js con npm:

```bash
composer require barryvdh/laravel-dompdf maatwebsite/excel
npm install          # instala chart.js, ya declarado en package.json
npm run build        # o `npm run dev` mientras se trabaja
php artisan test --compact
```

No hace falta publicar configuración: DomPDF y Laravel Excel se registran solos
y se usan con sus valores por defecto.

## Cómo está armado

| Archivo | Rol |
| --- | --- |
| `app/Services/SalesReport.php` | Todas las consultas del reporte. Es la única fuente de datos: la pantalla, el PDF, el Excel y el CSV lo consumen, así que no pueden contradecirse. |
| `app/Http/Controllers/Admin/ReportController.php` | Cuatro salidas del mismo reporte: `index` (pantalla), `pdf`, `excel` y `export` (CSV). |
| `resources/views/reports/pdf.blade.php` | Plantilla del PDF, con encabezado institucional y filas de totales. |
| `app/Exports/ReportExport.php` + `app/Exports/Sheets/*` | Libro Excel, una hoja por bloque del reporte. |
| `resources/js/lib/charts.ts` | Paleta de los gráficos y seguimiento del tema claro/oscuro. |
| `resources/js/components/admin/ChartCanvas.vue` | Envoltorio de Chart.js: crea, actualiza y destruye el gráfico. |
| `resources/js/pages/admin/reports/Index.vue` | Panel: KPIs, los cinco gráficos y las tablas. |

Rutas (todas exigen el permiso `reports.view`):

```
GET /admin/reportes                 pantalla
GET /admin/reportes/export.pdf      PDF (agregar ?inline=1 para verlo en el navegador)
GET /admin/reportes/export.xlsx     Excel
GET /admin/reportes/export.csv      CSV
```

Los filtros `from` y `to` viajan en la URL, así que la exportación siempre sale
del mismo rango que se ve en pantalla.

## Los indicadores gráficos

| Gráfico | Tipo | Consulta que lo alimenta |
| --- | --- | --- |
| Ingresos por día | línea | suma de `ventas.total` confirmadas, agrupada por día |
| Consultas por día | línea, dos series | conteo de `consultas` y de las vendidas, por día |
| Consultas por estado | dona | conteo de `consultas` agrupado por `estado` |
| Unidades vendidas por producto | barras horizontales | suma de `venta_items.cantidad` por producto |
| Ingresos por categoría | barras | join `venta_items → productos → categorias` |

Criterios que conviene poder defender al mostrarlos:

- **Un solo eje por gráfico.** Ingresos y cantidades no comparten escala: en un
  gráfico de dos ejes el cruce de las líneas parece un hallazgo cuando solo es
  el efecto de la escala elegida. Por eso son dos gráficos y no uno.
- **El color identifica a la serie, no a su posición.** El orden de la paleta es
  fijo; filtrar no repinta lo que queda.
- **Las porciones de la dona llevan etiqueta.** El color solo no alcanza: al
  lado va la lista con el nombre, el conteo y el porcentaje.
- **Sin datos se dice "sin datos".** Un gráfico en cero se lee como un error de
  carga.

## Agregar un indicador nuevo

1. Un método en `SalesReport` con la consulta agregada (que devuelva arrays,
   no modelos).
2. Una prop más en `ReportController@index`.
3. En `Index.vue`, un `computed` con la configuración de Chart.js y un
   `<ChartCanvas :config="..." label="..." />` dentro de una `Card`.
4. Un test en `tests/Feature/Admin/ReportExportsTest.php` que verifique el
   agregado con datos conocidos.

## Verificación antes de demostrar

- [ ] `php artisan test --compact` en verde.
- [ ] La pantalla abre en `/admin/reportes` con un rango de datos reales.
- [ ] El PDF descarga, abre y muestra el logo y los datos de la tienda
      (Admin → Configuración) en el encabezado.
- [ ] El Excel abre con sus cinco hojas y los ingresos suman en la celda.
- [ ] Los cinco gráficos responden al cambiar las fechas.
- [ ] Se ve bien en tema claro y en tema oscuro.
