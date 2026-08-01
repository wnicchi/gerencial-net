<template>
  <div class="wms">
    <!-- ── Barra de filtros ── -->
    <div class="filtros">
      <div class="f-grupo">
        <label>Empresa / Cliente</label>
        <select v-model.number="empresa" @change="cargar">
          <option :value="0">TODAS (Global)</option>
          <option v-for="e in empresas" :key="e.codigo" :value="e.codigo">
            {{ e.nombre }}{{ e.con_stock ? '' : '  ·(sin stock)' }}
          </option>
        </select>
      </div>
      <div class="f-grupo">
        <label>Desde</label>
        <input type="date" v-model="fecha1" @change="cargar" />
      </div>
      <div class="f-grupo">
        <label>Hasta</label>
        <input type="date" v-model="fecha2" @change="cargar" />
      </div>
      <button class="btn-refresh" @click="cargar" :disabled="cargando">
        {{ cargando ? 'Cargando…' : '↻ Actualizar' }}
      </button>
      <span class="alcance" v-if="data">{{ data.empresaNombre }}</span>
    </div>

    <div v-if="error" class="msg-error">{{ error }}</div>

    <template v-if="data">
      <!-- ── KPIs ── -->
      <div class="kpis">
        <div class="kpi"><span class="kpi-val">{{ nf(data.kpis.posiciones) }}</span><span class="kpi-lbl">Posiciones en stock</span></div>
        <div class="kpi"><span class="kpi-val">{{ nf(data.kpis.unidades) }}</span><span class="kpi-lbl">Unidades</span></div>
        <div class="kpi"><span class="kpi-val">{{ nf(data.kpis.productos) }}</span><span class="kpi-lbl">Productos (P.N.)</span></div>
        <div class="kpi"><span class="kpi-val">{{ nf(data.kpis.empresas) }}</span><span class="kpi-lbl">Empresas con stock</span></div>
        <div class="kpi kpi-alert"><span class="kpi-val">{{ nf(data.kpis.bloqueadas) }}</span><span class="kpi-lbl">Posiciones bloqueadas</span></div>
      </div>

      <div class="grid">
        <!-- ── Stock por empresa (comparativo) ── -->
        <section class="card">
          <h3>📦 Stock por empresa <small>(posiciones)</small></h3>
          <div v-if="!data.stockPorEmpresa.length" class="vacio">Sin stock.</div>
          <div v-else class="hbars">
            <div v-for="e in data.stockPorEmpresa" :key="e.empresa" class="hbar-row">
              <span class="hbar-lbl" :title="e.nombre">{{ e.nombre }}</span>
              <div class="hbar-track">
                <div class="hbar-fill" :style="{ width: pct(e.posiciones, maxEmpresa) + '%', background: C.c3 }"></div>
              </div>
              <span class="hbar-val">{{ nf(e.posiciones) }}</span>
            </div>
          </div>
        </section>

        <!-- ── Stock por estado ── -->
        <section class="card">
          <h3>🏷️ Stock por estado <small>(posiciones)</small></h3>
          <div v-if="!data.stockPorEstado.length" class="vacio">Sin datos.</div>
          <svg v-else :viewBox="`0 0 ${W} ${H}`" class="chart">
            <g v-for="(b, i) in barsEstado" :key="i">
              <rect :x="b.x" :y="b.y" :width="b.w" :height="b.h" :fill="estadoColor(b.tip)" rx="3">
                <title>{{ b.label }}: {{ nf(b.value) }}</title>
              </rect>
              <text :x="b.x + b.w/2" :y="H - 22" text-anchor="middle" class="ax-x">{{ b.short }}</text>
              <text :x="b.x + b.w/2" :y="b.y - 5" text-anchor="middle" class="ax-v">{{ nf(b.value) }}</text>
            </g>
          </svg>
        </section>

        <!-- ── Movimientos por mes ── -->
        <section class="card wide">
          <div class="card-head">
            <h3>🔄 Movimientos de stock por mes
              <small>{{ modoMov === 'todos' ? '(ingresos vs egresos)' : '(total por empresa)' }}</small>
            </h3>
            <div class="toggle">
              <button :class="{ on: modoMov === 'todos' }" @click="modoMov = 'todos'">Todos</button>
              <button :class="{ on: modoMov === 'empresas' }" @click="modoMov = 'empresas'">Por empresas</button>
            </div>
          </div>

          <template v-if="modoMov === 'todos'">
            <Leyenda :items="[['Ingresos', C.c3], ['Egresos', C.c8]]" />
            <div v-if="sinMovs" class="vacio">Sin movimientos en el período.</div>
            <svg v-else :viewBox="`0 0 ${WW} ${H}`" class="chart">
              <g v-for="(m, i) in gruposMov" :key="i">
                <rect :x="m.x" :y="m.yI" :width="m.bw" :height="m.hI" :fill="C.c3" rx="2"><title>{{ m.label }} · Ingresos: {{ nf(m.ing) }}</title></rect>
                <rect :x="m.x + m.bw + 2" :y="m.yE" :width="m.bw" :height="m.hE" :fill="C.c8" rx="2"><title>{{ m.label }} · Egresos: {{ nf(m.egr) }}</title></rect>
                <text :x="m.cx" :y="H - 22" text-anchor="middle" class="ax-x">{{ m.short }}</text>
              </g>
            </svg>
          </template>
          <template v-else>
            <div class="ley-empresas">
              <span v-for="e in empresasMov" :key="e.empresa" class="ley-item"><span class="ley-dot" :style="{ background: e.color }"></span>{{ e.nombre }}</span>
            </div>
            <div v-if="!empresasMov.length" class="vacio">Sin movimientos en el período.</div>
            <svg v-else :viewBox="`0 0 ${WW} ${H}`" class="chart">
              <g v-for="(m, i) in stackedMov" :key="i">
                <rect v-for="(s, j) in m.segs" :key="j" :x="s.x" :y="s.y" :width="s.w" :height="s.h" :fill="s.color"><title>{{ m.label }} · {{ s.nombre }}: {{ nf(s.total) }}</title></rect>
                <text :x="m.cx" :y="H - 22" text-anchor="middle" class="ax-x">{{ m.short }}</text>
              </g>
            </svg>
          </template>
        </section>

        <!-- ── Operación por mes ── -->
        <section class="card wide">
          <div class="card-head">
            <h3>🚚 Operación por mes
              <small>{{ modoOper === 'todos' ? '(recepciones vs despachos)' : '(total por empresa)' }}</small>
            </h3>
            <div class="toggle">
              <button :class="{ on: modoOper === 'todos' }" @click="modoOper = 'todos'">Todos</button>
              <button :class="{ on: modoOper === 'empresas' }" @click="modoOper = 'empresas'">Por empresas</button>
            </div>
          </div>

          <template v-if="modoOper === 'todos'">
            <Leyenda :items="[['Recepciones', C.c1], ['Despachos', C.c2]]" />
            <div v-if="sinOper" class="vacio">Sin recepciones ni despachos en el período.</div>
            <svg v-else :viewBox="`0 0 ${WW} ${H}`" class="chart">
              <g v-for="(m, i) in gruposOper" :key="i">
                <rect :x="m.x" :y="m.yR" :width="m.bw" :height="m.hR" :fill="C.c1" rx="2"><title>{{ m.label }} · Recepciones: {{ nf(m.rec) }}</title></rect>
                <rect :x="m.x + m.bw + 2" :y="m.yD" :width="m.bw" :height="m.hD" :fill="C.c2" rx="2"><title>{{ m.label }} · Despachos: {{ nf(m.des) }}</title></rect>
                <text :x="m.cx" :y="H - 22" text-anchor="middle" class="ax-x">{{ m.short }}</text>
              </g>
            </svg>
          </template>
          <template v-else>
            <div class="ley-empresas">
              <span v-for="e in empresasOper" :key="e.empresa" class="ley-item"><span class="ley-dot" :style="{ background: e.color }"></span>{{ e.nombre }}</span>
            </div>
            <div v-if="!empresasOper.length" class="vacio">Sin recepciones ni despachos en el período.</div>
            <svg v-else :viewBox="`0 0 ${WW} ${H}`" class="chart">
              <g v-for="(m, i) in stackedOper" :key="i">
                <rect v-for="(s, j) in m.segs" :key="j" :x="s.x" :y="s.y" :width="s.w" :height="s.h" :fill="s.color"><title>{{ m.label }} · {{ s.nombre }}: {{ nf(s.total) }}</title></rect>
                <text :x="m.cx" :y="H - 22" text-anchor="middle" class="ax-x">{{ m.short }}</text>
              </g>
            </svg>
          </template>
        </section>
      </div>

      <!-- ── Alertas operativas ── -->
      <section class="card alertas">
        <h3>⚠️ Alertas operativas de stock</h3>
        <div class="chips">
          <span class="chip chip-red">Vencidos: <b>{{ nf(data.alertas.resumen.vencidos) }}</b></span>
          <span class="chip chip-amber">Por vencer (180 d): <b>{{ nf(data.alertas.resumen.porVencer) }}</b></span>
          <span class="chip chip-slate">Bloqueadas: <b>{{ nf(data.alertas.resumen.bloqueados) }}</b></span>
        </div>

        <div class="alerta-cols">
          <div class="alerta-col">
            <h4>Productos vencidos</h4>
            <div v-if="!data.alertas.vencidos.length" class="vacio">Ninguno.</div>
            <ul v-else>
              <li v-for="(v, i) in data.alertas.vencidos" :key="i">
                <span class="a-emp">{{ v.empresa }}</span>
                <span class="a-pn">{{ v.pn }} · {{ v.des }}</span>
                <span class="a-fec venc">venció {{ fmtFecha(v.vence) }} ({{ Math.abs(v.dias) }} d)</span>
              </li>
            </ul>
          </div>
          <div class="alerta-col">
            <h4>Próximos a vencer</h4>
            <div v-if="!data.alertas.porVencer.length" class="vacio">Ninguno.</div>
            <ul v-else>
              <li v-for="(v, i) in data.alertas.porVencer" :key="i">
                <span class="a-emp">{{ v.empresa }}</span>
                <span class="a-pn">{{ v.pn }} · {{ v.des }}</span>
                <span class="a-fec pv">vence {{ fmtFecha(v.vence) }} (en {{ v.dias }} d)</span>
              </li>
            </ul>
          </div>
          <div class="alerta-col">
            <h4>Bloqueadas por empresa</h4>
            <div v-if="!data.alertas.bloqueados.length" class="vacio">Ninguna.</div>
            <ul v-else>
              <li v-for="(b, i) in data.alertas.bloqueados" :key="i">
                <span class="a-emp">{{ b.empresa }}</span>
                <span class="a-fec">{{ nf(b.posiciones) }} pos · {{ nf(b.unidades) }} u.</span>
              </li>
            </ul>
          </div>
        </div>
      </section>
    </template>

    <div v-else-if="cargando" class="cargando">Cargando datos de Stock/Logística…</div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, h } from 'vue'
