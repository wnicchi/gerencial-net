# Despliegue de RRHH.NET en IIS (Windows Server) — Tutorial paso a paso

> **Estado:** borrador de planificación. Todavía no se ejecuta; sirve para tener
> el camino claro cuando llegue el momento de montar el servidor.

Este documento describe cómo publicar el proyecto en un **Windows Server con IIS**,
para que las terminales accedan desde el navegador por un link.

---

## 0. Arquitectura del despliegue

```
                         WINDOWS SERVER (IIS)
   ┌─────────────────────────────────────────────────────────┐
   │  Sitio FRONTEND  (Vue compilado → archivos estáticos)    │
   │      http://rrhh.empresa.local/                          │
   │                                                          │
   │  Sitio/App BACKEND (Laravel vía PHP FastCGI)             │
   │      http://rrhh.empresa.local/api  → backend/public     │
   └───────────────┬──────────────────────────────────────────┘
                   │  (driver sqlsrv / ODBC 17)
                   ▼
        SQL SERVER  (sqlRRHH  +  DOCUMENTOS_DIGITALES)
                   ▲
   Terminales ─────┘  (sólo navegador, nada instalado)
```

Componentes del repo:
- **backend/** → API Laravel 13 (PHP 8.3). Se sirve desde `backend/public`.
- **frontend/** → SPA Vue 3 + Vite. Se **compila** (`npm run build`) a `frontend/dist`
  y se publican esos archivos estáticos.
- **Base de datos**: dos conexiones SQL Server — `sqlRRHH` (datos) y
  `DOCUMENTOS_DIGITALES` (archivos/fotos).

**Decisión clave de topología (recomendada):** un solo host (`rrhh.empresa.local`)
con el frontend en la raíz `/` y el backend como **aplicación IIS** bajo `/api`.
Así no hay problemas de CORS ni de cookies, y `VITE_API_URL` queda simplemente `/api`.

### Flujo de un request

```
 Terminal (navegador)
      │  http://rrhh.empresa.local/...
      ▼
 ┌──────────────────────── IIS (Windows Server) ────────────────────────┐
 │   ¿la URL empieza con /api ?                                          │
 │        │ NO                      │ SÍ                                 │
 │        ▼                         ▼                                    │
 │  Sitio FRONTEND            App /api  → backend\public                 │
 │  (archivos dist)           web.config reescribe todo a index.php      │
 │  web.config: si no es            │                                    │
 │  archivo real → index.html       ▼                                    │
 │  (modo history del SPA)    PHP 8.3 FastCGI (php-cgi.exe)              │
 │        │                         │  driver sqlsrv / ODBC 17           │
 └────────┼─────────────────────────┼───────────────────────────────────┘
          ▼                         ▼
   index.html + assets    SQL SERVER: sqlRRHH + DOCUMENTOS_DIGITALES
```

> **Automatización:** el script `docs/deploy-iis-setup.ps1` configura la parte de IIS
> (features+CGI, FastCGI de PHP, App Pool, sitio, app `/api`, permisos y los dos
> `web.config`). Es idempotente y tiene modo `-DryRun`. Lo que requiere instalador o
> decisión manual (PHP, drivers, URL Rewrite, certificado, DNS) lo lista al final.

---

## 1. Requisitos en el servidor

Versiones que deben **coincidir con desarrollo**:
- **PHP 8.3 x64** — en el server usar el build **Non-Thread-Safe (NTS)** (lo que pide IIS+FastCGI).
- **Laravel 13** (ya viene en el repo; no se instala aparte).
- **Composer** (para `composer install` en el server, o subir `vendor/` ya armado).
- **Node.js 20+** — sólo si se compila el frontend en el server (se puede compilar en la PC de dev y subir `dist`).

Software a instalar en el Windows Server:
1. **Rol Web Server (IIS)** con la característica **CGI** habilitada.
2. **URL Rewrite Module** (de Microsoft).
3. **PHP 8.3 NTS x64** + `php.ini` configurado (sección 3).
4. **Microsoft ODBC Driver 17 (o 18) for SQL Server**.
5. **Microsoft Drivers for PHP for SQL Server** (`php_sqlsrv` + `php_pdo_sqlsrv`), build **NTS x64 para PHP 8.3**.
6. **Visual C++ Redistributable** (el que pидан los drivers ODBC/PHP).

---

## 2. Instalar IIS + CGI + URL Rewrite

1. **Server Manager → Add Roles and Features → Web Server (IIS)**.
   - En *Application Development* marcar **CGI** (necesario para FastCGI de PHP).
   - Dejar *Static Content*, *Default Document*, *HTTP Errors*.
2. Instalar **URL Rewrite** (descarga de iis.net/downloads → "URL Rewrite Module 2.1").
   Es imprescindible para el front-controller de Laravel y para el modo *history* del SPA.
3. (Opcional pero recomendado) **Application Request Routing (ARR)** sólo si se va a
   usar proxy inverso; con la topología de la sección 0 **no hace falta**.

---

## 3. Instalar y configurar PHP (FastCGI)

1. Descargar **PHP 8.3 NTS x64** y descomprimir en, por ej., `C:\php83`.
2. Crear `C:\php83\php.ini` (copiar de `php.ini-production`) y habilitar extensiones:
   ```ini
   extension_dir = "ext"
   extension=openssl
   extension=mbstring
   extension=fileinfo
   extension=curl
   extension=pdo_sqlsrv
   extension=sqlsrv
   extension=gd          ; si se generan imágenes
   ; Ajustes recomendados
   cgi.fix_pathinfo=1
   upload_max_filesize=20M
   post_max_size=25M
   max_execution_time=120
   date.timezone="America/Argentina/Buenos_Aires"
   ```
3. Copiar `php_sqlsrv_83_nts_x64.dll` y `php_pdo_sqlsrv_83_nts_x64.dll` a `C:\php83\ext`
   (deben ser **NTS** y para **8.3**, o el `extension=` falla en silencio).
4. Verificar desde consola del server:
   ```
   C:\php83\php.exe -v
   C:\php83\php.exe -m   (deben aparecer sqlsrv y pdo_sqlsrv)
   ```
5. Registrar PHP en IIS como **FastCGI**:
   - **IIS Manager → (servidor) → FastCGI Settings → Add Application**
     - Full Path: `C:\php83\php-cgi.exe`
   - Recomendado en esa entrada: `InstanceMaxRequests=10000` y variable de entorno
     `PHP_FCGI_MAX_REQUESTS=10000` (evita fugas de memoria reciclando el proceso).
   - **Handler Mappings → Add Module Mapping**:
     - Request path: `*.php`
     - Module: `FastCgiModule`
     - Executable: `C:\php83\php-cgi.exe`
     - Name: `PHP_FastCGI`

---

## 4. Conectividad con SQL Server

1. En **SQL Server Configuration Manager**: habilitar **TCP/IP**, puerto **1433**, reiniciar el servicio.
2. **Firewall**: permitir 1433 entrante si SQL está en otra máquina.
3. **Autenticación** — dos opciones:
   - **SQL Auth (más simple):** crear un login SQL con permisos sobre `sqlRRHH` y
     `DOCUMENTOS_DIGITALES`. En el `.env` poner `DB_USERNAME`/`DB_PASSWORD` y
     `SQLSRV_USERNAME`/`SQLSRV_PASSWORD`.
   - **Windows Auth (trusted):** dejar usuario/clave vacíos. Entonces la **identidad
     del Application Pool** de IIS debe tener acceso a las bases. Configurar el App Pool
     con una **cuenta de dominio de servicio** y darle permisos en SQL Server.
4. Si se usa **ODBC Driver 18**, exige cifrado por defecto: agregar `;Encrypt=no` o
   `;TrustServerCertificate=yes` en la conexión, o instalar un certificado. (El Driver 17
   no lo exige — es el que ya se usa en desarrollo.)

> El proyecto usa **dos** conexiones: `sqlsrv_rrhh` (sqlRRHH) y `documentos`
> (DOCUMENTOS_DIGITALES). El login/cuenta debe poder leer/escribir en **ambas**.

---

## 5. Desplegar el backend (Laravel)

1. Copiar la carpeta `backend/` al server, por ej. `C:\inetpub\rrhhnet\backend`.
2. Instalar dependencias de producción:
   ```
   cd C:\inetpub\rrhhnet\backend
   composer install --no-dev --optimize-autoloader
   ```
   (o subir `vendor/` ya generado con la misma versión de PHP).
3. Crear el `.env` de **producción** (no copiar el de dev tal cual). Mínimo:
   ```ini
   APP_NAME=RRHH
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://rrhh.empresa.local
   APP_KEY=            ; se genera en el paso 4

   DB_CONNECTION=sqlsrv_rrhh
   DB_HOST=SERVIDOR_SQL
   DB_PORT=1433
   DB_DATABASE=sqlRRHH
   DB_USERNAME=usuario_sql
   DB_PASSWORD=clave_sql

   SQLSRV_HOST=SERVIDOR_SQL
   SQLSRV_PORT=1433
   SQLSRV_DATABASE=DOCUMENTOS_DIGITALES
   SQLSRV_USERNAME=usuario_sql
   SQLSRV_PASSWORD=clave_sql

   ; Base GESTIÓN (sqlSILCAR) — solapa Tarjetas y futuros módulos
   GESTION_HOST=SERVIDOR_SQL
   GESTION_PORT=1433
   GESTION_DATABASE=sqlSILCAR
   GESTION_USERNAME=usuario_sql
   GESTION_PASSWORD=clave_sql

   ; Asistente IA (API de Claude) — botón 🤖 IA de Empleados
   ANTHROPIC_API_KEY=sk-ant-...
   ANTHROPIC_MODEL=claude-opus-4-8

   SESSION_DRIVER=database
   CACHE_STORE=database

   ; SPA en el MISMO host → sin líos de CORS/cookies
   SANCTUM_STATEFUL_DOMAINS=rrhh.empresa.local
   SESSION_DOMAIN=rrhh.empresa.local

   ; Mail (igual que dev, ajustar credenciales)
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   ```
4. Generar key y cachear configuración (mejor rendimiento):
   ```
   php artisan key:generate
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
   > **Importante:** cada vez que cambie el `.env` hay que volver a correr
   > `php artisan config:cache` (o `config:clear`), porque con la config cacheada
   > Laravel **ignora** el `.env`.
5. **Permisos de escritura** para IIS en:
   - `storage\` (y subcarpetas) y `bootstrap\cache\`
   - Dar **Modify** al usuario del App Pool (`IIS AppPool\<NombreDelPool>`) o a `IIS_IUSRS`.
   ```
   icacls "C:\inetpub\rrhhnet\backend\storage" /grant "IIS_IUSRS:(OI)(CI)M" /T
   icacls "C:\inetpub\rrhhnet\backend\bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)M" /T
   ```
6. **Aplicar los cambios de estructura de BD** (tabla de reservas, índice único, etc.):
   ```
   sqlcmd -S SERVIDOR_SQL -d sqlRRHH -U usuario_sql -P clave_sql -i database\sql\produccion_cambios.sql
   ```
   (ver `backend/database/sql/README.md`).

---

## 6. Publicar el sitio en IIS (frontend en `/`, backend en `/api`)

### 6.1 Compilar el frontend
En la PC de desarrollo (o en el server con Node):
1. Setear la URL de la API en `frontend/.env` (producción):
   ```ini
   VITE_API_URL=/api
   ```
   (relativo → mismo host; evita CORS). Si el backend fuera otro host/puerto,
   acá iría la URL completa, ej. `http://rrhh.empresa.local:8080/api`.
2. Compilar:
   ```
   cd frontend
   npm ci
   npm run build      ; genera frontend/dist
   ```
3. Copiar el contenido de `frontend/dist` a `C:\inetpub\rrhhnet\public`.

### 6.2 Crear el sitio del FRONTEND
- **IIS Manager → Sites → Add Website**
  - Site name: `rrhhnet`
  - Physical path: `C:\inetpub\rrhhnet\public`  (el `dist` del frontend)
  - Binding: `http`, host name `rrhh.empresa.local`, puerto 80.
- Poner este `web.config` en esa carpeta (modo *history* del SPA — todo lo que no
  sea un archivo real cae en `index.html`):
  ```xml
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
        <!-- cache de assets con hash -->
        <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
      </staticContent>
    </system.webServer>
  </configuration>
  ```

### 6.3 Crear la aplicación del BACKEND bajo `/api`
- **IIS Manager → sitio `rrhhnet` → Add Application**
  - Alias: `api`
  - Physical path: `C:\inetpub\rrhhnet\backend\public`  ← **¡la carpeta `public`!**
  - Application pool: uno dedicado, **No Managed Code** (PHP no usa .NET).
- `web.config` en `backend\public` (front-controller de Laravel):
  ```xml
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
  ```
  > Como Laravel queda bajo `/api`, las rutas internas siguen siendo `/api/empleados/...`
  > definidas en `routes/api.php`. Verificar que el `APP_URL` y los links generados
  > respeten el prefijo (las APIs JSON normalmente no dependen de esto).

---

## 7. HTTPS (recomendado)

1. Conseguir un certificado (interno de dominio o público) e importarlo en IIS.
2. Agregar un **binding https (443)** al sitio con ese certificado.
3. Cambiar a `https` en `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` y
   re-cachear config (`php artisan config:cache`).
4. (Opcional) regla de URL Rewrite para forzar redirección http→https.

---

## 8. Auditoría de terminal (historial de cambios)

Recordatorio de cómo se resuelve el nombre de la terminal en este deploy central
(ver `EmpleadoController::nombreTerminal()`):
1. Header **`X-Terminal`** si una terminal lo manda (config manual opcional).
2. **DNS inverso** de la IP del cliente (red Windows con registros PTR).
3. Fallback: la IP.

Para que el DNS inverso funcione, la red debe tener **registros PTR** de las PCs
(habitual en dominio AD con DNS + DHCP que registra los equipos). Si no, se guarda la IP.

**Trusted proxies:** si en algún momento se pone un proxy/balanceador o ARR delante,
configurar `TrustProxies` en Laravel para que `$request->ip()` sea la IP real del
cliente y no la del proxy (si no, todas las terminales se verían iguales).

---

## 9. Tareas programadas y correo

- **Scheduler de Laravel** (si se agregan tareas): crear una tarea en el
  **Programador de tareas de Windows** que ejecute cada minuto:
  ```
  C:\php83\php.exe C:\inetpub\rrhhnet\backend\artisan schedule:run
  ```
- **Colas** (si se usan): el proyecto hoy usa `SESSION_DRIVER=database` y
  `CACHE_STORE=database`; si se agregan jobs en cola, configurar un worker
  (`queue:work`) como servicio o tarea.
- **Correo**: el envío SMTP (recuperación de clave, códigos) requiere que el server
  tenga salida al puerto SMTP configurado.

---

## 10. Checklist de verificación

- [ ] `C:\php83\php.exe -m` lista `sqlsrv` y `pdo_sqlsrv`.
- [ ] Abrir `http://rrhh.empresa.local/api/empleados/opciones` devuelve JSON (no error 500).
- [ ] Abrir `http://rrhh.empresa.local/` carga el SPA y permite login.
- [ ] Navegar a una ruta interna y recargar (F5) no da 404 (regla SPA OK).
- [ ] Crear/editar un empleado funciona y el **historial** guarda usuario (NOMBRE) y terminal.
- [ ] `produccion_cambios.sql` aplicado (tabla `codigo_reserva` + índice `UX_personal_PER_COD`).
- [ ] Permisos de escritura en `storage` y `bootstrap/cache`.
- [ ] `APP_DEBUG=false` en producción.

---

## 11. Problemas comunes (troubleshooting)

| Síntoma | Causa probable | Solución |
|---|---|---|
| HTTP 500 en blanco al abrir `/api` | falta permiso en `storage`/`bootstrap/cache`, o `APP_KEY` vacío | dar Modify a IIS_IUSRS; `php artisan key:generate` |
| `could not find driver` | `sqlsrv`/`pdo_sqlsrv` no cargan | DLLs NTS x64 para PHP 8.3 en `ext`, `extension=` en php.ini, VC++ redist |
| Error de conexión a SQL | TCP/IP off, firewall, o auth | habilitar TCP/IP 1433, abrir firewall, revisar login/permisos |
| `Encrypt`/certificado (Driver 18) | ODBC 18 cifra por defecto | `TrustServerCertificate=yes` o usar Driver 17 |
| 404 al recargar una ruta del SPA | falta URL Rewrite / regla SPA | instalar URL Rewrite y poner el `web.config` del front |
| Cambios del `.env` no toman efecto | config cacheada | `php artisan config:cache` (o `config:clear`) |
| El historial guarda siempre el mismo terminal | DNS inverso sin PTR / proxy sin trusted | configurar PTR, o `TrustProxies`, o header `X-Terminal` |
| 419 / CSRF / cookies | dominios stateful mal seteados | `SANCTUM_STATEFUL_DOMAINS` y `SESSION_DOMAIN` = host real |

---

## 12. Actualizar el proyecto (redeploy)

**Backend:**
```
git pull            ; o copiar archivos nuevos
composer install --no-dev --optimize-autoloader
php artisan migrate            ; si hay migraciones nuevas
sqlcmd ... -i database\sql\produccion_cambios.sql   ; si hubo cambios de BD
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
**Frontend:**
```
npm ci && npm run build
; copiar frontend/dist → C:\inetpub\rrhhnet\public
```

> Tras restaurar un backup de producción en desarrollo, recordá correr
> `produccion_cambios.sql` (ver `backend/database/sql/README.md`).
