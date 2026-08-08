<template>
  <div class="cp">
    <div class="cp-cab">
      <div class="cp-cab-ico">📅</div>
      <div class="cp-cab-tx">
        <h1>Estadística Cobros y Pagos</h1>
        <p>Promedio de días (ponderado por importe) — ventana móvil de 12 meses</p>
      </div>
      <ModuloAyudaIA style="margin-left:auto" modulo="Estadística Cobros y Pagos" icono="📅"
        descripcion="Informe solo lectura del promedio de días de cobro y de pago, ponderado por importe, sobre una ventana móvil de 12 meses."
        :sugerencias="['¿Qué mide este informe?', '¿Qué significa ponderado por importe?', '¿Para qué sirve comparar cobros y pagos?']"
        intro="Muestra en cuántos días, en promedio, se cobra y se paga."
        :pasos="['<b>Elegí</b> el mes y año de corte.', '<b>Generá</b> el informe.', 'Comparás los días promedio de cobro contra los de pago.']" />
    </div>

    <div class="cp-filtros">
      <div class="cp-f">
        <span class="cp-lbl">Hasta el mes</span>
        <select v-model.number="mes" class="cp-sel mes">
          <option v-for="(m, i) in MESES" :key="i" :value="i + 1">{{ m }}</option>
        </select>
        <span class="cp-lbl">Año</span>
        <input v-model.number="anio" type="number" class="cp-anio" />
      </div>
      <button class="cp-btn gen" :disabled="cargando" @click="generar">{{ cargando ? 'Generando…' : 'GENERAR' }}</button>
      <button v-if="filas.length" class="cp-btn" @click="aExcel">📊 Generar Archivo Excel</button>
      <button v-if="filas.length" class="cp-btn" @click="verGrafico = true">📈 Ver Gráfico</button>
    </div>

    <transition name="msg"><div v-if="msg" class="cp-msg" :class="{ err: msgErr }">{{ msg }}</div></transition>

    <div v-if="filas.length" class="cp-scroll">
      <table class="cp-tabla">
        <thead>
          <tr>
            <th class="rub">Rubro</th><th class="des">Descripción</th>
            <th v-for="c in columnas" :key="c">{{ c }}</th>
            <th class="t">Promedio Anual</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, idx) in filas" :key="idx" :class="{ alt: idx % 2 === 1, sep: f.separador }">
            <td class="rub">{{ f.rubro > 0 ? f.rubro : '' }}</td>
            <td class="des">{{ f.nombre }}</td>
            <td v-for="(v, i) in f.valores" :key="i" class="num">{{ v }}</td>
            <td class="num t">{{ f.promedio }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal gráfico: días de cobro vs. pago por mes -->
    <Teleport to="body">
      <div v-if="verGrafico" class="cp-ov" @click.self="verGrafico = false">
        <div class="cp-modal">
          <div class="cp-mhead"><span>Días de Cobros vs. Pagos por mes</span><button class="cp-x" @click="verGrafico = false">✕</button></div>
          <div class="cp-mbody" v-html="svgGrafico"></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
/**
 * CobrosPagosView.vue — Estadísticas › Estadística Cobros y Pagos (mensual).
 * Informe (solo lectura): promedio de días ponderado por importe de cobros (RECIBOS)
 * y pagos por proveedor (O_PAGOS), en una ventana móvil de 12 meses. Export a Excel.
 * Migra estadistica_cobros_pagos_mensual.scx.
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'
import * as XLSX from 'xlsx'
import { guardarDesdeUrl } from '@/utils/descargas'

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre']

interface Fila { rubro: number; nombre: string; valores: number[]; promedio: number; separador: boolean }

// Default: mes anterior (hoy-30), como el Fox.
const hace30 = new Date(); hace30.setDate(hace30.getDate() - 30)
const mes = ref(hace30.getMonth() + 1)
const anio = ref(hace30.getFullYear())

const columnas = ref<string[]>([])
const filas = ref<Fila[]>([])
const cargando = ref(false)
const verGrafico = ref(false)
const msg = ref(''); const msgErr = ref(false)
let msgT: ReturnType<typeof setTimeout>

function aviso (t: string, err = false) { msg.value = t; msgErr.value = err; clearTimeout(msgT); msgT = setTimeout(() => (msg.value = ''), 4000) }

async function generar () {
  cargando.value = true
  try {
    const d = (await api.get('/tablero/gestion/cobros-pagos', { params: { mes: mes.value, anio: anio.value } })).data
    columnas.value = d.columnas; filas.value = d.filas
  } catch { columnas.value = []; filas.value = []; aviso('No se pudo generar el informe.', true) }
  finally { cargando.value = false }
}

function aExcel () {
  const filasXls: any[] = [{ c0: 'Rubro', c1: 'Descripción', ...Object.fromEntries(columnas.value.map((c, i) => ['m' + i, c])), tot: 'Promedio Anual' }]
  for (const f of filas.value) {
    filasXls.push({ c0: f.rubro > 0 ? f.rubro : '', c1: f.nombre, ...Object.fromEntries(f.valores.map((v, i) => ['m' + i, v])), tot: f.promedio })
  }
  const ws = XLSX.utils.json_to_sheet(filasXls, { skipHeader: true })
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'CobrosPagos')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  guardarDesdeUrl(url, 'estadistica_cobros_pagos.xlsx')
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

// ── Gráfico: una línea de días por rubro (solo los que tienen datos) ──
const COLORES: Record<number, string> = { 1: '#16a34a', 2: '#2d6a9f', 3: '#7c3aed', 4: '#d97706' }
const svgGrafico = computed(() => {
  const activos = filas.value.filter(f => !f.separador && f.valores.some(v => v > 0))
  if (!activos.length || !columnas.value.length) return '<p style="color:#94a3b8">Sin datos para graficar.</p>'
  const W = 820, H = 420, mL = 45, mR = 20, mT = 30, mB = 55
  const iw = W - mL - mR, ih = H - mT - mB
  const n = columnas.value.length
  const maxV = Math.max(10, ...activos.flatMap(f => f.valores))
  const x = (i: number) => mL + (iw * i) / (n - 1)
  const y = (v: number) => mT + ih - (ih * v) / maxV
  let g = `<svg viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto;font-family:system-ui,sans-serif">`
  for (let t = 0; t <= 4; t++) {
    const v = Math.round((maxV * t) / 4), yy = y(v)
    g += `<line x1="${mL}" y1="${yy}" x2="${W - mR}" y2="${yy}" stroke="#e2e8f0"/><text x="${mL - 6}" y="${yy + 4}" text-anchor="end" font-size="11" fill="#64748b">${v}</text>`
  }
  columnas.value.forEach((c, i) => { g += `<text x="${x(i)}" y="${H - mB + 16}" text-anchor="middle" font-size="9" fill="#64748b">${c}</text>` })
  activos.forEach((f) => {
    const col = COLORES[f.rubro] ?? '#334155'
    g += `<polyline points="${f.valores.map((v, i) => `${x(i)},${y(v)}`).join(' ')}" fill="none" stroke="${col}" stroke-width="2.5"/>`
    f.valores.forEach((v, i) => { g += `<circle cx="${x(i)}" cy="${y(v)}" r="2.5" fill="${col}"/>` })
  })
  activos.forEach((f, idx) => {
    const col = COLORES[f.rubro] ?? '#334155', lx = mL + idx * 155, ly = mT - 14
    g += `<rect x="${lx}" y="${ly - 9}" width="11" height="11" fill="${col}"/><text x="${lx + 15}" y="${ly}" font-size="11" fill="#334155">${f.nombre}</text>`
  })
  g += '<text x="' + (mL - 34) + '" y="' + (mT + ih / 2) + '" transform="rotate(-90 ' + (mL - 34) + ' ' + (mT + ih / 2) + ')" text-anchor="middle" font-size="10" fill="#94a3b8">días</text>'
  g += '</svg>'
  return g
})

generar()
</script>

<style scoped>
.cp { padding: 14px 16px 40px; color: #1e293b; }
.cp-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.cp-cab-ico { font-size: 30px; }
.cp-cab-tx h1 { margin: 0; font-size: 19px; }
.cp-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.cp-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-top: 14px; }
.cp-f { display: flex; align-items: center; gap: 8px; }
.cp-lbl { font-size: 12px; font-weight: 700; color: #475569; }
.cp-sel, .cp-anio { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 9px; font-size: 13px; color: #1e293b; background: #fff; }
.cp-sel.mes { width: 110px; } .cp-anio { width: 80px; }
.cp-btn { border: none; border-radius: 7px; padding: 8px 14px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.cp-btn.gen { background: #1b4332; } .cp-btn:disabled { opacity: .6; cursor: default; }
.cp-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #d1fae5; color: #065f46; font-weight: 600; font-size: 13px; }
.cp-msg.err { background: #fee2e2; color: #b91c1c; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.cp-scroll { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 14px; }
.cp-tabla { width: 100%; border-collapse: collapse; font-size: 12px; white-space: nowrap; }
.cp-tabla th { position: sticky; top: 0; background: #2d6a9f; color: #fff; padding: 6px 8px; text-align: right; font-weight: 700; }
.cp-tabla th.rub, .cp-tabla th.des { text-align: left; }
.cp-tabla th.t { background: #1b4332; }
.cp-tabla td { padding: 5px 8px; border-bottom: 1px solid #f0f4f9; color: #1e293b; }
.cp-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cp-tabla td.rub { text-align: center; color: #64748b; }
.cp-tabla td.des { font-weight: 700; color: #1b4332; }
.cp-tabla td.t { font-weight: 800; background: #eef2f6; color: #1b4332; }
.cp-tabla tr.alt td { background: #eafaf1; }
.cp-tabla tr.alt td.t { background: #d9f2e4; }
.cp-tabla tr.sep td.des { color: #0f5132; }
.cp-ov { position: fixed; inset: 0; background: rgba(15,23,42,.6); display: flex; align-items: center; justify-content: center; z-index: 10000; padding: 18px; }
.cp-modal { width: min(900px, 97vw); background: #fff; border-radius: 10px; overflow: hidden; }
.cp-mhead { display: flex; align-items: center; justify-content: space-between; padding: 11px 14px; background: #1b4332; color: #fff; font-weight: 700; }
.cp-x { background: transparent; border: none; color: #fff; font-size: 16px; cursor: pointer; }
.cp-mbody { padding: 16px; }
</style>