import api from '@/services/auth'

// Paleta categórica validada (skill dataviz).
const C = { c1: '#2a78d6', c2: '#eb6834', c3: '#1baf7a', c4: '#eda100', c8: '#e34948' }

const nfInt = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 })
const nf = (n: number) => nfInt.format(n ?? 0)
const pct = (v: number, max: number) => (max > 0 ? Math.max(2, (v / max) * 100) : 0)
function fmtFecha (iso: string) {
  const [y, m, d] = (iso || '').split('-'); return d ? `${d}/${m}/${y}` : iso
}

// Leyenda inline (componente funcional pequeño).
const Leyenda = (props: { items: [string, string][] }) =>
  h('div', { class: 'leyenda' }, props.items.map(([txt, col]) =>
    h('span', { class: 'ley-item' }, [h('span', { class: 'ley-dot', style: { background: col } }), txt])))

interface Empresa { codigo: number; nombre: string; con_stock: boolean }
const empresas = ref<Empresa[]>([])
const empresa  = ref(0)
const hoy = new Date()
const haceUnAnio = new Date(hoy.getFullYear(), hoy.getMonth() - 11, 1)
const iso = (d: Date) => d.toISOString().slice(0, 10)
const fecha1 = ref(iso(haceUnAnio))
const fecha2 = ref(iso(hoy))

