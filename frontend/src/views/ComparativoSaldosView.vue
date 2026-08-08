<template>
  <div class="cs">
    <div class="cs-cab">
      <div class="cs-cab-ico">📊</div>
      <div class="cs-cab-tx">
        <h1>Comparativo Saldos en el Tiempo</h1>
        <p>Saldos de cuenta corriente por antigüedad, mes a mes</p>
      </div>
      <ModuloAyudaIA style="margin-left:auto" modulo="Comparativo Saldos en el Tiempo" icono="📊"
        descripcion="Informe solo lectura de los saldos de cuenta corriente por antigüedad, mes a mes, para ver cómo evolucionan en el tiempo."
        :sugerencias="['¿Qué muestra este comparativo?', '¿Qué es la antigüedad del saldo?', '¿Cómo interpreto la evolución?']"
        intro="Muestra cómo se mueven los saldos de cuenta corriente a lo largo de los meses."
        :pasos="['<b>Actualizá</b> para traer los datos.', 'Recorré las columnas de meses para ver la evolución.', '<b>Excel</b> exporta la tabla.']" />
      <div class="cs-acc">
        <button class="cs-btn" :disabled="cargando" @click="cargar">↻ Actualizar</button>
        <button v-if="filas.length" class="cs-btn" @click="aExcel">📊 Excel</button>
      </div>
    </div>

    <transition name="msg"><div v-if="msg" class="cs-msg err">{{ msg }}</div></transition>

    <template v-if="filas.length">
      <div class="cs-scroll">
        <table class="cs-tabla">
          <thead>
            <tr>
              <th class="a">MES</th><th class="a">AÑO</th>
              <th>Más de 1 Año</th><th>de 6 Meses a 1 Año</th><th>de 4 a 6 Meses</th>
              <th>de 91 a 120 Días</th><th>de 61 a 90 Días</th><th>de 31 a 60 Días</th><th>Hasta 30 Días</th>
              <th class="t">Totales</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(f, i) in filasOrden" :key="i" :class="{ alt: i % 2 === 1 }">
              <td class="a c">{{ f.mes }}</td><td class="a c">{{ f.anio }}</td>
              <td class="num">{{ money(f.m360) }}</td><td class="num">{{ money(f.m180) }}</td><td class="num">{{ money(f.m120) }}</td>
              <td class="num">{{ money(f.m090) }}</td><td class="num">{{ money(f.m060) }}</td><td class="num">{{ money(f.m030) }}</td><td class="num">{{ money(f.u030) }}</td>
              <td class="num t">{{ money(f.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="cs-tit">Gráfica comparativa de saldos en el tiempo (proporción %)</div>
      <div class="cs-leyenda">
        <span v-for="b in BUCKETS" :key="b.k" class="cs-leg"><i :style="{ background: b.color }"></i>{{ b.label }}</span>
      </div>
      <div class="cs-chart" v-html="svgGrafico"></div>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * ComparativoSaldosView.vue — Estadísticas › Comparativo Saldos en el Tiempo.
 * Lee SALDOS_TIEMPO (solo lectura): tabla de saldos por antigüedad mes a mes +
 * gráfico de barras apiladas al 100% (proporción de cada tramo). Migra
 * estadistica_comparativa_saldos_tiempo.scx.
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'
import * as XLSX from 'xlsx'
import { guardarDesdeUrl } from '@/utils/descargas'

interface Fila { mes: number; anio: number; m360: number; m180: number; m120: number; m090: number; m060: number; m030: number; u030: number; total: number }

// Orden de apilado (abajo→arriba) y colores, igual que las 7 series del Fox.
const BUCKETS = [
  { k: 'u030', label: '(Ult. 30 días)',        color: '#c084fc' },
  { k: 'm030', label: '(de 31 a 60 días)',     color: '#e879a6' },
  { k: 'm060', label: '(de 61 a 90 días)',     color: '#a21caf' },
  { k: 'm090', label: '(de 91 a 120 días)',    color: '#7c3aed' },
  { k: 'm120', label: '(de 4 a 6 meses)',      color: '#65a30d' },
  { k: 'm180', label: '(de 6 meses a un año)', color: '#06b6d4' },
  { k: 'm360', label: '(más de 1 año)',        color: '#a8a29e' },
] as const
const MES_ABR = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic']

const filas = ref<Fila[]>([])
// Grilla con el último período arriba (orden descendente por año y mes).
// El gráfico y el Excel se dejan en orden cronológico.
const filasOrden = computed(() => [...filas.value].sort((a, b) => b.anio - a.anio || b.mes - a.mes))
const cargando = ref(false)
const msg = ref('')

const money = (v: number) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)

async function cargar () {
  cargando.value = true; msg.value = ''
  try { filas.value = (await api.get('/tablero/gestion/comparativo-saldos')).data.filas }
  catch { filas.value = []; msg.value = 'No se pudo cargar el informe.' }
  finally { cargando.value = false }
}

function aExcel () {
  const heads = ['MES', 'AÑO', 'Más de 1 Año', 'de 6 Meses a 1 Año', 'de 4 a 6 Meses', 'de 91 a 120 Días', 'de 61 a 90 Días', 'de 31 a 60 Días', 'Hasta 30 Días', 'Totales']
  const rows = filas.value.map(f => [f.mes, f.anio, f.m360, f.m180, f.m120, f.m090, f.m060, f.m030, f.u030, f.total])
  const ws = XLSX.utils.aoa_to_sheet([heads, ...rows])
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Saldos')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  guardarDesdeUrl(url, 'estadistica_saldos_tiempo.xlsx')
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

// ── Gráfico: barras apiladas al 100% (proporción de cada tramo por período) ──
const svgGrafico = computed(() => {
  if (!filas.value.length) return ''
  const n = filas.value.length
  const W = Math.max(760, n * 34 + 80), H = 380, mL = 45, mR = 12, mT = 12, mB = 60
  const iw = W - mL - mR, ih = H - mT - mB
  const bw = Math.min(26, (iw / n) * 0.7)
  const cx = (i: number) => mL + (iw * (i + 0.5)) / n
  let g = `<svg viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" style="height:auto;font-family:system-ui,sans-serif">`
  for (let t = 0; t <= 4; t++) {
    const yy = mT + ih - (ih * t) / 4
    g += `<line x1="${mL}" y1="${yy}" x2="${W - mR}" y2="${yy}" stroke="#e2e8f0"/><text x="${mL - 6}" y="${yy + 4}" text-anchor="end" font-size="10" fill="#64748b">${t * 25}</text>`
  }
  filas.value.forEach((f, i) => {
    const x = cx(i) - bw / 2
    let acc = 0
    if (f.total > 0) {
      for (const b of BUCKETS) {
        const pct = (100 * (f as any)[b.k]) / f.total
        if (pct <= 0) continue
        const h = (ih * pct) / 100
        const y = mT + ih - (ih * acc) / 100 - h
        g += `<rect x="${x}" y="${y}" width="${bw}" height="${h}" fill="${b.color}"/>`
        acc += pct
      }
    }
    g += `<text x="${cx(i)}" y="${H - mB + 14}" text-anchor="end" font-size="9" fill="#64748b" transform="rotate(-45 ${cx(i)} ${H - mB + 14})">${MES_ABR[f.mes]}-${String(f.anio).slice(2)}</text>`
  })
  g += '</svg>'
  return g
})

cargar()
</script>

<style scoped>
.cs { padding: 14px 16px 40px; color: #1e293b; }
.cs-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.cs-cab-ico { font-size: 30px; }
.cs-cab-tx h1 { margin: 0; font-size: 19px; }
.cs-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.cs-acc { margin-left: auto; display: flex; gap: 8px; }
.cs-btn { border: none; border-radius: 7px; padding: 8px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.cs-btn:disabled { opacity: .6; cursor: default; }
.cs-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; }
.cs-msg.err { background: #fee2e2; color: #b91c1c; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.cs-scroll { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 14px; }
.cs-tabla { width: 100%; border-collapse: collapse; font-size: 12px; white-space: nowrap; }
.cs-tabla th { position: sticky; top: 0; background: #2d6a9f; color: #fff; padding: 6px 8px; text-align: right; font-weight: 700; }
.cs-tabla th.a { text-align: center; } .cs-tabla th.t { background: #1b4332; }
.cs-tabla td { padding: 5px 8px; border-bottom: 1px solid #f0f4f9; color: #1e293b; }
.cs-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cs-tabla td.c { text-align: center; font-weight: 700; color: #1b4332; }
.cs-tabla td.t { font-weight: 800; background: #eef2f6; color: #1b4332; }
.cs-tabla tr.alt td { background: #eafaf1; }
.cs-tabla tr.alt td.t { background: #d9f2e4; }
.cs-tit { margin: 18px 0 6px; font-size: 13px; font-weight: 800; color: #1b4332; }
.cs-leyenda { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 8px; }
.cs-leg { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; color: #475569; }
.cs-leg i { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
.cs-chart { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
.cs-chart :deep(svg) { display: block; }
</style>
