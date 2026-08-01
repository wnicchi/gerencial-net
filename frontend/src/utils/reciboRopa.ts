import jsPDF from 'jspdf'

/**
 * Constancia de entrega de ropa (Resolución 299/2011) — réplica fiel del informe
 * de Fox `resolucion299_2011.frx`: recuadro de datos + grilla de 7 columnas
 * (Producto / Tipo-Modelo / Marca-Talle / Posee certificación / Cantidad /
 * Fecha de Entrega / Firma del trabajador) + pie legal + "Quedo notificado".
 *
 * Es un documento LEGAL: el formato debe respetarse tal cual el de Fox.
 */
export interface ReciboItem {
  codigo?: number | string
  detalle: string
  marca?: string
  talle?: string
  cantidad: number | string
  certifica?: boolean
  fecha?: string          // dd/mm/aaaa; si falta se usa la fecha de hoy
}
export interface ReciboRopa {
  empresa: { razon: string; cuit: string; dom?: string; loc?: string; cpo?: string; prv?: string }
  empleado: { nombre: string; dni: string | number }
  items: ReciboItem[]
  puestos?: string[]
  elementos?: string[]
  obsequios?: boolean     // variante "OBSEQUIO INSTITUCIONAL" (resolucion299_2011_obsequios.frx)
}

