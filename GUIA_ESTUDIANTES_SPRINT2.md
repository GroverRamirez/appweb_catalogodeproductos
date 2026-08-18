# Guía: crear el Catálogo de Productos desde cero con ayuda de IA

Esta guía es para estudiantes que ya vieron lo básico de Laravel, Vue y bases
de datos en clase, y quieren construir una app real —un catálogo de
productos con carrito de consulta y panel de administración— usando un
asistente de IA gratuito como copiloto de programación.

No es un tutorial de "copiá y pegá". El objetivo es que aprendas a **dirigir**
a la IA: dividir el proyecto en fases chicas, pedirle un plan antes de tocar
código, revisar cada cambio, y correr los tests antes de seguir. La IA
escribe más rápido que vos, pero **vos seguís siendo responsable de entender
lo que se sube al repositorio**.

## 0. Qué vas a construir

Un catálogo público de productos (sin pagos online): el cliente navega,
arma un carrito y lo envía como **consulta** (el negocio lo atiende después
por WhatsApp o email). Además, un panel de administración con login y
roles/permisos para gestionar productos, categorías, cupones y consultas.

Stack: Laravel + Inertia + Vue + Tailwind CSS, con Pest para tests.

## 1. Herramientas que necesitás

| Herramienta | Para qué | Notas |
|---|---|---|
| PHP 8.3+ y Composer | correr Laravel | Laragon/XAMPP en Windows ya lo trae |
| Node.js (LTS) y npm | compilar el frontend (Vite) | |
| Git | versionar el código | |
| Un editor (VS Code) | escribir y revisar código | |
| Un asistente de IA en terminal | tu copiloto | ver punto 2 |

### 2. Instalar y configurar un asistente de IA gratuito (OpenCode)