const data = ref<any>(null)
const cargando = ref(false)
const error = ref('')

// ── Geometría de gráficos ──
const W = 360, WW = 720, H = 220
const estadoColores: Record<number, string> = { 1: C.c3, 2: C.c4, 3: C.c2, 4: C.c8, 5: '#94a3b8' }
const estadoColor = (tip: number) => estadoColores[tip] ?? '#94a3b8'

const maxEmpresa = computed(() => Math.max(1, ...(data.value?.stockPorEmpresa ?? []).map((e: any) => e.posiciones)))

const barsEstado = computed(() => {
  const items = data.value?.stockPorEstado ?? []
  const max = Math.max(1, ...items.map((x: any) => x.posiciones))
  const pad = 40, top = 24, bottom = 40
  const bw = items.length ? Math.min(70, (W - pad) / items.length - 12) : 0
  const gap = items.length ? (W - pad) / items.length : 0
  return items.map((x: any, i: number) => {
    const hh = ((x.posiciones) / max) * (H - top - bottom)
    return { tip: x.tip, label: x.estado, short: x.estado.split(' ')[0], value: x.posiciones,
      x: pad / 2 + i * gap + (gap - bw) / 2, y: H - bottom - hh, w: bw, h: hh }
  })
})

function grupos (rows: any[], keyA: string, keyB: string) {
  const max = Math.max(1, ...rows.map(r => Math.max(r[keyA], r[keyB])))
  const pad = 30, top = 18, bottom = 40
  const gw = rows.length ? (WW - pad) / rows.length : 0
  const bw = Math.max(4, Math.min(26, gw / 2 - 4))
  return rows.map((r, i) => {
    const base = pad / 2 + i * gw + (gw - (bw * 2 + 2)) / 2
    const hA = (r[keyA] / max) * (H - top - bottom)
    const hB = (r[keyB] / max) * (H - top - bottom)
    return { label: r.label, short: r.label.replace(' ', ' ').slice(0, 6), x: base, bw,
      cx: base + bw + 1,
      _a: r[keyA], _b: r[keyB],
      yA: H - bottom - hA, hA, yB: H - bottom - hB, hB }
  })
}

