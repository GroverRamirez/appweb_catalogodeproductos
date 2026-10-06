@echo off
title Detener PC Store Pro

echo.
echo   Deteniendo PC Store Pro...
echo.
echo   Nota: esto cierra todos los procesos PHP de la computadora.
echo   En una PC de prueba no hay otros; en una PC de desarrollo,
echo   mejor cerra la ventana negra de la tienda en vez de usar esto.
echo.
pause

taskkill /F /IM php.exe >nul 2>&1

echo   Listo.
timeout /t 2 /nobreak >nul
