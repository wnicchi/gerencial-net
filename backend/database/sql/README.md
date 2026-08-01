# database/sql — Cambios de estructura para la BD de producción

`produccion_cambios.sql` reúne **todos los objetos de base de datos** que el código
de RRHH.NET necesita y que **no** existen en la BD original de FoxPro
(tablas auxiliares, índices, constraints, etc.).

## Por qué existe
La app de desarrollo trabaja sobre un **backup restaurado de producción**. Ese backup
no incluye los objetos nuevos que vamos agregando, así que el código se rompería.
Este script los recrea. También sirve para aplicar los cambios en producción.

## Regla de trabajo
> **Cada vez que se agregue o modifique un objeto de BD desde el código, hay que
> reflejarlo acá**, en un bloque idempotente con fecha y descripción.

- Idempotente: usar `IF OBJECT_ID(...) IS NULL` / `IF NOT EXISTS (SELECT 1 FROM sys.indexes ...)`.
- No destructivo: nunca borrar datos.
- Una sección por cambio, fechada.

## Cómo ejecutar
- **SSMS**: abrir contra `sqlRRHH` y ejecutar (F5).
- **sqlcmd**: `sqlcmd -S WALTER-PC -d sqlRRHH -i produccion_cambios.sql`

## Cuándo ejecutar
1. En **producción**, al desplegar las funciones nuevas.
2. En **desarrollo**, después de restaurar cada backup de producción.

## Contenido actual
| Fecha | Objeto | Motivo |
|-------|--------|--------|
| 2026-06-12 | Tabla `codigo_reserva` | Reserva de código de empleado en altas concurrentes |
| 2026-06-12 | Índice `UX_personal_PER_COD` | Unicidad absoluta de `personal.PER_COD` (códigos PAR) |
