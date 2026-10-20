@echo off
title PC Store Pro
cd /d "%~dp0"

set "PHP=%~dp0php\php.exe"
set "APP=%~dp0app"
set "URL=http://127.0.0.1:8020"

if not exist "%PHP%" (
    echo.
    echo   No se encontro PHP dentro de la carpeta.
    echo   La instalacion esta incompleta: volve a correr el instalador.
    echo.
    pause
    exit /b 1
)

if not exist "%APP%\database\database.sqlite" (
    echo.
    echo   La tienda no termino de instalarse: falta la base de datos.
    echo   Volve a correr el instalador.
    echo.
    pause
    exit /b 1
)

rem Si la tienda ya esta abierta, solo se abre el navegador.
netstat -ano | findstr /R /C:"127.0.0.1:8020 .*LISTENING" >nul
if not errorlevel 1 (
    start "" "%URL%"
    exit /b 0
)

echo.
echo   ============================================
echo      PC Store Pro se esta iniciando...
echo.
echo      NO CIERRES ESTA VENTANA mientras uses
echo      la tienda. Al cerrarla, se apaga.
echo   ============================================
echo.

rem Pone la base al dia por si alguna actualizacion trajo cambios. Si no
rem hay nada pendiente no hace nada.
"%PHP%" "%APP%\artisan" migrate --force --no-interaction >nul 2>&1

rem Cotizacion del dolar del dia: en un servidor la pide el programador de
rem tareas; aca se pide al abrir. Sin internet, sigue la ultima cargada.
start "" /b "%PHP%" "%APP%\artisan" tipo-cambio:actualizar >nul 2>&1

rem El navegador se abre unos segundos despues, cuando el servidor ya responde.
start "" /min cmd /c "ping -n 4 127.0.0.1 >nul && start "" %URL%"

"%PHP%" "%APP%\artisan" serve --host=127.0.0.1 --port=8020

echo.
echo   La tienda se detuvo. Ya podes cerrar esta ventana.
pause
