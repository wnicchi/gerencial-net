# CONVENCIONES DEL PROYECTO — RRHH.NET

> Este archivo concentra **todas las reglas, convenciones y decisiones** acordadas
> durante el desarrollo. Se mantiene actualizado a medida que el proyecto avanza.
>
> **Para arrancar un proyecto nuevo:** pasale este archivo al asistente. Las
> secciones marcadas con 🟢 son **reutilizables** en cualquier proyecto similar;
> las marcadas con 🔵 son **específicas de RRHH.NET** (sirven de ejemplo del estilo
> esperado, adaptar a la nueva base/negocio).

Última actualización: 2026-07-13

---

## 1. 🟢 Stack y arquitectura

- **Backend:** Laravel 13 (PHP 8.3), API REST. Auth con `auth:sanctum`.
- **Frontend:** Vue 3 + TypeScript + Pinia + Vue Router + Vite. Build con `vite`.
- **Base de datos:** SQL Server (driver `sqlsrv`/`pdo_sqlsrv`).
- **Origen del sistema:** migración pantalla-por-pantalla de un sistema FoxPro
  (`RRHHWINSQL`). Cada módulo replica fielmente un formulario `.scx` original
  (pantalla + Data Environment + código de botones).
- **Estructura:** `backend/` (Laravel) y `frontend/` (Vue) en el mismo repo.

### Cómo se desarrolla cada módulo (flujo estándar)
1. Inspeccionar el **esquema real** de las tablas vía `php artisan tinker`
   (la DB es remota/lenta → correr tinker en background y esperar la notificación).
   - ⚠️ Pasar el script **por stdin** (`cat script.php | php artisan tinker` o
     `php artisan tinker < script.php`), **sin** la etiqueta `<?php`. Pasarlo como
     **argumento** (`php artisan tinker script.php`) deja el REPL abierto esperando
     input → el proceso queda colgado.
2. Crear el **Controller** Laravel; registrar rutas dentro del grupo `auth:sanctum`
   en `backend/routes/api.php`.
3. Agregar endpoint de **IA** + `systemPromptXxx()` en `IaController` (ver §6).
4. Construir la **vista Vue** + modal de **Ayuda** + botón **IA** (ver §6).
5. Verificar: `php -l` (lint PHP), `npx vue-tsc --noEmit` (tipos), **smoke test**
   con `DB::beginTransaction()/rollBack()` en tinker, y `npm run build-only`.
6. **Commit + push** (ver §8).

### Réplica fiel del FoxPro (🟢 importante)
- **Pantallas/lógica:** pedirle al usuario el `.scx`/`.prg` del formulario y replicar
  **el cálculo exacto** (fórmulas, tolerancias, límites). **No adivinar** reglas de
  negocio que se usan para liquidar/pagar: si falta el fragmento, pedirlo.
- **Informes:** los `.frx`/`.frt` son tablas DBF; se pueden **leer** para sacar
  títulos, columnas, orden y layout exactos, y replicarlos con jsPDF (encabezado,
  grilla cuadriculada, totales al pie, "una entidad por página" cuando corresponde).
- Cuando el comportamiento no coincide con Fox, comparar contra el código/informe
  original y ajustar, en vez de aproximar (ej. minutos de tardanza, agrupamientos).

---

## 2. 🟢 Backend — reglas

- **Anti-NULOS:** los `INSERT`/`UPDATE` nunca dejan `NULL`. Usar el helper
  `App\Support\Registro::completar($tabla, $valores)`, que rellena las columnas
  faltantes con defaults (`0` / `''` / `1900-01-01`).
  - ⚠️ Las **claves del array deben respetar el casing real** de las columnas
    (mayúsculas en la mayoría de las tablas); si no, colisiona con los defaults
    de `completar()` → error "column specified more than once".
- **Códigos incrementales:** por defecto `max(COD)+1` para nuevas filas
  (salvo regla de negocio distinta, ver §9).
