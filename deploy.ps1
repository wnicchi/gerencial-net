# deploy.ps1 — Despliegue de GERENCIAL.NET en el servidor de produccion (IIS + Laravel public).
# Uso:  cd C:\inetpub\gerencial-net ;  .\deploy.ps1
#
# Sincroniza el repo con GitHub y copia el frontend compilado (frontend\dist) a
# backend\public. Copia el CONTENIDO de assets (\*) para evitar el anidado
# assets\assets que produce Copy-Item de carpetas.

$ErrorActionPreference = 'Stop'
$raiz = $PSScriptRoot
Set-Location $raiz

Write-Host "== 1) Sincronizando con GitHub (origin/main) ==" -ForegroundColor Cyan
# -c gc.auto=0 evita el auto-gc que en Windows Server suele fallar al borrar
# packs viejos ("Unlink of file .git/objects/pack/*.idx failed").
git -c gc.auto=0 fetch origin
git -c gc.auto=0 reset --hard origin/main
$head = (git rev-parse --short HEAD)
Write-Host "   HEAD: $head"

$distAssets = Join-Path $raiz 'frontend\dist\assets'
$distIndex  = Join-Path $raiz 'frontend\dist\index.html'
$pubDir     = Join-Path $raiz 'backend\public'
$pubAssets  = Join-Path $pubDir 'assets'

if (-not (Test-Path $distIndex)) {
    Write-Host "ERROR: no existe $distIndex. El commit no trae el dist compilado (revisar 'git add -f frontend/dist')." -ForegroundColor Red
    exit 1
}

Write-Host "== 2) Copiando frontend\dist -> backend\public ==" -ForegroundColor Cyan
if (Test-Path $pubAssets) { Remove-Item $pubAssets -Recurse -Force -ErrorAction SilentlyContinue }
New-Item -ItemType Directory -Force $pubAssets | Out-Null
Copy-Item -Path (Join-Path $distAssets '*') -Destination $pubAssets -Recurse -Force
Copy-Item -Path $distIndex -Destination (Join-Path $pubDir 'index.html') -Force
foreach ($f in @('favicon.ico', 'favicon.png', 'favicon.svg')) {
    $src = Join-Path $raiz "frontend\dist\$f"
    if (Test-Path $src) { Copy-Item $src (Join-Path $pubDir $f) -Force }
}

Write-Host "== 3) Limpiando cache de Laravel (config + rutas + app) ==" -ForegroundColor Cyan
$artisan = Join-Path $raiz 'backend\artisan'
php $artisan config:clear
# route:clear es clave cuando el deploy agrega/renombra rutas (si estan cacheadas
# con route:cache, las nuevas no se reconocen hasta limpiar).
php $artisan route:clear
php $artisan cache:clear

$jsRef = (Select-String -Path (Join-Path $pubDir 'index.html') -Pattern 'assets/index-[^"]+\.js').Matches.Value
$ok = if ($jsRef) { Test-Path (Join-Path $pubDir ($jsRef -replace '/','\')) } else { $false }
Write-Host ""
if ($ok) {
    Write-Host "OK - Deploy completo (HEAD $head). El asset $jsRef esta publicado." -ForegroundColor Green
    Write-Host "   En el navegador hacer Ctrl+F5 para saltear el cache." -ForegroundColor Green
} else {
    Write-Host "ATENCION: el index.html apunta a '$jsRef' pero no se encontro el archivo en backend\public. Revisar." -ForegroundColor Red
}
