/**
 * pdfLogo.ts — Estampa el logo de la empresa en el encabezado de TODOS los PDF del proyecto.
 *
 * En vez de tocar cada generador de PDF (hay muchos), se intercepta jsPDF una sola vez:
 * al producir la salida (doc.output(...)) se recorren todas las páginas y se dibuja el logo
 * de la empresa activa arriba a la izquierda. La empresa se toma del store de auth (definida
 * por el .env / la base que usa cada servidor): Autoelevadores = logo verde, Logística = azul.
 *
 * El logo se precarga a un data URL reducido (para no inflar los PDF) al iniciar la app.
 */
import jsPDF from 'jspdf'
import { useAuthStore } from '@/stores/auth'
import logoSilcar from '@/assets/logo-silcar.jpg'
import logoLogist from '@/assets/logo-logist.png'

type LogoCache = { url: string; ratio: number; fmt: 'PNG' | 'JPEG' }
const cache: Record<string, LogoCache> = {}
const FUENTES: Record<string, string> = { silcar: logoSilcar, logist: logoLogist }

/** Carga una imagen y la reduce a un data URL de ~600px de ancho (mantiene la proporción). */
function prepararLogo (src: string): Promise<LogoCache | null> {
  return new Promise((res) => {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.onload = () => {
      const maxW = 600
      const escala = Math.min(1, maxW / img.naturalWidth)
      const w = Math.round(img.naturalWidth * escala), h = Math.round(img.naturalHeight * escala)
      const cv = document.createElement('canvas'); cv.width = w; cv.height = h
      const ctx = cv.getContext('2d')
      if (!ctx) { res(null); return }
      ctx.drawImage(img, 0, 0, w, h)
      // PNG conserva la transparencia (logo azul); JPG para el verde (fondo blanco) pesa menos.
      const esPng = /\.png($|\?)/i.test(src)
      res({ url: cv.toDataURL(esPng ? 'image/png' : 'image/jpeg', 0.92), ratio: img.naturalWidth / img.naturalHeight, fmt: esPng ? 'PNG' : 'JPEG' })
    }
    img.onerror = () => res(null)
    img.src = src
  })
}

/** Precarga los logos de ambas empresas (llamar una vez al iniciar la app). */
export async function precargarLogosPdf (): Promise<void> {
  for (const [emp, src] of Object.entries(FUENTES)) {
    const l = await prepararLogo(src)
    if (l) cache[emp] = l
  }
}

/** Estampa el logo de la empresa activa en cada página del documento (una sola vez por doc). */
const yaEstampados = new WeakSet<object>()
function estampar (doc: jsPDF): void {
  if (yaEstampados.has(doc)) return
  // Los PDF con su propio encabezado (ej. informes de banco) piden no estampar el logo de la empresa.
  if ((doc as any).__sinLogo) { yaEstampados.add(doc); return }
  let emp = ''
  try { emp = String(useAuthStore().empresa || '') } catch { /* store aún no listo */ }
  const logo = cache[emp] || cache['logist'] || cache['silcar']
  if (!logo) return

  const pw = doc.internal.pageSize.getWidth()
  // Logo chico y alto (en el margen superior) para no pisar los títulos, que suelen ir a y≥12.
  const ML = 8, yTop = 3, altura = 7
  const ancho = Math.min(altura * logo.ratio, pw * 0.34)
  // Lado: por defecto izquierda; los PDF con título arriba a la izquierda lo piden a la derecha
  // con  (doc as any).__logoDer = true  antes de generar la salida.
  const x = (doc as any).__logoDer ? (pw - ML - ancho) : ML
  const total = (doc as any).getNumberOfPages ? doc.getNumberOfPages() : (doc.internal.pages.length - 1)
  const actual = (doc as any).getCurrentPageInfo ? doc.getCurrentPageInfo().pageNumber : 1
  for (let p = 1; p <= total; p++) {
    doc.setPage(p)
    try { doc.addImage(logo.url, logo.fmt, x, yTop, ancho, altura) } catch { /* página sin espacio */ }
  }
  doc.setPage(actual)
  yaEstampados.add(doc)
}

/**
 * Instala la intercepción de jsPDF (llamar una vez al iniciar la app).
 *
 * En jsPDF v4 `output` es un closure por instancia (no está en el prototipo), así que se
 * usa el evento 'initialized' que dispara el constructor por cada documento nuevo: ahí se
 * envuelve el `output` de ESA instancia para estampar el logo antes de generar la salida.
 */
let instalado = false
export function instalarLogoPdf (): void {
  if (instalado) return
  instalado = true
  jsPDF.API.events.push(['initialized', function (this: jsPDF) {
    const orig = this.output.bind(this)
    ;(this as any).output = (...args: any[]) => {
      try { estampar(this) } catch { /* nunca romper la generación del PDF */ }
      return (orig as any)(...args)
    }
  }])
}