- **Cambios de estructura de DB:** todo cambio (tabla/columna nueva, etc.) va a
  **DOS** archivos: `backend/database/sql/produccion_cambios.sql` **y**
  `migration_sqlserver.sql` (raíz). Avisar al usuario para correrlo en el server.
- **Validación replicando el FoxPro:** respetar los mensajes y validaciones del
  formulario original (campos obligatorios, fechas, rangos).
- **⚠️ SQL Server + driver `sqlsrv` devuelve numéricos/bit como STRING.** Un campo
  `bit`/`char` que guarda `'0'`/`'1'` llega al frontend como `'0'` — y en JavaScript
  el string `'0'` es **truthy**, así que `valor ? …` da verdadero aunque sea 0.
  **Castear a int en PHP** (`(int) $v` en el `->map()`), NO alcanza con `CAST(... AS INT)`
  en SQL (el driver lo re-stringifica). Vale para todo flag/estado que el front lea.
- **Timezone de la app:** setear `config/app.php` → `'timezone'` a la zona del cliente
  (ej. `America/Argentina/Buenos_Aires`), **no** dejar `UTC` (el default de Laravel).
  Con UTC todos los `now()`/`today()` salen corridos (en AR +3h) y rompen horas de
  aparición, alertas por hora, timestamps mostrados, etc. Env-overridable:
  `env('APP_TIMEZONE', 'America/Argentina/Buenos_Aires')`.
- **Log global de errores SQL:** TODA excepción SQL del proyecto se registra en una
  tabla de log (`log_errores_sql`) capturada en la **capa de conexión** (extender
  `SqlServerConnection::runQueryCallback` + `Connection::resolverFor('sqlsrv', …)`),
  con guarda anti-recursión. No hay que acordarse de loguear en cada catch.

### Conexiones a base de datos (🔵 ejemplo RRHH.NET)
- `sqlsrv_rrhh` (sqlRRHH) — conexión principal (datos del sistema).
- conexión de **documentos digitales** (biblioteca digital de archivos).
- conexión de **gestión** (sqlSILCAR) — datos externos/integración.

### Documentos digitales (🟢 patrón)
- Metadata en tabla `documentos` (o `cap_documentacion` para capacitación), con
  discriminador `DOC_TIP` ('E' empleado, 'X' examen, 'K' capacitación, 'L' licencia…).
- Archivo físico vía `App\Services\BibliotecaDigitalService`
  (`archivoDigitalGuardar` / `archivoDigitalVisualizar` / `archivoDigitalEliminar`).
- La "identificación" del archivo se arma según el tipo (ej. examen:
  `"M"+DOC_TDO+pad(DOC_REF,10)+"."+EXT`). Extensiones bloqueadas:
  `EXE, BAT, DLL, ZIP, RAR, CMD, CAB`. Máx. 50 MB.

---

## 3. 🟢 Frontend / UI — reglas

- **Tema CLARO fijo.** La app NO adopta el modo oscuro del SO. En `base.css` el
  bloque `@media (prefers-color-scheme: dark)` queda neutralizado (mantiene
  variables claras) + `:root { color-scheme: light; }`.
- **Textos siempre visibles:** poner **color de texto oscuro explícito**
  (`#1e293b` típico) en TODO contenedor de texto: `td` de tablas, `li` de listas
  y dropdowns de búsqueda, chips, celdas. **No** confiar en el color heredado.
  - Ojo con **componentes funcionales** creados con `h()`: el CSS *scoped* no les
    llega → usar **estilos inline** para esos nodos.
- **Paleta aprobada (🔵):** sidebar blanco + verde oscuro, contenido blanco.
- **Enter → siguiente campo:** en altas/formularios, Enter pasa al próximo campo
  (directiva `v-enter-next`).
- **Buscador de empleado/registro:** input con autocomplete (debounce ~250ms)
  contra el endpoint de búsqueda → dropdown de resultados.
