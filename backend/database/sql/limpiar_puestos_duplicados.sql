/* ============================================================================
   limpiar_puestos_duplicados.sql
   ----------------------------------------------------------------------------
   Limpia los PUESTOS ASIGNADOS duplicados en la tabla puestoempleado, un defecto
   heredado del sistema FoxPro (el mismo empleado con el mismo puesto cargado más
   de una vez con idéntico estado). Deja UNA sola fila por combinación
   (CUIL + PUESTO + estado activo), conservando la de fecha de alta más reciente.

   Ejecutar EN CADA BASE (Silcar y Logística) por separado, desde SSMS/sqlcmd,
   apuntando a la base correspondiente. Es idempotente: si no hay duplicados no
   borra nada; correrlo dos veces no genera cambios.

   Se identifica por CUIL (PEM_CUIL) porque así vincula el sistema empleado↔puesto.
   ============================================================================ */

/* ----------------------------------------------------------------------------
   BASE DE DATOS — descomentar y poner el nombre real de la base ANTES de correr.
   Correr una vez con la base de Silcar y otra vez con la de Logística.
   (Si preferís, en vez de esto elegí la base en el desplegable de SSMS.)
   ---------------------------------------------------------------------------- */
-- USE [NOMBRE_DE_LA_BASE];   -- Silcar:  USE [sqlRRHH];   Logística: USE [<base de Logística>];
GO

SET NOCOUNT ON;

/* ----------------------------------------------------------------------------
   PASO 1 — CHEQUEO. Muestra los grupos duplicados y cuántas filas sobran.
   (Sólo lectura: no modifica nada. Revisar antes de correr el PASO 2.)
   ---------------------------------------------------------------------------- */
PRINT '=== Grupos duplicados en puestoempleado (CUIL + PUESTO + activo) ===';

SELECT
    UPPER(LTRIM(RTRIM(PEM_CUIL))) AS cuil,
    UPPER(LTRIM(RTRIM(PEM_PUE)))  AS puesto,
    PEM_ACT                        AS activo,
    COUNT(*)                       AS veces,
    COUNT(*) - 1                   AS sobrantes
FROM puestoempleado
GROUP BY UPPER(LTRIM(RTRIM(PEM_CUIL))), UPPER(LTRIM(RTRIM(PEM_PUE))), PEM_ACT
HAVING COUNT(*) > 1
ORDER BY veces DESC;

DECLARE @sobrantes INT = (
    SELECT ISNULL(SUM(veces - 1), 0) FROM (
        SELECT COUNT(*) AS veces
        FROM puestoempleado
        GROUP BY UPPER(LTRIM(RTRIM(PEM_CUIL))), UPPER(LTRIM(RTRIM(PEM_PUE))), PEM_ACT
        HAVING COUNT(*) > 1
    ) g
);
PRINT 'Filas duplicadas a eliminar: ' + CAST(@sobrantes AS VARCHAR(20));

/* ----------------------------------------------------------------------------
   PASO 2 — LIMPIEZA. Elimina los duplicados dejando la fila más reciente de
   cada grupo. Transaccional: si algo sale mal, hace ROLLBACK automático.
   ---------------------------------------------------------------------------- */
BEGIN TRY
    BEGIN TRAN;

    ;WITH dups AS (
        SELECT
            ROW_NUMBER() OVER (
                PARTITION BY UPPER(LTRIM(RTRIM(PEM_CUIL))),
                             UPPER(LTRIM(RTRIM(PEM_PUE))),
                             PEM_ACT
                ORDER BY PEM_FDES DESC
            ) AS rn
        FROM puestoempleado
    )
    DELETE FROM dups WHERE rn > 1;

    DECLARE @borradas INT = @@ROWCOUNT;
    PRINT 'Filas eliminadas: ' + CAST(@borradas AS VARCHAR(20));

    COMMIT TRAN;
    PRINT '=== Limpieza confirmada (COMMIT) ===';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRAN;
    PRINT '=== ERROR: se revirtió la limpieza (ROLLBACK) ===';
    PRINT ERROR_MESSAGE();
END CATCH;

/* ----------------------------------------------------------------------------
   PASO 3 — VERIFICACIÓN. Debe devolver 0 grupos duplicados.
   ---------------------------------------------------------------------------- */
PRINT '=== Verificación posterior (debe ser 0 grupos) ===';
SELECT COUNT(*) AS grupos_duplicados_restantes FROM (
    SELECT COUNT(*) AS veces
    FROM puestoempleado
    GROUP BY UPPER(LTRIM(RTRIM(PEM_CUIL))), UPPER(LTRIM(RTRIM(PEM_PUE))), PEM_ACT
    HAVING COUNT(*) > 1
) g;
