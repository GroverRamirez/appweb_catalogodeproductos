# =============================================================================
#  Arma el instalador de PC Store Pro para Windows
#
#  Uso, desde la raiz del proyecto:
#     powershell -ExecutionPolicy Bypass -File instalador\build-portable.ps1
#
#  1. Deja en dist\PC Store Pro una carpeta que corre sola en cualquier
#     Windows: su propio PHP, la app con el frontend compilado y las
#     dependencias de produccion. Va SIN base de datos: el instalador la crea
#     en la PC del cliente, con las migraciones y el propietario que este
#     elija. Ningun dato de esta maquina (usuarios, clientes, ventas,
#     copias de seguridad) viaja en el instalador.
#  2. Si Inno Setup 6 esta instalado, compila dist\PCStorePro-Setup.exe.
#
#  No toca el entorno de desarrollo: las dependencias de produccion se
#  instalan dentro de la copia, no sobre el vendor del proyecto.
# =============================================================================

$ErrorActionPreference = 'Stop'
# git devuelve los nombres en UTF-8; sin esto se rompen los que llevan tildes.
[Console]::OutputEncoding = [Text.Encoding]::UTF8

$proyecto = Split-Path -Parent $PSScriptRoot
$destino  = Join-Path $proyecto 'dist\PC Store Pro'
$appDir   = Join-Path $destino 'app'
$phpDir   = Join-Path $destino 'php'

function Paso($n, $texto) { Write-Host "`n== $n == $texto" -ForegroundColor Cyan }

Paso '1/7' 'Compilando el frontend'
Push-Location $proyecto
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build fallo' }

Paso '2/7' 'Copiando el proyecto'
if (Test-Path $destino) { Remove-Item $destino -Recurse -Force }
New-Item -ItemType Directory -Path $appDir -Force | Out-Null

# Solo viaja lo que esta en git: asi quedan afuera solos el .env, la base,
# storage/backups, los volcados .sql, "Claude outputs" y cualquier otro
# archivo suelto de esta maquina. De lo versionado se sacan ademas las cosas
# de desarrollo y documentacion interna.
$noViajan = '^(tests|docker|docs|instalador|\.github|\.claude|\.vscode|\.cursor|\.kiro|\.ai)/|' +
            '^[^/]+\.md$|^(Dockerfile|docker-compose\.yml|opencode\.json|boost\.json|test_out\.txt)$|' +
            '^database/.*\.sqlite$|\.sql(\.gz)?$|^\.env'
$archivos = git -c core.quotepath=off ls-files | Where-Object { $_ -notmatch $noViajan }
if ($LASTEXITCODE -ne 0) { throw 'git ls-files fallo: corre el script desde el repositorio' }

foreach ($rel in $archivos) {
    $origen = Join-Path $proyecto $rel
    if (-not (Test-Path -LiteralPath $origen -PathType Leaf)) { continue }   # borrado sin commitear
    $copia = Join-Path $appDir $rel
    New-Item -ItemType Directory -Path (Split-Path $copia) -Force | Out-Null
    Copy-Item -LiteralPath $origen -Destination $copia -Force
}

# El frontend compilado no esta en git.
Copy-Item (Join-Path $proyecto 'public\build') (Join-Path $appDir 'public\build') -Recurse -Force
Pop-Location
Write-Host "   $($archivos.Count) archivos copiados"

Paso '3/7' 'Configuracion de la copia'
# Sin APP_KEY: cada instalacion genera la suya al instalarse.
Copy-Item (Join-Path $PSScriptRoot 'plantilla\env.portable') (Join-Path $appDir '.env') -Force

Paso '4/7' 'Instalando dependencias de produccion en la copia'
Push-Location $appDir
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
if ($LASTEXITCODE -ne 0) { throw 'composer install fallo' }
Pop-Location

Paso '5/7' 'Copiando PHP portable'
$phpOrigen = Split-Path (Get-Command php).Source
Write-Host "   PHP encontrado en: $phpOrigen"
New-Item -ItemType Directory -Path $phpDir -Force | Out-Null
& robocopy $phpOrigen $phpDir /E /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy fallo copiando PHP (codigo $LASTEXITCODE)" }
# Nuestro php.ini reemplaza al de Laragon: activa solo lo que la app necesita.
# Va al lado de php.exe a proposito: asi PHP lo encuentra solo, sin -c, y usa
# su propia carpeta ext sin rutas absolutas que se romperian al instalar.
Get-ChildItem $phpDir -Filter 'php.ini*' | Remove-Item -Force
Copy-Item (Join-Path $PSScriptRoot 'plantilla\php.ini') (Join-Path $phpDir 'php.ini') -Force

# PHP para Windows necesita el runtime de Visual C++, que una PC recien
# formateada puede no tener. Microsoft permite llevar estas DLL al lado del
# ejecutable, y asi no hay que instalar nada aparte.
foreach ($dll in 'vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll') {
    $sistema = Join-Path $env:WINDIR "System32\$dll"
    if ((Test-Path $sistema) -and -not (Test-Path (Join-Path $phpDir $dll))) {
        Copy-Item $sistema $phpDir
    }
}

Paso '6/7' 'Probando la copia con su propio PHP'
$php = Join-Path $phpDir 'php.exe'
$modulos = & $php -m
foreach ($ext in 'pdo_sqlite', 'mbstring', 'openssl', 'fileinfo', 'gd', 'curl', 'zip') {
    if ($modulos -notcontains $ext) { throw "Al PHP portable le falta la extension $ext" }
}
Push-Location $appDir
& $php artisan about --only=environment | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'La app no arranca con el PHP portable' }
& $php artisan optimize:clear | Out-Null
Pop-Location
# Cualquier archivo que haya dejado la prueba no viaja.
Get-ChildItem (Join-Path $appDir 'storage\logs') -Filter '*.log' -ErrorAction SilentlyContinue | Remove-Item -Force

Copy-Item (Join-Path $PSScriptRoot 'plantilla\Iniciar PC Store Pro.bat') $destino -Force
Copy-Item (Join-Path $PSScriptRoot 'plantilla\Detener PC Store Pro.bat') $destino -Force
Copy-Item (Join-Path $PSScriptRoot 'plantilla\LEEME.txt') $destino -Force

$tam = [math]::Round((Get-ChildItem $destino -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Host "`nCarpeta lista: $destino  ($tam MB)" -ForegroundColor Green

Paso '7/7' 'Compilando el instalador'
$iscc = @(
    (Get-Command iscc -ErrorAction SilentlyContinue).Source,
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1

if (-not $iscc) {
    Write-Host 'No encontre Inno Setup 6. Instalalo desde https://jrsoftware.org/isdl.php' -ForegroundColor Yellow
    Write-Host 'y volve a correr este script, o abri instalador\pc-store-pro.iss y compila (F9).'
    exit 0
}

& $iscc /Q (Join-Path $PSScriptRoot 'pc-store-pro.iss')
if ($LASTEXITCODE -ne 0) { throw 'Inno Setup no pudo compilar el instalador' }
Write-Host "`nInstalador: $(Join-Path $proyecto 'dist\PCStorePro-Setup.exe')" -ForegroundColor Green
