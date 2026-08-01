# Buenas prácticas — checklist para proyectos web (SPA + IIS/Laravel)

Lecciones aprendidas para aplicar por defecto en éste y en próximos proyectos.
Copiá este archivo a cada proyecto nuevo y pedile a Claude que lo aplique.

## 1. Caché tras deploy (¡el más importante!)
Síntoma clásico: después de un deploy, a los usuarios "no les anda" hasta hacer Ctrl+F5.
- **`index.html` SIN caché**: `Cache-Control: no-cache, no-store, must-revalidate`.
  - Si lo sirve Laravel: `response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache, no-store, must-revalidate', 'Pragma' => 'no-cache', 'Expires' => '0'])`.
- **`/assets/*` (nombres con hash) cacheados 1 año** en `web.config`:
  ```xml
  <location path="assets">
    <system.webServer>
      <staticContent>
        <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
      </staticContent>
    </system.webServer>
  </location>
  ```
- Resultado: cada deploy se ve solo, sin Ctrl+F5 para nadie.

## 2. Sesión / seguridad
- Token de autenticación en **`sessionStorage`** (no `localStorage`) → obliga a re-loguear al cerrar el navegador.
- No condicionar el login a **flags que comparte un sistema legacy** (ej. un campo "logueado" de otro sistema): causa rechazos intermitentes de contraseña.

## 3. Recuperación de contraseña sin depender de email/SMTP
Muchos usuarios internos no tienen email cargado. Tener las tres capas:
- **Reset por administrador** desde la UI (fija la clave directo).
- **Self-service por código de 6 dígitos** al email *ya registrado* (no link → no depende de `FRONTEND_URL`).
- **Comando CLI de rescate** para el admin, que corre en el servidor sin depender de nadie:
  `php artisan usuario:password <login> "<clave>"`.

## 4. HTTPS en red interna (alias tipo https://miapp)
Instalar el certificado en la terminal NO alcanza. Hacen falta 3 cosas:
1. En IIS: **binding HTTPS 443** con el nombre de host + certificado (SHA256 + SAN).
2. Que el nombre **resuelva** a la IP del servidor: DNS interno (registro A) o `hosts` por PC.
3. Instalar el `.cer` en "Entidades de certificación raíz de confianza" de cada terminal.
   - Truco: la terminal puede **bajar el `.cer` del propio puerto 443** e instalarlo por PowerShell, sin copiar archivos.

## 5. Identidad de la pestaña del navegador (OBLIGATORIO en todo sistema)

Todo sistema que se migre debe identificarse en la pestaña desde el primer día —
nunca dejar el "Vite App" con el logo del framework:
- **`<title>`** con el nombre real del sistema (ej. "Sistema de Recursos Humanos",
  "WMS Universal") y **`lang="es"`** en el `<html>`.
- **Favicon propio**: las iniciales del sistema en blanco sobre el color primario
  del sistema, en cuadrado redondeado (mismo estilo que "RH" y "WMS"). Generar los
  3 formatos en `frontend/public/`: `favicon.svg` (nítido en navegadores modernos)
  + `favicon.png` + `favicon.ico` (fallback).
- Links con versión para bustear la caché cuando cambie:
  `<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=1">` + png + ico.
- El `deploy.ps1` debe copiar los `favicon.*` del dist a producción.

## 6. Deploy
- Un solo `deploy.ps1` que haga `git pull` + build/copy + `config:clear`.
- Dejar siempre el bloque PowerShell listo para copiar y correr en el servidor.
- Cambios de estructura de BD → script idempotente (`IF NOT EXISTS …`) versionado, corrido una sola vez.
