<!-- NovedadesView.vue — Planilla de Novedades de Sueldos (consulta). -->
<template>
  <div class="nv-view">
    <div class="nv-cab">
      <div class="nv-cab-ico">📋</div>
      <div class="nv-cab-tx">
        <h1>Planilla de Novedades de Sueldos</h1>
        <p>{{ rows.length ? rows.length + ' empleados — ' + periodo : 'Elegí el período y presioná Aceptar' }}</p>
      </div>
      <button class="nv-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="nv-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <NovedadesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/novedades" titulo="Asistente IA — Planilla de Novedades"
            subtitulo="Preguntá sobre la planilla de novedades de sueldos"
            :sugerencias="['¿Qué período abarca la planilla?','¿Cómo se calculan los días trabajados?','¿Qué significan las celdas en amarillo?','¿Cómo se calculan las horas extra?']"
            @close="modalIA = false" />

    <!-- Filtros -->
    <div class="nv-filtros">
      <label>Empresa
        <select v-model.number="f.empresa">
          <option v-for="e in empresas" :key="e.cod" :value="Number(e.cod)">{{ e.nombre }}</option>
        </select>
      </label>
      <label>Mes <input v-model.number="f.mes" type="number" min="1" max="12" /></label>
      <label>Año <input v-model.number="f.anio" type="number" min="2000" max="2999" /></label>
      <label>Planilla Hs. Extra
        <span class="nv-plan">
          <input v-model.number="f.nro_planilla" type="number" min="0" />
          <button class="nv-btn-buscar" title="Buscar planilla" @click="abrirBuscador">🔍</button>
        </span>
      </label>
      <div class="nv-contra">
        <span>Contratista:</span>
        <label><input v-model.number="f.modo_contra" type="radio" :value="1" /> Todos</label>
        <label><input v-model.number="f.modo_contra" type="radio" :value="2" /> Uno</label>
        <select v-model.number="f.contratista" :disabled="f.modo_contra !== 2">
          <option :value="0">—</option>
          <option v-for="c in contratistas" :key="c.cod" :value="Number(c.cod)">{{ c.nombre }}</option>
        </select>
      </div>
      <button class="nv-btn-aceptar" :disabled="cargando" @click="aceptar">{{ cargando ? '⟳ Calculando…' : 'ACEPTAR' }}</button>
    </div>

    <p v-if="bloqueado" class="nv-bloqueo">🔒 Período BLOQUEADO — consulta de solo lectura (no se puede modificar).</p>

    <!-- Selector de vista + exportar -->
    <div v-if="rows.length" class="nv-barra">
      <div class="nv-tabs">
        <label><input v-model="vista" type="radio" value="completa" /> Ver Completa</label>
        <label><input v-model="vista" type="radio" value="p1" /> Parte 1</label>
        <label><input v-model="vista" type="radio" value="p2" /> Parte 2</label>
      </div>
      <span class="nv-leyenda"><i class="nv-mu"></i> celda con valor &nbsp;·&nbsp; <i class="nv-conv"></i> SMATA / F.Convenio</span>
      <button v-if="!bloqueado" class="nv-btn-confirmar" :disabled="confirmando" @click="confirmar">
        {{ confirmando ? '⟳ Grabando…' : '✔ CONFIRMAR' }}
      </button>
      <button class="nv-btn-imprimir" @click="imprimir">🖨 Imprimir</button>
      <button class="nv-btn-excel" @click="exportar">📊 Exportar a Excel</button>
    </div>

    <!-- Grilla -->
    <div v-if="rows.length" class="nv-grid-wrap">
      <table class="nv-grid">
        <thead>
          <tr><th v-for="c in colsVisibles" :key="c.key" :style="alinear(c)">{{ c.label }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.codigo">
            <td v-for="c in colsVisibles" :key="c.key" :class="celdaClase(c, r)" :style="alinear(c)">{{ fmt(c, r[c.key]) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!cargando && consultado" class="nv-vacio">Sin novedades para el período seleccionado.</div>

    <!-- Buscador de planillas de horas extras -->
    <Teleport to="body">
      <div v-if="buscador" class="nv-ov" @click.self="buscador = false">
        <div class="nv-busc">
          <div class="nv-busc-cab"><h3>🔍 Buscar Planillas de Horas Extras</h3><button class="nv-busc-x" @click="buscador = false">✕</button></div>
          <div class="nv-busc-body">
            <div v-if="cargandoPlan" class="nv-busc-vacio">⟳ Cargando…</div>
            <div v-else-if="planillas.length === 0" class="nv-busc-vacio">No hay planillas de horas extras.</div>
            <table v-else class="nv-busc-tabla">
              <thead><tr><th style="width:70px">Nro.</th><th>Detalle de la Planilla</th><th style="width:110px">Fecha</th></tr></thead>
              <tbody>
                <tr v-for="p in planillas" :key="p.numero" :class="{ sel: p.numero === planSel }"
                    @click="planSel = p.numero" @dblclick="elegirPlanilla(p.numero)">
                  <td class="nv-busc-nro">{{ p.numero }}</td><td>{{ p.detalle }}</td><td>{{ p.fecha }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="nv-busc-pie">
            <button class="nv-btn-aceptar" :disabled="planSel === null" @click="elegirPlanilla(planSel!)">ACEPTAR</button>
            <button class="nv-busc-cerrar" @click="buscador = false">Cerrar</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal de previsualización PDF (Imprimir) -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="nv-pdf-ov" @click.self="cerrarPdf">
        <div class="nv-pdf-md">
          <div class="nv-pdf-head">
            <span>{{ pdfNombre }}</span>
            <div class="nv-pdf-acc">
              <button class="nv-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="nv-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="nv-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="nv-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { useAuthStore } from '@/stores/auth'
import NovedadesAyuda from '@/components/NovedadesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const authStore = useAuthStore()

const modalAyuda = ref(false); const modalIA = ref(false)

interface Opcion { cod: string | number; nombre: string }
type Tipo = 'int' | 'money' | 'num' | 'text'
interface Col { key: string; label: string; tipo: Tipo; parte: 0 | 1 | 2; hl?: boolean }

const empresas = ref<Opcion[]>([]); const contratistas = ref<Opcion[]>([])
const hoy = new Date()
const f = ref({ empresa: 1, mes: hoy.getMonth() + 1, anio: hoy.getFullYear(), nro_planilla: 0, modo_contra: 1, contratista: 0 })

const rows = ref<Record<string, any>[]>([])
const periodo = ref(''); const bloqueado = ref(false)
const cargando = ref(false); const consultado = ref(false)
const vista = ref<'completa' | 'p1' | 'p2'>('completa')

// Definición de columnas (parte 0 = siempre visible)
const cols: Col[] = [
  { key: 'codigo',        label: 'Código',        tipo: 'int',  parte: 0 },
  { key: 'nombre',        label: 'Nombre',        tipo: 'text', parte: 0, hl: true },
  { key: 'legajo',        label: 'Legajo',        tipo: 'text', parte: 0 },
  { key: 'convenio',      label: 'Convenio',      tipo: 'text', parte: 0 },
  { key: 'noremu',        label: 'No Remun.',     tipo: 'money', parte: 1, hl: true },
  { key: 'dias_mes',      label: 'Días Mes',      tipo: 'int',   parte: 1, hl: true },
  { key: 'dias_trab',     label: 'Días Trab.',    tipo: 'int',   parte: 1, hl: true },
  { key: 'com_vtas',      label: 'Com. Vtas.',    tipo: 'money', parte: 1, hl: true },
  { key: 'com_cob',       label: 'Com. Cob.',     tipo: 'money', parte: 1, hl: true },
  { key: 'adelantos',     label: 'Adelantos',     tipo: 'money', parte: 1, hl: true },
  { key: 'anticipos',     label: 'Anticipos',     tipo: 'money', parte: 1, hl: true },
  { key: 'almuerzos',     label: 'Almuerzos',     tipo: 'money', parte: 1, hl: true },
  { key: 'neto',          label: 'Neto',          tipo: 'money', parte: 1, hl: true },
  { key: 'adicional_neto', label: 'Adic. Neto',   tipo: 'money', parte: 1, hl: true },
  { key: 'presentismo',   label: 'Presentismo',   tipo: 'money', parte: 1, hl: true },
  { key: 'observaciones', label: 'Observaciones', tipo: 'text',  parte: 1, hl: true },
  { key: 'dias_vaca',     label: 'Días Vac.',     tipo: 'int',   parte: 2, hl: true },
  { key: 'lic_congoce',   label: 'Lic. c/Goce',   tipo: 'int',   parte: 2, hl: true },
  { key: 'lic_singoce',   label: 'Lic. s/Goce',   tipo: 'int',   parte: 2, hl: true },
  { key: 'can_h100',      label: 'Cant. H100%',   tipo: 'num',   parte: 2, hl: true },
  { key: 'horas_100',     label: 'Horas 100%',    tipo: 'money', parte: 2, hl: true },
  { key: 'can_h50',       label: 'Cant. H50%',    tipo: 'num',   parte: 2, hl: true },
  { key: 'horas_50',      label: 'Horas 50%',     tipo: 'money', parte: 2, hl: true },
  { key: 'can_noc',       label: 'Cant. Noct.',   tipo: 'num',   parte: 2, hl: true },
  { key: 'horas_noc',     label: 'Horas Noct.',   tipo: 'money', parte: 2, hl: true },
  { key: 'can_via',       label: 'Cant. A/Viaj.', tipo: 'num',   parte: 2, hl: true },
  { key: 'adi_via_neto',  label: 'Adic. Viaje',   tipo: 'money', parte: 2, hl: true },
  { key: 'neto_extra',    label: 'Neto Extra',    tipo: 'money', parte: 2, hl: true },
  { key: 'total_neto',    label: 'Total Neto',    tipo: 'money', parte: 2, hl: true },
  { key: 'detalles',      label: 'Detalles',      tipo: 'text',  parte: 2, hl: true },
]

const colsVisibles = computed(() => {
  if (vista.value === 'completa') return cols
  const parte = vista.value === 'p1' ? 1 : 2
  return cols.filter(c => c.parte === 0 || c.parte === parte)
})

const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const nfc = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 })
const fmt = (c: Col, v: any): string => {
  if (v === null || v === undefined || v === '') return c.tipo === 'text' ? '' : (c.tipo === 'int' ? '0' : '0,00')
  if (c.tipo === 'money') return nf.format(Number(v))
  if (c.tipo === 'int') return String(Number(v))
  if (c.tipo === 'num') return nfc.format(Number(v))
  return String(v)
}
const alinear = (c: Col) => (c.tipo === 'text' ? '' : 'text-align:right')
const tieneValor = (c: Col, r: Record<string, any>) => {
  const v = r[c.key]
  return c.tipo === 'text' ? String(v ?? '').trim().length > 0 : Number(v) !== 0
}
const celdaClase = (c: Col, r: Record<string, any>) => {
  if (c.key === 'nombre') return (r.convenio_cod === 3 || r.convenio_cod === 7) ? 'nv-conv-cell' : ''
  return c.hl && tieneValor(c, r) ? 'nv-mu-cell' : ''
}

// Buscador de planillas de horas extras
interface Planilla { numero: number; detalle: string; fecha: string }
const buscador = ref(false); const planillas = ref<Planilla[]>([]); const cargandoPlan = ref(false); const planSel = ref<number | null>(null)
const abrirBuscador = async () => {
  buscador.value = true; planSel.value = f.value.nro_planilla || null
  if (planillas.value.length) return
  cargandoPlan.value = true
  try { planillas.value = (await api.get('/novedades/planillas-hs-extras')).data } catch (e) { console.error(e) }
  finally { cargandoPlan.value = false }
}
const elegirPlanilla = (nro: number) => { f.value.nro_planilla = nro; buscador.value = false }

const aceptar = async () => {
  cargando.value = true; consultado.value = true
  try {
    const params: any = { empresa: f.value.empresa, mes: f.value.mes, anio: f.value.anio, nro_planilla: f.value.nro_planilla }
    if (f.value.modo_contra === 2 && f.value.contratista > 0) params.contratista = f.value.contratista
    const { data } = await api.get('/novedades/planilla', { params })
    rows.value = data.rows; periodo.value = data.periodo; bloqueado.value = data.bloqueado
  } catch (e) { console.error(e); rows.value = [] }
  finally { cargando.value = false }
}

// Exportar a Excel — columnas y encabezados idénticos a los del FoxPro.
// Se genera un .xlsx real (SheetJS) y no una tabla HTML: el HTML colapsaba los espacios
// dobles del detalle y no permitía nombrar la hoja como lo hace Fox.
const exportar = async () => {
  type EC = { label: string; val: (r: Record<string, any>) => any; num?: boolean }
  const cols2: EC[] = [
    { label: 'Nombre y apellido ', val: r => r.nombre },
    { label: 'legajo',             val: r => r.legajo },
    { label: 'convenio',           val: r => r.convenio },
    { label: 'mes',                val: r => r.dias_mes, num: true },
    { label: 'viatico',            val: r => r.almuerzos, num: true },
    { label: 'anticipo',           val: r => r.anticipos, num: true },
    { label: 'almuerzo',           val: r => r.almuerzos, num: true },
    { label: 'sueldo',             val: r => r.neto, num: true },
    { label: 'dias_vacaciones',    val: r => r.dias_vaca, num: true },
    { label: 'licencias_con_goce', val: r => r.lic_congoce, num: true },
    { label: 'licencias_sin_goce', val: r => r.lic_singoce, num: true },
    { label: 'h_e_50',             val: r => r.can_h50, num: true },
    { label: 'extra_50',           val: r => r.horas_50, num: true },
    { label: 'h_e_100',            val: r => r.can_h100, num: true },
    { label: 'extra_100',          val: r => r.horas_100, num: true },
    { label: 'nocturna',           val: r => r.can_noc, num: true },
    { label: 'extra_nocturna',     val: r => r.horas_noc, num: true },
    // round(...,2): la suma en coma flotante daba colas del tipo 22.630000000000003
    { label: 'total_h_e',          val: r => Math.round((Number(r.can_h50) + Number(r.can_h100) + Number(r.can_noc)) * 100) / 100, num: true },
    { label: 'descuentos',         val: () => 0, num: true },
    { label: 'detalles',           val: r => r.detalles },
  ]
  const aoa: any[][] = [cols2.map(c => c.label)]
  for (const r of rows.value) aoa.push(cols2.map(c => { const v = c.val(r); return c.num ? Number(v ?? 0) : String(v ?? '') }))

  const XLSX = await import('xlsx')
  const wb = XLSX.utils.book_new()
  // Nombre de la hoja como en Fox: "NOVEDADES SILCAR 07-2026" → usa la primera palabra
  // del nombre de la empresa. Excel limita el nombre de hoja a 31 caracteres.
  const empNom = (empresas.value.find(e => Number(e.cod) === f.value.empresa)?.nombre ?? '').trim().toUpperCase()
  const emp = empNom.split(/\s+/)[0] ?? ''
  const hoja = `NOVEDADES ${emp} ${String(f.value.mes).padStart(2, '0')}-${f.value.anio}`.trim().slice(0, 31)
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(aoa), hoja)
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
  await guardarComo(blob, `${hoja}.xlsx`)
}

// ── IMPRIMIR: PDF (horizontal, Legal) con preview en modal ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const imprimir = () => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const PW = 297, PH = 210, ML = 8, MR = 8
  const empresaNom = empresas.value.find(e => Number(e.cod) === f.value.empresa)?.nombre ?? ''
  const titulo = `PLANILLA DE NOVEDADES DEL MES ${f.value.mes}  AÑO ${f.value.anio}`

  // Columnas: k=campo, t=título, w=ancho mm, n=numérico(derecha), i=entero
  type PC = { k?: string; t: string; w: number; n?: boolean; i?: boolean; calc?: (r: any) => number }
  const cols: PC[] = [
    { k: 'codigo',        t: 'Código',     w: 11, i: true },
    { k: 'nombre',        t: 'Empleado',   w: 40 },
    { k: 'legajo',        t: 'Legajo',     w: 13 },
    { k: 'convenio',      t: 'Convenio',   w: 20 },
    { k: 'basico',        t: 'Básico',     w: 20, n: true },
    { k: 'noremu',        t: 'NO Remun.',  w: 18, n: true },
    { k: 'dias_mes',      t: 'Días Mes',   w: 12, i: true },
    { k: 'dias_trab',     t: 'Días Trab.', w: 12, i: true },
    { k: 'com_vtas',      t: 'Com.Vtas.',  w: 16, n: true },
    { k: 'com_cob',       t: 'Com.Cob.',   w: 16, n: true },
    { t: 'Adel.+Antic.',  w: 19, n: true, calc: r => Number(r.adelantos) + Number(r.anticipos) },
    { k: 'almuerzos',     t: 'Almuerzos',  w: 15, n: true },
    { k: 'presentismo',   t: '%Present.',  w: 13, n: true },
    { k: 'neto',          t: 'Neto RRHH',  w: 20, n: true },
    { k: 'adicional_neto', t: 'Adic.Neto', w: 17, n: true },
    { k: 'observaciones', t: 'Observac.',  w: 19 },
  ]
  const money = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  const valor = (c: PC, r: any): string => {
    const raw = c.calc ? c.calc(r) : r[c.k!]
    if (c.n) { const num = Number(raw); return num === 0 ? '' : money.format(num) }
    if (c.i) { const num = Number(raw); return c.k === 'codigo' ? String(num) : (num === 0 ? '' : String(num)) }
    return String(raw ?? '')
  }

  let y = 0
  const dibujarCab = () => {
    y = 11
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(27, 67, 50)
    doc.text(titulo, PW / 2, y, { align: 'center' }); y += 5
    doc.setFontSize(8); doc.setTextColor(60, 60, 60)
    doc.text('EMPRESA :  ' + empresaNom.trim(), ML, y); y += 4
    doc.setTextColor(0, 0, 0); doc.setFontSize(7)
    let x = ML
    doc.setFillColor(229, 231, 235); doc.rect(ML, y - 3.5, PW - ML - MR, 6, 'F')
    doc.setFont('helvetica', 'bold')
    for (const c of cols) {
      if (c.n || c.i) doc.text(c.t, x + c.w - 1, y, { align: 'right' })
      else doc.text(c.t, x + 1, y)
      x += c.w
    }
    y += 4.5
    doc.setDrawColor(180); doc.setLineWidth(0.2); doc.line(ML, y - 2.5, PW - MR, y - 2.5)
  }
  dibujarCab()

  // Totales
  const tot = { dt: 0, cv: 0, cc: 0, aa: 0, al: 0, od: 0, nj: 0, an: 0 }
  let n = 0
  doc.setFont('helvetica', 'normal')
  for (const r of rows.value) {
    if (y > PH - 16) { doc.addPage(); dibujarCab(); doc.setFont('helvetica', 'normal') }
    if (n % 2 === 1) { doc.setFillColor(247, 250, 252); doc.rect(ML, y - 3.3, PW - ML - MR, 4.6, 'F') }
    let x = ML
    doc.setTextColor(30, 41, 59); doc.setFontSize(6.8)
    for (const c of cols) {
      let val = valor(c, r)
      if (c.n || c.i) {
        while (val && doc.getTextWidth(val) > c.w - 2) val = val.slice(1)
        doc.text(val, x + c.w - 1, y, { align: 'right' })
      } else {
        while (val && doc.getTextWidth(val) > c.w - 2) val = val.slice(0, -1)
        doc.text(val, x + 1, y)
      }
      x += c.w
    }
    tot.dt += Number(r.dias_trab); tot.cv += Number(r.com_vtas); tot.cc += Number(r.com_cob)
    tot.aa += Number(r.adelantos) + Number(r.anticipos); tot.al += Number(r.almuerzos)
    tot.od += Number(r.presentismo); tot.nj += Number(r.neto); tot.an += Number(r.adicional_neto)
    y += 4.6; n++
  }

  // Fila de totales
  if (y > PH - 16) { doc.addPage(); dibujarCab(); }
  y += 1
  doc.setDrawColor(120); doc.setLineWidth(0.3); doc.line(ML, y - 2, PW - MR, y - 2)
  doc.setFont('helvetica', 'bold'); doc.setFontSize(7); doc.setTextColor(27, 67, 50)
  const totByKey: Record<string, number> = { dias_trab: tot.dt, com_vtas: tot.cv, com_cob: tot.cc, almuerzos: tot.al, presentismo: tot.od, neto: tot.nj, adicional_neto: tot.an }
  let x = ML
  for (const c of cols) {
    if (c.k === 'codigo') doc.text('TOTALES :', x + 1, y + 1)
    else if (c.t === 'Adel.+Antic.') doc.text(money.format(tot.aa), x + c.w - 1, y + 1, { align: 'right' })
    else if (c.k && totByKey[c.k] !== undefined) {
      const v = c.k === 'dias_trab' ? String(Math.round(totByKey[c.k])) : money.format(totByKey[c.k])
      doc.text(v, x + c.w - 1, y + 1, { align: 'right' })
    }
    x += c.w
  }

  // Pie de página
  const total = doc.getNumberOfPages()
  const txtPie = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
  for (let p = 1; p <= total; p++) {
    doc.setPage(p)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90)
    doc.text(txtPie, ML, PH - 5)
    doc.text(`Página ${p} de ${total}`, PW - MR, PH - 5, { align: 'right' })
  }

  cerrarPdf()
  pdfNombre.value = `Novedades_${f.value.anio}_${String(f.value.mes).padStart(2, '0')}.pdf`
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const confirmando = ref(false)
const confirmar = async () => {
  if (!confirm('¿Confirma la planilla de novedades de sueldos? Se grabarán las novedades del período.')) return
  confirmando.value = true
  try {
    const params: any = { empresa: f.value.empresa, mes: f.value.mes, anio: f.value.anio, nro_planilla: f.value.nro_planilla }
    if (f.value.modo_contra === 2 && f.value.contratista > 0) params.contratista = f.value.contratista
    const { data } = await api.post('/novedades/confirmar', params)
    alert(`✔ Planilla confirmada. Se grabaron ${data.grabados} novedades.`)
  } catch (e: any) {
    alert('⚠️ ' + (e?.response?.data?.message ?? 'No se pudo confirmar la planilla.'))
  } finally { confirmando.value = false }
}

