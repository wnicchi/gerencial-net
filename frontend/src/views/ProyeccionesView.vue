<template>
  <div class="pf">
    <div class="pf-cab">
      <div class="pf-cab-ico">💰</div>
      <div class="pf-cab-tx">
        <h1>Proyecciones Financieras Mensual</h1>
        <p>Flujo proyectado por concepto — 4 semanas</p>
      </div>
      <ModuloAyudaIA style="margin-left:auto" modulo="Proyecciones Financieras Mensual" icono="💰"
        descripcion="Informe solo lectura del flujo proyectado por concepto (ingresos a cobrar y egresos a pagar) para las próximas semanas."
        :sugerencias="['¿Qué proyecta este informe?', '¿De dónde salen los importes?', '¿Cómo leo el flujo por concepto?']"
        intro="Proyecta el flujo de fondos por concepto para las próximas 4 semanas."
        :pasos="['<b>Generá</b> el informe.', 'Revisá los importes por concepto y semana.', '<b>Informe</b> imprime el resultado.']" />
      <div class="pf-acc">
        <button class="pf-btn" :disabled="cargando" @click="cargar">↻ Generar Informe</button>
        <button v-if="filas.length" class="pf-btn" @click="imprimir">🖨 Informe</button>
        <button v-if="filas.length" class="pf-btn" @click="aExcel">📊 Excel</button>
      </div>
    </div>

    <!-- Modal de previsualización del PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pdf-ov" @click.self="cerrarPdf">
        <div class="pdf-md">
          <div class="pdf-head"><span>{{ pdfNombre }}</span>
            <div class="pdf-acc">
              <button class="pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>

    <transition name="msg"><div v-if="msg" class="pf-msg err">{{ msg }}</div></transition>

    <div v-if="cargando" class="pf-load">Calculando proyección… (puede demorar unos segundos)</div>

    <template v-if="filas.length">
      <!-- Filtros -->
      <div class="pf-filtros">
        <label class="pf-f">Concepto
          <select v-model="fConcepto" class="pf-sel">
            <option value="">TODO</option>
            <option v-for="t in tiposPresentes" :key="t" :value="t">{{ LABELS[t] }}</option>
          </select>
        </label>
        <label class="pf-f">Banco
          <select v-model.number="fBanco" class="pf-sel">
            <option :value="0">TODOS</option>
            <option v-for="b in bancosPresentes" :key="b.cod" :value="b.cod">{{ b.nom }}</option>
          </select>
        </label>
        <label class="pf-f grow">Buscar cuenta
          <input v-model="fTexto" class="pf-in" placeholder="filtrar por detalle…" />
        </label>
      </div>

      <div class="pf-scroll">
        <table class="pf-tabla">
          <thead>
            <tr>
              <th class="det">Cuentas</th>
              <th v-for="(col, i) in columnas" :key="i">{{ col }}<br /><small>{{ rangos[i] }}</small></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(f, idx) in filasFiltradas" :key="idx" :style="{ background: COLORES[f.tipo] || '#fff', color: f.tipo === 'M' ? '#fff' : '#111' }">
              <td class="det">{{ f.detalle }}</td>
              <td class="num" :class="{ neg: f.s1 < 0 }">{{ money(f.s1) }}</td>
              <td class="num" :class="{ neg: f.s2 < 0 }">{{ money(f.s2) }}</td>
              <td class="num" :class="{ neg: f.s3 < 0 }">{{ money(f.s3) }}</td>
              <td class="num" :class="{ neg: f.s4 < 0 }">{{ money(f.s4) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="pf-tot">
              <td class="det">TOTALES MENSUALES</td>
              <td v-for="(t, i) in totalesFiltrados" :key="i" class="num" :class="t < 0 ? 'tneg' : 'tpos'">{{ money(t) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="pf-gral" :class="totalGral < 0 ? 'neg' : 'pos'">
        TOTAL GRAL: {{ money(totalGral) }}
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * ProyeccionesView.vue — Estadísticas › Proyecciones Financieras Mensual.
 * Grilla de 4 semanas con el flujo proyectado por concepto (coloreado por tipo),
 * filtros por concepto/banco/texto y totales. Migra proyecciones_interface.scx.
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'
import * as XLSX from 'xlsx'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

interface Fila { detalle: string; tipo: string; banco_cod: number; banco_nom: string; s1: number; s2: number; s3: number; s4: number }

// Colores por tipo (misma paleta que el Fox).
const COLORES: Record<string, string> = {
  I: 'rgb(255,255,0)', L: 'rgb(202,235,251)', C: 'rgb(227,157,210)', F: 'rgb(176,233,152)',
  S: 'rgb(238,149,147)', H: 'rgb(214,200,171)', D: 'rgb(147,152,210)', T: 'rgb(225,134,72)',
  P: 'rgb(126,190,190)', O: 'rgb(255,159,255)', R: 'rgb(128,255,128)', E: 'rgb(0,171,253)',
  M: 'rgb(217,26,83)', V: 'rgb(255,223,191)',
}
const LABELS: Record<string, string> = {
  I: 'Impuestos y Servicios', L: 'Leasing', C: 'Créditos', F: 'Fondo Fijo', S: 'Seguros',
  H: 'Haberes', D: 'Cheques Diferidos', T: 'Transferencias', P: 'Cheques a Proveedores',
  E: 'Compras Equipos', M: 'Interbanking / Manuales', V: 'Órdenes Compra Varias',
  R: 'Cheques en Cartera', O: 'Cobranzas',
}

const columnas = ref<string[]>([])
const rangos = ref<string[]>([])
const filas = ref<Fila[]>([])
const totales = ref<number[]>([0, 0, 0, 0])
const cargando = ref(false)
const msg = ref('')

const fConcepto = ref('')
const fBanco = ref(0)
const fTexto = ref('')

const money = (v: number) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)

async function cargar () {
  cargando.value = true; msg.value = ''
  try {
    const d = (await api.get('/tablero/gestion/proyecciones')).data
    columnas.value = d.columnas; rangos.value = d.rangos; filas.value = d.filas; totales.value = d.totales
  } catch { filas.value = []; msg.value = 'No se pudo generar la proyección.' }
  finally { cargando.value = false }
}

const tiposPresentes = computed(() => [...new Set(filas.value.map(f => f.tipo))].sort())
const bancosPresentes = computed(() => {
  const m = new Map<number, string>()
  for (const f of filas.value) if (f.banco_cod > 0) m.set(f.banco_cod, f.banco_nom || String(f.banco_cod))
  return [...m.entries()].map(([cod, nom]) => ({ cod, nom })).sort((a, b) => a.nom.localeCompare(b.nom))
})

const filasFiltradas = computed(() => filas.value.filter(f =>
  (!fConcepto.value || f.tipo === fConcepto.value) &&
  (!fBanco.value || f.banco_cod === fBanco.value) &&
  (!fTexto.value || f.detalle.toUpperCase().includes(fTexto.value.toUpperCase())),
))
const totalesFiltrados = computed(() => {
  const t = [0, 0, 0, 0]
  for (const f of filasFiltradas.value) { t[0] += f.s1; t[1] += f.s2; t[2] += f.s3; t[3] += f.s4 }
  return t.map(v => Math.round(v * 100) / 100)
})
const totalGral = computed(() => totalesFiltrados.value.reduce((a, b) => a + b, 0))

// ── PDF (A4 apaisado, previsualización en modal) — réplica de la grilla en pantalla ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const pdfFrame = ref<HTMLIFrameElement>()
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function rgb (s: string): [number, number, number] {
  const m = s.match(/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/)
  return m ? [+m[1]!, +m[2]!, +m[3]!] : [255, 255, 255]
}

function imprimir () {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 10, MR = 287; let y = 15
  const xDet = ML, wDet = 112
  const nS = columnas.value.length
  const wCol = (MR - (ML + wDet)) / nS
  const colR = (j: number) => ML + wDet + (j + 1) * wCol   // borde derecho de la columna semana j

  // Cabecera
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14)
  doc.text('PROYECCIONES FINANCIERAS MENSUAL', 148.5, y, { align: 'center' })
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  doc.text('SILCAR Logística y Representaciones S.A', ML, y)
  doc.text(new Date().toLocaleString('es-AR'), MR, y, { align: 'right' })
  y += 8

  const cabTabla = () => {
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    doc.rect(ML, y - 4.6, MR - ML, 8.6, 'F')
    doc.text('Cuentas', xDet + 1, y)
    columnas.value.forEach((c, j) => {
      doc.text(c, colR(j) - 1, y, { align: 'right' })
      doc.setFontSize(6.5); doc.setFont('helvetica', 'normal')
      doc.text(rangos.value[j] || '', colR(j) - 1, y + 3, { align: 'right' })
      doc.setFontSize(8); doc.setFont('helvetica', 'bold')
    })
    y += 6.5; doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal')
  }
  cabTabla(); doc.setFontSize(7.6)

  filasFiltradas.value.forEach(f => {
    if (y > 195) { doc.addPage(); y = 15; cabTabla(); doc.setFontSize(7.6) }
    const [r, g, b] = rgb(COLORES[f.tipo] || '255,255,255')
    doc.setFillColor(r, g, b); doc.rect(ML, y - 3.6, MR - ML, 5, 'F')
    doc.setTextColor(f.tipo === 'M' ? 255 : 17, f.tipo === 'M' ? 255 : 17, f.tipo === 'M' ? 255 : 17)
    doc.text(f.detalle.slice(0, 66), xDet + 1.5, y)
    const vals = [f.s1, f.s2, f.s3, f.s4]
    vals.forEach((v, j) => {
      if (v < 0) doc.setTextColor(178, 34, 34); else doc.setTextColor(f.tipo === 'M' ? 255 : 17, f.tipo === 'M' ? 255 : 17, f.tipo === 'M' ? 255 : 17)
      doc.text(money(v), colR(j) - 1, y, { align: 'right' })
    })
    y += 5
  })

  // Totales mensuales
  if (y > 190) { doc.addPage(); y = 15 }
  doc.setFillColor(27, 67, 50); doc.rect(ML, y - 3.6, MR - ML, 6, 'F')
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(255, 255, 255)
  doc.text('TOTALES MENSUALES', xDet + 1.5, y)
  totalesFiltrados.value.forEach((t, j) => {
    doc.setTextColor(t < 0 ? 252 : 134, t < 0 ? 165 : 239, t < 0 ? 165 : 172)
    doc.text(money(t), colR(j) - 1, y, { align: 'right' })
  })
  y += 9

  // Total general
  doc.setTextColor(totalGral.value < 0 ? 178 : 22, totalGral.value < 0 ? 34 : 101, totalGral.value < 0 ? 34 : 52)
  doc.setFontSize(11); doc.text('TOTAL GRAL: ' + money(totalGral.value), MR, y, { align: 'right' })

  // Pie
  const paginas = doc.getNumberOfPages()
  const usuario = auth.usuario?.NOMBRE || ''
  for (let p = 1; p <= paginas; p++) {
    doc.setPage(p); doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(120, 120, 120)
    doc.text(usuario, ML, 205); doc.text(`Página ${p} de ${paginas}`, MR, 205, { align: 'right' })
  }

  cerrarPdf()
  pdfNombre.value = 'PROYECCIONES_MENSUAL.pdf'
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

function aExcel () {
  const heads = ['Cuentas', ...columnas.value]
  const rows = filasFiltradas.value.map(f => [f.detalle, f.s1, f.s2, f.s3, f.s4])
  rows.push(['TOTALES MENSUALES', ...totalesFiltrados.value])
  const ws = XLSX.utils.aoa_to_sheet([heads, ...rows])
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Proyecciones')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  guardarDesdeUrl(url, 'proyecciones_financieras.xlsx')
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

cargar()
</script>

<style scoped>
.pf { padding: 14px 16px 40px; color: #1e293b; }
.pf-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.pf-cab-ico { font-size: 30px; }
.pf-cab-tx h1 { margin: 0; font-size: 19px; }
.pf-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.pf-acc { margin-left: auto; display: flex; gap: 8px; }
.pf-btn { border: none; border-radius: 7px; padding: 8px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.pf-btn:disabled { opacity: .6; cursor: default; }
.pf-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 13px; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.pf-load { margin-top: 16px; color: #64748b; font-size: 13px; }
.pf-filtros { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-top: 14px; }
.pf-f { display: flex; flex-direction: column; gap: 4px; font-size: 11.5px; font-weight: 700; color: #475569; }
.pf-f.grow { flex: 1 1 220px; }
.pf-sel, .pf-in { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 9px; font-size: 13px; color: #1e293b; background: #fff; }
.pf-scroll { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 14px; }
.pf-tabla { width: 100%; border-collapse: collapse; font-size: 12px; white-space: nowrap; }
.pf-tabla th { position: sticky; top: 0; background: #2d6a9f; color: #fff; padding: 6px 10px; text-align: right; font-weight: 700; }
.pf-tabla th.det { text-align: left; }
.pf-tabla th small { font-weight: 400; opacity: .8; }
.pf-tabla td { padding: 4px 10px; border-bottom: 1px solid rgba(0,0,0,.06); }
.pf-tabla td.det { text-align: left; font-weight: 600; }
.pf-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.pf-tabla td.num.neg { color: #b91c1c; font-weight: 700; }
.pf-tot td { background: #1b4332; color: #fff; font-weight: 800; padding: 6px 10px; }
.pf-tot td.num.tneg { color: #fca5a5; }
.pf-tot td.num.tpos { color: #86efac; }
.pf-gral { margin-top: 10px; text-align: right; font-size: 15px; font-weight: 800; padding: 8px 14px; border-radius: 8px; }
.pf-gral.pos { background: #dcfce7; color: #166534; }
.pf-gral.neg { background: #fee2e2; color: #b91c1c; }

/* Modal PDF */
.pdf-ov { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.pdf-md { width: min(1040px, 96vw); height: 90vh; background: #fff; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; }
.pdf-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #f4f7fc; border-bottom: 1px solid #dde4ee; font-weight: 700; color: #1a3a5c; }
.pdf-acc { display: flex; gap: 8px; }
.pdf-b { border: none; border-radius: 6px; padding: 7px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; }
.pdf-b.ok { background: #1b4332; color: #fff; }
.pdf-b.cancel { background: #e2e8f0; color: #334155; }
.pdf-frame { flex: 1; border: none; width: 100%; }
</style>
