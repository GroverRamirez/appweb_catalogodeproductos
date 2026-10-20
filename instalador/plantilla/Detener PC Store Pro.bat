@echo off
title Detener PC Store Pro

rem Cierra solo el PHP de esta carpeta: si en la PC hay otros programas con
rem PHP (por ejemplo Laragon), no se tocan.
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-Process php -ErrorAction SilentlyContinue | Where-Object { $_.Path -like '%~dp0*' } | Stop-Process -Force"

echo.
echo   PC Store Pro se detuvo.
timeout /t 2 /nobreak >nul
