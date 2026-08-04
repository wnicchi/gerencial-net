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
      <button class="btn-print" @click="imprimirPDF" :disabled="!data || cargando || generandoPdf" title="Ver PDF (previsualizar, descargar o imprimir)">{{ generandoPdf ? '⟳…' : '🖨 Imprimir PDF' }}</button>
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

        <!-- ── Torta: participación por empresa ── -->
        <section class="card">
          <h3>🥧 Participación en el stock por empresa <small>(posiciones)</small></h3>
          <div v-if="!torta.length" class="vacio">Sin stock.</div>
          <div v-else class="torta-wrap">
            <svg viewBox="0 0 180 180" class="torta-svg">
              <path v-for="(s, i) in torta" :key="i" :d="s.d" :fill="s.color" stroke="#fff" stroke-width="1.5">
                <title>{{ s.nombre }}: {{ nf(s.pos) }} pos ({{ s.pct.toFixed(1) }}%)</title>
              </path>
              <text v-for="(s, i) in tortaLabels" :key="'l' + i" :x="s.lx" :y="s.ly" text-anchor="middle" dominant-baseline="middle" class="torta-lbl">{{ s.pct.toFixed(0) }}%</text>
            </svg>
            <div class="torta-leg">
              <div v-for="(s, i) in torta" :key="i" class="torta-leg-item">
                <span class="torta-dot" :style="{ background: s.color }"></span>
                <span class="torta-nom" :title="s.nombre">{{ s.nombre }}</span>
                <span class="torta-val">{{ s.pct.toFixed(1) }}% · {{ nf(s.pos) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Torta: salud del inventario por estado ── -->
        <section class="card">
          <h3>🩺 Salud del inventario (por estado) <small>(posiciones)</small></h3>
          <div v-if="!tortaEstado.length" class="vacio">Sin datos.</div>
          <div v-else class="torta-wrap">
            <svg viewBox="0 0 180 180" class="torta-svg">
              <path v-for="(s, i) in tortaEstado" :key="i" :d="s.d" :fill="s.color" stroke="#fff" stroke-width="1.5">
                <title>{{ s.nombre }}: {{ nf(s.pos) }} pos ({{ s.pct.toFixed(1) }}%)</title>
              </path>
              <text v-for="(s, i) in tortaEstadoLabels" :key="'e' + i" :x="s.lx" :y="s.ly" text-anchor="middle" dominant-baseline="middle" class="torta-lbl">{{ s.pct.toFixed(0) }}%</text>
            </svg>
            <div class="torta-leg">
              <div v-for="(s, i) in tortaEstado" :key="i" class="torta-leg-item">
                <span class="torta-dot" :style="{ background: s.color }"></span>
                <span class="torta-nom" :title="s.nombre">{{ s.nombre }}</span>
                <span class="torta-val">{{ s.pct.toFixed(1) }}% · {{ nf(s.pos) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ── Ranking: empresas que más mueven (recepciones + despachos) ── -->
        <section class="card">
          <h3>🚚 Empresas que más mueven <small>(recepciones + despachos)</small></h3>
          <Leyenda :items="[['Recepciones', C.c1], ['Despachos', C.c2]]" />
          <div v-if="!actividadPorEmpresa.length" class="vacio">Sin operación en el período.</div>
          <div v-else class="hbars">
            <div v-for="e in actividadPorEmpresa" :key="e.empresa" class="hbar-row">
              <span class="hbar-lbl" :title="e.nombre">{{ e.nombre }}</span>
              <div class="hbar-track split">
                <div class="hbar-seg" :style="{ width: (e.recepciones / maxActividad * 100) + '%', background: C.c1 }" :title="'Recepciones: ' + nf(e.recepciones)"></div>
                <div class="hbar-seg" :style="{ width: (e.despachos / maxActividad * 100) + '%', background: C.c2 }" :title="'Despachos: ' + nf(e.despachos)"></div>
              </div>
              <span class="hbar-val">{{ nf(e.total) }}</span>
            </div>
          </div>
        </section>

        <!-- ── Ocupación por nave (distribución física) ── -->
        <section class="card">
          <h3>🏭 Ocupación por nave <small>(posiciones)</small></h3>
          <div class="ley-empresas" v-if="ocupacionNave.length">
            <span v-for="e in empresasNave" :key="e.empresa" class="ley-item"><span class="ley-dot" :style="{ background: e.color }"></span>{{ e.nombre }}</span>
          </div>
          <div v-if="!ocupacionNave.length" class="vacio">Sin datos.</div>
          <div v-else class="hbars">
            <div v-for="n in ocupacionNave" :key="n.nave" class="hbar-row">
              <span class="hbar-lbl" :title="n.nave">{{ n.nave }}</span>
              <div class="hbar-track split">
                <div v-for="(e, i) in n.porEmpresa" :key="i" class="hbar-seg" :style="{ width: (e.total / maxNave * 100) + '%', background: colorEmpresa(e.empresa) }" :title="e.nombre + ': ' + nf(e.total)"></div>
              </div>
              <span class="hbar-val">{{ nf(n.posiciones) }}</span>
            </div>
          </div>
        </section>

        <!-- ── Antigüedad del stock ── -->
        <section class="card">
          <h3>⏳ Antigüedad del stock <small>(clic en un rango para ver los productos)</small></h3>
          <div v-if="!antiguedad.length" class="vacio">Sin datos.</div>
          <div v-else class="hbars">
            <div v-for="a in antiguedad" :key="a.rango" class="hbar-row hbar-click" @click="abrirAntiguedad(a.rango)" :title="'Ver productos: ' + a.rango">
              <span class="hbar-lbl">{{ a.rango }}</span>
              <div class="hbar-track">
                <div class="hbar-fill" :style="{ width: pct(a.posiciones, maxAntiguedad) + '%', background: colorAntiguedad(a.rango) }"></div>
              </div>
              <span class="hbar-val">{{ nf(a.posiciones) }}</span>
            </div>
          </div>
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

        <!-- ── Línea: recepciones y despachos por semana ── -->
        <section class="card wide">
          <div class="card-head">
            <h3>📉 Recepciones y despachos por semana
              <small>{{ modoSemanal === 'total' ? '(recep. vs desp.)' : '(operación total por empresa)' }}</small>
            </h3>
            <div class="toggle">
              <button :class="{ on: modoSemanal === 'total' }" @click="modoSemanal = 'total'">Total</button>
              <button :class="{ on: modoSemanal === 'empresas' }" @click="modoSemanal = 'empresas'">Por empresas</button>
            </div>
          </div>
          <div class="ley-empresas">
            <span v-for="s in linea.built" :key="s.nombre" class="ley-item"><span class="ley-dot" :style="{ background: s.color }"></span>{{ s.nombre }}</span>
          </div>
          <div v-if="sinSemanal" class="vacio">Sin recepciones ni despachos en el período.</div>
          <svg v-else :viewBox="`0 0 ${WW} ${LH}`" class="chart">
            <line :x1="linea.padL" :y1="linea.baseY" :x2="WW - 12" :y2="linea.baseY" stroke="#e2e8f0" stroke-width="1" />
            <polyline v-for="s in linea.built" :key="s.nombre" :points="s.puntos" fill="none" :stroke="s.color" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
            <g v-for="s in linea.built" :key="'d' + s.nombre">
              <circle v-for="(d, i) in s.dots" :key="i" :cx="d.cx" :cy="d.cy" r="2.4" :fill="s.color"><title>{{ s.nombre }} · {{ nf(d.v) }}</title></circle>
            </g>
            <text v-for="(l, i) in linea.labels" v-show="l.show" :key="i" :x="l.x" :y="LH - 12" text-anchor="middle" class="ax-x">{{ l.label }}</text>
          </svg>
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

    <!-- Visor del PDF (previsualizar → descargar / imprimir) -->
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

    <!-- Detalle de antigüedad (drill-down: productos del rango) -->
    <Teleport to="body">
      <div v-if="drillOpen" class="es-pdf-ov" @click.self="cerrarDrill">
        <div class="es-pdf-md">
          <div class="es-pdf-head">
            <span>⏳ Antigüedad · {{ drillRango }} — {{ nf(drillTotal) }} posiciones</span>
            <div class="es-pdf-acc">
              <button class="es-pdf-b cancel" @click="cerrarDrill">✕ Cerrar</button>
            </div>
          </div>
          <div class="drill-body">
            <div v-if="drillCargando" class="cargando">Cargando…</div>
            <table v-else class="drill-tabla">
              <thead>
                <tr>
                  <th class="th-sort" @click="ordenar('empresa')">Empresa{{ flecha('empresa') }}</th>
                  <th class="th-sort" @click="ordenar('pn')">P.N.{{ flecha('pn') }}</th>
                  <th class="th-sort" @click="ordenar('des')">Descripción{{ flecha('des') }}</th>
                  <th class="th-sort r" @click="ordenar('unidades')">Unidades{{ flecha('unidades') }}</th>
                  <th class="th-sort r" @click="ordenar('posiciones')">Posic.{{ flecha('posiciones') }}</th>
                  <th class="th-sort" @click="ordenar('fecha')">Ingreso + viejo{{ flecha('fecha') }}</th>
                  <th class="th-sort r" @click="ordenar('dias')">Días{{ flecha('dias') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in drillRowsSorted" :key="i">
                  <td>{{ r.empresa }}</td>
                  <td>{{ r.pn }}</td>
                  <td>{{ r.des }}</td>
                  <td class="r">{{ nf(r.unidades) }}</td>
                  <td class="r">{{ nf(r.posiciones) }}</td>
                  <td>{{ fmtFecha(r.fecha) }}</td>
                  <td class="r">{{ r.dias ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
            <p v-if="!drillCargando && drillTotal > drillRows.length" class="drill-nota">Mostrando las primeras {{ nf(drillRows.length) }} de {{ nf(drillTotal) }} (ordenadas por unidades).</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, h } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { useAutoRefresh } from '@/composables/useAutoRefresh'

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
const W = 360, WW = 720, H = 220, LH = 240
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
  for (const e of (data.value?.stockPorEmpresa ?? [])) empresas.add(e.empresa)
  for (const e of (data.value?.actividadEmpresa ?? [])) empresas.add(e.empresa)
  for (const n of (data.value?.ocupacionNave ?? [])) for (const e of n.porEmpresa) empresas.add(e.empresa)
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

// ── Tortas (participación por empresa + salud del inventario por estado) ──
function pieSlices (items: any[], getVal: (x: any) => number, getColor: (x: any) => string, getNombre: (x: any) => string) {
  const arr = items.filter((e) => getVal(e) > 0)
  const total = arr.reduce((s, e) => s + getVal(e), 0)
  if (total <= 0) return []
  const cx = 90, cy = 90, R = 82
  let ang = -Math.PI / 2   // arranca arriba
  return arr.map((e) => {
    const val = getVal(e)
    const frac = val / total
    const a0 = ang, a1 = ang + frac * 2 * Math.PI
    ang = a1
    const mid = (a0 + a1) / 2
    const p = (r: number, a: number) => [(cx + r * Math.cos(a)).toFixed(2), (cy + r * Math.sin(a)).toFixed(2)]
    const [x0, y0] = p(R, a0); const [x1, y1] = p(R, a1); const [lx, ly] = p(R * 0.6, mid)
    const large = (a1 - a0) > Math.PI ? 1 : 0
    // Un solo grupo (100%) → círculo completo.
    const d = frac >= 0.999
      ? `M ${cx - R} ${cy} A ${R} ${R} 0 1 1 ${cx + R} ${cy} A ${R} ${R} 0 1 1 ${cx - R} ${cy} Z`
      : `M ${cx} ${cy} L ${x0} ${y0} A ${R} ${R} 0 ${large} 1 ${x1} ${y1} Z`
    return { d, color: getColor(e), nombre: getNombre(e), pos: val, pct: frac * 100, lx, ly, mostrar: frac >= 0.05 }
  })
}
const torta = computed(() => pieSlices(data.value?.stockPorEmpresa ?? [], (e) => e.posiciones, (e) => colorEmpresa(e.empresa), (e) => e.nombre))
const tortaLabels = computed(() => torta.value.filter((s: any) => s.mostrar))
const tortaEstado = computed(() => pieSlices(data.value?.stockPorEstado ?? [], (e) => e.posiciones, (e) => estadoColor(e.tip), (e) => e.estado))
const tortaEstadoLabels = computed(() => tortaEstado.value.filter((s: any) => s.mostrar))

// ── Ranking de empresas por actividad (recepciones + despachos), del backend
//    con el desglose para la barra dividida en dos colores. ──
const actividadPorEmpresa = computed<any[]>(() => data.value?.actividadEmpresa ?? [])
const maxActividad = computed(() => Math.max(1, ...actividadPorEmpresa.value.map((e: any) => e.total)))

// ── Ocupación por nave (barra apilada por empresa) ──
const ocupacionNave = computed<any[]>(() => data.value?.ocupacionNave ?? [])
const maxNave = computed(() => Math.max(1, ...ocupacionNave.value.map((n: any) => n.posiciones)))
const empresasNave = computed(() => empresasDe(ocupacionNave.value))

// ── Antigüedad del stock (barras con semáforo verde→rojo) ──
const antiguedad = computed<any[]>(() => data.value?.antiguedad ?? [])
const maxAntiguedad = computed(() => Math.max(1, ...antiguedad.value.map((a: any) => a.posiciones)))
const ANTIG_COLOR: Record<string, string> = {
  '0 a 30 días': C.c3, '31 a 90 días': C.c4, '91 a 180 días': C.c2, 'Más de 180 días': C.c8, 'Sin fecha': '#94a3b8',
}
const colorAntiguedad = (r: string) => ANTIG_COLOR[r] ?? '#94a3b8'

// ── Drill-down de antigüedad: qué productos hay en cada rango ──
const drillRango = ref(''); const drillRows = ref<any[]>([]); const drillTotal = ref(0); const drillCargando = ref(false)
const drillOpen = computed(() => drillRango.value !== '')
const cerrarDrill = () => { drillRango.value = ''; drillRows.value = [] }

// Orden de la tabla del modal. Por defecto: fecha de ingreso más vieja → más nueva.
const NUMERICAS = new Set(['unidades', 'posiciones', 'dias'])
const sortKey = ref('fecha'); const sortDir = ref<'asc' | 'desc'>('asc')
function ordenar (key: string) {
  if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = key; sortDir.value = NUMERICAS.has(key) ? 'desc' : 'asc' }
}
const flecha = (key: string) => sortKey.value === key ? (sortDir.value === 'asc' ? ' ▲' : ' ▼') : ''
const drillRowsSorted = computed(() => {
  const rows = [...drillRows.value]
  const k = sortKey.value, dir = sortDir.value === 'asc' ? 1 : -1
  rows.sort((a, b) => {
    let av = a[k], bv = b[k]
    if (k === 'dias') { av = av ?? -1; bv = bv ?? -1 }
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
    return String(av ?? '').localeCompare(String(bv ?? ''), 'es', { numeric: true }) * dir
  })
  return rows
})

async function abrirAntiguedad (rango: string) {
  drillRango.value = rango; drillRows.value = []; drillTotal.value = 0; drillCargando.value = true
  sortKey.value = 'fecha'; sortDir.value = 'asc'   // default: más viejo primero
  try {
    const { data: d } = await api.get('/tablero/wms/antiguedad-detalle', { params: { rango, empresa: empresa.value } })
    drillRows.value = d.rows; drillTotal.value = d.total
  } catch { drillRows.value = [] } finally { drillCargando.value = false }
}

// ── Gráfico de línea: recepciones/despachos por semana (Total o Por empresas) ──
const modoSemanal = ref<'total' | 'empresas'>('total')
const sinSemanal = computed(() => (data.value?.operacionSemanal ?? []).every((r: any) => !r.recepciones && !r.despachos))
const linea = computed(() => {
  const rows = data.value?.operacionSemanal ?? []
  const n = rows.length
  const padL = 34, padR = 12, padT = 16, padB = 34
  const plotW = WW - padL - padR, plotH = LH - padT - padB
  const X = (i: number) => padL + (n <= 1 ? plotW / 2 : (i / (n - 1)) * plotW)

  let series: { nombre: string; color: string; vals: number[] }[]
  if (modoSemanal.value === 'total') {
    series = [
      { nombre: 'Recepciones', color: C.c1, vals: rows.map((r: any) => r.recepciones) },
      { nombre: 'Despachos', color: C.c2, vals: rows.map((r: any) => r.despachos) },
    ]
  } else {
    const emp = new Map<number, string>()
    for (const r of rows) for (const e of r.porEmpresa) if (!emp.has(e.empresa)) emp.set(e.empresa, e.nombre)
    series = [...emp.entries()].map(([cod, nombre]) => ({
      nombre, color: colorEmpresa(cod),
      vals: rows.map((r: any) => (r.porEmpresa.find((e: any) => e.empresa === cod)?.total ?? 0)),
    }))
  }
  const max = Math.max(1, ...series.flatMap(s => s.vals))
  const Y = (v: number) => padT + plotH - (v / max) * plotH
  const built = series.map(s => ({
    nombre: s.nombre, color: s.color,
    puntos: s.vals.map((v, i) => `${X(i).toFixed(1)},${Y(v).toFixed(1)}`).join(' '),
    dots: s.vals.map((v, i) => ({ cx: +X(i).toFixed(1), cy: +Y(v).toFixed(1), v })),
  }))
  const paso = Math.max(1, Math.ceil(n / 10))
  const labels = rows.map((r: any, i: number) => ({ x: X(i), label: r.label, show: i % paso === 0 || i === n - 1 }))
  return { built, labels, baseY: padT + plotH, padL }
})

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

// ── Imprimir PDF (previsualizar en modal → descargar / imprimir) ──
const fechaHoy = new Date().toLocaleDateString('es-AR')
const generandoPdf = ref(false)
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

// Quita emojis (la fuente del PDF no los dibuja).
const sinEmoji = (s: string) => s.replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2190}-\u{21FF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}]/gu, '').trim()

// Estilos embebidos al rasterizar el SVG (el CSS scoped no viaja). Los rellenos de
// las barras son atributos `fill` inline, así que solo hace falta el estilo de los textos.
const SVG_CSS = `
  text{font-family:system-ui,-apple-system,'Segoe UI',sans-serif}
  .ax-x{fill:#64748b;font-size:9px}.ax-v{fill:#475569;font-size:9px;font-weight:600}
  .torta-lbl{fill:#fff;font-size:10px;font-weight:700}
`
function svgAPng (svg: SVGSVGElement, escala = 2): Promise<{ url: string; w: number; h: number }> {
  const vb = (svg.getAttribute('viewBox') || '0 0 720 220').split(/\s+/).map(Number)
  const w = vb[2] || 720, h = vb[3] || 220
  const clon = svg.cloneNode(true) as SVGSVGElement
  clon.setAttribute('xmlns', 'http://www.w3.org/2000/svg')
  clon.setAttribute('width', String(w)); clon.setAttribute('height', String(h))
  const st = document.createElementNS('http://www.w3.org/2000/svg', 'style'); st.textContent = SVG_CSS
  clon.insertBefore(st, clon.firstChild)
  const xml = new XMLSerializer().serializeToString(clon)
  const url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml)
  return new Promise((res) => {
    const img = new Image()
    img.onload = () => {
      const cv = document.createElement('canvas'); cv.width = w * escala; cv.height = h * escala
      const ctx = cv.getContext('2d')!; ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, cv.width, cv.height)
      ctx.drawImage(img, 0, 0, cv.width, cv.height)
      res({ url: cv.toDataURL('image/png'), w, h })
    }
    img.onerror = () => res({ url: '', w, h })
    img.src = url
  })
}

async function imprimirPDF () {
  if (!data.value) return
  generandoPdf.value = true
  try {
    await nextTick()
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const PW = 210, PH = 297, ML = 12, W = PW - ML * 2
    let y = 16
    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
    doc.text('Tablero Gerencial — Stock / Logística (WMS)', ML, y); y += 6
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(0, 0, 0)
    doc.text(`${data.value.empresaNombre}   ·   Período: ${fmtFecha(fecha1.value)} al ${fmtFecha(fecha2.value)}   ·   Impreso: ${fechaHoy}`, ML, y); y += 5
    doc.setDrawColor(27, 67, 50); doc.setLineWidth(0.4); doc.line(ML, y, PW - ML, y); y += 8

    // KPIs en una fila
    const k = data.value.kpis
    const kpis: [string, string][] = [
      ['Posiciones', nf(k.posiciones)], ['Unidades', nf(k.unidades)], ['Productos', nf(k.productos)],
      ['Empresas c/stock', nf(k.empresas)], ['Bloqueadas', nf(k.bloqueadas)],
    ]
    const kw = W / kpis.length
    kpis.forEach((kp, i) => {
      const x = ML + i * kw
      doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(27, 67, 50); doc.text(kp[1], x, y)
      doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(100, 116, 139); doc.text(kp[0], x, y + 4)
    })
    y += 11
    doc.setDrawColor(220); doc.setLineWidth(0.2); doc.line(ML, y, PW - ML, y); y += 7

    // Gráficos SVG que estén a la vista (estado, movimientos, operación — en su modo actual)
    const cards = Array.from(document.querySelectorAll('.wms .grid .card')) as HTMLElement[]
    for (const card of cards) {
      const svg = card.querySelector('svg') as SVGSVGElement | null
      if (!svg) continue   // la tarjeta de "Stock por empresa" (barras HTML) va como tabla
      const titulo = sinEmoji(card.querySelector('h3')?.textContent || '')
      const { url, w, h } = await svgAPng(svg)
      if (!url) continue
      let imgW = W, imgH = imgW * (h / w)
      const maxH = 95   // los gráficos cuadrados (torta) no deben ocupar toda la hoja
      if (imgH > maxH) { imgH = maxH; imgW = imgH * (w / h) }
      const imgX = ML + (W - imgW) / 2
      if (y + 8 + imgH > PH - 14) { doc.addPage(); y = 16 }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(30, 41, 59); doc.text(titulo, ML, y); y += 4
      doc.addImage(url, 'PNG', imgX, y, imgW, imgH); y += imgH + 8
    }

    // Tablas de datos
    const tabla = (titulo: string, head: string[], rows: (string | number)[][], anchos: number[]) => {
      if (!rows.length) return
      if (y > PH - 24) { doc.addPage(); y = 16 }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50); doc.text(titulo, ML, y); y += 5
      doc.setFontSize(8); doc.setTextColor(0, 0, 0)
      const xs: number[] = []; let acc = ML; for (const a of anchos) { xs.push(acc); acc += a }
      doc.setFont('helvetica', 'bold'); head.forEach((c, i) => doc.text(String(c), xs[i] ?? ML, y)); y += 1.5
      doc.setDrawColor(180); doc.line(ML, y, PW - ML, y); y += 4
      doc.setFont('helvetica', 'normal')
      for (const r of rows) {
        if (y > PH - 14) { doc.addPage(); y = 16 }
        r.forEach((c, i) => doc.text(doc.splitTextToSize(String(c ?? ''), (anchos[i] ?? 30) - 2)[0] || '', xs[i] ?? ML, y)); y += 4.4
      }
      y += 6
    }
    tabla('Stock por empresa', ['Empresa', 'Posiciones', 'Unidades'],
      data.value.stockPorEmpresa.map((e: any) => [e.nombre, nf(e.posiciones), nf(e.unidades)]), [110, 38, 38])
    tabla('Stock por estado', ['Estado', 'Posiciones', 'Unidades'],
      data.value.stockPorEstado.map((e: any) => [e.estado, nf(e.posiciones), nf(e.unidades)]), [110, 38, 38])
    tabla('Empresas que más mueven', ['Empresa', 'Recep.', 'Desp.', 'Total'],
      actividadPorEmpresa.value.map((e: any) => [e.nombre, nf(e.recepciones), nf(e.despachos), nf(e.total)]), [104, 28, 28, 26])
    tabla('Ocupación por nave', ['Nave', 'Posiciones', 'Unidades'],
      ocupacionNave.value.map((n: any) => [n.nave, nf(n.posiciones), nf(n.unidades)]), [110, 38, 38])
    tabla('Antigüedad del stock', ['Antigüedad', 'Posiciones', 'Unidades'],
      antiguedad.value.map((a: any) => [a.rango, nf(a.posiciones), nf(a.unidades)]), [110, 38, 38])
    tabla('Alertas — Productos vencidos', ['Empresa', 'P.N.', 'Descripción', 'Vence', 'Unid.'],
      data.value.alertas.vencidos.map((v: any) => [v.empresa, v.pn, v.des, fmtFecha(v.vence), nf(v.unidades)]), [42, 26, 62, 26, 20])
    tabla('Alertas — Próximos a vencer', ['Empresa', 'P.N.', 'Descripción', 'Vence', 'Unid.'],
      data.value.alertas.porVencer.map((v: any) => [v.empresa, v.pn, v.des, fmtFecha(v.vence), nf(v.unidades)]), [42, 26, 62, 26, 20])
    tabla('Alertas — Bloqueadas por empresa', ['Empresa', 'Posiciones', 'Unidades'],
      data.value.alertas.bloqueados.map((b: any) => [b.empresa, nf(b.posiciones), nf(b.unidades)]), [110, 38, 38])

    cerrarPdf()
    const emp = data.value.empresa ? `EMP${data.value.empresa}` : 'GLOBAL'
    pdfNombre.value = `TABLERO_WMS_${emp}_${fecha1.value}_${fecha2.value}.pdf`
    pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (e) { console.error(e); error.value = 'No se pudo generar el PDF.' }
  finally { generandoPdf.value = false }
}

onMounted(async () => { await cargarEmpresas(); await cargar() })
useAutoRefresh(cargar)   // tablero en tiempo real: recarga cada 5 min
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
.btn-print { padding: 0.45rem 0.9rem; background: #fff; color: #1b4332; border: 1px solid #1b4332; border-radius: 7px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.btn-print:hover { background: #f0faf4; }
.btn-print:disabled { opacity: 0.5; cursor: default; }
.alcance { margin-left: auto; font-size: 0.85rem; color: #1b4332; font-weight: 700; background: #f0faf4; border: 1px solid #c3e6cb; border-radius: 20px; padding: 0.3rem 0.8rem; }

/* Modal visor del PDF (previsualizar / descargar / imprimir) */
.es-pdf-ov { position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 18px; }
.es-pdf-md { width: min(1000px, 98vw); height: 92vh; background: #fff; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(0,0,0,.5); }
.es-pdf-head { display: flex; align-items: center; gap: 14px; padding: 10px 14px; background: #1b4332; color: #fff; font-size: 13px; flex-wrap: wrap; }
.es-pdf-acc { margin-left: auto; display: flex; gap: 8px; }
.es-pdf-b { border: none; padding: 7px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
.es-pdf-b.ok { background: #22c55e; color: #fff; }
.es-pdf-b.cancel { background: #ef4444; color: #fff; }
.es-pdf-frame { flex: 1; border: none; width: 100%; }

/* Drill-down de antigüedad */
.hbar-click { cursor: pointer; border-radius: 6px; transition: background 0.15s; }
.hbar-click:hover { background: #f1f5f9; }
.drill-body { flex: 1; overflow: auto; background: #fff; }
.drill-tabla { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.drill-tabla th { position: sticky; top: 0; background: #f0faf4; color: #1b4332; text-align: left; padding: 0.5rem 0.7rem; border-bottom: 1px solid #c3e6cb; font-weight: 700; white-space: nowrap; }
.drill-tabla th.th-sort { cursor: pointer; user-select: none; }
.drill-tabla th.th-sort:hover { background: #e2f5ea; }
.drill-tabla td { padding: 0.4rem 0.7rem; border-bottom: 1px solid #eef2f7; color: #334155; }
.drill-tabla tr:hover td { background: #f8fafc; }
.drill-tabla .r { text-align: right; }
.drill-nota { padding: 0.7rem; color: #94a3b8; font-size: 0.78rem; text-align: center; margin: 0; }

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
.hbar-track.split { display: flex; }
.hbar-seg { height: 100%; transition: width 0.3s; }
.hbar-val { font-size: 0.78rem; font-weight: 700; color: #1e293b; text-align: right; }

/* Torta de participación por empresa */
.torta-wrap { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
.torta-svg { width: 170px; height: 170px; flex-shrink: 0; }
.torta-lbl { font-size: 10px; fill: #fff; font-weight: 700; paint-order: stroke; stroke: rgba(0,0,0,0.25); stroke-width: 2px; }
.torta-leg { display: flex; flex-direction: column; gap: 0.4rem; min-width: 180px; flex: 1; }
.torta-leg-item { display: grid; grid-template-columns: 12px 1fr auto; align-items: center; gap: 0.5rem; font-size: 0.8rem; }
.torta-dot { width: 12px; height: 12px; border-radius: 3px; }
.torta-nom { color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.torta-val { color: #1e293b; font-weight: 600; white-space: nowrap; }

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
