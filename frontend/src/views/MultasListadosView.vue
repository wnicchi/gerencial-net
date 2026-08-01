<!-- MultasListadosView.vue — Multas - Listados de Control (multas_listados). -->
<template>
  <div class="ml-view">
    <div class="ml-cab">
      <div class="ml-cab-ico">⚠️</div>
      <div class="ml-cab-tx"><h1>Multas - Listados de Control</h1><p>Cuenta corriente de multas por empleado (egresos vs descuentos)</p></div>
      <button class="ml-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ml-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <MultasListadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/multas" titulo="Asistente IA — Multas - Listados de Control"
            subtitulo="Preguntá sobre el control de multas"
            :sugerencias="['¿Cómo se calcula el saldo?','¿Por qué no aparece un empleado?','¿Qué es Histórico?']"
            @close="modalIA = false" />

    <div class="ml-filtros" v-enter-next>
      <div class="ml-f">
        <span class="ml-lbl">Empleados</span>
        <div class="ml-radio"><label><input type="radio" :value="1" v-model.number="empleadoModo" /> Todos</label><label><input type="radio" :value="2" v-model.number="empleadoModo" /> Uno</label></div>
      </div>
      <div class="ml-f ml-busc" v-if="empleadoModo === 2">
        <span class="ml-lbl">Empleado</span>
        <input v-model="busqueda" type="text" class="ml-inp" placeholder="Buscar por código o nombre…" @input="buscar" @focus="buscar" />
        <ul v-if="resultados.length" class="ml-result">
          <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
        </ul>
      </div>
      <div class="ml-f">
        <span class="ml-lbl">Período</span>
        <div class="ml-radio"><label><input type="radio" :value="1" v-model.number="periodo" /> Histórico</label><label><input type="radio" :value="2" v-model.number="periodo" /> Rango</label></div>
      </div>
      <template v-if="periodo === 2">
        <div class="ml-f"><span class="ml-lbl">Desde</span><input v-model="fecha1" type="date" class="ml-date" :min="PISO" /></div>
        <div class="ml-f"><span class="ml-lbl">Hasta</span><input v-model="fecha2" type="date" class="ml-date" :min="PISO" /></div>
      </template>
      <div class="ml-f">
        <span class="ml-lbl">Modo</span>
        <div class="ml-radio"><label><input type="radio" value="detallado" v-model="modo" /> Detallado</label><label><input type="radio" value="resumido" v-model="modo" /> Resumido</label></div>
      </div>
      <button class="ml-btn-ok" :disabled="cargando || (empleadoModo === 2 && !empleadoCod)" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
    </div>

    <div v-if="cargado && empleados.length" class="ml-wrap">
      <!-- Resumido -->
      <table v-if="modo === 'resumido'" class="ml-grid">
        <thead><tr><th>Código</th><th>Empleado</th><th class="r">Saldo</th></tr></thead>
        <tbody>
          <tr v-for="e in empleados" :key="e.cod"><td>{{ e.cod }}</td><td>{{ e.nombre }}</td><td class="r deuda">{{ money(e.saldo) }}</td></tr>
        </tbody>
        <tfoot><tr class="ml-tfoot"><td colspan="2">SALDO TOTAL</td><td class="r">{{ money(totalSaldo) }}</td></tr></tfoot>
      </table>

      <!-- Detallado -->
      <div v-else class="ml-detalle">
        <div v-for="e in empleados" :key="e.cod" class="ml-emp">
          <div class="ml-emp-cab"><span class="ml-emp-nom">{{ e.nombre }} <small>({{ e.cod }})</small></span><span class="ml-emp-saldo">Saldo: <b>{{ money(e.saldo) }}</b></span></div>
          <table class="ml-grid">
            <thead><tr><th>Fecha</th><th>Detalle</th><th class="r">Egreso</th><th class="r">Ingreso</th><th class="r">Saldo</th></tr></thead>
            <tbody>
              <tr v-for="(m, i) in e.movimientos" :key="i">
                <td>{{ m.fecha }}</td><td>{{ m.detalle }}</td>
                <td class="r">{{ m.egreso ? money(m.egreso) : '' }}</td><td class="r">{{ m.ingreso ? money(m.ingreso) : '' }}</td>
                <td class="r" :class="m.saldo > 0 ? 'pos' : ''">{{ money(m.saldo) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div v-else-if="cargado" class="ml-vacio">No hay empleados con saldo de multas pendiente para los filtros seleccionados.</div>

    <div v-if="cargado && empleados.length" class="ml-pie">
      <div class="ml-total-box">
        <span class="ml-total-lbl">SALDO TOTAL ADEUDADO</span>
        <span class="ml-total-val">$ {{ money(totalSaldo) }}</span>
        <span class="ml-total-cant">{{ empleados.length }} empleados</span>
      </div>
      <button class="ml-btn-pdf" @click="imprimir">📄 Visualizar / Imprimir PDF</button>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ml-pdf-ov" @click.self="cerrarPdf">
        <div class="ml-pdf-md">
          <div class="ml-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ml-pdf-acc">
              <button class="ml-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ml-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ml-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ml-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import MultasListadosAyuda from '@/components/MultasListadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

interface Mov { fecha: string; detalle: string; egreso: number; ingreso: number; saldo: number }
interface Emp { cod: number; nombre: string; saldo: number; movimientos: Mov[] }

const PISO = '2020-07-01'
const modalAyuda = ref(false); const modalIA = ref(false)
const empleadoModo = ref(1); const empleadoCod = ref(0); const empleadoNom = ref('')
const periodo = ref(1); const modo = ref('detallado')
const hoy = new Date()
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const fecha1 = ref(PISO); const fecha2 = ref(_iso(hoy))

const busqueda = ref(''); const resultados = ref<any[]>([])
let tBusca: any = null
const buscar = () => {
  clearTimeout(tBusca)
  const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tBusca = setTimeout(async () => {
    try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] }
    catch (e) { console.error(e); resultados.value = [] }
  }, 250)
}
const seleccionar = (r: any) => {
  empleadoCod.value = r.PER_COD; empleadoNom.value = (r.PER_NOM || '').trim()
  busqueda.value = `${r.PER_COD} — ${empleadoNom.value}`; resultados.value = []
}

