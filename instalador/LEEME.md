# Instalador de PC Store Pro para Windows

Genera `dist\PCStorePro-Setup.exe`: un instalador que deja la tienda
funcionando en cualquier Windows de 64 bits sin instalar nada más. Lleva su
propio PHP, la app con el frontend compilado y la base en un archivo SQLite.

## Generar el instalador

Requisitos en esta máquina: lo de siempre para desarrollar (PHP de Laragon,
Composer, Node) y [Inno Setup 6](https://jrsoftware.org/isdl.php).

Desde la raíz del proyecto, en PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File instalador\build-portable.ps1
```

Tarda unos minutos. Arma `dist\PC Store Pro\` y, si encuentra Inno Setup,
compila `dist\PCStorePro-Setup.exe`. Sin Inno Setup se queda en la carpeta;
después se compila abriendo `instalador\pc-store-pro.iss` y presionando F9.

El script:

1. Compila el frontend (`npm run build`).
2. Copia **solo los archivos versionados en git**, sin tests, docker, docs ni
   los `.md` internos. Así no viajan el `.env`, la base, `storage/backups`,
   los volcados `.sql` ni `Claude outputs`.
3. Pone el `.env` de `plantilla\env.portable`, **sin clave**.
4. Instala las dependencias de producción dentro de la copia.
5. Copia el PHP de Laragon con un `php.ini` propio y las DLL del runtime de
   Visual C++ (una PC recién formateada puede no tenerlas).
6. Prueba que la app arranque con ese PHP.
7. Compila el `.exe` con Inno Setup.

## Qué hace el instalador en la PC del cliente

1. Instala en `%LOCALAPPDATA%\PC Store Pro`, sin pedir administrador.
2. Pide el nombre de la tienda y los datos del propietario (nombre, correo y
   contraseña).
3. Corre `php artisan app:instalar`: genera la clave propia de esa
   instalación, crea la base, aplica las migraciones, carga los roles con sus
   permisos y la configuración inicial, crea al propietario y enlaza
   `public/storage`.
4. Crea los accesos directos y abre la tienda.

Si algo falla, el detalle queda en `app\storage\logs\instalacion.log` y se
puede volver a correr el instalador.

**Actualizar:** se corre el instalador nuevo encima. Detecta la base, no pide
nada, conserva el `.env` (y con él la clave) y solo aplica las migraciones
pendientes. Los roles y la configuración no se vuelven a sembrar, para no
pisar lo que el propietario ajustó.

**Desinstalar:** pregunta si borrar también los datos; por defecto no.

## Decisiones y por qué

- **Instalación limpia, no copia de la base de desarrollo.** El instalador
  anterior exportaba la base MySQL (`demo:exportar-sqlite`) y metía usuarios
  con sus hashes y clientes con su CI dentro del `.exe`. Ahora cada cliente
  arranca vacío y con su propio propietario. El comando de exportación sigue
  en el proyecto, pero el armado ya no lo usa.
- **SQLite en vez de MySQL**: la base es un archivo. No hay servicio que
  instalar ni contraseñas, y la copia de seguridad del panel la copia con
  `VACUUM INTO` (`db_database_<fecha>.sqlite.gz`).
- **`APP_FORCE_HTTPS=false`**: en producción la app genera todas las URLs con
  https; acá se sirve en `http://127.0.0.1` y eso rompía el inicio de sesión.
- **`storage:link` en la instalación**: en Windows Laravel crea una unión de
  carpetas (`mklink /J`), que no pide administrador. Así las imágenes que se
  suban desde el panel se ven.
- **`QUEUE_CONNECTION=sync` y `MAIL_MAILER=log`**: sin worker ni SMTP. Los
  correos quedan escritos en `storage/logs`.
- **El tipo de cambio se pide al abrir la tienda**, porque no hay programador
  de tareas corriendo.
- **`php artisan serve`**: alcanza para una tienda usada desde esa misma PC.
  Para publicarla en internet está `DEPLOY.md`.

## Limitaciones

- La tienda solo se ve desde la PC donde se instaló (escucha en 127.0.0.1).
- Usa el puerto 8020.
- El `.exe` no está firmado: Windows SmartScreen puede avisar la primera vez
  ("Más información" > "Ejecutar de todas formas").
