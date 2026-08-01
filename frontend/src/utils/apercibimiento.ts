// apercibimiento.ts — comprobante de apercibimiento en jsPDF (réplica de apercibimiento.frx).
// Dos copias en la misma hoja A4: una para el empleado y otra para la empresa.
import type jsPDF from 'jspdf'

interface DatosApercibimiento {
  nombre: string
  cuit: string
  empresa: { nombre: string; domicilio: string; cuit: string }
}

const fmtFecha = (v: string) => v ? v.split('-').reverse().join('/') : ''

/** Dibuja una copia del comprobante a partir de la coordenada Y dada; devuelve la Y final. */
function copia (doc: jsPDF, d: DatosApercibimiento, fecha: string, razon: string, y0: number): number {
  const ML = 20, AW = 170
  let y = y0
  doc.setFont('courier', 'bold'); doc.setFontSize(11)
  doc.text('EMPRESA:', ML, y)
  doc.setFont('helvetica', 'normal'); doc.setFontSize(10)
  doc.text(d.empresa.nombre || '', ML + 26, y); y += 5
  doc.text(d.empresa.domicilio || '', ML + 26, y); y += 5
  doc.text('CUIT: ' + (d.empresa.cuit || ''), ML + 26, y); y += 9

  doc.setFont('courier', 'bold'); doc.text('PERSONAL:', ML, y)
  doc.setFont('helvetica', 'normal'); doc.text(d.nombre || '', ML + 26, y); y += 5
  doc.setFont('courier', 'bold'); doc.text('CUIT:', ML, y)
  doc.setFont('helvetica', 'normal'); doc.text(d.cuit || '', ML + 26, y); y += 9

  doc.setFont('courier', 'bold'); doc.text('FECHA:', ML, y)
  doc.setFont('helvetica', 'normal'); doc.text(fmtFecha(fecha), ML + 26, y); y += 8

  doc.setFont('courier', 'bold'); doc.text('RAZON:', ML, y); y += 6
  doc.setFont('courier', 'normal'); doc.setFontSize(10)
  for (const ln of doc.splitTextToSize(razon || '', AW)) { doc.text(ln, ML, y); y += 5 }
  y += 16

  doc.setFont('courier', 'normal'); doc.setFontSize(10)
  doc.text('.......................................', ML, y)
  doc.text('.......................................', ML + 92, y); y += 5
  doc.text('Firma Empleado', ML + 8, y)
  doc.text('Aclaración de Firma', ML + 98, y); y += 6
  return y
}

/** Genera el comprobante completo (copia empleado + copia empresa) en el doc. */
export function comprobanteApercibimiento (doc: jsPDF, d: DatosApercibimiento, fecha: string, razon: string): void {
  let y = copia(doc, d, fecha, razon, 24)
  // separador entre copias
  doc.setDrawColor(180); doc.setLineDashPattern([2, 2], 0); doc.line(20, y + 4, 190, y + 4); doc.setLineDashPattern([], 0)
  copia(doc, d, fecha, razon, y + 16)
}
