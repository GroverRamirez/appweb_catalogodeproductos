@echo off
title PC Store Pro
cd /d "%~dp0"

set "PHP=%~dp0php\php.exe"
set "APP=%~dp0app"

if not exist "%PHP%" (
    echo.
    echo   No se encontro PHP dentro de la carpeta.
    echo   La instalacion esta incompleta: volve a copiarla completa.
    echo.
    pause
    exit /b 1
)

echo.
echo   ============================================
echo      PC Store Pro se esta iniciando...
echo.
echo      NO CIERRES ESTA VENTANA mientras uses
echo      la tienda. Al cerrarla, se apaga.
echo   ============================================
echo.

rem Primera vez en esta computadora: pone la base al dia y deja una marca
rem para no repetirlo en cada arranque.
if not exist "%APP%\storage\instalado.txt" (
    echo   Preparando la base de datos, tarda unos segundos...
    "%PHP%" "%APP%\artisan" migrate --force
    if errorlevel 1 (
        echo.
        echo   No se pudo preparar la base de datos.
        pause
        exit /b 1
    )
    echo instalado > "%APP%\storage\instalado.txt"
)

rem El navegador se abre unos segundos despues, cuando el servidor ya responde.
start "" /min cmd /c "ping -n 4 127.0.0.1 >nul && explorer http://127.0.0.1:8020"

"%PHP%" "%APP%\artisan" serve --host=127.0.0.1 --port=8020

echo.
echo   La tienda se detuvo. Ya podes cerrar esta ventana.
pause