const empleados = ref<Emp[]>([]); const totalSaldo = ref(0); const cargando = ref(false); const cargado = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const consultar = async () => {
  cargando.value = true; cargado.value = true
  try {
    const params: any = { empleado: empleadoModo.value === 2 ? empleadoCod.value : 0, periodo: periodo.value, modo: modo.value }
    if (periodo.value === 2) { params.fecha1 = fecha1.value; params.fecha2 = fecha2.value }
    const { data } = await api.get('/multas-listados', { params })
    empleados.value = data.empleados || []; totalSaldo.value = data.totalSaldo || 0
  } catch (e) { console.error(e); empleados.value = []; totalSaldo.value = 0 }
  finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const titulo = () => {
  const p: string[] = []
  if (empleadoModo.value === 2 && empleadoNom.value) p.push(`Empleado ${empleadoNom.value}`)
  if (periodo.value === 2) p.push(`Desde ${fmt(fecha1.value)} al ${fmt(fecha2.value)}`)
  return p.join('  -  ')
}
const fmt = (iso: string) => { if (!iso) return ''; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }

const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text(modo.value === 'resumido' ? 'INFORME DE MULTAS (resumen)' : 'INFORME DE MULTAS', mL, y); y += 6
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  if (titulo()) { doc.text(titulo(), mL, y); y += 5 }
  doc.setLineWidth(0.4); doc.line(mL, y, mR, y); y += 5

  if (modo.value === 'resumido') {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
    doc.text('Personal', mL, y); doc.text('Saldo', mR, y, { align: 'right' }); y += 1; doc.line(mL, y, mR, y); y += 5
    doc.setFont('helvetica', 'normal')
    for (const e of empleados.value) {
      if (y > 278) { doc.addPage(); y = 18 }
      doc.text(`${e.nombre} (${e.cod})`.slice(0, 60), mL, y); doc.text(money(e.saldo), mR, y, { align: 'right' }); y += 5
    }
    y += 1; doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold')
    doc.text('SALDO TOTAL', mL, y); doc.text(money(totalSaldo.value), mR, y, { align: 'right' })
  } else {
    for (const e of empleados.value) {
      if (y > 264) { doc.addPage(); y = 18 }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.text(`${e.nombre} (${e.cod})`, mL, y); y += 5
      doc.setFontSize(8); doc.text('Fecha', mL, y); doc.text('Detalle', mL + 24, y)
      doc.text('Egreso', mL + 110, y, { align: 'right' }); doc.text('Ingreso', mL + 145, y, { align: 'right' }); doc.text('Saldo', mR, y, { align: 'right' }); y += 1
      doc.line(mL, y, mR, y); y += 4; doc.setFont('helvetica', 'normal')
      for (const m of e.movimientos) {
        if (y > 284) { doc.addPage(); y = 18 }
        doc.text(m.fecha, mL, y); doc.text(m.detalle.slice(0, 50), mL + 24, y)
        if (m.egreso) doc.text(money(m.egreso), mL + 110, y, { align: 'right' })
        if (m.ingreso) doc.text(money(m.ingreso), mL + 145, y, { align: 'right' })
        doc.text(money(m.saldo), mR, y, { align: 'right' }); y += 4.2
      }
      doc.setFont('helvetica', 'bold'); doc.text('SALDO', mL + 110, y, { align: 'right' }); doc.text(money(e.saldo), mR, y, { align: 'right' }); y += 7
    }
  }
  cerrarPdf(); pdfNombre.value = 'Informe_Multas.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ml-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ml-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ml-cab-ico { font-size:28px; } .ml-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ml-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ml-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ml-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ml-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:16px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ml-f { display:flex; flex-direction:column; gap:5px; position:relative; } .ml-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.ml-radio { display:flex; gap:14px; font-size:13px; color:#1e293b; } .ml-radio label { display:flex; align-items:center; gap:5px; cursor:pointer; }
.ml-busc { min-width:260px; } .ml-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; color:#1e293b; min-width:260px; }
.ml-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.ml-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .ml-result li:hover { background:#f0faf4; }
.ml-date { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; color:#1e293b; }
.ml-btn-ok { background:#16a34a; color:#fff; border:none; padding:9px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ml-btn-ok:disabled { background:#cbd5e1; }
.ml-wrap { margin:12px 18px; overflow:auto; max-height:60vh; }
.ml-grid { width:100%; border-collapse:collapse; font-size:13px; }
.ml-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .ml-grid th.r { text-align:right; }
.ml-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .ml-grid td.r { text-align:right; }
.ml-grid tr:nth-child(even) td { background:#f8fafc; }
.ml-grid td.deuda { color:#b45309; font-weight:700; } .ml-grid td.pos { color:#b45309; font-weight:600; }
.ml-tfoot td { background:#14532d !important; color:#fff !important; font-weight:800; padding:9px 10px; }
.ml-detalle { display:flex; flex-direction:column; gap:18px; }
.ml-emp { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.ml-emp-cab { display:flex; align-items:center; justify-content:space-between; padding:8px 12px; background:#ecfdf5; border-bottom:1px solid #d1fae5; }
.ml-emp-nom { font-size:14px; font-weight:700; color:#1b4332; } .ml-emp-nom small { color:#64748b; font-weight:500; }
.ml-emp-saldo { font-size:13px; color:#475569; } .ml-emp-saldo b { color:#b45309; font-size:15px; }
.ml-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.ml-pie { display:flex; align-items:center; gap:16px; padding:4px 18px 18px; }
.ml-total-box { display:flex; align-items:center; gap:14px; background:linear-gradient(120deg,#b45309,#f59e0b); color:#fff; padding:12px 22px; border-radius:10px; box-shadow:0 6px 18px rgba(180,83,9,.35); }
.ml-total-lbl { font-size:12px; font-weight:700; letter-spacing:.4px; opacity:.95; }
.ml-total-val { font-size:24px; font-weight:800; } .ml-total-cant { font-size:12px; opacity:.9; }
.ml-btn-pdf { margin-left:auto; background:#2563eb; color:#fff; border:none; padding:11px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ml-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ml-pdf-md { width:min(880px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ml-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ml-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ml-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ml-pdf-b.ok { background:#22c55e; color:#fff; } .ml-pdf-b.cancel { background:#ef4444; color:#fff; }
.ml-pdf-frame { flex:1; border:none; width:100%; }
</style>
