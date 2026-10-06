# Versión portable e instalador de PC Store Pro

Empaqueta la app para correrla en otra computadora sin instalar nada: lleva su
propio PHP, la base en un archivo SQLite con el catálogo real y el frontend ya
compilado. El usuario hace doble clic y se le abre la tienda en el navegador.

## Armar la carpeta portable

Desde la raíz del proyecto, en PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File instalador\build-portable.ps1
```

Tarda unos minutos y deja todo en `dist\PC Store Pro\`. El script:

1. Compila el frontend (`npm run build`).
2. Exporta la base actual de MySQL a SQLite (`php artisan demo:exportar-sqlite`).
3. Copia el proyecto sin `node_modules`, `vendor`, `.git` ni los tests.
4. Escribe el `.env` de la copia y pone la base en su lugar.
5. Instala las dependencias **dentro de la copia** (`composer install --no-dev`),
   sin tocar el `vendor` de desarrollo.
6. Copia el PHP de tu Laragon y le pone un `php.ini` propio.
7. Genera una clave de aplicación nueva para la copia.
8. Copia las imágenes de productos y los lanzadores.

Probala ahí mismo con `Iniciar PC Store Pro.bat` antes de llevarla a otra
máquina.

## Armar el instalador .exe

Con la carpeta portable ya hecha:

1. Instalá [Inno Setup 6](https://jrsoftware.org/isdl.php).
2. Abrí `instalador\pc-store-pro.iss` y presioná **Compilar** (F9).
3. Queda `dist\PCStorePro-Setup.exe`.

El instalador va a `%LOCALAPPDATA%\PC Store Pro`, no a *Archivos de programa*:
así no pide permisos de administrador y la app puede escribir su base de datos
y sus imágenes dentro de su propia carpeta.

## Decisiones y por qué

- **SQLite en vez de MySQL**: la base es un archivo adentro de la carpeta. No
  hay servicio que instalar, ni contraseñas, ni un segundo proceso que pueda
  no arrancar. Los tests del proyecto ya corren sobre SQLite.
- **Copia real de las imágenes en vez de `storage:link`**: el enlace simbólico
  necesita permisos de administrador en Windows.
- **`QUEUE_CONNECTION=sync` y `MAIL_MAILER=log`**: sin worker de colas ni SMTP,
  la app funciona con solo abrir el acceso directo. Los correos que generaría
  quedan escritos en `storage/logs`.
- **`php artisan serve`**: es el servidor de desarrollo de PHP, suficiente para
  un usuario a la vez. No sirve para producción — para eso está `DEPLOY.md`.

## Limitaciones a tener presentes

- Es una copia congelada: lo que el usuario cargue ahí no vuelve al sistema
  real, y lo que cambies acá después no llega a esa PC hasta que le lleves una
  carpeta nueva.
- Usa el puerto 8020. Si está ocupado, la tienda no levanta.
- Al ser SQLite, no sirve para probar rendimiento ni varios usuarios a la vez.
- Antes de entregarla, revisá qué usuarios quedaron en la base exportada: viajan
  todos, con sus contraseñas. Si la demo va a manos de terceros, conviene dejar
  solo un usuario de prueba.
