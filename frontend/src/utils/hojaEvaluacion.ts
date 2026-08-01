// hojaEvaluacion.ts — dibuja la Hoja de Evaluación de desempeño (FG-6.02-03) en jsPDF.
// Una hoja por puesto/empleado. Compartido por Hojas de Evaluación (módulo de Puestos)
// y por la solapa Puestos de la ficha del empleado.
import type jsPDF from 'jspdf'

export const CRITERIOS = [
  'Responsabilidad en las tareas del puesto.',
  'Calidad en el trabajo.',
  'Logro de objetivos.',
  'Eficiencia.',
  'Adaptabilidad al trabajo en equipo.',
  'Organización de las tareas.',
  'Iniciativa (sugerencia de ideas, soluciones, etc).',
  'Proactividad.',
  'Capacidad para identificar problemas.',
  'Compromiso para resolución de problemas.',
  'Reacción ante dificultades.',
  'Respeto por supervisores y pares.',
  'Cumplimiento de normas de Higiene y Seguridad y MA.',
]
export const ESCALA = ['Deficiente', 'Regular', 'Bueno', 'Muy Bueno', 'Excelente']

/** Dibuja una hoja de evaluación (nombre + puesto) en la página actual del doc. */
export function dibujarHojaEvaluacion (doc: jsPDF, emp: { nombre?: string; puesto?: string }, logoDataUrl = ''): void {
  const ML = 12, PW = 210, W = PW - ML * 2
  let y = 12
  doc.setDrawColor(40); doc.setLineWidth(0.3)

  // Encabezado
  const hH = 18
  doc.rect(ML, y, W, hH)
  if (logoDataUrl) { try { doc.addImage(logoDataUrl, 'JPEG', ML + 2, y + 3, 36, hH - 6) } catch { /* */ } }
  doc.line(ML + 42, y, ML + 42, y + hH)
  doc.line(ML + W - 38, y, ML + W - 38, y + hH)
  doc.setFont('helvetica', 'bolditalic'); doc.setFontSize(15); doc.setTextColor(0, 0, 0)
  doc.text('Evaluación de desempeño', ML + 42 + (W - 42 - 38) / 2, y + 11, { align: 'center' })
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  doc.text('FG-6.02-03 V0', ML + W - 36, y + 8); doc.text('Página 1 de 1', ML + W - 36, y + 13)
  y += hH + 3

  // Nombre y Apellido (con la Fecha en blanco a la derecha) / Puesto — sin recuadro, como Fox.
  doc.setTextColor(0, 0, 0)
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.text('Nombre y Apellido:', ML, y + 4)
  doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
  doc.text(doc.splitTextToSize(emp.nombre || '', W - 90)[0] || '', ML + 33, y + 4)
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  doc.text('Fecha ...... / ...... / ..........', ML + W, y + 4, { align: 'right' })
  y += 8
  doc.text('Puesto:', ML, y + 4)
  doc.text(doc.splitTextToSize(emp.puesto || '', W - 24)[0] || '', ML + 33, y + 4)
  y += 9

  // Tabla PUNTOS A EVALUAR
  const cCrit = 96, cRate = (W - cCrit) / 5
  const headRow = (etiqueta: string, hh = 8) => {
    doc.setFillColor(228, 228, 228); doc.rect(ML, y, cCrit, hh, 'FD')
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5)
    doc.text(etiqueta, ML + 2, y + hh / 2 + 1.4)
    doc.setFontSize(6.6); doc.setFont('helvetica', 'bold')
    ESCALA.forEach((s, i) => {
      const x = ML + cCrit + i * cRate
      doc.setFillColor(228, 228, 228); doc.rect(x, y, cRate, hh, 'FD')
      const ls = doc.splitTextToSize(s, cRate - 2)
      doc.text(ls, x + cRate / 2, y + hh / 2 - (ls.length > 1 ? 1.2 : -1) , { align: 'center' })
    })
    y += hh
  }
  headRow('PUNTOS A EVALUAR')
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  const rh = 9   // filas altas: la tabla ocupa casi toda la hoja, como en Fox
  CRITERIOS.forEach((c) => {
    doc.rect(ML, y, cCrit, rh)
    const ls = doc.splitTextToSize(c, cCrit - 4)
    doc.text(ls, ML + 2, y + (rh / 2) + 1.2)
    for (let i = 0; i < 5; i++) doc.rect(ML + cCrit + i * cRate, y, cRate, rh)
    y += rh
  })
  y += 5

  // CONCLUSIÓN / EVALUACIÓN GENERAL
  headRow('CONCLUSIÓN')  // reutiliza la fila de escala (Deficiente…Excelente)
  doc.rect(ML, y, cCrit, rh); for (let i = 0; i < 5; i++) doc.rect(ML + cCrit + i * cRate, y, cRate, rh)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  doc.text('EVALUACIÓN GENERAL', ML + 2, y + (rh / 2) + 1.2); y += rh
  y += 6

  // OBSERVACIONES
  doc.setFillColor(228, 228, 228); doc.rect(ML, y, W, 7, 'FD')
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
  doc.text('OBSERVACIONES - CAPACITACIONES SUGERIDAS', PW / 2, y + 5, { align: 'center' }); y += 7
  doc.rect(ML, y, W, 46); y += 46   // ~2 cm más alto que antes
  y += 8

  // FIRMAS
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('FIRMAS', ML, y); y += 14
  doc.setLineWidth(0.2)
  doc.line(ML + 6, y, ML + 78, y); doc.line(ML + W - 78, y, ML + W - 6, y); y += 4
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
  doc.text('Ocupante del Puesto', ML + 42, y, { align: 'center' })
  doc.text('Responsable Directo del Puesto', ML + W - 42, y, { align: 'center' })
}
