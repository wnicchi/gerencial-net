<template>
  <div class="pf">
    <div class="pf-head">
      <div>
        <h1>Pendiente de Facturación</h1>
        <p class="pf-sub">Gestión (Logística) — montos por facturar: contratos, transportes y servicios.</p>
      </div>
      <div class="pf-acc">
        <button class="btn-refresh" @click="cargar" :disabled="cargando">{{ cargando ? 'Cargando…' : '↻ Actualizar' }}</button>
        <button class="btn-print" @click="imprimirPDF" :disabled="!data || cargando || generandoPdf">{{ generandoPdf ? '⟳…' : '🖨 Imprimir PDF' }}</button>
      </div>
    </div>

    <div v-if="error" class="msg-error">{{ error }}</div>
    <div v-else-if="cargando && !data" class="cargando">Cargando datos de Gestión…</div>

    <template v-if="data">
      <!-- KPIs -->
      <div class="kpis">
        <div class="kpi kpi-total"><span class="kpi-val">{{ money(data.total) }}</span><span class="kpi-lbl">TOTAL pendiente</span></div>
        <div class="kpi"><span class="kpi-val">{{ money(data.contratos) }}</span><span class="kpi-lbl">Contratos</span></div>
        <div class="kpi"><span class="kpi-val">{{ money(data.transportes) }}</span><span class="kpi-lbl">Transportes</span></div>
        <div class="kpi"><span class="kpi-val">{{ money(data.servicios) }}</span><span class="kpi-lbl">Servicios</span></div>
        <div class="kpi"><span class="kpi-val">{{ nf(data.clientes) }}</span><span class="kpi-lbl">Clientes</span></div>
      </div>

      <div class="grid">
        <!-- Torta por servicio -->
        <section class="card">
          <h3>🥧 Pendiente por tipo <small>(contratos / transportes / servicios)</small></h3>
          <div v-if="!torta.length" class="vacio">Sin pendientes.</div>
          <div v-else class="torta-wrap">
            <svg viewBox="0 0 180 180" class="torta-svg">
              <path v-for="(s, i) in torta" :key="i" :d="s.d" :fill="s.color" stroke="#fff" stroke-width="1.5"><title>{{ s.nombre }}: {{ money(s.val) }} ({{ s.pct.toFixed(1) }}%)</title></path>
              <text v-for="(s, i) in torta.filter(x=>x.mostrar)" :key="'l'+i" :x="s.lx" :y="s.ly" text-anchor="middle" dominant-baseline="middle" class="torta-lbl">{{ s.pct.toFixed(0) }}%</text>
            </svg>
            <div class="torta-leg">
              <div v-for="(s, i) in torta" :key="i" class="torta-leg-item">
                <span class="torta-dot" :style="{ background: s.color }"></span>
                <span class="torta-nom">{{ s.nombre }}</span>
                <span class="torta-val">{{ s.pct.toFixed(1) }}% · {{ money(s.val) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Top clientes -->
        <section class="card">
          <h3>🏢 Pendiente por cliente</h3>
          <div v-if="!data.porCliente.length" class="vacio">Sin pendientes.</div>
          <div v-else class="hbars">
            <div v-for="(c, i) in data.porCliente" :key="i" class="hbar-row">
              <span class="hbar-lbl" :title="c.cliente">{{ c.cliente }}</span>
              <div class="hbar-track"><div class="hbar-fill" :style="{ width: pct(c.monto, maxCliente) + '%', background: C.c1 }"></div></div>
              <span class="hbar-val">{{ money(c.monto) }}</span>
            </div>
          </div>
        </section>
      </div>

      <!-- Detalle -->
      <section class="card">
        <h3>📋 Detalle por cliente / período <small>({{ nf(data.filas.length) }} filas)</small></h3>
        <div class="tabla-wrap">
          <table class="tabla">
            <thead>
              <tr>
                <th class="th-sort" @click="ordenar('cliente_nombre')">Cliente{{ flecha('cliente_nombre') }}</th>
                <th class="th-sort" @click="ordenar('servicio')">Tipo{{ flecha('servicio') }}</th>
                <th class="th-sort" @click="ordenar('periodo')">Período{{ flecha('periodo') }}</th>
                <th class="th-sort r" @click="ordenar('monto_neto')">Monto{{ flecha('monto_neto') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(f, i) in filasSorted" :key="i">
                <td>{{ f.cliente_nombre }}</td>
                <td><span class="tag" :style="{ background: colorServicio(f.servicio) }">{{ f.servicio }}</span></td>
                <td>{{ f.mes ? String(f.mes).padStart(2,'0') + '/' + f.anio : '—' }}</td>
                <td class="r">{{ money(f.monto_neto) }}</td>
              </tr>
            </tbody>
            <tfoot><tr><td colspan="3" class="r"><b>TOTAL</b></td><td class="r"><b>{{ money(data.total) }}</b></td></tr></tfoot>
          </table>
        </div>
      </section>
    </template>

    <!-- Visor PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="es-pdf-ov" @click.self="cerrarPdf">
        <div class="es-pdf-md">
          <div class="es-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="es-pdf-acc">
              <button class="es-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="es-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="es-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="es-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'

const C = { c1: '#2a78d6', c2: '#eb6834', c3: '#1baf7a' }
const nfInt = new Intl.NumberFormat('es-AR')
const nf = (n: number) => nfInt.format(n ?? 0)
const money = (n: number) => '$ ' + new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const pct = (v: number, max: number) => (max > 0 ? Math.max(2, (v / max) * 100) : 0)
const COL_SRV: Record<string, string> = { CONTRATOS: C.c3, TRANSPORTES: C.c1, SERVICIOS: C.c2 }
const colorServicio = (s: string) => COL_SRV[s] ?? '#94a3b8'

const data = ref<any>(null)
const cargando = ref(false)
const error = ref('')

const maxCliente = computed(() => Math.max(1, ...(data.value?.porCliente ?? []).map((c: any) => c.monto)))

// Torta por tipo de servicio.
const torta = computed(() => {
  const items = [
    { nombre: 'Contratos', val: data.value?.contratos ?? 0, color: C.c3 },
    { nombre: 'Transportes', val: data.value?.transportes ?? 0, color: C.c1 },
    { nombre: 'Servicios', val: data.value?.servicios ?? 0, color: C.c2 },
  ].filter(x => x.val > 0)
  const total = items.reduce((s, e) => s + e.val, 0)
  if (total <= 0) return []
  const cx = 90, cy = 90, R = 82
  let ang = -Math.PI / 2
  return items.map(e => {
    const frac = e.val / total
    const a0 = ang, a1 = ang + frac * 2 * Math.PI; ang = a1
    const mid = (a0 + a1) / 2
    const p = (r: number, a: number) => [(cx + r * Math.cos(a)).toFixed(2), (cy + r * Math.sin(a)).toFixed(2)]
    const [x0, y0] = p(R, a0); const [x1, y1] = p(R, a1); const [lx, ly] = p(R * 0.6, mid)
    const large = (a1 - a0) > Math.PI ? 1 : 0
    const d = frac >= 0.999
      ? `M ${cx - R} ${cy} A ${R} ${R} 0 1 1 ${cx + R} ${cy} A ${R} ${R} 0 1 1 ${cx - R} ${cy} Z`
      : `M ${cx} ${cy} L ${x0} ${y0} A ${R} ${R} 0 ${large} 1 ${x1} ${y1} Z`
    return { d, color: e.color, nombre: e.nombre, val: e.val, pct: frac * 100, lx, ly, mostrar: frac >= 0.05 }
  })
})

// ── Orden de la tabla ──
const NUMERICAS = new Set(['monto_neto', 'periodo'])
const sortKey = ref('monto_neto'); const sortDir = ref<'asc' | 'desc'>('desc')
function ordenar (key: string) {
  if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = key; sortDir.value = NUMERICAS.has(key) ? 'desc' : 'asc' }
}
const flecha = (key: string) => sortKey.value === key ? (sortDir.value === 'asc' ? ' ▲' : ' ▼') : ''
const filasSorted = computed(() => {
  const rows = [...(data.value?.filas ?? [])]
  const k = sortKey.value, dir = sortDir.value === 'asc' ? 1 : -1
  rows.sort((a, b) => {
    let av: any = k === 'periodo' ? a.anio * 100 + a.mes : a[k]
    let bv: any = k === 'periodo' ? b.anio * 100 + b.mes : b[k]
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
    return String(av ?? '').localeCompare(String(bv ?? ''), 'es', { numeric: true }) * dir
  })
  return rows
})

async function cargar () {
  cargando.value = true; error.value = ''
  try { data.value = (await api.get('/tablero/gestion/pendiente-facturacion')).data }
  catch (e: any) { error.value = e?.response?.data?.message || 'No se pudieron cargar los datos de Gestión.' }
  finally { cargando.value = false }
}

// ── PDF ──
const generandoPdf = ref(false); const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
async function imprimirPDF () {
  if (!data.value) return
  generandoPdf.value = true
  try {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const PW = 210, ML = 12, W = PW - ML * 2, PH = 297
    let y = 16
    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
    doc.text('Pendiente de Facturación — Gestión (Logística)', ML, y); y += 6
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(0, 0, 0)
    doc.text('Impreso: ' + new Date().toLocaleDateString('es-AR'), ML, y); y += 5
    doc.setDrawColor(27, 67, 50); doc.setLineWidth(0.4); doc.line(ML, y, PW - ML, y); y += 7

    const kv = [['TOTAL pendiente', money(data.value.total)], ['Contratos', money(data.value.contratos)], ['Transportes', money(data.value.transportes)], ['Servicios', money(data.value.servicios)], ['Clientes', nf(data.value.clientes)]]
    for (const [k, v] of kv) { doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(String(k) + ':', ML, y); doc.setFont('helvetica', 'normal'); doc.text(String(v), ML + 42, y); y += 5 }
    y += 3

    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50); doc.text('Detalle', ML, y); y += 5
    const xs = [ML, ML + 92, ML + 120, PW - ML]; const heads = ['Cliente', 'Tipo', 'Período', 'Monto']
    doc.setFontSize(8); doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'bold')
    heads.forEach((h, i) => doc.text(h, i === 3 ? xs[3] : xs[i], y, i === 3 ? { align: 'right' } : undefined)); y += 1.5
    doc.setDrawColor(180); doc.line(ML, y, PW - ML, y); y += 4; doc.setFont('helvetica', 'normal')
    for (const f of filasSorted.value) {
      if (y > PH - 16) { doc.addPage(); y = 16 }
      doc.text(doc.splitTextToSize(String(f.cliente_nombre), 88)[0] || '', xs[0], y)
      doc.text(f.servicio, xs[1], y)
      doc.text(f.mes ? String(f.mes).padStart(2, '0') + '/' + f.anio : '—', xs[2], y)
      doc.text(money(f.monto_neto), xs[3], y, { align: 'right' }); y += 4.4
    }
    doc.setFont('helvetica', 'bold'); doc.line(ML, y, PW - ML, y); y += 4
    doc.text('TOTAL', xs[0], y); doc.text(money(data.value.total), xs[3], y, { align: 'right' })

    cerrarPdf(); pdfNombre.value = 'PENDIENTE_FACTURACION.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (e) { console.error(e); error.value = 'No se pudo generar el PDF.' }
  finally { generandoPdf.value = false }
}

onMounted(cargar)
</script>

<style scoped>
.pf { padding: 1.2rem 1.4rem 2.5rem; color: #1e293b; }
.pf-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.pf-head h1 { margin: 0; color: #1b4332; font-size: 1.5rem; font-weight: 800; }
.pf-sub { margin: 0.2rem 0 0; color: #475569; font-size: 0.9rem; }
.pf-acc { display: flex; gap: 0.6rem; }
.btn-refresh { padding: 0.45rem 0.9rem; background: #1b4332; color: #fff; border: none; border-radius: 7px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-refresh:hover { background: #14532d; } .btn-refresh:disabled { opacity: 0.6; }
.btn-print { padding: 0.45rem 0.9rem; background: #fff; color: #1b4332; border: 1px solid #1b4332; border-radius: 7px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-print:hover { background: #f0faf4; } .btn-print:disabled { opacity: 0.5; }
.msg-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.7rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
.cargando { color: #64748b; padding: 2rem; text-align: center; }

.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.8rem; margin-bottom: 1.2rem; }
.kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.kpi-val { font-size: 1.25rem; font-weight: 800; color: #1b4332; }
.kpi-lbl { font-size: 0.76rem; color: #64748b; }
.kpi-total { border-top: 4px solid #1baf7a; } .kpi-total .kpi-val { color: #0f766e; }

.grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.card h3 { margin: 0 0 0.7rem; font-size: 0.98rem; color: #1e293b; font-weight: 700; }
.card h3 small { color: #94a3b8; font-weight: 500; }
.vacio { color: #94a3b8; font-size: 0.85rem; padding: 1rem 0; text-align: center; }

.torta-wrap { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
.torta-svg { width: 170px; height: 170px; flex-shrink: 0; }
.torta-lbl { font-size: 10px; fill: #fff; font-weight: 700; paint-order: stroke; stroke: rgba(0,0,0,0.25); stroke-width: 2px; }
.torta-leg { display: flex; flex-direction: column; gap: 0.4rem; min-width: 180px; flex: 1; }
.torta-leg-item { display: grid; grid-template-columns: 12px 1fr auto; align-items: center; gap: 0.5rem; font-size: 0.8rem; }
.torta-dot { width: 12px; height: 12px; border-radius: 3px; }
.torta-nom { color: #475569; } .torta-val { color: #1e293b; font-weight: 600; white-space: nowrap; }

.hbars { display: flex; flex-direction: column; gap: 0.5rem; }
.hbar-row { display: grid; grid-template-columns: 150px 1fr 130px; align-items: center; gap: 0.5rem; }
.hbar-lbl { font-size: 0.78rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hbar-track { background: #f1f5f9; border-radius: 5px; height: 16px; overflow: hidden; }
.hbar-fill { height: 100%; border-radius: 5px; }
.hbar-val { font-size: 0.78rem; font-weight: 700; color: #1e293b; text-align: right; }

.tabla-wrap { overflow-x: auto; }
.tabla { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
.tabla th { background: #f0faf4; color: #1b4332; text-align: left; padding: 0.5rem 0.7rem; border-bottom: 1px solid #c3e6cb; font-weight: 700; white-space: nowrap; }
.tabla th.th-sort { cursor: pointer; user-select: none; } .tabla th.th-sort:hover { background: #e2f5ea; }
.tabla td { padding: 0.4rem 0.7rem; border-bottom: 1px solid #eef2f7; color: #334155; }
.tabla tr:hover td { background: #f8fafc; }
.tabla .r { text-align: right; }
.tabla tfoot td { background: #f8fafc; border-top: 2px solid #c3e6cb; }
.tag { color: #fff; font-size: 0.68rem; font-weight: 700; padding: 0.12rem 0.5rem; border-radius: 20px; }

.es-pdf-ov { position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 18px; }
.es-pdf-md { width: min(1000px, 98vw); height: 92vh; background: #fff; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(0,0,0,.5); }
.es-pdf-head { display: flex; align-items: center; gap: 14px; padding: 10px 14px; background: #1b4332; color: #fff; font-size: 13px; flex-wrap: wrap; }
.es-pdf-acc { margin-left: auto; display: flex; gap: 8px; }
.es-pdf-b { border: none; padding: 7px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
.es-pdf-b.ok { background: #22c55e; color: #fff; } .es-pdf-b.cancel { background: #ef4444; color: #fff; }
.es-pdf-frame { flex: 1; border: none; width: 100%; }

@media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
</style>
