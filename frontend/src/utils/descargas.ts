/**
 * Utilidad de descarga de archivos para todo el proyecto.
 *
 * REGLA DEL PROYECTO: cada vez que se descarga un archivo (Excel, PDF, etc.)
 * se debe abrir el cuadro de diálogo "Guardar como" para que el usuario elija
 * dónde guardarlo. Se usa la File System Access API (showSaveFilePicker) cuando
 * está disponible (Chrome/Edge) y, si no, se cae a la descarga clásica.
 */

export type ResultadoDescarga = 'ok' | 'cancelado'

interface TipoArchivo { description: string; accept: Record<string, string[]> }

/**
 * Infiere el tipo (para el filtro del diálogo) a partir de la extensión.
 *
 * IMPORTANTE: siempre hay que declarar la extensión. Si el diálogo "Guardar como"
 * no la declara, el archivo se puede guardar SIN extensión (pasaba con los .TXT de
 * los bancos). Por eso, para cualquier extensión no mapeada se arma un tipo genérico.
 */
function tipoPorNombre (nombre: string): TipoArchivo[] {
  const ext = (nombre.split('.').pop() || '').toLowerCase()
  const mapa: Record<string, TipoArchivo> = {
    pdf:  { description: 'Documento PDF',       accept: { 'application/pdf': ['.pdf'] } },
    xls:  { description: 'Planilla de Excel',   accept: { 'application/vnd.ms-excel': ['.xls'] } },
    xlsx: { description: 'Planilla de Excel',   accept: { 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'] } },
    csv:  { description: 'Archivo CSV',         accept: { 'text/csv': ['.csv'] } },
    txt:  { description: 'Archivo de texto',    accept: { 'text/plain': ['.txt'] } },
    eml:  { description: 'Mensaje de correo',   accept: { 'message/rfc822': ['.eml'] } },
    jpg:  { description: 'Imagen',              accept: { 'image/jpeg': ['.jpg', '.jpeg'] } },
    jpeg: { description: 'Imagen',              accept: { 'image/jpeg': ['.jpg', '.jpeg'] } },
    png:  { description: 'Imagen',              accept: { 'image/png': ['.png'] } },
  }
  if (mapa[ext]) return [mapa[ext]]
  // Sin mapeo: tipo genérico pero declarando la extensión, para no perderla al guardar.
  if (/^[a-z0-9]{1,8}$/.test(ext)) {
    return [{ description: `Archivo ${ext.toUpperCase()}`, accept: { 'application/octet-stream': ['.' + ext] } }]
  }
  return []
}

/**
 * Pasa la extensión a minúsculas (el nombre queda igual).
 * El diálogo del navegador sólo admite extensiones en minúscula al declarar el tipo:
 * si el nombre trae ".TXT" y el tipo declara ".txt" el archivo puede terminar sin
 * extensión o duplicada (".TXT.txt"). En Windows la extensión no distingue mayúsculas.
 */
function normalizarExtension (nombre: string): string {
  const i = nombre.lastIndexOf('.')
  return i > 0 ? nombre.slice(0, i) + nombre.slice(i).toLowerCase() : nombre
}

/**
 * Guarda un Blob preguntando dónde (diálogo "Guardar como").
 * @returns 'ok' si se guardó/descargó, 'cancelado' si el usuario cerró el diálogo.
 */
export async function guardarComo (blob: Blob, nombreOriginal: string): Promise<ResultadoDescarga> {
  const nombre = normalizarExtension(nombreOriginal)
  const picker = (window as any).showSaveFilePicker
  if (typeof picker === 'function') {
    try {
      const handle = await picker.call(window, {
        suggestedName: nombre,
        types: tipoPorNombre(nombre),
      })
      const writable = await handle.createWritable()
      await writable.write(blob)
      await writable.close()
      return 'ok'
    } catch (e: any) {
      if (e?.name === 'AbortError') return 'cancelado'   // el usuario cerró el diálogo
      // Cualquier otro error → cae al método clásico
    }
  }
  // Fallback: descarga a la carpeta de descargas del navegador
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = nombre
  document.body.appendChild(a); a.click(); a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1500)
  return 'ok'
}

/**
 * Arma un Blob a partir de la respuesta de exportación del backend.
 *
 * Si el backend marca `encoding: 'base64'` (archivos de banco en Windows-1252),
 * se decodifica a bytes crudos SIN pasar por string, para que los acentos queden
 * en 1 byte (0xED) y el banco no rechace el TXT por corrimiento de columnas.
 * Si no, se usa el texto tal cual (el navegador lo codifica como UTF-8).
 */
export function blobDeRespuesta (data: { contenido: string; encoding?: string; mime?: string }): Blob {
  const tipo = data.mime || 'application/octet-stream'
  if (data.encoding === 'base64') {
    const bin = atob(data.contenido)
    const bytes = new Uint8Array(bin.length)
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
    return new Blob([bytes], { type: tipo })
  }
  return new Blob([data.contenido], { type: tipo })
}

/**
 * Guarda lo que apunta un object URL (ej. el src de un iframe de previsualización),
 * preguntando dónde. Útil para los modales de PDF que ya tienen el blob como URL.
 */
export async function guardarDesdeUrl (url: string, nombre: string): Promise<ResultadoDescarga> {
  const blob = await (await fetch(url)).blob()
  return guardarComo(blob, nombre)
}
