@echo off
title Instalar Tablero Gerencial (https://gerencia)

:: ==========================================================================
::  instalar-terminal.bat  -  GERENCIAL.NET
::  Doble clic -> pide permiso de Administrador (UAC) -> ejecuta el instalador
::  PowerShell (instalar-terminal.ps1) que debe estar en la MISMA carpeta.
:: ==========================================================================

:: 1) Si no somos administrador, relanzarse elevado (aparece el cartel de UAC)
net session >nul 2>&1
if %errorlevel% neq 0 (
  echo Solicitando permisos de administrador...
  powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

:: 2) Ya somos administrador: correr el script PowerShell de al lado
if not exist "%~dp0instalar-terminal.ps1" (
  echo No se encontro instalar-terminal.ps1 en esta carpeta.
  echo Copia los DOS archivos juntos: instalar-terminal.bat y instalar-terminal.ps1
  pause
  exit /b 1
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-terminal.ps1"