- **Ordenamiento por columna:** los encabezados de las tablas son clicables
  (asc/desc con flecha ▲/▼). Comparador: numérico para números, `localeCompare('es',
  {numeric:true})` para texto, booleanos a 0/1.
- **Claves de `v-for`:** si los datos pueden tener **códigos repetidos**, usar el
  índice como `:key` (no el código) para que el DOM respete el orden.

### 🟢 ABM — edición explícita + validación de largo (NORMA GENERAL)
Aplica a **todos los ABM** (datos maestros: código/descripción, fichas, etc.):
- **No se edita hasta apretar "Editar".** Al seleccionar un registro, el formulario
  queda en **solo lectura**; hay un botón **✏️ Editar** que habilita los campos y
  pasa a **💾 Guardar**; al guardar (o al elegir otro registro) vuelve a solo lectura.
  El alta ("Nuevo") entra directo en modo editable con botón **Crear**. Mientras se
  edita, ocultar **Eliminar** para no mezclar acciones.
  - En los ABM con **modal** de alta/edición esto ya se cumple (el modal es la acción
    de editar); no hace falta el toggle inline.
- **Cada textbox valida el largo real de la columna CHAR** de la base:
  - El **`maxlength`** del `<input>`/`<textarea>` y la **regla `max:`** del backend
    (`$request->validate`) deben ser **iguales al `CHARACTER_MAXIMUM_LENGTH`** de la
    columna. Verificar con `INFORMATION_SCHEMA.COLUMNS`. **No** poner números
    "de memoria": si la columna es más grande, un dato viejo de Fox que la supere
    se rechaza al guardar; si es más chica, se trunca/error silencioso.
  - En los ABM inline conviene mostrar un **contador en vivo** `N / max` que se pone
    **rojo** al exceder, resalta el campo y **bloquea Guardar** con un aviso. En los
    modales alcanza con alinear `maxlength`+`max:` (sin contador).

### PDFs e informes (🟢)
- Todos los PDF del proyecto se generan en **formato A4**.
- **Previsualización obligatoria:** todo informe se muestra primero en un **modal**
  (iframe) con botones **Descargar / Imprimir / Cerrar** antes de bajarlo.
- Generación con **jsPDF**.

### Descargas (🟢)
- Toda descarga (Excel/PDF/archivo) abre el diálogo **"Guardar como"**
  (helpers `guardarComo` / `guardarDesdeUrl` en `@/utils/descargas`).

### Foto del empleado (🔵 patrón JSON)
- `GET /api/empleados/{cod}/foto` devuelve **JSON** `{ "foto": "data:image/...;base64,..." }`
  (o `{foto:null}`), **no** un blob. Leer `data.foto` directo (NO `responseType:'blob'`).
  Agregar `@error="fotoUrl=''"` al `<img>` como fallback.

---

## 4. 🟢 Visor de documentos asociados (DocViewer)

- Todo lugar que **visualice un documento asociado** usa el componente compartido
  **`frontend/src/components/DocViewer.vue`** (no modales propios).
- **Regla del visor:**
  - **PDF e imágenes** (png/jpg/jpeg/gif/webp/bmp/svg) → iframe en modal.
  - **Excel** (.xlsx/.xls) → tabla con **SheetJS** (`xlsx`).
  - **Word** (.docx) → **`docx-preview`**.
  - **Cualquier otro** → **descarga directa** (sin modal vacío).
- **Ojito por fila (obligatorio):** TODA lista/tabla de documentos debe tener un
  botón **👁️** por fila (clase `btn-ojo`) que abra ese documento con `DocViewer`.
  No alcanza con un botón general sobre el seleccionado.
- **Uso:**
  ```vue
  <DocViewer ref="visor" />
  ```
  ```ts
  import DocViewer from '@/components/DocViewer.vue'
  const visor = ref<InstanceType<typeof DocViewer> | null>(null)
  // blob = File local, o resp.data de api.get(url, { responseType: 'blob' })
  visor.value?.open(blob, `${nombre}.${ext}`)
  ```
