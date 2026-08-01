// pdfAcreditaciones.ts — Informe de acreditaciones por lote (formato FoxPro), compartido por
// todas las consultas de banco. Encabezado con banco/empresa/modalidad/fecha/monto + Nro. Interno,
// y grilla cuadriculada: Legajo | Empleado | Sistema | Cuenta | Importe | Comprobante | Extracto.
import jsPDF from 'jspdf'

export interface FilaAcred { legajo: number | string; nombre: string; cuenta: string; importe: number }
export interface DatosAcred {
  banco: string        // ej. "Nuevo Banco Santa Fe"
  empresa: string
  concepto: string     // VACACIONES, HORAS EXTRAS, …
  fecha: string        // dd/mm/aaaa
  monto: number
  nroInterno: number | string
  modalidad?: string   // por defecto "Pagos on line"
  sistema?: string     // por defecto "CA" (caja de ahorro)
  bancoLogoUrl?: string    // data URL del logo del banco (PNG/JPEG), arriba a la derecha
  bancoLogoRatio?: number  // ancho/alto del logo del banco
  filas: FilaAcred[]
}

const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

/** Genera el PDF y devuelve el object URL (para abrir/descargar). */
export function pdfAcreditaciones (d: DatosAcred): string {
  // Hoja HORIZONTAL (apaisada), como el informe original de FoxPro: así entran holgadas
  // las columnas Importe / Comprobante / Extracto sin superponerse.
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  // NO se suprime el logo de la empresa: la interceptación global lo estampa arriba a la izquierda
  // (nuestro logo); el logo del BANCO va arriba a la derecha.
  const PW = 297, PH = 210, mL = 8, mR = 289

  const dibujarEncabezado = () => {
    // Logo del banco arriba a la derecha (si vino). El de la empresa lo pone la interceptación global.
    let rightBottom = 6
    if (d.bancoLogoUrl) {
      const h = 15, w = h * (d.bancoLogoRatio || 3)
      try { doc.addImage(d.bancoLogoUrl, mR - w, 4, w, h) } catch { /* */ }
      rightBottom = 4 + h + 3
    }
    // El texto arranca en y=16 para no pisar el logo de la empresa (que va a y≈3–10 a la izquierda).
    let y = 17
    doc.setTextColor(0, 0, 0)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.text(d.banco, mL, y); y += 5
    doc.setFontSize(8.5); doc.text('Detalle de movimientos enviados', mL, y); y += 5
    const dato = (lbl: string, val: string) => {
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.text(lbl, mL, y)
      doc.setFont('helvetica', 'bold'); doc.text(val, mL + doc.getTextWidth(lbl) + 1, y); y += 4.4
    }
    dato('Empresa : ', d.empresa)
    dato('Modalidad : ', d.modalidad || 'Pagos on line')
    dato('Fecha de Acreditación : ', d.fecha)
    dato('Monto de Acreditación : ', money(d.monto))
    // Centro: concepto
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.text((d.concepto || '').toUpperCase(), PW / 2, 18, { align: 'center' })
    // Derecha: Nro. Interno (debajo del logo del banco)
    doc.setFontSize(11); doc.text(`Nro. Interno:  ${d.nroInterno}`, mR, Math.max(rightBottom, 18), { align: 'right' })
    return Math.max(y, rightBottom) + 4   // y donde arranca la grilla
  }

  // Grilla — hoja apaisada: columnas anchas, sin superposición de títulos.
  const bx = [mL, 30, 120, 150, 195, 235, 262, mR]
  const rowH = 6
  const cxc = (i: number) => (bx[i]! + bx[i + 1]!) / 2

  const dibujarCabGrilla = (y: number) => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.setDrawColor(0); doc.setLineWidth(0.3)
    doc.line(mL, y, mR, y)
    const b = y + 4.2
    doc.text('Legajo', cxc(0), b, { align: 'center' })
    doc.text('Empleado', bx[1]! + 2, b)
    doc.text('Sistema', cxc(2), b, { align: 'center' })
    doc.text('Cuenta', bx[4]! - 1, b, { align: 'right' })
    doc.text('Importe', bx[5]! - 1, b, { align: 'right' })
    doc.text('Comprobante', cxc(5), b, { align: 'center' })
    doc.text('Extracto', cxc(6), b, { align: 'center' })
    const bot = y + rowH
    doc.line(mL, bot, mR, bot)
    return bot
  }

  let y = dibujarEncabezado()
  let top = y
  y = dibujarCabGrilla(y)
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)

  const cerrarTramo = (desde: number, hasta: number) => { doc.setDrawColor(0); doc.setLineWidth(0.3); for (const x of bx) doc.line(x, desde, x, hasta) }

  const sistema = d.sistema || 'CA'
  for (const f of d.filas) {
    if (y + rowH > PH - 12) {
      cerrarTramo(top, y)
      doc.addPage(); y = 12; top = y; y = dibujarCabGrilla(y); doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
    }
    const b = y + 4.2
    doc.text(String(f.legajo ?? ''), cxc(0), b, { align: 'center' })
    doc.text(String(f.nombre || '').slice(0, 42), bx[1]! + 2, b)
    doc.text(sistema, cxc(2), b, { align: 'center' })
    doc.text(String(f.cuenta || ''), bx[4]! - 1, b, { align: 'right' })
    doc.text(money(f.importe), bx[5]! - 1, b, { align: 'right' })
    y += rowH
    doc.setDrawColor(0); doc.setLineWidth(0.2); doc.line(mL, y, mR, y)
  }
  cerrarTramo(top, y)

  return URL.createObjectURL(doc.output('blob'))
}
