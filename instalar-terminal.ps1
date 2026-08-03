# ============================================================================
#  instalar-terminal.ps1  —  GERENCIAL.NET (Tablero Gerencial)
#  Deja una TERMINAL lista para entrar por  https://gerencia  (con candado).
#
#  Hace 3 cosas:
#    1) Agrega 'gerencia' al archivo hosts apuntando al servidor.
#    2) Instala el certificado del sitio en "Entidades de certificacion raiz
#       de confianza" (asi el navegador no muestra aviso).
#    3) Crea un acceso directo "Tablero Gerencial" en el Escritorio (Chrome).
#
#  USO:  clic derecho sobre este archivo -> "Ejecutar con PowerShell"
#        (debe correr como ADMINISTRADOR). Si pide permiso, aceptar.
#
#  Si el servidor tiene otra IP, cambiar el valor de $IP mas abajo.
# ============================================================================

$IP     = "192.168.1.101"   # <-- IP del servidor (server2026). Ajustar si cambia.
$HOST_N = "gerencia"        # nombre corto del sitio

# --- Requiere Administrador (hosts + certificado en LocalMachine) ---
$esAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $esAdmin) {
  Write-Host "Este script debe ejecutarse como ADMINISTRADOR." -ForegroundColor Red
  Write-Host "Cerra esta ventana y volve a abrirlo con clic derecho -> Ejecutar como administrador." -ForegroundColor Yellow
  Read-Host "Enter para salir"; exit 1
}

Write-Host "== 1) hosts: $HOST_N -> $IP ==" -ForegroundColor Cyan
$hostsFile = "$env:WINDIR\System32\drivers\etc\hosts"
if (Select-String -Path $hostsFile -Pattern "\b$HOST_N\b" -Quiet) {
  Write-Host "   Ya estaba en hosts. OK."
} else {
  Add-Content $hostsFile "`r`n$IP $HOST_N"
  Write-Host "   Agregado."
}

Write-Host "== 2) Certificado del sitio en Raiz de confianza ==" -ForegroundColor Cyan
try {
  [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
  [Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
  $req = [Net.HttpWebRequest]::Create("https://$HOST_N")
  try { $req.GetResponse().Dispose() } catch {}
  $cert  = $req.ServicePoint.Certificate
  $bytes = $cert.Export([Security.Cryptography.X509Certificates.X509ContentType]::Cert)
  $cer   = "$env:TEMP\$HOST_N.cer"
  [IO.File]::WriteAllBytes($cer, $bytes)
  Import-Certificate -FilePath $cer -CertStoreLocation Cert:\LocalMachine\Root | Out-Null
  Write-Host "   Certificado instalado."
} catch {
  Write-Host "   No se pudo instalar el certificado automaticamente: $($_.Exception.Message)" -ForegroundColor Yellow
} finally {
  [Net.ServicePointManager]::ServerCertificateValidationCallback = $null
}

Write-Host "== 3) Acceso directo en el Escritorio ==" -ForegroundColor Cyan
$chrome = (Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\chrome.exe" -ErrorAction SilentlyContinue).'(default)'
if (-not $chrome -or -not (Test-Path $chrome)) { $chrome = "C:\Program Files\Google\Chrome\Application\chrome.exe" }
if (Test-Path $chrome) {
  $lnk = [Environment]::GetFolderPath("Desktop") + "\Tablero Gerencial.lnk"
  $ws  = New-Object -ComObject WScript.Shell
  $s   = $ws.CreateShortcut($lnk)
  $s.TargetPath   = $chrome
  $s.Arguments    = "--app=https://$HOST_N"
  $s.IconLocation = "$chrome,0"
  $s.Description  = "Tablero Gerencial (RRHH + Logistica)"
  $s.Save()
  Write-Host "   Creado: Tablero Gerencial (Escritorio)."
} else {
  Write-Host "   Chrome no encontrado. Abri manualmente https://$HOST_N" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "LISTO. Abri 'Tablero Gerencial' del Escritorio (o https://$HOST_N) y entra con tu usuario de GESTION." -ForegroundColor Green
Read-Host "Enter para cerrar"