const gruposMov = computed(() => grupos(data.value?.movimientos ?? [], 'ingresos', 'egresos')
  .map(g => ({ ...g, ing: g._a, egr: g._b, yI: g.yA, hI: g.hA, yE: g.yB, hE: g.hB })))
const sinMovs = computed(() => (data.value?.movimientos ?? []).every((m: any) => !m.ingresos && !m.egresos))

const gruposOper = computed(() => grupos(data.value?.operacion ?? [], 'recepciones', 'despachos')
  .map(g => ({ ...g, rec: g._a, des: g._b, yR: g.yA, hR: g.hA, yD: g.yB, hD: g.hB })))
const sinOper = computed(() => (data.value?.operacion ?? []).every((m: any) => !m.recepciones && !m.despachos))

// ── Modo Todos / Por empresas (barras apiladas por empresa) ──
const modoMov  = ref<'todos' | 'empresas'>('todos')
const modoOper = ref<'todos' | 'empresas'>('todos')

// Color estable por empresa (mismo color en ambos gráficos).
const PALETA = ['#2a78d6', '#1baf7a', '#eb6834', '#eda100', '#e34948', '#7c5cff', '#0ea5e9', '#84cc16', '#e879f9', '#14b8a6']
const colorMap = computed(() => {
  const set = new Map<number, string>()
  const empresas = new Set<number>()
  for (const r of (data.value?.movimientos ?? [])) for (const e of r.porEmpresa) empresas.add(e.empresa)
  for (const r of (data.value?.operacion ?? [])) for (const e of r.porEmpresa) empresas.add(e.empresa)
  ;[...empresas].sort((a, b) => a - b).forEach((cod, i) => set.set(cod, PALETA[i % PALETA.length]))
  return set
})
const colorEmpresa = (cod: number) => colorMap.value.get(cod) ?? '#94a3b8'

function empresasDe (rows: any[]) {
  const m = new Map<number, string>()
  for (const r of rows) for (const e of r.porEmpresa) if (!m.has(e.empresa)) m.set(e.empresa, e.nombre)
  return [...m.entries()].map(([empresa, nombre]) => ({ empresa, nombre, color: colorEmpresa(empresa) }))
}
const empresasMov  = computed(() => empresasDe(data.value?.movimientos ?? []))
const empresasOper = computed(() => empresasDe(data.value?.operacion ?? []))

// Barras apiladas: cada mes es una barra; cada segmento, una empresa.
function stacked (rows: any[]) {
  const totals = rows.map(r => r.porEmpresa.reduce((s: number, e: any) => s + e.total, 0))
  const max = Math.max(1, ...totals)
  const pad = 30, top = 18, bottom = 40
  const gw = rows.length ? (WW - pad) / rows.length : 0
  const bw = Math.max(6, Math.min(34, gw - 10))
  return rows.map((r, i) => {
    const x = pad / 2 + i * gw + (gw - bw) / 2
    let cursor = H - bottom
    const segs = r.porEmpresa.map((e: any) => {
      const hh = (e.total / max) * (H - top - bottom)
      cursor -= hh
      return { x, y: cursor, w: bw, h: hh, nombre: e.nombre, total: e.total, color: colorEmpresa(e.empresa) }
    })
    return { label: r.label, short: r.label.slice(0, 6), cx: x + bw / 2, segs }
  })
}
const stackedMov  = computed(() => stacked(data.value?.movimientos ?? []))
const stackedOper = computed(() => stacked(data.value?.operacion ?? []))

async function cargarEmpresas () {
  try { empresas.value = (await api.get('/tablero/wms/empresas')).data } catch { /* sigue global */ }
}