export function generarReciboRopa (p: ReciboRopa): Blob {
  const obseq = !!p.obsequios
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 12, MR = 198, PW = 210, PH = 297
  const W = MR - ML
  const hoy = new Date().toLocaleDateString('es-AR')

  doc.setLineWidth(0.3)
  doc.setDrawColor(0)
  doc.setTextColor(0, 0, 0)

  let y = 12

  // ---------- Recuadro de encabezado (datos empresa / trabajador / puesto) ----------
  const boxTop = y
  const line = (h: number) => { doc.line(ML, y, MR, y); return y + h }

  // Título (celda a todo el ancho)
  doc.line(ML, y, MR, y)               // borde superior
  y += 5.5
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5)
  doc.text(obseq ? 'CONSTANCIA DE ENTREGA: OBSEQUIO INSTITUCIONAL' : 'CONSTANCIA DE ENTREGA DE ROPA DE TRABAJO Y ELEMENTOS DE PROTECCION PERSONAL', PW / 2, y, { align: 'center' })
  y += 2.5
  y = line(0)                          // borde bajo el título

  // Fila: razón social | CUIT
  const xCuit = MR - 40
  y += 5
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  doc.text(p.empresa.razon || '', ML + 1.5, y)
  doc.text(p.empresa.cuit || '', xCuit + 1.5, y)
  y += 2
  doc.line(xCuit, y - 7, xCuit, y)     // separador vertical CUIT
  y = line(0)

  // Fila: dirección | CP | provincia
  const xCp = MR - 66, xPrv = MR - 40
  y += 5
  doc.text(p.empresa.dom || '', ML + 1.5, y)
  doc.text(p.empresa.cpo || '', xCp + 1.5, y)
  doc.text(p.empresa.prv || '', xPrv + 1.5, y)
  y += 2
  doc.line(xCp, y - 7, xCp, y)
  doc.line(xPrv, y - 7, xPrv, y)
  y = line(0)

  // Fila: nombre trabajador | DNI
  const xDni = MR - 40
  y += 5
  doc.text(`Nombre del Trabajador: ${p.empleado.nombre || ''}`, ML + 1.5, y)
  doc.text(`D.N.I.: ${p.empleado.dni ?? ''}`, xDni + 1.5, y)
  y += 2
  doc.line(xDni, y - 7, xDni, y)
  y = line(0)

  if (!obseq) {
    // Fila: descripción del puesto
    y += 5
    const puestos = (p.puestos && p.puestos.length) ? p.puestos.join(' / ') : '-'
    doc.text(`Descripción breve del puesto de trabajo en los cuales se desempeña el trabajador: ${puestos}`, ML + 1.5, y, { maxWidth: W - 3 })
    y += 2
    y = line(0)

    // Fila: elementos necesarios (varias líneas)
    y += 4
    doc.text('Elementos de protección personal, necesario para el trabajador, según el puesto de trabajo:', ML + 1.5, y)
    y += 4
    const elementos = (p.elementos && p.elementos.length) ? p.elementos.join(' / ') : '-'
    const lns = doc.splitTextToSize(elementos, W - 3)
    doc.text(lns, ML + 1.5, y)
    y += lns.length * 3.6 + 1.5
    y = line(0)
  }

  // Bordes verticales del recuadro de encabezado
  doc.line(ML, boxTop, ML, y)
  doc.line(MR, boxTop, MR, y)

  // ---------- Grilla de ítems ----------
  y += 6
  // Anchos de columna (suman W)
  const wProd = 20, wTipo = 56, wMarca = 28, wCert = 23, wCant = 15, wFecha = 22
  const wFirma = W - (wProd + wTipo + wMarca + wCert + wCant + wFecha)
  const cx = [
    ML,
    ML + wProd,
    ML + wProd + wTipo,
    ML + wProd + wTipo + wMarca,
    ML + wProd + wTipo + wMarca + wCert,
    ML + wProd + wTipo + wMarca + wCert + wCant,
    ML + wProd + wTipo + wMarca + wCert + wCant + wFecha,
    MR,
  ]
  const cellText = (t: string, colIni: number, colFin: number, yy: number, align: 'left' | 'center' = 'left') => {
    if (align === 'center') doc.text(t, (cx[colIni] + cx[colFin]) / 2, yy, { align: 'center' })
    else doc.text(t, cx[colIni] + 1.5, yy)
  }

  const rowH = 6
  const headTop = y
  // Encabezado de la grilla (dos líneas)
  doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5)
  const hy = y + 4
  cellText('Producto', 0, 1, hy, 'center')
  cellText('Tipo / Modelo', 1, 2, hy, 'center')
  cellText('Marca \\ Talle', 2, 3, hy, 'center')
  if (obseq) {
    cellText('Tipo Obsequio', 3, 4, hy, 'center')
  } else {
    doc.text('Posee certificación', (cx[3] + cx[4]) / 2, y + 3, { align: 'center' })
    doc.text('SI / NO', (cx[3] + cx[4]) / 2, y + 6.2, { align: 'center' })
  }
  cellText('Cantidad', 4, 5, hy, 'center')
  cellText('Fecha de Entrega', 5, 6, hy, 'center')
  cellText('Firma del trabajador', 6, 7, hy, 'center')
  const headH = 8
  y += headH

  // Filas de datos + relleno para completar la planilla
  doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5)
  const items = [...(p.items || [])]
  const filasTotal = Math.max(items.length, 16)
  const bodyTop = y
  for (let i = 0; i < filasTotal; i++) {
    const it = items[i]
    if (y + rowH > PH - 34) { // reserva para el pie
      // cerramos la grilla actual antes del salto
      break
    }
    const ty = y + 4
    if (it) {
      cellText(String(it.codigo ?? ''), 0, 1, ty, 'center')
      doc.text(doc.splitTextToSize(it.detalle || '', wTipo - 3)[0] || '', cx[1] + 1.5, ty)
      const mt = (it.marca || '').trim() + (it.talle ? ' \\ ' + it.talle : '')
      doc.text(doc.splitTextToSize(mt, wMarca - 3)[0] || '', cx[2] + 1.5, ty)
      cellText(obseq ? 'SI' : (it.certifica ? 'SI' : 'NO'), 3, 4, ty, 'center')
      cellText(Number(it.cantidad).toFixed(2), 4, 5, ty, 'center')
      cellText(it.fecha || hoy, 5, 6, ty, 'center')
    }
    y += rowH
  }
  const bodyBottom = y

  // Bordes de la grilla
  doc.setLineWidth(0.3)
  // horizontales: encabezado
  doc.line(ML, headTop, MR, headTop)
  doc.line(ML, headTop + headH, MR, headTop + headH)
  // horizontales: cada fila
  for (let yy = bodyTop; yy <= bodyBottom + 0.01; yy += rowH) doc.line(ML, yy, MR, yy)
  // verticales (de arriba del encabezado a la última fila)
  for (const x of cx) doc.line(x, headTop, x, bodyBottom)

  // ---------- Pie ----------
  y = bodyBottom + 6
  doc.setFont('helvetica', 'normal'); doc.setFontSize(7)
  const pie = obseq
    ? 'Información adicional: Dejando constancia de conformidad con la misma, para su efecto firma el colaborador este registro.'
    : 'Según Resolución 299/11 de la Superintendencia de Riesgo de Trabajo este formulario será de utilización obligatoria por parte de los empleadores, donde se registrarán las respectivas entregas de ropa de trabajo y Elementos de Protección Personal.'
  const pieLns = doc.splitTextToSize(pie, W)
  doc.text(pieLns, ML, y)
  y += pieLns.length * 3.3 + 8

  if (!obseq) {
    doc.setFontSize(8)
    doc.text('Quedo notificado:', ML, y)
    doc.line(ML + 30, y, ML + 95, y)
  }

  return doc.output('blob')
}
