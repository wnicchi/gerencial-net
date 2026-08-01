/**
 * useForm.ts — Composable compartido para formularios de ABM
 * =============================================================
 * Provee utilidades reutilizables para todos los módulos:
 *
 *  · cargarDesdeApi()  — Mapea la respuesta de la API a un objeto de formulario,
 *                        convirtiendo fechas ISO a YYYY-MM-DD y omitiendo valores
 *                        vacíos/nulos (se usa el default del form como fallback).
 *
 *  · formatFecha()     — Formatea una fecha para mostrar en la UI.
 *
 * Convención FoxPro / RRHH.NET:
 *   - La API nunca devuelve null (normalizarNulos en backend).
 *   - Las fechas "vacías" se devuelven como "1900-01-01" → se muestran como "—".
 *   - Los datetimes ISO ("2026-06-09T00:00:00.000Z") se convierten a "2026-06-09"
 *     para inputs type="date".
 *
 * IMPORTANTE — Bug histórico evitado:
 *   NO usar .includes('T') para detectar fechas porque nombres como
 *   "JERONIMO MATIAS" también contienen la letra T.
 *   Usar siempre ISO_DATETIME_RE que exige el formato completo YYYY-MM-DDTHH:...
 */

// Detecta strings ISO 8601 datetime: "2026-06-09T14:30:00.000Z"
// Requiere 4 dígitos - 2 - 2 seguidos de T, evitando falsos positivos con texto libre.
const ISO_DATETIME_RE = /^\d{4}-\d{2}-\d{2}T/

// Detecta fechas plain YYYY-MM-DD (sin hora)
const ISO_DATE_RE = /^\d{4}-\d{2}-\d{2}$/

// Detecta datetime SQL separado por espacio: "2024-06-27 00:00:00.000"
const SQL_DATETIME_RE = /^\d{4}-\d{2}-\d{2} \d{2}:/

/**
 * Carga los datos de la API en un objeto de formulario.
 *
 * - Itera solo las claves del formulario (defaultForm), no toca claves extras de la API.
 * - Convierte fechas ISO a YYYY-MM-DD para inputs type="date".
 * - Ignora valores vacíos ('', null, undefined) y deja el default del form.
 * - Las fechas 1900-01-01 (vacías en FoxPro) se dejan como '' en el form.
 *
 * @param defaultForm  Objeto con la estructura y defaults del formulario
 * @param apiData      Respuesta de la API (objeto plano)
 * @returns            Nuevo objeto form con los datos de la API aplicados
 */
export function cargarDesdeApi<T extends Record<string, any>>(
  defaultForm: T,
  apiData: Record<string, any>
): T {
  const f = { ...defaultForm }

  Object.keys(f).forEach(k => {
    const val = apiData[k]

    // Omitir valores vacíos → usar default del form
    if (val === undefined || val === null || val === '') return

    // Detectar datetime ISO ("2026-06-09T00:00:00.000Z") → YYYY-MM-DD
    if (typeof val === 'string' && ISO_DATETIME_RE.test(val)) {
      const d = new Date(val)
      if (!isNaN(d.getTime()) && d.getFullYear() > 1900) {
        f[k] = d.toISOString().split('T')[0] as any
      }
      // Si año <= 1900 (fecha FoxPro vacía) → dejar default del form ('')
      return
    }

    // Detectar date plain "2026-06-09" → verificar que no sea 1900
    if (typeof val === 'string' && ISO_DATE_RE.test(val)) {
      if (!val.startsWith('1900-')) {
        f[k] = val as any
      }
      return
    }

    // Resto de valores (strings, números, booleans)
    f[k] = val
  })

  return f
}

/**
 * Formatea una fecha para mostrar en tablas y etiquetas de la UI.
 *
 * - undefined / null / '' / '1900-01-01' → '—'
 * - ISO datetime → 'DD/MM/YYYY'
 * - YYYY-MM-DD  → 'DD/MM/YYYY'
 *
 * @param fecha  String de fecha (ISO o YYYY-MM-DD)
 * @returns      String formateado para la UI
 */
export function formatFecha(fecha: string | null | undefined): string {
  if (!fecha || fecha.startsWith('1900-')) return '—'

  // Datetime ISO
  if (ISO_DATETIME_RE.test(fecha)) {
    const d = new Date(fecha)
    if (isNaN(d.getTime()) || d.getFullYear() <= 1900) return '—'
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  }

  // Date plain YYYY-MM-DD
  if (ISO_DATE_RE.test(fecha)) {
    const [y, m, d] = fecha.split('-')
    return `${d}/${m}/${y}`
  }

  // Datetime SQL separado por espacio: "2024-06-27 00:00:00.000"
  // Se recorta la parte de fecha por string (sin Date(), evita corrimiento de zona horaria).
  if (SQL_DATETIME_RE.test(fecha)) {
    const [y, m, d] = fecha.slice(0, 10).split('-')
    return `${d}/${m}/${y}`
  }

  return fecha
}

/**
 * Genera las iniciales de un nombre completo para el avatar.
 * "APELLIDO Nombre Segundo" → "AN"
 *
 * @param nombre  Nombre completo
 * @returns       1 o 2 iniciales en mayúscula
 */
export function iniciales(nombre: string | null | undefined): string {
  if (!nombre) return '?'
  const partes = nombre.trim().split(/\s+/)
  if (partes.length === 1) return partes[0][0].toUpperCase()
  return (partes[0][0] + partes[1][0]).toUpperCase()
}
