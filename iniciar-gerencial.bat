@echo off
title GERENCIAL.NET - Iniciando Tablero Gerencial...
color 0A

echo.
echo  ==========================================
echo   GERENCIAL.NET - Tablero Gerencial
echo   (RRHH + Stock/Logistica, solo lectura)
echo  ==========================================
echo.

:: Nota: la base es SQL Server (servicio de Windows, siempre activo).
:: El backend DEBE correr en una ventana cmd real (Windows Auth / SSPI).

:: 1. Backend Laravel (puerto 8030) - ventana interactiva
echo  [1/2] Iniciando backend Laravel (puerto 8030)...
start "GERENCIAL - Backend" cmd /k "cd /d C:\Users\nicch\Claude\Projects\GERENCIAL.NET\backend && php artisan serve --port=8030"
ping -n 3 127.0.0.1 >nul
echo        Backend listo.

:: 2. Frontend Vite (puerto 5176)
echo  [2/2] Iniciando frontend Vite (puerto 5176)...
start "GERENCIAL - Frontend" cmd /k "cd /d C:\Users\nicch\Claude\Projects\GERENCIAL.NET\frontend && npm run dev"
ping -n 5 127.0.0.1 >nul
echo        Frontend listo.

:: Abrir Chrome (usar Chrome real: el navegador interno bloquea el puerto de la API)
echo.
echo  Abriendo Chrome...
start "" "chrome.exe" "http://localhost:5176/login"

echo.
echo  ==========================================
echo   Todo listo!
echo.
echo   Frontend : http://localhost:5176
echo   Backend  : http://localhost:8030
echo   Bases    : sqlRRHHlog + LOGIST_UNIVERSAL (WALTER-PC)
echo  ==========================================
echo.
echo  No cierres las ventanas de Backend
echo  y Frontend mientras trabajas.
echo.
pause
