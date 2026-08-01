/**
 * valePdf.ts — Dibuja el comprobante de un vale de efectivo en un documento jsPDF.
 * Reutilizado por Agregar Vales, Reimprimir Vales y Cerrar Vales.
 * Se dibuja una copia (original / duplicado) a partir de un offset vertical.
 */
import type jsPDF from 'jspdf'

export interface ValeData {
  numero: number
  empresa_nombre: string
  empresa_domicilio: string
  empresa_cuit: string
  personal_nombre: string
  personal_cuit: string
  importe: number
  en_letras: string
  fecha: string
  observaciones: string
  fondo_salida: string
}

const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** Dibuja una copia del vale en la página, a partir de la coordenada vertical `oy` (mm). */
export function dibujarVale (doc: jsPDF, v: ValeData, oy: number, etiqueta: string): void {
  const ML = 16, W = 178
  let y = oy + 10
  doc.setDrawColor(120); doc.setLineWidth(0.3); doc.rect(ML - 2, oy + 4, W + 4, 124)
  doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50)
  doc.text('VALE POR DINERO EN EFECTIVO', ML, y)
  doc.setFontSize(8); doc.setTextColor(120, 120, 120); doc.text(etiqueta, ML + W, y, { align: 'right' }); y += 8
  doc.setTextColor(0, 0, 0)
  const lin = (lbl: string, val: string, salto = 6) => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(lbl, ML, y)
    doc.setFont('helvetica', 'normal'); doc.text(val, ML + 34, y); y += salto
  }
  lin('EMPRESA:', v.empresa_nombre)
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(70, 70, 70)
  doc.text(`${v.empresa_domicilio}   CUIT: ${v.empresa_cuit}`, ML + 34, y - 2); doc.setTextColor(0, 0, 0); y += 2
  lin('PERSONAL:', v.personal_nombre)
  lin('CUIT:', v.personal_cuit)
  lin('FECHA:', v.fecha)
  lin('NÚMERO DE VALE:', String(v.numero))
  doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.text('IMPORTE:', ML, y)
  doc.setFontSize(13); doc.text('$ ' + nf.format(Number(v.importe)), ML + 34, y); y += 7
  doc.setFont('helvetica', 'italic'); doc.setFontSize(9); doc.text(v.en_letras, ML, y); y += 7
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('OBSERVACIONES:', ML, y)
  doc.setFont('helvetica', 'normal'); doc.text(v.observaciones || '', ML + 34, y); y += 7
  doc.setFont('helvetica', 'bold'); doc.text('FONDO:', ML, y); doc.setFont('helvetica', 'normal'); doc.text(v.fondo_salida, ML + 34, y); y += 9
  doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(150, 0, 0)
  doc.text('EL PRESENTE VALE DEBERÁ SER RENDIDO DENTRO DE LOS 10 DÍAS HÁBILES DE EMITIDO.-', ML, y); y += 12
  doc.setTextColor(0, 0, 0); doc.setDrawColor(120); doc.setLineWidth(0.2)
  doc.line(ML, y, ML + 70, y); doc.line(ML + 100, y, ML + W, y); y += 4
  doc.setFontSize(8); doc.text('Firma Empleado', ML, y); doc.text('Aclaración de Firma', ML + 100, y)
}
