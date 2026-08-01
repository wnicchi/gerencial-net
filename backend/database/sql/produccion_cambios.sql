/* ============================================================================
   RRHH.NET — Cambios de estructura sobre la BD de PRODUCCIÓN (sqlRRHH)
   ----------------------------------------------------------------------------
   PROPÓSITO
     Este script contiene TODOS los objetos de base de datos que agrega/modifica
     el código de RRHH.NET y que NO vienen en la BD original de FoxPro.

     Debe ejecutarse:
       1) En la BD de PRODUCCIÓN, una vez, para habilitar las nuevas funciones.
       2) En esta PC de desarrollo, cada vez que se restaura un backup de
          producción (el backup no trae estos objetos y el código se rompería
          sin ellos).

   REQUISITOS
     - Motor: Microsoft SQL Server.
     - Es IDEMPOTENTE: se puede correr múltiples veces sin error ni duplicar nada
       (cada bloque verifica si el objeto ya existe antes de crearlo).
     - No borra ni modifica datos existentes.

   CÓMO EJECUTAR
     - SSMS: abrir contra la base sqlRRHH y ejecutar (F5).
     - sqlcmd:  sqlcmd -S WALTER-PC -d sqlRRHH -i produccion_cambios.sql
   ============================================================================ */

USE sqlRRHH;
GO

/* ----------------------------------------------------------------------------
   2026-06-12 — Reserva de código de empleado (altas concurrentes)
   ----------------------------------------------------------------------------
   Tabla temporal donde cada terminal "reserva" el próximo PER_COD libre mientras
   da de alta un empleado, para que dos terminales no reciban el mismo código.
   Las reservas expiran solas (la app purga las de más de 30 min).
   Usada por: EmpleadoController::reservarCodigo() / liberarCodigo() / generarCodigoPar()
---------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.codigo_reserva', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.codigo_reserva (
        CODIGO  INT          NOT NULL PRIMARY KEY,  -- PK = garantiza una reserva por código
        USUARIO NVARCHAR(50) NULL,                  -- quién reservó (auditoría)
        CREADO  DATETIME2    NOT NULL               -- para expirar reservas viejas
    );
    PRINT 'Tabla codigo_reserva creada.';
END
ELSE
    PRINT 'Tabla codigo_reserva ya existe - sin cambios.';
GO

/* ----------------------------------------------------------------------------
   2026-06-12 — Unicidad absoluta del código de empleado (PER_COD)
   ----------------------------------------------------------------------------
   La tabla original de FoxPro no tenía índice ni PK sobre PER_COD. Este índice
   UNIQUE hace IMPOSIBLE que existan dos empleados con el mismo código, incluso
   ante altas simultáneas desde distintas terminales (la BD rechaza el duplicado
   con error 2627/2601 y el backend reintenta con el siguiente par libre).

   NOTA: si el backup de producción llegara a tener PER_COD duplicados, la
   creación del índice fallará. En ese caso, depurar duplicados primero con:
       SELECT PER_COD, COUNT(*) FROM dbo.personal GROUP BY PER_COD HAVING COUNT(*) > 1;
---------------------------------------------------------------------------- */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'UX_personal_PER_COD'
      AND object_id = OBJECT_ID('dbo.personal')
)
BEGIN
    CREATE UNIQUE INDEX UX_personal_PER_COD ON dbo.personal(PER_COD);
    PRINT 'Indice UNIQUE UX_personal_PER_COD creado.';
END
ELSE
    PRINT 'Indice UX_personal_PER_COD ya existe - sin cambios.';
GO

PRINT '== produccion_cambios.sql aplicado correctamente ==';
GO

-- 2026-06-21 — Roles/Plantillas de permisos (repurpose de "Niveles")
-- Claves de menú permitidas por nivel (rol). Espeja usuario_permisos pero a nivel de rol.
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='nivel_permisos' AND xtype='U')
CREATE TABLE nivel_permisos (
    id            INT IDENTITY(1,1) PRIMARY KEY,
    nivel_codigo  INT          NOT NULL,
    permiso_clave VARCHAR(100) NOT NULL
);

/* ----------------------------------------------------------------------------
   usuarios.ES_ADMIN — Administrador total del sistema
   ----------------------------------------------------------------------------
   En FoxPro el campo NIVEL identifica el ROL/nivel de acceso del usuario
   (CODNIV de la tabla niveles) y NO debe usarse para marcar al administrador.
   Se agrega una columna dedicada ES_ADMIN (1 = administrador total: ve todo el
   menú e ignora la lista de permisos; 0 = usuario normal regido por sus permisos).
---------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('usuarios') AND name = 'ES_ADMIN')
BEGIN
    ALTER TABLE usuarios ADD ES_ADMIN INT NOT NULL CONSTRAINT DF_usuarios_ES_ADMIN DEFAULT 0;
END
GO

/* Marcar como administrador total al usuario responsable del sistema (WALTER NICCHI, login WNICCHI). */
UPDATE usuarios SET ES_ADMIN = 1 WHERE DATO1 = 'WNICCHI' AND ES_ADMIN = 0;
GO

