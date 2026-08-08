<template>
  <div class="cv">
    <div class="cv-cab">
      <div class="cv-cab-ico">💸</div>
      <div class="cv-cab-tx">
        <h1>Comparativa de Gastos</h1>
        <p>Gastos (compras) por mes y año — variación interanual</p>
      </div>
      <ModuloAyudaIA style="margin-left:auto" modulo="Comparativa de Gastos" icono="💸"
        descripcion="Informe solo lectura de gastos (compras) por mes y año con la variación interanual (%). Filtro por rubro de compra y opción de mostrar en miles."
        :sugerencias="['¿Qué mide este informe?', '¿Cómo leo la variación interanual?', '¿Qué es el rubro de compra?']"
        intro="Muestra los gastos por mes y año, y cuánto variaron respecto del año anterior."
        :pasos="['<b>Elegí</b> el rubro de compra.', '<b>Generá</b> el informe.', 'La segunda tabla muestra la variación % año contra año.']" />
    </div>

    <div class="cv-filtros">
      <div class="cv-f">
        <span class="cv-lbl">Rubro de compra</span>
        <select v-model.number="rubro" class="cv-sel rub">
          <option :value="-1">TOTAL</option>
          <option v-for="r in rubros" :key="r.cod" :value="r.cod">{{ r.des }}</option>
        </select>
      </div>
      <label class="cv-chk"><input type="checkbox" v-model="enMiles" /> Mostrar en MILES</label>
      <button class="cv-btn gen" :disabled="cargando" @click="generar">{{ cargando ? 'Generando…' : 'GENERAR INFORME' }}</button>
    </div>

    <transition name="msg"><div v-if="msg" class="cv-msg" :class="{ err: msgErr }">{{ msg }}</div></transition>

    <template v-if="pres.length">
      <div class="cv-tit">Gastos{{ enMiles ? ' (en miles)' : '' }}</div>
      <div class="cv-scroll">
        <table class="cv-tabla">
          <thead>
            <tr><th class="a">AÑO</th><th v-for="m in MESES" :key="m">{{ m }}</th><th class="t">Total Anual</th></tr>
          </thead>
          <tbody>
            <tr v-for="p in pres" :key="p.anio">
              <td class="a">{{ p.anio }}</td>
              <td v-for="(v, i) in p.meses" :key="i" class="num">{{ ent(v) }}</td>
              <td class="num t">{{ ent(p.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="cv-tit">Variación interanual (%)</div>
      <div class="cv-scroll">
        <table class="cv-tabla">
          <thead>
            <tr><th class="a">AÑO</th><th v-for="m in MESES" :key="m">{{ m }}</th><th class="t">Total Anual</th></tr>
          </thead>
          <tbody>
            <tr v-for="it in interOrden" :key="it.detalle">
              <td class="a">{{ it.detalle }}</td>
              <td v-for="(v, i) in it.meses" :key="i" class="num" :class="{ neg: v < 0 }">{{ pct(v) }}</td>
              <td class="num t" :class="{ neg: it.total < 0 }">{{ pct(it.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="cv-acc">
        <button class="cv-btn" @click="aExcel">📊 A Excel</button>
        <button class="cv-btn" @click="verGrafico = true">📈 Ver Gráfico</button>
      </div>
    </template>

    <Teleport to="body">
      <div v-if="verGrafico" class="cv-ov" @click.self="verGrafico = false">
        <div class="cv-modal">
          <div class="cv-mhead"><span>Gráfica de gastos</span><button class="cv-x" @click="verGrafico = false">✕</button></div>
          <div class="cv-mbody" v-html="svgGrafico"></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
/**
 * ComparativaGastosView.vue — Estadísticas › Comparativa de Gastos.
 * Informe (solo lectura): gastos (COMPRAS) por mes/año de los últimos 10 años +
 * variación interanual %, Excel y gráfico SVG. Migra estadistica_comparativa_gastos.scx.
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'
import * as XLSX from 'xlsx'
import { guardarDesdeUrl } from '@/utils/descargas'

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre']

interface Fila { anio: number; meses: number[]; total: number }
interface Inter { detalle: string; meses: number[]; total: number }

const rubros = ref<{ cod: number; des: string }[]>([])
const rubro = ref(-1)
const enMiles = ref(false)

const pres = ref<Fila[]>([])
const inter = ref<Inter[]>([])
// Interanual con el año más reciente arriba (orden descendente por año).
const interOrden = computed(() => [...inter.value].sort((a, b) => parseInt(b.detalle) - parseInt(a.detalle)))
const cargando = ref(false)
const verGrafico = ref(false)
const msg = ref(''); const msgErr = ref(false)
let msgT: ReturnType<typeof setTimeout>

function aviso (t: string, err = false) { msg.value = t; msgErr.value = err; clearTimeout(msgT); msgT = setTimeout(() => (msg.value = ''), 4000) }
const ent = (v: number) => new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(v)
const pct = (v: number) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) + '%'

async function cargarCatalogo () {
  try { rubros.value = (await api.get('/tablero/gestion/comparativa-gastos/catalogo')).data.rubros } catch { /* noop */ }
}

async function generar () {
  cargando.value = true
  try {
    const params = { rubro: rubro.value, en_miles: enMiles.value ? 1 : 0 }
    const d = (await api.get('/tablero/gestion/comparativa-gastos', { params })).data
    pres.value = d.presentacion; inter.value = d.interanual
    if (!pres.value.length) aviso('Sin datos.')
  } catch { pres.value = []; inter.value = []; aviso('No se pudo generar el informe.', true) }
  finally { cargando.value = false }
}

function aExcel () {
  const filas: any[] = [{ AÑO: '', ...Object.fromEntries(MESES.map(m => [m, m])), 'Total Anual': 'Total Anual' }]
  for (const p of pres.value) {
    const row: any = { AÑO: p.anio }
    MESES.forEach((m, i) => (row[m] = p.meses[i]))
    row['Total Anual'] = p.total
    filas.push(row)
  }
  const ws = XLSX.utils.json_to_sheet(filas, { skipHeader: true })
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Gastos')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  guardarDesdeUrl(url, 'estadistica_gastos.xlsx')
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

const COLORES = ['#7c3aed', '#1b4332', '#d97706', '#2d6a9f', '#dc2626', '#0891b2', '#65a30d', '#db2777', '#0d9488', '#ca8a04', '#4f46e5']
const svgGrafico = computed(() => {
  if (!pres.value.length) return ''
  const W = 820, H = 420, mL = 70, mR = 20, mT = 30, mB = 60
  const iw = W - mL - mR, ih = H - mT - mB
  const maxV = Math.max(1, ...pres.value.flatMap(p => p.meses))
  const x = (i: number) => mL + (iw * i) / 11
  const y = (v: number) => mT + ih - (ih * v) / maxV
  const fmtM = (v: number) => (v >= 1e9 ? (v / 1e9).toFixed(1) + ' MM' : v >= 1e6 ? Math.round(v / 1e6) + ' M' : Math.round(v / 1e3) + ' K')
  let g = `<svg viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto;font-family:system-ui,sans-serif">`
  for (let t = 0; t <= 4; t++) {
    const v = (maxV * t) / 4, yy = y(v)
    g += `<line x1="${mL}" y1="${yy}" x2="${W - mR}" y2="${yy}" stroke="#e2e8f0"/><text x="${mL - 8}" y="${yy + 4}" text-anchor="end" font-size="11" fill="#64748b">${fmtM(v)}</text>`
  }
  MESES.forEach((m, i) => { g += `<text x="${x(i)}" y="${H - mB + 18}" text-anchor="middle" font-size="10" fill="#64748b">${m.slice(0, 3)}</text>` })
  pres.value.forEach((p, idx) => {
    const col = COLORES[idx % COLORES.length]
    g += `<polyline points="${p.meses.map((v, i) => `${x(i)},${y(v)}`).join(' ')}" fill="none" stroke="${col}" stroke-width="2.5"/>`
    p.meses.forEach((v, i) => { g += `<circle cx="${x(i)}" cy="${y(v)}" r="2.5" fill="${col}"/>` })
  })
  pres.value.forEach((p, idx) => {
    const col = COLORES[idx % COLORES.length], lx = mL + (idx % 6) * 75, ly = mT - 14 + Math.floor(idx / 6) * 16
    g += `<rect x="${lx}" y="${ly - 9}" width="11" height="11" fill="${col}"/><text x="${lx + 15}" y="${ly}" font-size="11" fill="#334155">${p.anio}</text>`
  })
  g += '</svg>'
  return g
})

cargarCatalogo()
generar()
</script>

<style scoped>
.cv { padding: 14px 16px 40px; color: #1e293b; }
.cv-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.cv-cab-ico { font-size: 30px; }
.cv-cab-tx h1 { margin: 0; font-size: 19px; }
.cv-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.cv-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-top: 14px; }
.cv-f { display: flex; align-items: center; gap: 8px; }
.cv-lbl { font-size: 12px; font-weight: 700; color: #475569; }
.cv-chk { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: #1e293b; cursor: pointer; }
.cv-sel { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 9px; font-size: 13px; color: #1e293b; background: #fff; }
.cv-sel.rub { min-width: 240px; }
.cv-btn { border: none; border-radius: 7px; padding: 8px 14px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.cv-btn.gen { background: #1b4332; } .cv-btn:disabled { opacity: .6; cursor: default; }
.cv-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #d1fae5; color: #065f46; font-weight: 600; font-size: 13px; }
.cv-msg.err { background: #fee2e2; color: #b91c1c; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.cv-tit { margin: 18px 0 6px; font-size: 13px; font-weight: 800; color: #1b4332; }
.cv-scroll { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
.cv-tabla { width: 100%; border-collapse: collapse; font-size: 12px; white-space: nowrap; }
.cv-tabla th { position: sticky; top: 0; background: #2d6a9f; color: #fff; padding: 6px 8px; text-align: right; font-weight: 700; }
.cv-tabla th.a { text-align: left; }
.cv-tabla td { padding: 5px 8px; border-bottom: 1px solid #f0f4f9; color: #1e293b; }
.cv-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cv-tabla td.a { font-weight: 700; color: #1b4332; }
.cv-tabla td.t { font-weight: 800; background: #eef2f6; color: #1b4332; }
.cv-tabla th.t { font-weight: 800; background: #1b4332; color: #fff; }
.cv-tabla td.neg { background: #dc2626; color: #fff; font-weight: 700; }
.cv-tabla tbody tr:hover td:not(.neg) { background: #f0f7ff; }
.cv-acc { display: flex; gap: 10px; margin-top: 14px; }
.cv-ov { position: fixed; inset: 0; background: rgba(15,23,42,.6); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 18px; }
.cv-modal { width: min(900px, 97vw); background: #fff; border-radius: 10px; overflow: hidden; }
.cv-mhead { display: flex; align-items: center; justify-content: space-between; padding: 11px 14px; background: #1b4332; color: #fff; font-weight: 700; }
.cv-x { background: transparent; border: none; color: #fff; font-size: 16px; cursor: pointer; }
.cv-mbody { padding: 16px; }
</style>
