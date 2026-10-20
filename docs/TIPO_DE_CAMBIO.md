# Precios en dólares, cobro en bolivianos

El catálogo trabaja con dos monedas, igual que las tiendas de importación en
Bolivia: **la lista de precios se carga en dólares y el cliente paga en
bolivianos**, al tipo de cambio oficial flexible del día.

## Qué moneda usa cada cosa

| Dato | Moneda | Por qué |
| --- | --- | --- |
| `productos.precio`, `precio_oferta`, `costo` | **USD** | Es la lista del proveedor; no se retoca cuando se mueve el dólar. |
| Vitrina, carrito, ficha de producto | Bs | Se convierte al mostrar, con la cotización del día. |
| `consultas` y `consulta_items` | Bs | Es lo que se le cotizó al cliente. |
| `ventas` y `venta_items` | Bs | Es lo que se cobró. |
| `compras` y `compra_items` | USD | Es lo que se le paga al proveedor, y alimenta el costo de la lista. |
| Reportes y panel | Bs | Los montos en dólares (inventario, compras) se convierten al mostrar. |

`consultas.tipo_cambio` y `ventas.tipo_cambio` guardan la cotización con la que
se cerró cada documento. Un recibo de la semana pasada sigue diciendo lo mismo
aunque el BCB publique otra cotización hoy.

## De dónde sale la cotización

Una tarea diaria (00:20) consulta la API pública del BCB y guarda el valor:

```bash
php artisan tipo-cambio:actualizar             # consulta al BCB
php artisan tipo-cambio:actualizar --valor=12,35   # la fija a mano
```

El endpoint se configura con `BCB_TC_URL` (por defecto
`https://apibcb.cucu.bo/api/v1/tc/oficial`, pública y sin clave). Si la consulta
falla —sin internet, API caída, valor absurdo— **no pasa nada**: queda vigente
la última cotización cargada y la tienda sigue vendiendo.

También se puede editar a mano en **Admin → Configuración → Tipo de cambio**.
Al hacerlo, el sistema marca el origen como `manual` con su fecha, y la tarea
diaria vuelve a ponerlo en `bcb` la próxima vez que corra.

El valor vigente se muestra en la barra superior del catálogo ("Tipo de cambio:
12,02 Bs") y al lado de las migas del panel.

## Cómo se convierte

Todo pasa por `App\Services\ExchangeRate`: `toBs()` y `toUsd()`. Nadie
multiplica ni divide por su cuenta. Los puntos donde se aplica:

- `ProductResource` — el catálogo público publica siempre bolivianos, y manda
  además `price_usd` y `exchange_rate` por transparencia.
- `CatalogController` — los filtros de precio llegan en Bs y se convierten a USD
  antes de consultar la base; el JSON-LD publica el precio en Bs (`BOB`).
- `CartValidationController` — devuelve el precio del día para que un carrito
  guardado ayer en el navegador se actualice solo.
- Checkout y venta — congelan la cotización en el documento.
- `DashboardController` — convierte inventario y compras (USD) para mostrarlos
  junto a las ventas (Bs).

## Puesta en marcha (una sola vez)

```bash
php artisan migrate
php artisan tipo-cambio:actualizar
php artisan precios:convertir-a-usd --dry-run   # revisar la conversión
php artisan precios:convertir-a-usd             # ¡con backup hecho!
```

El último comando divide `precio`, `precio_oferta` y `costo` por la cotización
indicada (`--tc=12.02` para forzar otra) y deja marcada la lista como `USD`, de
modo que no se pueda convertir dos veces.

Además, en **Admin → Configuración** hay que dejar la moneda de cobro en `BOB`
con símbolo `Bs` si la instalación venía con otra.

## Detalles a tener presentes

- La **importación de catálogo por CSV** carga `precio` y `precio_oferta` en
  dólares. La plantilla no cambió de columnas, sí de significado.
- La **lista de favoritos** guarda el precio en el navegador al momento de
  marcarlo; se actualiza cuando el visitante vuelve a la ficha.
- Las **compras se cargan en dólares**. Si un proveedor factura en bolivianos,
  hay que dividir por la cotización antes de cargarla.
