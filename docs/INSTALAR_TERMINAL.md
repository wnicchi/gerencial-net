# Instalar GERENCIAL en una terminal

Deja la PC lista para entrar por **`https://gerencia`** (con candado 🔒) y crea un
acceso directo **"Tablero Gerencial"** en el Escritorio. Se hace **una sola vez por
terminal**.

> **Requisito:** ejecutar como **Administrador** (modifica el `hosts` y el almacén de
> certificados del equipo).
> **Server IP = `192.168.1.101`** (server2026). Ajustar si cambia.

---

## Opción A — El script (recomendado)

El instalador está en la raíz del repo: **`instalar-terminal.ps1`**.

1. Copiá `instalar-terminal.ps1` a la terminal (pendrive o carpeta de red).
2. **Clic derecho** → **Ejecutar con PowerShell** (si pregunta, aceptá elevar a
   administrador).
3. Hace todo solo:
   - Agrega `gerencia` al `hosts` apuntando al servidor.
   - Instala el certificado del sitio en *Entidades de certificación raíz de confianza*.
   - Crea el acceso directo **Tablero Gerencial** en el Escritorio.
4. Doble clic en **Tablero Gerencial** → entrás con tu usuario de **GESTIÓN**.

---

## Opción B — Una sola línea (PowerShell **como Administrador**)

> Pegar bloques multilínea suele **comerse los saltos de línea** y romper. Por eso
> este comando va en **UNA sola línea** (con `;` entre cada paso): aunque se pegue
> todo junto, funciona. Copiala **entera** y pegala en PowerShell como Administrador.

```powershell
$IP="192.168.1.101"; $H="gerencia"; $hosts="$env:WINDIR\System32\drivers\etc\hosts"; if(-not (Select-String -Path $hosts -Pattern "\b$H\b" -Quiet)){ Add-Content $hosts "`r`n$IP $H" }; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; [Net.ServicePointManager]::ServerCertificateValidationCallback={ $true }; $req=[Net.HttpWebRequest]::Create("https://$H"); try{$req.GetResponse().Dispose()}catch{}; $cer="$env:TEMP\$H.cer"; [IO.File]::WriteAllBytes($cer,$req.ServicePoint.Certificate.Export('Cert')); Import-Certificate -FilePath $cer -CertStoreLocation Cert:\LocalMachine\Root | Out-Null; [Net.ServicePointManager]::ServerCertificateValidationCallback=$null; $chrome=(Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\chrome.exe" -EA SilentlyContinue).'(default)'; if(-not $chrome){$chrome="C:\Program Files\Google\Chrome\Application\chrome.exe"}; $lnk=[Environment]::GetFolderPath("Desktop")+"\Tablero Gerencial.lnk"; $s=(New-Object -ComObject WScript.Shell).CreateShortcut($lnk); $s.TargetPath=$chrome; $s.Arguments="--app=https://$H"; $s.IconLocation="$chrome,0"; $s.Save(); Write-Host "Listo. Abri 'Tablero Gerencial' del Escritorio." -ForegroundColor Green
```

---

## Verificar

- `ping gerencia` → responde `192.168.1.101`.
- Abrir **`https://gerencia`** (o el acceso directo) → carga el login **con candado**,
  sin aviso de certificado.

---

## Notas

- **DNS interno:** si la red tiene DNS, en vez del `hosts` en cada PC se puede cargar
  `gerencia → 192.168.1.101` una sola vez para toda la red; el script entonces solo
  instala el certificado + el acceso directo.
- **Edge en vez de Chrome:** el `hosts` + certificado sirven igual; solo cambia el
  acceso directo (o se abre `https://gerencia` desde Edge).
- El acceso directo abre en **modo app** (`--app`), ventana limpia sin barra de
  direcciones. Para pestaña normal, quitar `--app=` y dejar solo la URL.
- **Respaldo:** el sitio también responde por `http://server2026:2029` (sin candado)
  si en alguna terminal no se pudo instalar el certificado.

---

## Contexto (para referencia)

- Sitio IIS **GERENCIAL** en el servidor (puerto 2029) con binding
  `https *:443:gerencia` (SNI, convive con `rrhhweb`).
- El login es contra el **sistema de GESTIÓN** (base `sqlLOGIST`): se entra con el
  usuario/clave de GESTIÓN.
- Deploy/actualizaciones del servidor: ver `deploy.ps1` en la raíz.