- Los **PDF generados** (jsPDF: legajos, listados) NO usan DocViewer; siguen en su
  propio modal/`window.open` porque siempre son PDF.

### Envío de correos a clientes/terceros (🟢)
- **NO** configurar cuenta SMTP de servidor. El correo debe salir por la **cuenta y
  bandeja de salida del cliente de correo del propio usuario** (réplica del MAPI/Outlook
  de FoxPro).
- El backend arma un archivo **`.eml`** (RFC 822, con `Symfony\Component\Mime\Email`)
  con Para/CC, asunto, cuerpo y **adjuntos incrustados** (bytes desde la biblioteca
  digital vía `archivoDigitalRecuperar`). Cabecera **`X-Unsent: 1`** para que Outlook
  lo abra en modo redacción con botón Enviar. `From` es un placeholder cosmético
  (Outlook lo ignora con `X-Unsent`).
- El frontend descarga el `.eml`; el usuario marca *"Abrir siempre este tipo de
  archivo"* en el navegador para que se abra solo. Registrar el envío en el histórico.

---

## 5. 🟢 Confidencialidad / seguridad de datos

- **Nunca revelar el esquema** (nombres de tablas, columnas, códigos internos) a
  los operadores. La base de datos solo la conocen el dev y el asistente. Aplica a:
  - **Respuestas de IA** (el system prompt puede usar el esquema *internamente*
    para responder, pero está OBLIGADO a traducir a lenguaje funcional y nunca
    mostrar nombres técnicos).
  - **Sugerencias y subtítulos del chat IA** (`:sugerencias`, `subtitulo`): NO
    proponer "¿en qué tabla…?", "¿qué campos usa?", "modelo de datos", etc.
    Reformular en términos de negocio ("¿Cómo cargo…?", "¿Dónde se usa…?").
  - **Textos de Ayuda**.
  - Excepción válida: hablar de **columnas de un Excel** que el propio usuario
    importa/exporta (eso es su planilla, no el esquema de la base).
- **Autenticación:** contraseñas con **bcrypt** (`Hash::make`/`Hash::check`).
  Las rutas públicas de `auth` (login, recuperación) llevan **`throttle`**
  (rate-limit) para frenar fuerza bruta; los códigos de verificación tienen
  **límite de intentos** y comparación con `hash_equals`.

---

## 6. 🟢 Ayuda + IA por módulo

- Cada módulo finalizado suma **dos botones**: **❓ Ayuda** (modal con explicación
  funcional) y **🤖 IA**.
- **IA:** componente reutilizable `ChatIA` (props: `endpoint`, `titulo`,
  `subtitulo`, `sugerencias`, `@close`). En el backend, `IaController` tiene un
  método por módulo + un `systemPromptXxx()` con:
  - "Respondés SIEMPRE en español, claro y conciso. No inventes datos."
  - La **regla crítica de confidencialidad** (§5).
  - Una descripción funcional de qué hace el módulo.
- Modelo por defecto para features de IA: **Claude Opus 4.8** (`claude-opus-4-8`),
  thinking adaptativo.

---

## 7. 🟢 Despliegue (IIS)

- Producción en **IIS + PHP FastCGI**. El código del repo se clona en el server
  (ej. `C:\inetpub\rrhh-net`); IIS sirve `backend\public`.
- **Node NO está instalado en el server.** El frontend se **compila en la PC de
  desarrollo** (`npm run build-only`) y el `dist/` se versiona en Git.
- El repo en el server está en **`C:\inetpub\rrhh-net`** (no confundir con la PC dev).
- **Comandos de actualización en el server (PowerShell):**
  ```powershell
  cd C:\inetpub\rrhh-net
  git pull origin main
  .\deploy.ps1     # git reset --hard origin/main + copia dist→backend/public + php artisan config:clear
  ```
  Luego en el navegador **Ctrl+F5** (hard refresh) para tomar los bundles nuevos.
