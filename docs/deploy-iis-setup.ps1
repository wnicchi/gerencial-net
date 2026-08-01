<#
=============================================================================
 RRHH.NET — Configuración de IIS en Windows Server (semi-automática)
=============================================================================
 Automatiza la parte REPETITIVA y propensa a errores del despliegue en IIS:
   - Habilita los roles/características de IIS (incluido CGI).
   - Registra PHP como FastCGI y crea el Handler Mapping *.php.
   - Crea el Application Pool, el sitio del frontend y la app /api del backend.
   - Aplica permisos de escritura en storage / bootstrap\cache.
   - Escribe los dos web.config (SPA fallback + front-controller Laravel).
   - Verifica prerrequisitos (PHP, extensiones sqlsrv) y avisa lo que falta.

 NO automatiza (requieren descarga/instalador y decisiones):
   - Instalar PHP 8.3 NTS, los drivers sqlsrv/pdo_sqlsrv, ODBC Driver 17/18.
   - Instalar el módulo URL Rewrite.
   - Certificado HTTPS, cuenta de servicio de SQL, registros DNS/PTR.
   - Compilar el frontend (npm run build) ni el composer install del backend.

 USO (PowerShell COMO ADMINISTRADOR):
   1. Revisar y ajustar la sección PARÁMETROS de abajo.
   2. Ejecutar:  .\deploy-iis-setup.ps1
      Para ver qué haría sin aplicar nada:  .\deploy-iis-setup.ps1 -DryRun

 Este script es idempotente: se puede correr varias veces.
=============================================================================
#>

param(
    [switch]$DryRun
)

# ───────────────────────── PARÁMETROS (ajustar) ─────────────────────────
$SiteName     = 'rrhhnet'
$HostName     = 'rrhh.empresa.local'          # nombre con el que las terminales abren el link
$HttpPort     = 80

$BaseDir      = 'C:\inetpub\rrhhnet'
$FrontendDir  = Join-Path $BaseDir 'public'   # acá va el contenido de frontend/dist
$BackendDir   = Join-Path $BaseDir 'backend'  # acá va la carpeta backend del repo
$BackendPublic= Join-Path $BackendDir 'public'

$PhpDir       = 'C:\php83'
$PhpCgi       = Join-Path $PhpDir 'php-cgi.exe'
$PhpExe       = Join-Path $PhpDir 'php.exe'

$AppPoolName  = 'rrhhnet_pool'
# ─────────────────────────────────────────────────────────────────────────

$ErrorActionPreference = 'Stop'

function Step($msg)  { Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Info($msg)  { Write-Host "    $msg" -ForegroundColor Gray }
function Ok($msg)    { Write-Host "    OK: $msg" -ForegroundColor Green }
function Warn($msg)  { Write-Host "    AVISO: $msg" -ForegroundColor Yellow }
function Do($desc, [scriptblock]$action) {
    if ($DryRun) { Write-Host "    [DRY-RUN] $desc" -ForegroundColor DarkYellow }
    else         { & $action; Ok $desc }
}

# 0) Admin check
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()
        ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ejecutá este script en PowerShell COMO ADMINISTRADOR.'
}

# 1) Habilitar características de IIS (incluido CGI)
Step '1. Características de IIS'
$features = @(
    'IIS-WebServerRole','IIS-WebServer','IIS-CommonHttpFeatures','IIS-StaticContent',
    'IIS-DefaultDocument','IIS-HttpErrors','IIS-CGI','IIS-RequestFiltering',
    'IIS-HttpCompressionStatic','IIS-ManagementConsole'
)
foreach ($f in $features) {
    $state = (Get-WindowsOptionalFeature -Online -FeatureName $f -ErrorAction SilentlyContinue).State
    if ($state -eq 'Enabled') { Info "$f ya habilitado" }
    else { Do "Habilitar $f" { Enable-WindowsOptionalFeature -Online -FeatureName $f -All -NoRestart | Out-Null } }
}

Import-Module WebAdministration -ErrorAction Stop

# 2) Verificar prerrequisitos que NO instala este script
Step '2. Verificación de prerrequisitos'
if (Test-Path $PhpCgi) { Ok "PHP encontrado en $PhpDir" }
else { Warn "No existe $PhpCgi. Instalá PHP 8.3 NTS x64 antes de continuar." }

if (Test-Path $PhpExe) {
    $mods = & $PhpExe -m 2>$null
    foreach ($ext in @('sqlsrv','pdo_sqlsrv')) {
        if ($mods -contains $ext) { Ok "Extensión $ext cargada" }
        else { Warn "Falta la extensión $ext (revisá php.ini y los DLL NTS x64 de PHP 8.3)." }
    }
}
# URL Rewrite
$rewriteOk = Test-Path 'HKLM:\SOFTWARE\Microsoft\IIS Extensions\URL Rewrite'
if ($rewriteOk) { Ok 'URL Rewrite instalado' } else { Warn 'Falta el módulo URL Rewrite (instalalo de iis.net).' }

