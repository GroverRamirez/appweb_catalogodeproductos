# =============================================================================
#  Arma la version portable de PC Store Pro en dist\PC Store Pro
#
#  Uso, desde la raiz del proyecto:
#     powershell -ExecutionPolicy Bypass -File instalador\build-portable.ps1
#
#  Deja una carpeta que corre sola en cualquier Windows: lleva su propio PHP,
#  la base en un archivo SQLite con el catalogo real y el frontend ya compilado.
#  No toca el entorno de desarrollo: las dependencias de produccion se instalan
#  dentro de la copia, no sobre el vendor del proyecto.
# =============================================================================

$ErrorActionPreference = 'Stop'

$proyecto = Split-Path -Parent $PSScriptRoot
$destino  = Join-Path $proyecto 'dist\PC Store Pro'
$appDir   = Join-Path $destino 'app'
$phpDir   = Join-Path $destino 'php'

function Paso($n, $texto) { Write-Host "`n== $n == $texto" -ForegroundColor Cyan }

# robocopy usa codigos 0-7 para "todo bien"; 8 o mas es error real.
# El parametro no se llama $args a proposito: ese nombre ya lo usa PowerShell.
function Copiar($origen, $destino, $extra) {
    $parametros = @($origen, $destino, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP') + $extra
    & robocopy @parametros | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy fallo copiando $origen (codigo $LASTEXITCODE)" }
}

Paso '1/8' 'Compilando el frontend'
Push-Location $proyecto
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build fallo' }

Paso '2/8' 'Exportando la base actual a SQLite'
php artisan demo:exportar-sqlite --file=database/demo.sqlite --force
if ($LASTEXITCODE -ne 0) { throw 'La exportacion a SQLite fallo' }
Pop-Location

Paso '3/8' 'Copiando el proyecto'
if (Test-Path $destino) { Remove-Item $destino -Recurse -Force }
New-Item -ItemType Directory -Path $appDir -Force | Out-Null

# Lo que no viaja: dependencias de desarrollo, historia de git, tests, la
# carpeta de armado y los archivos propios de esta instalacion.
$excluirDirs = @('/XD',
    (Join-Path $proyecto 'node_modules'),
    (Join-Path $proyecto '.git'),
    (Join-Path $proyecto 'vendor'),
    (Join-Path $proyecto 'tests'),
    (Join-Path $proyecto 'dist'),
    (Join-Path $proyecto 'instalador'),
    (Join-Path $proyecto 'docker'),
    (Join-Path $proyecto '.github'),
    (Join-Path $proyecto 'storage\logs'),
    (Join-Path $proyecto 'storage\framework\cache\data'),
    (Join-Path $proyecto 'storage\framework\sessions'),
    (Join-Path $proyecto 'storage\framework\views')
)
$excluirFiles = @('/XF', '.env', '.env.production.example', 'backup-*.sql', '*.log')

Copiar $proyecto $appDir ($excluirDirs + $excluirFiles)

Paso '4/8' 'Configuracion de la copia portable'
$env_portable = Get-Content (Join-Path $PSScriptRoot 'plantilla\env.portable') -Raw
Set-Content -Path (Join-Path $appDir '.env') -Value $env_portable -Encoding UTF8
Copy-Item (Join-Path $proyecto 'database\demo.sqlite') (Join-Path $appDir 'database\database.sqlite') -Force
Remove-Item (Join-Path $appDir 'database\demo.sqlite') -ErrorAction SilentlyContinue

Paso '5/8' 'Instalando dependencias de produccion en la copia'
# Los scripts de post-install de Laravel arrancan la app, por eso el .env y la
# base ya tienen que estar arriba.
Push-Location $appDir
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
if ($LASTEXITCODE -ne 0) { throw 'composer install fallo' }
Pop-Location

Paso '6/8' 'Copiando PHP portable'
$phpOrigen = Split-Path (Get-Command php).Source
Write-Host "   PHP encontrado en: $phpOrigen"
New-Item -ItemType Directory -Path $phpDir -Force | Out-Null
Copiar $phpOrigen $phpDir @()
# Nuestro php.ini reemplaza al de Laragon: activa solo lo que la app necesita.
# Va al lado de php.exe a proposito — asi PHP lo encuentra solo, sin -c, y usa
# su propia carpeta ext sin rutas absolutas que se romperian al instalar.
Copy-Item (Join-Path $PSScriptRoot 'plantilla\php.ini') (Join-Path $phpDir 'php.ini') -Force

Paso '7/8' 'Clave propia y cache limpio'
Push-Location $appDir
# Clave propia para la copia portable: no se reusa la de desarrollo.
& (Join-Path $phpDir 'php.exe') artisan key:generate --force --no-interaction
& (Join-Path $phpDir 'php.exe') artisan optimize:clear
Pop-Location

Paso '8/8' 'Copiando imagenes y lanzadores'
# Copia real en vez de enlace simbolico: storage:link necesita permisos de
# administrador en Windows y aca no hay ninguno.
$publicStorage = Join-Path $appDir 'public\storage'
if (Test-Path (Join-Path $proyecto 'storage\app\public')) {
    New-Item -ItemType Directory -Path $publicStorage -Force | Out-Null
    Copiar (Join-Path $proyecto 'storage\app\public') $publicStorage @()
}
Copy-Item (Join-Path $PSScriptRoot 'plantilla\Iniciar PC Store Pro.bat') $destino -Force
Copy-Item (Join-Path $PSScriptRoot 'plantilla\Detener PC Store Pro.bat') $destino -Force
Copy-Item (Join-Path $PSScriptRoot 'plantilla\LEEME.txt') $destino -Force

Write-Host ''
$tam = [math]::Round((Get-ChildItem $destino -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host "`nCarpeta portable: $destino  ($tam MB)" -ForegroundColor Green
Write-Host 'Probala aca mismo con "Iniciar PC Store Pro.bat" antes de llevarla a otra maquina.'
Write-Host 'Para el instalador .exe: abri instalador\pc-store-pro.iss con Inno Setup y compila.'