async function cargar () {
  cargando.value = true; error.value = ''
  try {
    const { data: d } = await api.get('/tablero/wms', { params: { empresa: empresa.value, fecha1: fecha1.value, fecha2: fecha2.value } })
    data.value = d
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'No se pudieron cargar los datos de Stock/Logística.'
  } finally { cargando.value = false }
}

onMounted(async () => { await cargarEmpresas(); await cargar() })
</script>

<style scoped>
.wms { padding: 1.2rem 1.4rem 2.5rem; color: #1e293b; }

/* Filtros */
.filtros { display: flex; align-items: flex-end; gap: 0.9rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
.f-grupo { display: flex; flex-direction: column; gap: 0.2rem; }
.f-grupo label { font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
.f-grupo select, .f-grupo input { padding: 0.4rem 0.55rem; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.86rem; background: #fff; color: #1e293b; }
.f-grupo select { min-width: 220px; }
.btn-refresh { padding: 0.45rem 0.9rem; background: #1b4332; color: #fff; border: none; border-radius: 7px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-refresh:hover { background: #14532d; }
.btn-refresh:disabled { opacity: 0.6; cursor: default; }
.alcance { margin-left: auto; font-size: 0.85rem; color: #1b4332; font-weight: 700; background: #f0faf4; border: 1px solid #c3e6cb; border-radius: 20px; padding: 0.3rem 0.8rem; }

.msg-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.7rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
.cargando { color: #64748b; padding: 2rem; text-align: center; }

/* KPIs */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.8rem; margin-bottom: 1.2rem; }
.kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.kpi-val { font-size: 1.5rem; font-weight: 800; color: #1b4332; }
.kpi-lbl { font-size: 0.76rem; color: #64748b; }
.kpi-alert .kpi-val { color: #dc2626; }

/* Grid de gráficos */
.grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.card.wide { grid-column: 1 / -1; }
.card-head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.6rem; flex-wrap: wrap; }
.card-head h3 { margin: 0; }
.toggle { display: inline-flex; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
.toggle button { border: none; background: #fff; color: #475569; font-size: 0.78rem; font-weight: 600; padding: 0.32rem 0.75rem; cursor: pointer; transition: background 0.15s; }
.toggle button:hover { background: #f0faf4; }
.toggle button.on { background: #1b4332; color: #fff; }
.ley-empresas { display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; margin-bottom: 0.5rem; }
.card h3 { margin: 0 0 0.7rem; font-size: 0.98rem; color: #1e293b; font-weight: 700; }
.card h3 small { color: #94a3b8; font-weight: 500; }
.vacio { color: #94a3b8; font-size: 0.85rem; padding: 1rem 0; text-align: center; }
.chart { width: 100%; height: auto; }
.ax-x { font-size: 9px; fill: #64748b; }
.ax-v { font-size: 9px; fill: #475569; font-weight: 600; }

/* Barras horizontales */
.hbars { display: flex; flex-direction: column; gap: 0.5rem; }
.hbar-row { display: grid; grid-template-columns: 130px 1fr 60px; align-items: center; gap: 0.5rem; }
.hbar-lbl { font-size: 0.78rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hbar-track { background: #f1f5f9; border-radius: 5px; height: 16px; overflow: hidden; }
.hbar-fill { height: 100%; border-radius: 5px; transition: width 0.3s; }
.hbar-val { font-size: 0.78rem; font-weight: 700; color: #1e293b; text-align: right; }

/* Leyenda */
:deep(.leyenda) { display: flex; gap: 1rem; margin-bottom: 0.4rem; }
:deep(.ley-item) { display: flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: #475569; }
:deep(.ley-dot) { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }

/* Alertas */
.alertas h3 { margin-bottom: 0.6rem; }
.chips { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.9rem; }
.chip { font-size: 0.82rem; padding: 0.3rem 0.7rem; border-radius: 20px; border: 1px solid; }
.chip-red { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.chip-amber { background: #fffbeb; border-color: #fde68a; color: #b45309; }
.chip-slate { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.alerta-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
.alerta-col h4 { margin: 0 0 0.4rem; font-size: 0.85rem; color: #334155; }
.alerta-col ul { list-style: none; margin: 0; padding: 0; max-height: 260px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.35rem; }
.alerta-col li { display: flex; flex-direction: column; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 7px; padding: 0.4rem 0.55rem; }
.a-emp { font-size: 0.72rem; color: #1b4332; font-weight: 700; }
.a-pn { font-size: 0.78rem; color: #334155; }
.a-fec { font-size: 0.72rem; color: #64748b; }
.a-fec.venc { color: #b91c1c; font-weight: 600; }
.a-fec.pv { color: #b45309; font-weight: 600; }

@media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
</style>