onMounted(async () => {
  try {
    const [e, c] = await Promise.all([api.get('/empresas'), api.get('/contratistas')])
    empresas.value = e.data; contratistas.value = c.data
  } catch (err) { console.error(err) }
})
</script>

<style scoped>
.nv-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.nv-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.nv-cab-ico { font-size:28px; } .nv-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .nv-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.nv-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.nv-btn-ia:hover { filter:brightness(1.1); }
.nv-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.nv-btn-ayuda:hover { background:#f0faf4; }
.nv-filtros { display:flex; flex-wrap:wrap; align-items:end; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.nv-filtros label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.nv-filtros input, .nv-filtros select { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.nv-filtros input:focus, .nv-filtros select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.nv-filtros input[type=number] { width:90px; }
.nv-contra { display:flex; align-items:center; gap:10px; font-size:12px; font-weight:600; color:#374151; }
.nv-contra label { flex-direction:row; align-items:center; gap:4px; }
.nv-btn-aceptar { background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.nv-btn-aceptar:hover:not(:disabled){ background:#15803d; } .nv-btn-aceptar:disabled { background:#cbd5e1; }
.nv-bloqueo { margin:10px 18px 0; padding:8px 12px; background:#fef3c7; color:#92400e; border:1px solid #fde68a; border-radius:6px; font-size:13px; font-weight:600; }
.nv-barra { display:flex; align-items:center; gap:18px; padding:10px 18px; flex-wrap:wrap; }
.nv-tabs { display:flex; gap:14px; font-size:13px; font-weight:600; color:#374151; }
.nv-tabs label { display:flex; align-items:center; gap:5px; cursor:pointer; }
.nv-leyenda { font-size:12px; color:#6b7280; display:flex; align-items:center; margin-right:auto; }
.nv-mu, .nv-conv { display:inline-block; width:13px; height:13px; border-radius:3px; margin-right:4px; border:1px solid #cbd5e1; vertical-align:middle; }
.nv-mu { background:#fff7ae; } .nv-conv { background:#fff200; }
.nv-btn-confirmar { background:#2563eb; color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.nv-btn-confirmar:hover:not(:disabled){ background:#1d4ed8; } .nv-btn-confirmar:disabled { background:#cbd5e1; }
.nv-btn-imprimir { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.nv-btn-imprimir:hover { background:#f0faf4; }
.nv-btn-excel { background:#1d6f42; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.nv-btn-excel:hover { background:#15803d; }
.nv-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.nv-pdf-md { width:min(1100px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.nv-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.nv-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.nv-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.nv-pdf-b.ok { background:#22c55e; color:#fff; } .nv-pdf-b.ok:hover { background:#16a34a; }
.nv-pdf-b.cancel { background:#ef4444; color:#fff; } .nv-pdf-b.cancel:hover { background:#dc2626; }
.nv-pdf-frame { flex:1; border:none; width:100%; }
.nv-grid-wrap { margin:0 18px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.nv-grid { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.nv-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; border:1px solid #14532d; font-size:11px; }
.nv-grid td { padding:5px 10px; border:1px solid #e5e7eb; color:#1e293b; background:#fff; }
.nv-grid tr:hover td { filter:brightness(.97); }
.nv-mu-cell { background:#fff7ae !important; }
.nv-conv-cell { background:#fff200 !important; font-weight:600; }
.nv-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.nv-plan { display:flex; align-items:center; gap:6px; }
.nv-btn-buscar { background:#fff; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; cursor:pointer; font-size:14px; line-height:1; }
.nv-btn-buscar:hover { background:#f0faf4; border-color:#40916c; }
.nv-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.nv-busc { width:min(560px,96vw); max-height:88vh; display:flex; flex-direction:column; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.nv-busc-cab { display:flex; align-items:center; gap:10px; padding:12px 16px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.nv-busc-cab h3 { margin:0; font-size:15px; } .nv-busc-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:26px; height:26px; border-radius:7px; cursor:pointer; } .nv-busc-x:hover{ background:rgba(255,255,255,.3); }
.nv-busc-body { overflow:auto; padding:0; }
.nv-busc-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.nv-busc-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.nv-busc-tabla th { position:sticky; top:0; background:#c0dcc0; color:#1f2937; font-weight:700; text-align:left; padding:7px 12px; border-bottom:1px solid #9bbf9b; }
.nv-busc-tabla td { padding:6px 12px; border-bottom:1px solid #eef2f7; color:#1e293b; }
.nv-busc-tabla tbody tr:nth-child(even) td { background:#eef6ee; }
.nv-busc-tabla tbody tr:hover td { background:#dcefdc; cursor:pointer; }
.nv-busc-tabla tr.sel td { background:#1b4332 !important; color:#fff; }
.nv-busc-nro { font-family:monospace; font-weight:700; }
.nv-busc-pie { display:flex; justify-content:center; gap:10px; padding:12px; border-top:1px solid #e5e7eb; }
.nv-busc-cerrar { background:#ef4444; color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; } .nv-busc-cerrar:hover{ background:#dc2626; }
</style>
