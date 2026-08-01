// svgToPng.ts — Rasteriza un <svg> del DOM a un PNG (data URL), para incrustarlo en PDFs con jsPDF.
export interface PngResultado { url: string; w: number; h: number; ratio: number }

export function svgToPng (svg: SVGSVGElement, escala = 3): Promise<PngResultado | null> {
  const vb = (svg.getAttribute('viewBox') || '').split(/\s+/).map(Number)
  const w = vb[2] || svg.clientWidth || 64
  const h = vb[3] || svg.clientHeight || 64
  const clon = svg.cloneNode(true) as SVGSVGElement
  clon.setAttribute('xmlns', 'http://www.w3.org/2000/svg')
  clon.setAttribute('width', String(w)); clon.setAttribute('height', String(h))
  const xml = new XMLSerializer().serializeToString(clon)
  return rasterizar(xml, w, h, escala)
}

/** Rasteriza un SVG dado como texto (para logos que en pantalla son HTML/CSS, no <svg>). */
export function svgMarkupToPng (markup: string, w: number, h: number, escala = 3): Promise<PngResultado | null> {
  return rasterizar(markup, w, h, escala)
}

function rasterizar (xml: string, w: number, h: number, escala: number): Promise<PngResultado | null> {
  const src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml)
  return new Promise((res) => {
    const img = new Image()
    img.onload = () => {
      const cv = document.createElement('canvas'); cv.width = w * escala; cv.height = h * escala
      const ctx = cv.getContext('2d'); if (!ctx) { res(null); return }
      ctx.drawImage(img, 0, 0, cv.width, cv.height)
      res({ url: cv.toDataURL('image/png'), w, h, ratio: w / h })
    }
    img.onerror = () => res(null)
    img.src = src
  })
}