- Al terminar cada trabajo, **dejar el bloque PowerShell listo para copiar y correr**.
- Cambios de `config/app.php` (timezone, etc.): el `config:clear` de `deploy.ps1` los
  toma; conviene además **reiniciar el pool de IIS** por las dudas.
- Si el módulo cambió estructura de DB, además correr el SQL de §2.

---

## 8. 🟢 Git / commits

- Al terminar **y testear** cada módulo (lint + vue-tsc + smoke con rollback +
  `npm run build-only`), hacer **commit + push directo a `main`**, sin esperar a
  que lo pidan. Ofrecer siempre los comandos de pull del server (§7).
- ⚠️ **`frontend/dist` está en `.gitignore`.** `git add -A` **NO** incluye los
  bundles JS hasheados nuevos → el server quedaría con un `index.html` que apunta
  a un `.js` inexistente (**pantalla en blanco**). Por eso, **siempre** antes de
  commitear:
  ```bash
  npm run build-only            # en frontend/
  git rm -r --cached --quiet frontend/dist
  git add -f frontend/dist      # fuerza TODOS los assets nuevos
  git add -A                    # backend, src, router, etc.
  # verificar que cada asset de frontend/dist/index.html quedó staged
  ```
- Mensaje de commit descriptivo + línea final:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`
- **Puntos de restauración:** antes de un refactor grande (o al cerrar un hito
  estable), crear un **tag** + **rama de respaldo** con fecha
  (ej. `estable-2026-07-10` / `respaldo-estable-2026-07-10`) y pushearlos. Para
  volver: `git reset --hard <tag>` y `.\deploy.ps1` en el server.

---

## 9. 🔵 Reglas de negocio específicas de RRHH.NET

> (Ejemplos del tipo de reglas que cada proyecto tendrá; adaptar.)

- **Alta de empleado:** el código se asigna por regla **PAR** (no `max+1`), con
  validaciones y presets propios.
- **Convenios:** `CON_COD` 3 = F.CONVENIO, 7 = SMATA; resaltado amarillo si 3 o 7.
- **Roles / Plantillas:** "Niveles" reconvertido en **Roles** = plantilla de
  permisos de menú (`nivel_permisos`).
- **Admin:** `usuarios.ES_ADMIN` = admin total; `NIVEL` (CODNIV) queda como rol.
- **Sueldos:** la remuneración (sueldos/banco) es visible **solo a NIVEL 9**.
- **Multi-servidor:** un solo proyecto; los nombres de base se definen por `.env`
  (no separar en 2 proyectos). La **empresa** (branding, reglas por empresa) se
  autodetecta del nombre de la base (`config('rrhh.empresa')` / `docs_sistema`
  derivados de `DB_DATABASE`): si contiene "log" → Logística, si no → Autoelevadores.
- **Carga solo para activos:** todo alta/proceso que pide un empleado bloquea a los
  dados de baja (`personal.PER_AOP='A'`; helper `App\Support\Empleado::activo`).
  El maestro de Empleados es la única excepción.
- **Consolidación de ABM (patrón 🟢):** los módulos FoxPro partidos en verbos
  (Agregar/Modificar/Eliminar/Consultar/Imprimir) se reemplazan por **UN** ABM con
  grilla (buscador + filtros) y acciones por fila (👁️ ver, ✏️ editar, 🖨️ imprimir,
  🗑️ borrar) + "＋ Nuevo" en modal. Al migrar, **no borrar** las vistas viejas de
  una (dejarlas fuera del menú para rollback) y recién eliminarlas cuando el usuario
  confirme que el ABM unificado está probado.

---

## 10. Mantenimiento de este archivo

- Cada vez que se acuerde una **nueva regla/convención**, agregarla acá (y al
  índice de memoria del asistente) en el mismo commit del módulo.
- Marcar 🟢 (reutilizable) o 🔵 (específica del proyecto) para que sea fácil
  separar lo portable al iniciar un proyecto nuevo.