[OpenCode](https://opencode.ai/) es un agente de IA de código abierto que
corre en la terminal y funciona con más de 75 modelos (incluye opciones
gratuitas). Instalación:

```bash
curl -fsSL https://opencode.ai/install | bash
```

Después, conectá un proveedor de modelo. Para uso gratuito en un proyecto de
clase, dos caminos simples:

- **Google Gemini** (tiene capa gratuita generosa): conseguí una API key en
  [Google AI Studio](https://aistudio.google.com/), corré `opencode auth login`,
  elegí Gemini y pegá la key.
- **OpenCode Zen**: un conjunto de modelos ya verificados por el equipo de
  OpenCode, pensado para arrancar rápido sin configurar nada más.

Una vez configurado, corré `opencode` dentro de la carpeta del proyecto y
usá `/models` para confirmar qué modelo está activo.

> Nunca pegues una API key en el chat de la IA ni la subas al repositorio.
> Va en variables de entorno locales, fuera de git.

## 3. Reglas de oro para trabajar con IA en este proyecto

1. **Un archivo de contexto desde el día 1.** Creá un `AGENTS.md` (o
   `CLAUDE.md`) en la raíz con: qué hace la app, qué stack usás, y las
   convenciones del equipo (idioma de los commits, dónde van los
   comentarios, reglas de negocio importantes). La IA lo lee antes de cada
   tarea y toma mejores decisiones.
2. **Pedí un plan antes de programar.** No arranques con "hacé el carrito".
   Pedí primero: "explicame cómo lo implementarías, qué archivos tocarías,
   antes de escribir código". Revisá el plan, ajustalo, recién ahí decile
   que programe.
3. **Fases chicas, commits chicos.** Una fase = una funcionalidad completa
   (migración + backend + frontend + test). No mezcles fases en un mismo
   commit.
4. **Corré los tests después de cada fase.** `php artisan test --compact`
   debe quedar en verde antes de pasar a la siguiente fase.
5. **Revisá el diff vos mismo.** `git diff` antes de commitear. Si no
   entendés una línea, preguntale a la IA por qué la escribió así.
6. **Los precios y totales se calculan siempre en el servidor.** Nunca
   confíes en un monto que venga del navegador.
7. **No le pidas que invente datos sensibles.** Si necesitás productos de
   ejemplo, usá datos ficticios o un factory, no información real de
   clientes.

## 4. Fases sugeridas (roadmap)

Cada fase incluye el objetivo, un prompt de ejemplo para arrancar, y qué
verificar antes de seguir. Adaptá el texto del prompt a tu propio criterio
(rubro del catálogo, nombre del negocio, etc.).

### Fase 0 — Arrancar el proyecto

**Objetivo:** proyecto Laravel nuevo con Inertia + Vue + Tailwind
funcionando, corriendo en local.

**Prompt de ejemplo:**
> Quiero crear un proyecto Laravel nuevo usando el starter kit de Inertia
> con Vue y Tailwind. Explicame los comandos paso a paso y qué hace cada
> uno antes de correrlos.

**Verificar:** `php artisan serve` levanta la página de bienvenida sin
errores en consola del navegador. Primer commit: `chore: proyecto inicial`.

### Fase 1 — Modelo de datos y catálogo público

**Objetivo:** migraciones de `productos`, `categorias`, `marcas`; catálogo
público que lista y filtra productos; página de detalle.

**Prompt de ejemplo:**
> Necesito migraciones para productos, categorías y marcas (una app de
> catálogo, sin pagos online). Antes de crear nada, proponeme el esquema de
> columnas y las relaciones entre tablas.

**Verificar:** seeders cargan datos de ejemplo, `/` muestra productos,
tests de listado en verde.

### Fase 2 — Carrito de consulta (sin pagos)

**Objetivo:** el cliente arma un carrito en el navegador y lo envía como
consulta; el precio final se recalcula en el servidor al enviar.

**Prompt de ejemplo:**
> El carrito no procesa pagos: al enviarlo se crea una "consulta" en la
> base de datos con los productos y cantidades, y el staff la atiende
> después por WhatsApp. Proponeme el flujo completo (frontend + backend)
> antes de programar, prestando atención a que el precio se recalcule en
> el servidor.

**Verificar:** test que arma un carrito, lo envía, y confirma que la
consulta quedó guardada con el total correcto (no el que mandó el
navegador).

### Fase 3 — Autenticación y panel de administración

**Objetivo:** login del staff con Fortify, roles y permisos (ej.
`propietario`, `encargado`, `vendedor`) con Spatie Permission. El registro
público queda deshabilitado a propósito.

**Prompt de ejemplo:**
> Quiero un panel de administración separado del catálogo público. El
> login es solo para staff (sin registro público). Los permisos deciden
> qué ve cada rol. Explicame cómo estructurarías las rutas y middlewares
> antes de tocar código.

**Verificar:** un `vendedor` no ve las pantallas que solo son de
`propietario`.

### Fase 4 — CRUD de productos con imágenes

**Objetivo:** el staff puede crear/editar productos y subir fotos, que se
procesan (redimensionadas, convertidas a un formato liviano, sin datos
EXIF) antes de guardarse.

**Prompt de ejemplo:**
> Al subir una imagen de producto quiero que se guarde optimizada
> (tamaño razonable, formato liviano) y sin metadatos EXIF. Proponeme
> dónde pondrías esa lógica para poder reutilizarla en banners y logo
> del sitio.

**Verificar:** test que sube una imagen falsa y comprueba que se guardó
procesada; probarlo también a mano en el navegador.

### Fase 5 — Funcionalidades extra (elegí 2 o 3)

- Favoritos/wishlist en el catálogo público.
- Cupones de descuento.
- Notas internas y historial en cada consulta.
- Búsqueda y filtros avanzados.

Para cada una: pedile el plan primero, implementá, testeá, commit.

### Fase 6 — Calidad

- `php artisan test --compact` en verde.
- `composer audit` y `npm audit` sin vulnerabilidades altas/críticas.
- Revisar accesibilidad básica (¿los diálogos tienen texto alternativo?
  ¿el foco del teclado funciona?).

### Fase 7 — Despliegue (opcional, si el profesor lo pide)

Servidor con HTTPS, variables de entorno de producción, backups. Esta
fase suele requerir más contexto del profesor/infraestructura disponible;
pedile a la IA que te arme un checklist antes de tocar un servidor real.

## 5. Prompts: ejemplos de "flojo" vs "bueno"

| ❌ Flojo | ✅ Bueno |
|---|---|
| "Hacé el carrito" | "El carrito no procesa pagos, arma una consulta que el staff atiende por WhatsApp. Antes de programar, proponeme el flujo completo y qué recalculás en el servidor." |
| "Arreglá el bug" | "Este test falla con este mensaje de error (pegar el error). Explicame por qué creés que falla antes de cambiar código." |
| "Hacé como Digicorp" | "Quiero un layout de header centrado con barra de búsqueda, como [link o captura de referencia]. Mostrame primero cómo lo estructurarías en componentes Vue." |

## 6. Checklist de entrega (para el profesor)

- [ ] El repo está en GitHub, con `README.md` explicando cómo levantar el
      proyecto local.
- [ ] `php artisan test --compact` queda en verde.
- [ ] `composer audit` y `npm audit` sin vulnerabilidades altas.
- [ ] El catálogo público funciona: buscar → carrito → enviar consulta.
- [ ] El panel admin funciona: login, CRUD de productos con imagen, roles
      y permisos aplicados.
- [ ] Los commits están en español, uno por funcionalidad chica, y no
      figura una IA como "Contributor" del repositorio (si usaste un
      asistente que agrega una línea `Co-Authored-By` a los commits,
      quitala antes de la entrega si tu profesor lo pide así).

## 7. Recursos

- [Documentación de Laravel](https://laravel.com/docs)
- [Documentación de Inertia](https://inertiajs.com/)
- [Documentación de Vue](https://vuejs.org/)
- [Documentación de Tailwind CSS](https://tailwindcss.com/docs)
- [Documentación de OpenCode](https://opencode.ai/docs/)
- [Pest (testing en PHP)](https://pestphp.com/)