# 3) Registrar PHP como FastCGI + Handler Mapping
Step '3. PHP FastCGI'
if (Test-Path $PhpCgi) {
    $fcgi = Get-WebConfiguration "system.webServer/fastCgi/application[@fullPath='$PhpCgi']" -PSPath 'IIS:\'
    if (-not $fcgi) {
        Do 'Agregar aplicación FastCGI para php-cgi.exe' {
            Add-WebConfiguration 'system.webServer/fastCgi' -PSPath 'IIS:\' -Value @{ fullPath = $PhpCgi } | Out-Null
            Set-WebConfigurationProperty "system.webServer/fastCgi/application[@fullPath='$PhpCgi']" `
                -PSPath 'IIS:\' -Name instanceMaxRequests -Value 10000
        }
    } else { Info 'FastCGI ya registrado' }
} else { Warn 'Salteo FastCGI: PHP no está instalado todavía.' }

# 4) Application Pool (No Managed Code)
Step '4. Application Pool'
if (-not (Test-Path "IIS:\AppPools\$AppPoolName")) {
    Do "Crear App Pool $AppPoolName (No Managed Code)" {
        New-WebAppPool -Name $AppPoolName | Out-Null
        Set-ItemProperty "IIS:\AppPools\$AppPoolName" -Name managedRuntimeVersion -Value ''
    }
} else { Info "App Pool $AppPoolName ya existe" }

# 5) Carpetas
Step '5. Carpetas'
foreach ($d in @($BaseDir,$FrontendDir,$BackendDir)) {
    if (-not (Test-Path $d)) { Do "Crear $d" { New-Item -ItemType Directory -Path $d -Force | Out-Null } }
    else { Info "$d ya existe" }
}
Warn "Recordá copiar: frontend/dist -> $FrontendDir   y   backend/ -> $BackendDir"

# 6) Sitio del FRONTEND
Step '6. Sitio del frontend'
if (-not (Test-Path "IIS:\Sites\$SiteName")) {
    Do "Crear sitio $SiteName ($HostName:$HttpPort)" {
        New-Website -Name $SiteName -PhysicalPath $FrontendDir -ApplicationPool $AppPoolName `
            -HostHeader $HostName -Port $HttpPort | Out-Null
    }
} else { Info "Sitio $SiteName ya existe" }

# 7) Aplicación /api del BACKEND
Step '7. Aplicación /api (backend Laravel)'
if (Test-Path "IIS:\Sites\$SiteName") {
    $app = Get-WebApplication -Site $SiteName -Name 'api' -ErrorAction SilentlyContinue
    if (-not $app) {
        Do "Crear app /api -> $BackendPublic" {
            New-WebApplication -Site $SiteName -Name 'api' -PhysicalPath $BackendPublic `
                -ApplicationPool $AppPoolName | Out-Null
        }
    } else { Info 'App /api ya existe' }
}

# 8) Permisos de escritura para IIS en storage y bootstrap\cache
Step '8. Permisos de escritura'
foreach ($p in @((Join-Path $BackendDir 'storage'), (Join-Path $BackendDir 'bootstrap\cache'))) {
    if (Test-Path $p) {
        Do "Otorgar Modify a IIS_IUSRS en $p" {
            icacls $p /grant 'IIS_IUSRS:(OI)(CI)M' /T | Out-Null
        }
    } else { Warn "No existe aún $p (se crea al copiar el backend)." }
}

# 9) Escribir los web.config
Step '9. web.config'
$frontWebConfig = @'
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="SPA Fallback" stopProcessing="true">
          <match url=".*" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
            <add input="{REQUEST_URI}" pattern="^/api/" negate="true" />
          </conditions>
          <action type="Rewrite" url="/index.html" />
        </rule>
      </rules>
    </rewrite>
    <staticContent>
      <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
    </staticContent>
  </system.webServer>
</configuration>
'@

$backWebConfig = @'
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="Laravel Front Controller" stopProcessing="true">
          <match url="^(.*)$" ignoreCase="false" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
          </conditions>
          <action type="Rewrite" url="index.php" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
'@

if (Test-Path $FrontendDir) {
    Do "Escribir $FrontendDir\web.config" { Set-Content -Path (Join-Path $FrontendDir 'web.config') -Value $frontWebConfig -Encoding UTF8 }
}
if (Test-Path $BackendPublic) {
    Do "Escribir $BackendPublic\web.config" { Set-Content -Path (Join-Path $BackendPublic 'web.config') -Value $backWebConfig -Encoding UTF8 }
} else { Warn "No existe $BackendPublic todavía (copiá el backend y reejecutá para el web.config)." }

# 10) Cierre
Step 'Resumen / pasos manuales pendientes'
Write-Host @"
    Falta hacer a mano (ver docs/deploy-iis.md):
      - Instalar PHP 8.3 NTS, drivers sqlsrv/pdo_sqlsrv (NTS x64) y ODBC Driver 17/18.
      - Instalar URL Rewrite si el AVISO de arriba lo marcó.
      - Copiar frontend/dist -> $FrontendDir  y  backend -> $BackendDir.
      - composer install --no-dev, configurar .env de producción, php artisan key:generate + config:cache.
      - Correr backend\database\sql\produccion_cambios.sql en SQL Server.
      - Agregar el binding HTTPS y el certificado.
      - Registrar $HostName en el DNS de la red para que las terminales lo resuelvan.
"@ -ForegroundColor Yellow
Write-Host "`nListo.$([string]::new(' ',1))" -ForegroundColor Green