/* ============================================================================
   LOG DE ERRORES SQL — registra cada error de base de datos capturado
   (fecha/hora, terminal, usuario, módulo, comando SQL y detalle completo).
   Alta: 2026-07. Idempotente.
   ============================================================================ */
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'log_errores_sql')
BEGIN
    CREATE TABLE log_errores_sql (
        ERR_ID  INT IDENTITY(1,1) PRIMARY KEY,
        ERR_FEC DATETIME      NOT NULL CONSTRAINT DF_logerr_fec DEFAULT GETDATE(),
        ERR_TER VARCHAR(60)   NOT NULL CONSTRAINT DF_logerr_ter DEFAULT '',
        ERR_USU VARCHAR(60)   NOT NULL CONSTRAINT DF_logerr_usu DEFAULT '',
        ERR_MOD NVARCHAR(200) NOT NULL CONSTRAINT DF_logerr_mod DEFAULT '',
        ERR_SQL NVARCHAR(MAX) NOT NULL CONSTRAINT DF_logerr_sql DEFAULT '',
        ERR_DET NVARCHAR(MAX) NOT NULL CONSTRAINT DF_logerr_det DEFAULT '',
        ERR_COD VARCHAR(20)   NOT NULL CONSTRAINT DF_logerr_cod DEFAULT ''
    );
    CREATE INDEX IX_logerr_fec ON log_errores_sql (ERR_FEC DESC);
END
GO

/* ============================================================================
   LOG DE ACTIVIDAD — auditoría de lo que hacen los usuarios (altas, cambios y
   bajas). Guarda fecha/hora, usuario, terminal, módulo, un texto simple y el
   comando SQL técnico. Alta: 2026-07. Idempotente.
   ============================================================================ */
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'log_actividad')
BEGIN
    CREATE TABLE log_actividad (
        ACT_ID  INT IDENTITY(1,1) PRIMARY KEY,
        ACT_FEC DATETIME      NOT NULL CONSTRAINT DF_logact_fec DEFAULT GETDATE(),
        ACT_USU VARCHAR(60)   NOT NULL CONSTRAINT DF_logact_usu DEFAULT '',
        ACT_TER VARCHAR(60)   NOT NULL CONSTRAINT DF_logact_ter DEFAULT '',
        ACT_MOD NVARCHAR(200) NOT NULL CONSTRAINT DF_logact_mod DEFAULT '',
        ACT_OP  VARCHAR(10)   NOT NULL CONSTRAINT DF_logact_op  DEFAULT '',
        ACT_TXT NVARCHAR(500) NOT NULL CONSTRAINT DF_logact_txt DEFAULT '',
        ACT_SQL NVARCHAR(MAX) NOT NULL CONSTRAINT DF_logact_sql DEFAULT ''
    );
    CREATE INDEX IX_logact_fec ON log_actividad (ACT_FEC DESC);
END
GO

/* ============================================================================
   PORTAL DEL ENCARGADO — vínculo usuario ↔ empleado.
   Los encargados cargan permisos laborales para su personal a cargo. Para saber
   qué empleado es cada usuario se usa usuarios.LEGAJO (= personal.PER_COD), igual
   que el sistema FoxPro. El valor se completa desde el sistema de gestión con:
       php artisan usuarios:sincronizar-legajos --aplicar
   (matchea por login DATO1 en MAYÚSCULAS y copia solo el LEGAJO). Alta: 2026-07.
   Idempotente.
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'LEGAJO')
BEGIN
    ALTER TABLE usuarios ADD LEGAJO INT NOT NULL CONSTRAINT DF_usuarios_LEGAJO DEFAULT 0;
END
GO

/* ============================================================================
   VER CLAVE (solo administradores) — copia cifrada REVERSIBLE de la clave.
   La clave real se guarda hasheada en 'password' (bcrypt, irreversible). Para
   que un administrador pueda VER la clave actual de un usuario, se guarda además
   una copia cifrada con la APP_KEY (Laravel Crypt) en CLA_VER cada vez que la
   clave se crea/cambia/restablece. Es reversible SOLO desde la app (con la clave
   del .env). Las claves anteriores a este cambio no se pueden recuperar: CLA_VER
   queda vacío ('') hasta que cada usuario cambie su clave o el admin la restablezca.
   Alta: 2026-07. Idempotente.
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'CLA_VER')
BEGIN
    ALTER TABLE usuarios ADD CLA_VER NVARCHAR(500) NOT NULL CONSTRAINT DF_usuarios_CLA_VER DEFAULT '';
END
GO
