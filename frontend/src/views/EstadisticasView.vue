<!--
  EstadisticasView.vue — Tablero Gerencial / Estadísticas de RRHH.
  Filtros comunes (período + empresa/contratista/convenio/sector/categoría/lugar) y
  cinco gráficos SVG: sueldos por mes, composición de la masa salarial, dotación,
  horas extras y ausentismo. Paleta categórica validada (dataviz), tema claro.
-->
<template>
  <div class="es-view">
    <div class="es-cab">
      <div class="es-ico">📊</div>
      <div class="es-tx"><h1>Tablero Gerencial</h1><p>Estadísticas de RRHH para la toma de decisiones</p></div>
      <button class="es-pdf" :disabled="!datos || generandoPdf" @click="imprimirPDF">{{ generandoPdf ? '⟳…' : '🖨 Imprimir PDF' }}</button>
      <button class="es-exp" :disabled="!datos" @click="exportarExcel">📊 Exportar Excel</button>
    </div>

    <!-- Filtros -->
    <div class="es-filtros" v-enter-next>
      <div class="es-f"><span>Desde</span><input v-model="f.fecha1" type="date" @change="ajustarRango('desde')" /></div>
      <div class="es-f"><span>Hasta</span><input v-model="f.fecha2" type="date" @change="ajustarRango('hasta')" /></div>
      <div class="es-f"><span>Empresa</span><select v-model.number="f.empresa" @change="cargar"><option :value="0">Todas</option><option v-for="o in op.empresas" :key="o.EMP_COD" :value="Number(o.EMP_COD)">{{ o.EMP_NOM }}</option></select></div>
      <div class="es-f"><span>Contratista</span><select v-model.number="f.contratista" @change="cargar"><option :value="0">Todos</option><option v-for="o in op.contratistas" :key="o.CONT_COD" :value="Number(o.CONT_COD)">{{ o.CONT_DET }}</option></select></div>
      <div class="es-f"><span>Convenio</span><select v-model.number="f.convenio" @change="cargar"><option :value="0">Todos</option><option v-for="o in op.convenios" :key="o.CON_COD" :value="Number(o.CON_COD)">{{ o.CON_DES }}</option></select></div>
      <div class="es-f"><span>Sector</span><select v-model.number="f.sector" @change="cargar"><option :value="0">Todos</option><option v-for="o in op.sectores" :key="o.SEC_COD" :value="Number(o.SEC_COD)">{{ o.SEC_DES }}</option></select></div>
      <div class="es-f"><span>Categoría</span><select v-model.number="f.categoria" @change="cargar"><option :value="0">Todas</option><option v-for="o in op.categorias" :key="o.CAT_COD" :value="Number(o.CAT_COD)">{{ o.CAT_DES }}</option></select></div>
      <div class="es-f"><span>Lugar</span><select v-model.number="f.lugar" @change="cargar"><option :value="0">Todos</option><option v-for="o in op.lugares" :key="o.LUG_COD" :value="Number(o.LUG_COD)">{{ o.LUG_NOM }}</option></select></div>
    </div>

    <div v-if="cargando" class="es-info">⟳ Calculando…</div>
    <div v-else-if="msg" class="es-info err">{{ msg }}</div>

    <template v-else-if="datos">
      <!-- KPIs -->
      <div class="es-kpis">
        <div class="es-kpi"><span class="k-num">{{ moneyM(datos.kpis.neto) }}</span><span class="k-lbl">Neto pagado</span></div>
        <div class="es-kpi"><span class="k-num">{{ moneyM(datos.kpis.haberes) }}</span><span class="k-lbl">Haberes</span></div>
        <div class="es-kpi"><span class="k-num">{{ moneyM(datos.kpis.deducciones) }}</span><span class="k-lbl">Deducciones</span></div>
        <div class="es-kpi"><span class="k-num">{{ datos.kpis.empleados }}</span><span class="k-lbl">Empleados liquidados</span></div>
        <div class="es-kpi"><span class="k-num">{{ moneyM(datos.kpis.promedio) }}</span><span class="k-lbl">Neto promedio</span></div>
      </div>

      <div class="es-grid" ref="gridRef">
        <!-- 1) Sueldos netos por mes -->
        <div class="es-card wide">
          <div class="es-card-h">
            <h3>💰 Sueldos netos pagados por mes</h3>
            <div class="es-card-h-acc">
              <select v-model.number="f.tipoSueldo" class="es-mini" @change="cargar">
                <option :value="0">Todos los tipos</option>
                <option v-for="t in tiposSueldo" :key="t.tip" :value="t.tip">{{ t.label }}</option>
              </select>
              <button class="es-ojo" title="Ver el desglose detallado" @click="abrirDetalleSueldos">👁️</button>
            </div>
          </div>
          <svg :viewBox="`0 0 ${WW} ${HW}`" class="es-svg" @mouseleave="tip = null">
            <line v-for="t in gSueldos.ticks" :key="'gs'+t.v" :x1="PL" :x2="WW-PR" :y1="t.y" :y2="t.y" class="grid" />
            <text v-for="t in gSueldos.ticks" :key="'ts'+t.v" :x="PL-6" :y="t.y+3" class="ejeY">{{ t.lbl }}</text>
            <template v-for="b in gSueldos.bars" :key="'bs'+b.i">
              <rect :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="3" class="barra c1"
                    @mousemove="ponerTip($event, `${b.label}: ${money(b.val)}`)" />
              <text :x="b.x + b.w/2" :y="HW-PB+14" class="ejeX">{{ b.label }}</text>
            </template>
          </svg>
        </div>

        <!-- 2) Composición de la masa salarial -->
        <div class="es-card">
          <div class="es-card-h">
            <h3>🥧 Composición de la masa salarial</h3>
            <div class="es-card-h-acc">
              <select v-model="f.agrupar" class="es-mini" @change="recargarComposicion">
                <option value="convenio">por Convenio</option><option value="sector">por Sector</option>
                <option value="categoria">por Categoría</option><option value="contratista">por Contratista</option>
                <option value="lugar">por Lugar</option>
              </select>
              <button class="es-ojo" title="Ver el desglose detallado" @click="abrirDesglose('composicion')">👁️</button>
            </div>
          </div>
          <svg :viewBox="`0 0 ${W} ${Hh(gComp.n)}`" class="es-svg" @mouseleave="tip = null">
            <template v-for="b in gComp.bars" :key="'bc'+b.i">
              <rect :x="gComp.x0" :y="b.y" :width="b.w" :height="b.h" rx="3" class="barra c3"
                    @mousemove="ponerTip($event, `${b.label}: ${money(b.val)} (${b.emp} emp)`)" />
              <text :x="gComp.x0-6" :y="b.y + b.h/2 + 3" class="ejeYh">{{ b.label }}</text>
              <text :x="gComp.x0 + b.w + 5" :y="b.y + b.h/2 + 3" class="valH">{{ pct(b.val) }}</text>
            </template>
          </svg>
        </div>

        <!-- 3) Dotación: activos + altas/bajas -->
        <div class="es-card">
          <div class="es-card-h"><h3>📈 Dotación por mes</h3><button class="es-ojo" title="Ver el desglose detallado" @click="abrirDesglose('dotacion')">👁️</button></div>
          <svg :viewBox="`0 0 ${W} ${H}`" class="es-svg" @mouseleave="tip = null">
            <line v-for="t in gDota.ticks" :key="'gd'+t.v" :x1="PL" :x2="W-PR" :y1="t.y" :y2="t.y" class="grid" />
            <text v-for="t in gDota.ticks" :key="'td'+t.v" :x="PL-6" :y="t.y+3" class="ejeY">{{ t.v }}</text>
            <template v-for="b in gDota.bars" :key="'bd'+b.i">
              <rect :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="3" class="barra c1"
                    @mousemove="ponerTip($event, `${b.label}: ${b.val} activos · +${b.altas} / -${b.bajas}`)" />
              <text :x="b.x + b.w/2" :y="H-PB+14" class="ejeX">{{ b.label }}</text>
            </template>
          </svg>
          <div class="es-leg"><span class="lg c1"></span>Empleados activos (al cierre del mes)</div>
        </div>

        <!-- 4) Horas extras por mes (apiladas por tipo) -->
        <div class="es-card">
          <div class="es-card-h"><h3>⏱️ Horas extras por mes</h3><button class="es-ojo" title="Ver el desglose detallado" @click="abrirDesglose('horasExtras')">👁️</button></div>
          <svg :viewBox="`0 0 ${W} ${H}`" class="es-svg" @mouseleave="tip = null">
            <line v-for="t in gHE.ticks" :key="'gh'+t.v" :x1="PL" :x2="W-PR" :y1="t.y" :y2="t.y" class="grid" />
            <text v-for="t in gHE.ticks" :key="'th'+t.v" :x="PL-6" :y="t.y+3" class="ejeY">{{ t.v }}</text>
            <template v-for="col in gHE.cols" :key="'hc'+col.i">
              <rect v-for="seg in col.segs" :key="seg.k" :x="col.x" :y="seg.y" :width="col.w" :height="seg.h" :class="['barra', seg.cls]"
                    @mousemove="ponerTip($event, `${col.label} · ${seg.nom}: ${seg.val} hs`)" />
              <text :x="col.x + col.w/2" :y="H-PB+14" class="ejeX">{{ col.label }}</text>
            </template>
          </svg>
          <div class="es-leg">
            <span class="lg c1"></span>50% <span class="lg c2"></span>100% <span class="lg c4"></span>Nocturnas
            <span class="es-leg-costo">Costo período: {{ money(costoHE) }}</span>
          </div>
        </div>

        <!-- 5) Ausentismo por tipo -->
        <div class="es-card">
          <div class="es-card-h"><h3>🤒 Ausentismo por tipo de licencia</h3><div class="es-card-h-acc"><span class="es-sub">{{ datos.ausentismo.totalDias }} días</span><button class="es-ojo" title="Ver el desglose detallado" @click="abrirDesglose('ausentismo')">👁️</button></div></div>
          <svg :viewBox="`0 0 ${W} ${Hh(gAus.n)}`" class="es-svg" @mouseleave="tip = null">
            <template v-for="b in gAus.bars" :key="'ba'+b.i">
              <rect :x="gAus.x0" :y="b.y" :width="b.w" :height="b.h" rx="3" class="barra c8"
                    @mousemove="ponerTip($event, `${b.label}: ${b.val} días`)" />
              <text :x="gAus.x0-6" :y="b.y + b.h/2 + 3" class="ejeYh">{{ b.label }}</text>
              <text :x="gAus.x0 + b.w + 5" :y="b.y + b.h/2 + 3" class="valH">{{ b.val }}</text>
            </template>
          </svg>
        </div>

        <!-- 7) Liquidaciones finales por mes -->
        <div class="es-card">
          <div class="es-card-h"><h3>📄 Liquidaciones finales por mes</h3><div class="es-card-h-acc"><span class="es-sub">{{ datos.liqFinales.empleados }} bajas · {{ moneyM(datos.liqFinales.total) }}</span><button class="es-ojo" title="Ver el desglose detallado" @click="abrirDesglose('liqFinales')">👁️</button></div></div>
          <svg :viewBox="`0 0 ${W} ${H}`" class="es-svg" @mouseleave="tip = null">
            <line v-for="t in gLiqFin.ticks" :key="'gl'+t.v" :x1="PL" :x2="W-PR" :y1="t.y" :y2="t.y" class="grid" />
            <text v-for="t in gLiqFin.ticks" :key="'tl'+t.v" :x="PL-6" :y="t.y+3" class="ejeY">{{ t.lbl }}</text>
            <template v-for="b in gLiqFin.bars" :key="'bl'+b.i">
              <rect :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="3" class="barra c3"
                    @mousemove="ponerTip($event, `${b.label}: ${money(b.val)} (${b.emp} baja${b.emp===1?'':'s'})`)" />
              <text :x="b.x + b.w/2" :y="H-PB+14" class="ejeX">{{ b.label }}</text>
            </template>
          </svg>
        </div>

        <!-- 6) Empleados con más faltas -->
        <div class="es-card">
          <div class="es-card-h"><h3>🏅 Empleados con más faltas</h3><button class="es-ojo" title="Ver el desglose detallado" @click="abrirDesglose('faltasEmp')">👁️</button></div>
          <svg :viewBox="`0 0 ${W} ${Hh(gFaltasEmp.n)}`" class="es-svg" @mouseleave="tip = null">
            <template v-for="b in gFaltasEmp.bars" :key="'fe'+b.i">
              <rect :x="gFaltasEmp.x0" :y="b.y" :width="b.w" :height="b.h" rx="3" class="barra c2"
                    @mousemove="ponerTip($event, `${b.label}: ${b.val} días`)" />
              <text :x="gFaltasEmp.x0-6" :y="b.y + b.h/2 + 3" class="ejeYh">{{ b.label }}</text>
              <text :x="gFaltasEmp.x0 + b.w + 5" :y="b.y + b.h/2 + 3" class="valH">{{ b.val }}</text>
            </template>
          </svg>
          <p v-if="!gFaltasEmp.n" class="es-vacio">Sin faltas registradas en el período.</p>
        </div>
      </div>
    </template>

    <!-- Modal de desglose de sueldos (ojito) -->
    <Teleport to="body">
      <div v-if="detalle.abierto" class="es-det-ov" @click.self="detalle.abierto = false">
        <div class="es-det-md">
          <div class="es-det-head">
            <span>💰 Sueldos por mes — {{ fmtFecha(f.fecha1) }} al {{ fmtFecha(f.fecha2) }}</span>
            <button class="es-x" @click="detalle.abierto = false">✕</button>
          </div>

          <!-- NIVEL 1: resumen por mes -->
          <template v-if="!detalle.mesSel">
            <div class="es-det-tools">
              <span class="es-det-cont">👆 Tocá un mes para ver el detalle de sus liquidaciones</span>
              <span style="flex:1"></span>
              <button class="es-exp" :disabled="!resumenMeses.length" @click="exportarDetalle">📊 Excel</button>
            </div>
            <div class="es-det-wrap">
              <table class="es-det-tabla">
                <thead><tr><th>Mes</th><th class="r">Empleados</th><th class="r">Neto pagado</th><th></th></tr></thead>
                <tbody>
                  <tr v-if="detalle.cargando"><td colspan="4" class="es-det-vacio">⟳ Cargando…</td></tr>
                  <tr v-else-if="!resumenMeses.length"><td colspan="4" class="es-det-vacio">Sin datos.</td></tr>
                  <tr v-for="m in resumenMeses" :key="m.clave" class="es-mes-row" @click="abrirMes(m.clave)">
                    <td class="b">{{ m.label }}</td>
                    <td class="r">{{ m.empleados }}</td>
                    <td class="r b">{{ money(m.neto) }}</td>
                    <td class="r"><span class="es-ver-mes">ver ›</span></td>
                  </tr>
                </tbody>
                <tfoot v-if="resumenMeses.length"><tr><td class="b">TOTAL</td><td></td><td class="r b">{{ money(totalResumen) }}</td><td></td></tr></tfoot>
              </table>
            </div>
          </template>

          <!-- NIVEL 2: detalle de un mes -->
          <template v-else>
            <div class="es-det-tools">
              <button class="es-volver" @click="volverResumen">‹ Volver a meses</button>
              <strong class="es-mes-tit">{{ mesSelLabel }}</strong>
              <input v-model="detalle.buscar" class="es-det-buscar" placeholder="🔍 Empleado, legajo o tipo…" />
              <span class="es-det-cont">{{ detalleVista.length }} liquidaciones</span>
              <span v-if="detalle.truncado" class="es-det-trunc">⚠️ Datos parciales ({{ detalle.tope }} máx.)</span>
              <span style="flex:1"></span>
              <button class="es-exp" :disabled="!detalleVista.length" @click="exportarDetalle">📊 Excel</button>
            </div>
            <div class="es-det-wrap">
              <table class="es-det-tabla">
                <thead><tr><th>Fecha</th><th>Legajo</th><th>Empleado</th><th>Tipo</th><th class="r">Haberes</th><th class="r">Deducciones</th><th class="r">Neto</th></tr></thead>
                <tbody>
                  <tr v-if="!detalleVista.length"><td colspan="7" class="es-det-vacio">Sin datos.</td></tr>
                  <tr v-for="(r, i) in detalleVista" :key="i">
                    <td>{{ fmtFecha(r.fecha) }}</td><td>{{ r.legajo }}</td><td>{{ r.nombre }}</td><td>{{ r.tipo }}</td>
                    <td class="r">{{ money(r.haberes) }}</td><td class="r">{{ money(r.deducciones) }}</td><td class="r b">{{ money(r.neto) }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="detalleVista.length"><tr><td colspan="6" class="r b">TOTAL NETO</td><td class="r b">{{ money(totalDetalle) }}</td></tr></tfoot>
              </table>
            </div>
          </template>
        </div>
      </div>

      <!-- Desglose genérico del resto de gráficos -->
      <div v-if="desg.abierto" class="es-det-ov" @click.self="desg.abierto = false">
        <div class="es-det-md">
          <div class="es-det-head">
            <span>{{ desg.icono }} {{ desg.titulo }}<template v-if="desg.nivel === 'sub'"> — {{ desg.sub.titulo }}</template></span>
            <button class="es-x" @click="desg.abierto = false">✕</button>
          </div>

          <!-- Nivel 1: desglose del gráfico (filas clickeables) -->
          <template v-if="desg.nivel === 'grupos'">
            <div class="es-det-tools">
              <span class="es-det-cont">{{ desg.rows.length }} filas · {{ fmtFecha(f.fecha1) }} al {{ fmtFecha(f.fecha2) }} · 👆 tocá una fila para ver el detalle</span>
              <span style="flex:1"></span>
              <button class="es-exp" :disabled="!desg.rows.length" @click="exportarDesglose">📊 Excel</button>
            </div>
            <div class="es-det-wrap">
              <table class="es-det-tabla">
                <thead><tr><th v-for="c in desg.cols" :key="c.key" :class="{ r: c.r }">{{ c.label }}</th><th></th></tr></thead>
                <tbody>
                  <tr v-if="!desg.rows.length"><td :colspan="desg.cols.length + 1" class="es-det-vacio">Sin datos.</td></tr>
                  <tr v-for="(r, i) in desg.rows" :key="i" class="es-mes-row" @click="abrirSub(r)">
                    <td v-for="c in desg.cols" :key="c.key" :class="{ r: c.r, b: c.money }">{{ celda(r, c) }}</td>
                    <td class="r"><span class="es-ver-mes">ver ›</span></td>
                  </tr>
                </tbody>
                <tfoot v-if="desg.footer"><tr><td v-for="(cell, j) in desg.footer" :key="j" :class="{ r: desg.cols[j]?.r, b: true }">{{ cell }}</td><td></td></tr></tfoot>
              </table>
            </div>
          </template>

          <!-- Nivel 2: detalle (empleados / movimientos / tipos) -->
          <template v-else>
            <div class="es-det-tools">
              <button class="es-volver" @click="volverGrupos">‹ Volver</button>
              <strong class="es-mes-tit">{{ desg.sub.titulo }}</strong>
              <span class="es-det-cont">{{ desg.sub.rows.length }} filas</span>
              <span style="flex:1"></span>
              <button class="es-exp" :disabled="!desg.sub.rows.length" @click="exportarSub">📊 Excel</button>
            </div>
            <div class="es-det-wrap">
              <table class="es-det-tabla">
                <thead><tr><th v-for="c in desg.sub.cols" :key="c.key" :class="{ r: c.r }">{{ c.label }}</th></tr></thead>
                <tbody>
                  <tr v-if="desg.sub.cargando"><td :colspan="desg.sub.cols.length" class="es-det-vacio">⟳ Cargando…</td></tr>
                  <tr v-else-if="!desg.sub.rows.length"><td :colspan="desg.sub.cols.length" class="es-det-vacio">Sin datos.</td></tr>
                  <tr v-for="(r, i) in desg.sub.rows" :key="i">
                    <td v-for="c in desg.sub.cols" :key="c.key" :class="{ r: c.r, b: c.money }">{{ celda(r, c) }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="desg.sub.footer"><tr><td v-for="(cell, j) in desg.sub.footer" :key="j" :class="{ r: desg.sub.cols[j]?.r, b: true }">{{ cell }}</td></tr></tfoot>
              </table>
            </div>
          </template>
        </div>
      </div>
    </Teleport>

    <!-- Tooltip -->
    <div v-if="tip" class="es-tip" :style="{ left: tip.x + 'px', top: tip.y + 'px' }">{{ tip.text }}</div>

    <!-- Visor del PDF -->
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
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

// ── Geometría base de los gráficos ──
const W = 560, H = 260, PL = 48, PR = 16, PT = 14, PB = 26
// Gráfico ancho (sueldos, ocupa toda la fila): aspecto panorámico para que no quede tan alto.
const WW = 1120, HW = 300
const Hh = (n: number) => Math.max(120, PT + n * 26 + 10)   // alto de los horizontales según cantidad

const op = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], sectores: [] })
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date()
const f = reactive({
  fecha1: _iso(new Date(hoy.getFullYear(), 0, 1)), fecha2: _iso(hoy),
  empresa: 0, contratista: 0, convenio: 0, sector: 0, categoria: 0, lugar: 0, tipoSueldo: 0, agrupar: 'convenio',
})
const tiposSueldo = ref<{ tip: number; label: string }[]>([])

const datos = ref<any>(null); const cargando = ref(false); const msg = ref('')
const tip = ref<{ x: number; y: number; text: string } | null>(null)
const ponerTip = (e: MouseEvent, text: string) => { tip.value = { x: e.clientX + 12, y: e.clientY - 10, text } }

const nf0 = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 })
const nf1 = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 1 })
const money = (v: number) => '$ ' + nf0.format(v)
const moneyM = (v: number) => Math.abs(v) >= 1e6 ? '$ ' + nf1.format(v / 1e6) + ' M' : '$ ' + nf0.format(v)
const pct = (v: number) => { const t = totalComp.value; return t > 0 ? (v / t * 100).toFixed(1) + '%' : '' }

// Tope de 1 año: si el rango se pasa, se ajusta sola la OTRA fecha (la que no tocó el usuario).
const MAX_MESES = 12
const addMeses = (iso: string, n: number) => { const d = new Date(iso + 'T00:00:00'); d.setMonth(d.getMonth() + n); return _iso(d) }
function ajustarRango (movio: 'desde' | 'hasta') {
  if (f.fecha1 && f.fecha2 && f.fecha1 > f.fecha2) {   // si se cruzaron, empareja
    if (movio === 'desde') f.fecha2 = f.fecha1; else f.fecha1 = f.fecha2
  }
  const topeHasta = addMeses(f.fecha1, MAX_MESES)
  if (f.fecha2 > topeHasta) {
    if (movio === 'desde') f.fecha2 = topeHasta            // moviste Desde atrás → Hasta se recorta
    else f.fecha1 = addMeses(f.fecha2, -MAX_MESES)          // moviste Hasta adelante → Desde se corre
  }
  cargar()
}

// Cambiar el "agrupar por" recalcula SOLO la composición (no todo el tablero).
async function recargarComposicion () {
  if (!datos.value) return
  try {
    const params: any = { fecha1: f.fecha1, fecha2: f.fecha2, agrupar: f.agrupar }
    for (const k of ['empresa', 'contratista', 'convenio', 'sector', 'categoria', 'lugar', 'tipoSueldo'] as const) if ((f as any)[k]) params[k] = (f as any)[k]
    const { data } = await api.get('/estadisticas/composicion', { params })
    datos.value = { ...datos.value, composicion: data }
  } catch { /* si falla, se deja la composición anterior */ }
}

// ── Carga ──
async function cargar () {
  cargando.value = true; msg.value = ''
  try {
    const params: any = { fecha1: f.fecha1, fecha2: f.fecha2, agrupar: f.agrupar }
    for (const k of ['empresa', 'contratista', 'convenio', 'sector', 'categoria', 'lugar', 'tipoSueldo'] as const) if ((f as any)[k]) params[k] = (f as any)[k]
    datos.value = (await api.get('/estadisticas', { params })).data
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudieron calcular las estadísticas.'; datos.value = null }
  finally { cargando.value = false }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    Object.assign(op, { empresas: data.empresas ?? [], contratistas: data.contratistas ?? [], lugares: data.lugares ?? [], convenios: data.convenios ?? [], categorias: data.categorias ?? [], sectores: data.sectores ?? [] })
  } catch { /* */ }
  try { tiposSueldo.value = (await api.get('/estadisticas/tipos-sueldo')).data ?? [] } catch { /* */ }
  cargar()
})

// ── Escalas ──
/** Ticks "lindos" (0, ¼, ½, ¾, max) para un eje vertical de alto $h. */
function ticksY (max: number, fmt: (v: number) => string, h = H) {
  const top = max <= 0 ? 1 : max
  const y = (v: number) => h - PB - (v / top) * (h - PB - PT)
  return [0, 0.25, 0.5, 0.75, 1].map(p => ({ v: p, y: y(p * top), lbl: fmt(p * top) }))
}
const abrev = (v: number) => Math.abs(v) >= 1e6 ? nf1.format(v / 1e6) + 'M' : (Math.abs(v) >= 1e3 ? nf0.format(v / 1e3) + 'k' : nf0.format(v))

// 1) Sueldos por mes
const gSueldos = computed(() => {
  const d = datos.value?.sueldosPorMes ?? []
  const max = Math.max(1, ...d.map((x: any) => x.neto))
  const bw = (WW - PL - PR) / Math.max(1, d.length)
  const bars = d.map((x: any, i: number) => {
    const h = (x.neto / max) * (HW - PB - PT)
    return { i, x: PL + i * bw + bw * 0.22, w: bw * 0.56, y: HW - PB - h, h, val: x.neto, label: x.label.slice(0, 3) }
  })
  return { bars, ticks: ticksY(max, abrev, HW) }
})

// 2) Composición
const totalComp = computed(() => (datos.value?.composicion?.items ?? []).reduce((a: number, x: any) => a + x.val, 0))
const gComp = computed(() => {
  const items = (datos.value?.composicion?.items ?? []).slice(0, 8)
  const max = Math.max(1, ...items.map((x: any) => x.monto))
  const x0 = 150, availW = W - x0 - 54
  const bars = items.map((x: any, i: number) => ({
    i, y: PT + i * 26, h: 18, w: (x.monto / max) * availW, val: x.monto, emp: x.empleados,
    label: (x.clave || '').length > 22 ? x.clave.slice(0, 21) + '…' : x.clave,
  }))
  return { bars, n: items.length, x0 }
})

// 3) Dotación (activos por mes)
const gDota = computed(() => {
  const d = datos.value?.dotacion ?? []
  const max = Math.max(1, ...d.map((x: any) => x.activos))
  const bw = (W - PL - PR) / Math.max(1, d.length)
  const bars = d.map((x: any, i: number) => {
    const h = (x.activos / max) * (H - PB - PT)
    return { i, x: PL + i * bw + bw * 0.14, w: bw * 0.72, y: H - PB - h, h, val: x.activos, altas: x.altas, bajas: x.bajas, label: x.label.slice(0, 3) }
  })
  return { bars, ticks: ticksY(max, (v) => nf0.format(v)) }
})

// 4) Horas extras (apiladas)
const costoHE = computed(() => (datos.value?.horasExtras ?? []).reduce((a: number, x: any) => a + x.costo, 0))
const gHE = computed(() => {
  const d = datos.value?.horasExtras ?? []
  const tot = d.map((x: any) => x.hs50 + x.hs100 + x.hsnoc)
  const max = Math.max(1, ...tot)
  const bw = (W - PL - PR) / Math.max(1, d.length)
  const escala = (v: number) => (v / max) * (H - PB - PT)
  const cols = d.map((x: any, i: number) => {
    const partes = [['50%', x.hs50, 'c1'], ['100%', x.hs100, 'c2'], ['Nocturnas', x.hsnoc, 'c4']] as [string, number, string][]
    let yAcum = H - PB; const segs: any[] = []
    for (const [nom, val, cls] of partes) { const h = escala(val); yAcum -= h; if (val > 0) segs.push({ k: nom, nom, val, cls, y: yAcum, h }) }
    return { i, x: PL + i * bw + bw * 0.14, w: bw * 0.72, segs, label: x.label.slice(0, 3) }
  })
  return { cols, ticks: ticksY(max, (v) => nf0.format(v)) }
})

// 5) Ausentismo
const gAus = computed(() => {
  const items = (datos.value?.ausentismo?.items ?? []).slice(0, 10)
  const max = Math.max(1, ...items.map((x: any) => x.dias))
  const x0 = 240, availW = W - x0 - 40
  const bars = items.map((x: any, i: number) => ({
    i, y: PT + i * 26, h: 18, w: (x.dias / max) * availW, val: x.dias,
    label: (x.tipo || '').length > 36 ? x.tipo.slice(0, 35) + '…' : x.tipo,
  }))
  return { bars, n: items.length, x0 }
})

// 7) Liquidaciones finales por mes
const gLiqFin = computed(() => {
  const d = datos.value?.liqFinales?.items ?? []
  const max = Math.max(1, ...d.map((x: any) => x.monto))
  const bw = (W - PL - PR) / Math.max(1, d.length)
  const bars = d.map((x: any, i: number) => {
    const h = (x.monto / max) * (H - PB - PT)
    return { i, x: PL + i * bw + bw * 0.14, w: bw * 0.72, y: H - PB - h, h, val: x.monto, emp: x.empleados, label: x.label.slice(0, 3) }
  })
  return { bars, ticks: ticksY(max, abrev) }
})

// 6) Empleados con más faltas (ranking)
const gFaltasEmp = computed(() => {
  const items = (datos.value?.faltasEmpleado?.items ?? []).slice(0, 12)
  const max = Math.max(1, ...items.map((x: any) => x.dias))
  const x0 = 260, availW = W - x0 - 40   // columna de nombres amplia: nombre completo sin recortar
  const bars = items.map((x: any, i: number) => ({
    i, y: PT + i * 26, h: 18, w: (x.dias / max) * availW, val: x.dias,
    label: `${x.legajo} ${(x.nombre || '').trim()}`,
  }))
  return { bars, n: items.length, x0 }
})

// ── Desglose genérico (ojito) para el resto de los gráficos ──
type Col = { key: string; label: string; r?: boolean; money?: boolean }
const desg = reactive<{ abierto: boolean; icono: string; titulo: string; tipo: string; cols: Col[]; rows: any[]; footer: any[] | null; archivo: string; nivel: 'grupos' | 'sub'; sub: { titulo: string; cols: Col[]; rows: any[]; footer: any[] | null; cargando: boolean; archivo: string } }>({
  abierto: false, icono: '', titulo: '', tipo: '', cols: [], rows: [], footer: null, archivo: '', nivel: 'grupos',
  sub: { titulo: '', cols: [], rows: [], footer: null, cargando: false, archivo: '' },
})
const LBL_AGRUP: Record<string, string> = { convenio: 'Convenio', sector: 'Sector', categoria: 'Categoría', contratista: 'Contratista', lugar: 'Lugar' }
const sumBy = (rows: any[], k: string) => rows.reduce((a: number, r: any) => a + Number(r[k] || 0), 0)

// Configuración del drill (2° nivel) por gráfico: endpoint + columnas + cómo armar filas/total.
const DRILL: Record<string, { endpoint: string; extra: (r: any) => Record<string, any>; cols: Col[]; titulo: (r: any) => string; rowsFrom?: (data: any) => any[]; footer?: (rows: any[]) => any[]; archivo: (r: any) => string }> = {
  composicion: {
    endpoint: '/estadisticas/composicion-empleados', extra: (r) => ({ agrupar: f.agrupar, clave: r.clave }),
    cols: [{ key: 'legajo', label: 'Legajo', r: true }, { key: 'nombre', label: 'Empleado' }, { key: 'neto', label: 'Neto', r: true, money: true }],
    titulo: (r) => r.clave, footer: (rows) => ['', 'TOTAL', money(sumBy(rows, 'neto'))], archivo: (r) => `COMPOSICION_${f.agrupar}_${r.clave}`,
  },
  dotacion: {
    endpoint: '/estadisticas/dotacion-movimientos', extra: (r) => ({ mes: r.mes }),
    rowsFrom: (data) => [
      ...(data.altas ?? []).map((x: any) => ({ mov: '↑ Ingresó', legajo: x.legajo, nombre: x.nombre, fecha: fmtFecha(x.fecha) })),
      ...(data.bajas ?? []).map((x: any) => ({ mov: '↓ Se fue', legajo: x.legajo, nombre: x.nombre, fecha: fmtFecha(x.fecha) })),
    ],
    cols: [{ key: 'mov', label: 'Movimiento' }, { key: 'legajo', label: 'Legajo', r: true }, { key: 'nombre', label: 'Empleado' }, { key: 'fecha', label: 'Fecha', r: true }],
    titulo: (r) => r.label, archivo: (r) => `DOTACION_${r.mes}`,
  },
  horasExtras: {
    endpoint: '/estadisticas/horas-extras-empleados', extra: (r) => ({ mes: r.mes }),
    cols: [{ key: 'legajo', label: 'Legajo', r: true }, { key: 'nombre', label: 'Empleado' }, { key: 'hs50', label: '50%', r: true }, { key: 'hs100', label: '100%', r: true }, { key: 'hsnoc', label: 'Nocturnas', r: true }, { key: 'total', label: 'Total hs', r: true }, { key: 'costo', label: 'Costo', r: true, money: true }],
    titulo: (r) => r.label, footer: (rows) => ['', 'TOTAL', sumBy(rows, 'hs50'), sumBy(rows, 'hs100'), sumBy(rows, 'hsnoc'), sumBy(rows, 'total'), money(sumBy(rows, 'costo'))], archivo: (r) => `HORAS_EXTRAS_${r.mes}`,
  },
  ausentismo: {
    endpoint: '/estadisticas/ausentismo-empleados', extra: (r) => ({ tipo: r.tipo }),
    cols: [{ key: 'legajo', label: 'Legajo', r: true }, { key: 'nombre', label: 'Empleado' }, { key: 'dias', label: 'Días', r: true }],
    titulo: (r) => r.tipo, footer: (rows) => ['', 'TOTAL', sumBy(rows, 'dias')], archivo: (r) => `AUSENTISMO_${r.tipo}`,
  },
  liqFinales: {
    endpoint: '/estadisticas/liq-finales-empleados', extra: (r) => ({ mes: r.mes }),
    cols: [{ key: 'legajo', label: 'Legajo', r: true }, { key: 'nombre', label: 'Empleado' }, { key: 'monto', label: 'Monto', r: true, money: true }],
    titulo: (r) => r.label, footer: (rows) => ['', 'TOTAL', money(sumBy(rows, 'monto'))], archivo: (r) => `LIQ_FINALES_${r.mes}`,
  },
  faltasEmp: {
    endpoint: '/estadisticas/faltas-empleado-detalle', extra: (r) => ({ legajo: r.legajo }),
    cols: [{ key: 'tipo', label: 'Tipo de licencia' }, { key: 'dias', label: 'Días', r: true }],
    titulo: (r) => `${r.legajo} ${r.nombre}`, footer: (rows) => ['TOTAL', sumBy(rows, 'dias')], archivo: (r) => `FALTAS_${r.legajo}`,
  },
}

// Drill: clic en una fila del desglose → 2° nivel (empleados / movimientos / tipos, según el gráfico).
async function abrirSub (r: any) {
  const cfg = DRILL[desg.tipo]; if (!cfg) return
  desg.nivel = 'sub'
  Object.assign(desg.sub, { titulo: cfg.titulo(r), cols: cfg.cols, rows: [], footer: null, cargando: true, archivo: cfg.archivo(r) })
  try {
    const params: any = { fecha1: f.fecha1, fecha2: f.fecha2, ...cfg.extra(r) }
    for (const k of ['empresa', 'contratista', 'convenio', 'sector', 'categoria', 'lugar'] as const) if ((f as any)[k]) params[k] = (f as any)[k]
    const { data } = await api.get(cfg.endpoint, { params })
    const rows = cfg.rowsFrom ? cfg.rowsFrom(data) : (data.rows ?? [])
    desg.sub.rows = rows
    desg.sub.footer = cfg.footer ? cfg.footer(rows) : null
  } catch { desg.sub.rows = [] }
  finally { desg.sub.cargando = false }
}
function volverGrupos () { desg.nivel = 'grupos' }

function abrirDesglose (tipo: string) {
  const d = datos.value; if (!d) return
  desg.tipo = tipo; desg.nivel = 'grupos'
  if (tipo === 'composicion') {
    const items = (d.composicion?.items ?? [])
    const total = items.reduce((a: number, x: any) => a + Number(x.monto || 0), 0) || 1
    desg.icono = '🥧'; desg.titulo = `Composición de la masa salarial — por ${LBL_AGRUP[f.agrupar] ?? f.agrupar}`
    desg.cols = [{ key: 'clave', label: LBL_AGRUP[f.agrupar] ?? 'Grupo' }, { key: 'empleados', label: 'Empleados', r: true }, { key: 'monto', label: 'Neto', r: true, money: true }, { key: 'pct', label: '%', r: true }]
    desg.rows = items.map((x: any) => ({ ...x, pct: (Number(x.monto || 0) / total * 100).toFixed(1) + '%' }))
    desg.footer = ['TOTAL', items.reduce((a: number, x: any) => a + Number(x.empleados || 0), 0), money(total), '100%']
    desg.archivo = `COMPOSICION_${f.agrupar}`
  } else if (tipo === 'dotacion') {
    const rows = (d.dotacion ?? [])
    desg.icono = '📈'; desg.titulo = 'Dotación por mes'
    desg.cols = [{ key: 'label', label: 'Mes' }, { key: 'activos', label: 'Activos (cierre)', r: true }, { key: 'altas', label: 'Altas', r: true }, { key: 'bajas', label: 'Bajas', r: true }]
    desg.rows = rows; desg.footer = null; desg.archivo = 'DOTACION'
  } else if (tipo === 'horasExtras') {
    const rows = (d.horasExtras ?? []).map((x: any) => ({ ...x, total: (x.hs50 || 0) + (x.hs100 || 0) + (x.hsnoc || 0) }))
    const sum = (k: string) => rows.reduce((a: number, x: any) => a + Number(x[k] || 0), 0)
    desg.icono = '⏱️'; desg.titulo = 'Horas extras por mes'
    desg.cols = [{ key: 'label', label: 'Mes' }, { key: 'hs50', label: '50%', r: true }, { key: 'hs100', label: '100%', r: true }, { key: 'hsnoc', label: 'Nocturnas', r: true }, { key: 'total', label: 'Total hs', r: true }, { key: 'costo', label: 'Costo', r: true, money: true }]
    desg.rows = rows
    desg.footer = ['TOTAL', sum('hs50'), sum('hs100'), sum('hsnoc'), sum('total'), money(sum('costo'))]
    desg.archivo = 'HORAS_EXTRAS'
  } else if (tipo === 'ausentismo') {
    const rows = (d.ausentismo?.items ?? [])
    desg.icono = '🤒'; desg.titulo = 'Ausentismo por tipo de licencia'
    desg.cols = [{ key: 'tipo', label: 'Tipo de licencia' }, { key: 'dias', label: 'Días', r: true }]
    desg.rows = rows; desg.footer = ['TOTAL', rows.reduce((a: number, x: any) => a + Number(x.dias || 0), 0)]
    desg.archivo = 'AUSENTISMO'
  } else if (tipo === 'liqFinales') {
    const rows = (d.liqFinales?.items ?? [])
    desg.icono = '📄'; desg.titulo = 'Liquidaciones finales por mes'
    desg.cols = [{ key: 'label', label: 'Mes' }, { key: 'empleados', label: 'Bajas', r: true }, { key: 'monto', label: 'Monto', r: true, money: true }]
    desg.rows = rows
    desg.footer = ['TOTAL', rows.reduce((a: number, x: any) => a + Number(x.empleados || 0), 0), money(rows.reduce((a: number, x: any) => a + Number(x.monto || 0), 0))]
    desg.archivo = 'LIQ_FINALES'
  } else if (tipo === 'faltasEmp') {
    const rows = (d.faltasEmpleado?.items ?? [])
    desg.icono = '🏅'; desg.titulo = 'Empleados con más faltas'
    desg.cols = [{ key: 'legajo', label: 'Legajo', r: true }, { key: 'nombre', label: 'Empleado' }, { key: 'dias', label: 'Días', r: true }]
    desg.rows = rows; desg.footer = null; desg.archivo = 'FALTAS_EMPLEADO'
  } else return
  desg.abierto = true
}
function celda (r: any, c: Col) { return c.money ? money(r[c.key]) : r[c.key] }
async function exportarDesglose () {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const head = desg.cols.map(c => c.label)
  const body = desg.rows.map(r => desg.cols.map(c => c.money ? Number(r[c.key] || 0) : r[c.key]))
  if (desg.footer) body.push(desg.footer)
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + body.map(r => '<tr>' + r.map((c: any) => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), `${desg.archivo}_${f.fecha1}_${f.fecha2}.xls`)
}

async function exportarSub () {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const head = desg.sub.cols.map(c => c.label)
  const body = desg.sub.rows.map(r => desg.sub.cols.map(c => c.money ? Number(r[c.key] || 0) : r[c.key]))
  if (desg.sub.footer) body.push(desg.sub.footer)
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + body.map(r => '<tr>' + r.map((c: any) => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), `${desg.sub.archivo}.xls`)
}

// ── Desglose de sueldos (ojito) ──
const detalle = reactive<{ abierto: boolean; cargando: boolean; rows: any[]; total: number; truncado: boolean; tope: number; buscar: string; mesSel: string }>({
  abierto: false, cargando: false, rows: [], total: 0, truncado: false, tope: 0, buscar: '', mesSel: '',
})
const claveMes = (fecha: string) => String(fecha || '').slice(0, 7)   // "2026-06-15" → "2026-06"

// Nivel 1: resumen por mes (totales exactos, salen del propio gráfico — nunca truncados).
const resumenMeses = computed(() =>
  (datos.value?.sueldosPorMes ?? [])
    .filter((m: any) => m.empleados > 0 || Number(m.neto) !== 0)
    .map((m: any) => ({ clave: m.mes, label: m.label, empleados: m.empleados, neto: Number(m.neto || 0) })))
const totalResumen = computed(() => resumenMeses.value.reduce((a: number, m: any) => a + m.neto, 0))
const mesSelLabel = computed(() => resumenMeses.value.find((m: any) => m.clave === detalle.mesSel)?.label ?? '')

// Nivel 2: detalle de un mes (filas del mes elegido + buscador).
const detalleVista = computed(() => {
  if (!detalle.mesSel) return []
  let rows = detalle.rows.filter((r: any) => claveMes(r.fecha) === detalle.mesSel)
  const q = detalle.buscar.trim().toLowerCase()
  if (q) rows = rows.filter((r: any) => (r.nombre || '').toLowerCase().includes(q) || String(r.legajo).includes(q) || (r.tipo || '').toLowerCase().includes(q))
  return rows
})
const totalDetalle = computed(() => detalleVista.value.reduce((a: number, r: any) => a + Number(r.neto || 0), 0))

function abrirMes (clave: string) { detalle.mesSel = clave; detalle.buscar = '' }
function volverResumen () { detalle.mesSel = ''; detalle.buscar = '' }

async function abrirDetalleSueldos () {
  detalle.abierto = true; detalle.cargando = true; detalle.rows = []; detalle.buscar = ''; detalle.mesSel = ''
  try {
    const params: any = { fecha1: f.fecha1, fecha2: f.fecha2 }
    for (const k of ['empresa', 'contratista', 'convenio', 'sector', 'categoria', 'lugar', 'tipoSueldo'] as const) if ((f as any)[k]) params[k] = (f as any)[k]
    const { data } = await api.get('/estadisticas/detalle-sueldos', { params })
    detalle.rows = data.rows ?? []; detalle.total = data.total ?? 0; detalle.truncado = !!data.truncado; detalle.tope = data.tope ?? 0
  } catch { detalle.rows = [] }
  finally { detalle.cargando = false }
}
async function exportarDetalle () {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  let head: string[]; let rows: any[][]; let nombre: string
  if (detalle.mesSel) {   // detalle del mes
    head = ['Fecha', 'Legajo', 'Empleado', 'Tipo', 'Haberes', 'Deducciones', 'Neto']
    rows = detalleVista.value.map((r: any) => [fmtFecha(r.fecha), r.legajo, r.nombre, r.tipo, r.haberes, r.deducciones, r.neto])
    nombre = `SUELDOS_${detalle.mesSel}.xls`
  } else {                // resumen por mes
    head = ['Mes', 'Empleados', 'Neto pagado']
    rows = resumenMeses.value.map((m: any) => [m.label, m.empleados, m.neto])
    rows.push(['TOTAL', '', totalResumen.value])
    nombre = `SUELDOS_RESUMEN_${f.fecha1}_${f.fecha2}.xls`
  }
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), nombre)
}

// ── Texto de los filtros aplicados (para el encabezado del PDF) ──
const nombreDe = (arr: any[], codKey: string, nomKey: string, val: number) => {
  const o = arr.find((x: any) => Number(x[codKey]) === val); return o ? String(o[nomKey]).trim() : String(val)
}
const filtrosTexto = computed(() => {
  const p: string[] = []
  if (f.empresa) p.push('Empresa: ' + nombreDe(op.empresas, 'EMP_COD', 'EMP_NOM', f.empresa))
  if (f.contratista) p.push('Contratista: ' + nombreDe(op.contratistas, 'CONT_COD', 'CONT_DET', f.contratista))
  if (f.convenio) p.push('Convenio: ' + nombreDe(op.convenios, 'CON_COD', 'CON_DES', f.convenio))
  if (f.sector) p.push('Sector: ' + nombreDe(op.sectores, 'SEC_COD', 'SEC_DES', f.sector))
  if (f.categoria) p.push('Categoría: ' + nombreDe(op.categorias, 'CAT_COD', 'CAT_DES', f.categoria))
  if (f.lugar) p.push('Lugar: ' + nombreDe(op.lugares, 'LUG_COD', 'LUG_NOM', f.lugar))
  if (f.tipoSueldo) p.push('Tipo de sueldo: ' + (tiposSueldo.value.find(t => t.tip === f.tipoSueldo)?.label ?? f.tipoSueldo))
  return p.length ? p.join('  ·  ') : 'Todos los empleados'
})

// ── PDF: gráficos tal cual se ven + tablas de datos ──
const generandoPdf = ref(false)
const gridRef = ref<HTMLElement | null>(null)
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

// Estilos que hay que embeber en el SVG clonado (el CSS scoped no viaja al rasterizar).
const SVG_CSS = `
  text{font-family:system-ui,-apple-system,'Segoe UI',sans-serif}
  .grid{stroke:#e1e0d9;stroke-width:1}
  .ejeY{fill:#898781;font-size:9px;text-anchor:end}.ejeX{fill:#898781;font-size:9px;text-anchor:middle}
  .ejeYh{fill:#52514e;font-size:9.5px;text-anchor:end}.valH{fill:#52514e;font-size:9.5px;font-weight:600}
  .c1{fill:#2a78d6}.c2{fill:#eb6834}.c3{fill:#1baf7a}.c4{fill:#eda100}.c8{fill:#e34948}
`
/** Rasteriza un <svg> del DOM a PNG (data URL) manteniendo su relación de aspecto. */
function svgAPng (svg: SVGSVGElement, escala = 2): Promise<{ url: string; w: number; h: number }> {
  const vb = (svg.getAttribute('viewBox') || '0 0 560 260').split(/\s+/).map(Number)
  const w = vb[2] || 560, h = vb[3] || 260
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
  if (!datos.value || !gridRef.value) return
  generandoPdf.value = true
  try {
    await nextTick()
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    ;(doc as any).__logoDer = true   // el título va arriba a la izquierda → logo a la derecha
    const PW = 210, PH = 297, ML = 12, W = PW - ML * 2
    let y = 16
    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
    doc.text('Tablero Gerencial — Estadísticas', ML, y); y += 6
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(0, 0, 0)
    doc.text(`Período: ${fmtFecha(f.fecha1)} al ${fmtFecha(f.fecha2)}`, ML, y); y += 5
    for (const ln of doc.splitTextToSize('Filtros — ' + filtrosTexto.value, W)) { doc.text(ln, ML, y); y += 4.5 }
    doc.setDrawColor(27, 67, 50); doc.setLineWidth(0.4); doc.line(ML, y, PW - ML, y); y += 6

    // Cada tarjeta de gráfico → título + imagen
    const cards = Array.from(gridRef.value.querySelectorAll('.es-card')) as HTMLElement[]
    for (const card of cards) {
      const svg = card.querySelector('svg') as SVGSVGElement | null
      // La fuente del PDF no dibuja emojis (salen como "Ø=Ü°"): se quitan del título.
      const titulo = sinEmoji(card.querySelector('h3')?.textContent || '')
      if (!svg) continue
      const { url, w, h } = await svgAPng(svg)
      if (!url) continue
      const imgW = W, imgH = imgW * (h / w)
      if (y + 8 + imgH > PH - 14) { doc.addPage(); y = 16 }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(30, 41, 59)
      doc.text(titulo, ML, y); y += 4
      doc.addImage(url, 'PNG', ML, y, imgW, imgH); y += imgH + 8
    }

    // Hojas siguientes: los datos (igual que el Excel)
    doc.addPage(); y = 16
    const tabla = (titulo: string, head: string[], rows: any[][], anchos: number[]) => {
      if (y > PH - 24) { doc.addPage(); y = 16 }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
      doc.text(titulo, ML, y); y += 5
      doc.setFontSize(8); doc.setTextColor(0, 0, 0)
      const xs: number[] = []; let acc = ML; for (const a of anchos) { xs.push(acc); acc += a }
      head.forEach((c, i) => doc.text(String(c), xs[i] ?? ML, y)); y += 1.5
      doc.setDrawColor(180); doc.line(ML, y, PW - ML, y); y += 4
      doc.setFont('helvetica', 'normal')
      for (const r of rows) {
        if (y > PH - 14) { doc.addPage(); y = 16 }
        r.forEach((c, i) => doc.text(doc.splitTextToSize(String(c ?? ''), (anchos[i] ?? 30) - 2)[0] || '', xs[i] ?? ML, y)); y += 4.4
      }
      y += 5
    }
    tabla('Sueldos netos por mes', ['Mes', 'Neto', 'Haberes', 'Deducciones', 'Empl.'],
      datos.value.sueldosPorMes.map((x: any) => [x.label, money(x.neto), money(x.haberes), money(x.deducciones), x.empleados]), [30, 48, 48, 48, 12])
    tabla(`Composición (${datos.value.composicion.agrupar})`, ['Grupo', 'Monto', 'Empl.'],
      datos.value.composicion.items.map((x: any) => [x.clave, money(x.monto), x.empleados]), [110, 60, 16])
    tabla('Dotación por mes', ['Mes', 'Activos', 'Altas', 'Bajas'],
      datos.value.dotacion.map((x: any) => [x.label, x.activos, x.altas, x.bajas]), [40, 40, 40, 40])
    tabla('Horas extras por mes', ['Mes', 'HS 50', 'HS 100', 'HS Noc', 'Costo'],
      datos.value.horasExtras.map((x: any) => [x.label, x.hs50, x.hs100, x.hsnoc, money(x.costo)]), [30, 32, 32, 32, 60])
    tabla('Ausentismo por tipo', ['Tipo', 'Días'], datos.value.ausentismo.items.map((x: any) => [x.tipo, x.dias]), [150, 30])
    tabla('Empleados con más faltas', ['Legajo', 'Empleado', 'Días'],
      (datos.value.faltasEmpleado?.items ?? []).map((x: any) => [x.legajo, x.nombre, x.dias]), [26, 120, 30])
    tabla('Liquidaciones finales por mes', ['Mes', 'Monto', 'Bajas'],
      (datos.value.liqFinales?.items ?? []).map((x: any) => [x.label, money(x.monto), x.empleados]), [40, 60, 30])

    cerrarPdf(); pdfNombre.value = `ESTADISTICAS_${f.fecha1}_${f.fecha2}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (e) { console.error(e); msg.value = 'No se pudo generar el PDF.' }
  finally { generandoPdf.value = false }
}
const fmtFecha = (iso: string) => iso ? iso.split('-').reverse().join('/') : ''
// Quita emojis/pictogramas (la fuente del PDF no los dibuja) y espacios sobrantes.
const sinEmoji = (s: string) => s.replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2190}-\u{21FF}\u{2B00}-\u{2BFF}\u{FE0F}\u{200D}]/gu, '').trim()

// ── Exportar a Excel (las tablas que alimentan los gráficos) ──
async function exportarExcel () {
  if (!datos.value) return
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const tabla = (titulo: string, head: string[], rows: any[][]) =>
    `<tr><th colspan="${head.length}" style="background:#1b4332;color:#fff">${esc(titulo)}</th></tr>`
    + '<tr>' + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('')
    + '<tr><td></td></tr>'
  let body = ''
  body += tabla('SUELDOS NETOS POR MES', ['Mes', 'Neto', 'Haberes', 'Deducciones', 'Empleados'],
    datos.value.sueldosPorMes.map((x: any) => [x.label, x.neto, x.haberes, x.deducciones, x.empleados]))
  body += tabla(`COMPOSICION (${datos.value.composicion.agrupar})`, ['Grupo', 'Monto', 'Empleados'],
    datos.value.composicion.items.map((x: any) => [x.clave, x.monto, x.empleados]))
  body += tabla('DOTACION POR MES', ['Mes', 'Activos', 'Altas', 'Bajas'],
    datos.value.dotacion.map((x: any) => [x.label, x.activos, x.altas, x.bajas]))
  body += tabla('HORAS EXTRAS POR MES', ['Mes', 'HS 50', 'HS 100', 'HS Noc', 'Costo'],
    datos.value.horasExtras.map((x: any) => [x.label, x.hs50, x.hs100, x.hsnoc, x.costo]))
  body += tabla('AUSENTISMO POR TIPO', ['Tipo', 'Días'], datos.value.ausentismo.items.map((x: any) => [x.tipo, x.dias]))
  body += tabla('EMPLEADOS CON MAS FALTAS', ['Legajo', 'Empleado', 'Días'],
    (datos.value.faltasEmpleado?.items ?? []).map((x: any) => [x.legajo, x.nombre, x.dias]))
  body += tabla('LIQUIDACIONES FINALES POR MES', ['Mes', 'Monto', 'Bajas'],
    (datos.value.liqFinales?.items ?? []).map((x: any) => [x.label, x.monto, x.empleados]))
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1">' + body + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), `ESTADISTICAS_${f.fecha1}_${f.fecha2}.xls`)
}
</script>

<style scoped>
.es-view { display:flex; flex-direction:column; min-height:100%; }
.es-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.es-ico { font-size:28px; } .es-tx h1 { margin:0; font-size:19px; color:#1e293b; } .es-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.es-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.es-pdf { background:#1b4332; color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; margin-left:auto; } .es-pdf:disabled { background:#cbd5e1; }
.es-exp { background:#107c41; color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; } .es-exp:disabled { background:#cbd5e1; }
.es-vacio { font-size:12px; color:#94a3b8; margin:8px 0 0; }
.es-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.es-pdf-md { width:min(1000px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.es-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.es-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.es-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .es-pdf-b.ok { background:#22c55e; color:#fff; } .es-pdf-b.cancel { background:#ef4444; color:#fff; }
.es-pdf-frame { flex:1; border:none; width:100%; }
.es-filtros { display:flex; flex-wrap:wrap; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.es-f { display:flex; flex-direction:column; gap:3px; } .es-f span { font-size:11px; font-weight:700; color:#1b4332; }
.es-f input, .es-f select { border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:13px; color:#1e293b; }
.es-info { padding:40px; text-align:center; color:#64748b; } .es-info.err { color:#b91c1c; }
.es-kpis { display:flex; flex-wrap:wrap; gap:12px; padding:16px 18px 4px; }
.es-kpi { flex:1; min-width:150px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; display:flex; flex-direction:column; gap:2px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
.k-num { font-size:20px; font-weight:800; color:#1b4332; } .k-lbl { font-size:12px; color:#64748b; }
.es-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:12px 18px 24px; }
.es-card { background:#fcfcfb; border:1px solid #e2e8f0; border-radius:12px; padding:14px; } .es-card.wide { grid-column:1 / -1; }
.es-card h3 { margin:0 0 8px; font-size:14px; color:#1e293b; } .es-card-h { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; } .es-card-h h3 { margin:0; }
.es-sub { font-size:12px; color:#64748b; } .es-mini { border:1px solid #d1d5db; border-radius:6px; padding:4px 8px; font-size:12px; }
.es-card-h-acc { display:flex; align-items:center; gap:8px; }
.es-ojo { background:#fff; border:1px solid #d1d5db; border-radius:6px; padding:3px 8px; cursor:pointer; font-size:14px; } .es-ojo:hover { background:#eff6ff; }
/* Modal de desglose */
.es-det-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9800; display:flex; align-items:center; justify-content:center; padding:20px; }
.es-det-md { width:min(920px,98vw); max-height:90vh; background:#fff; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,.4); display:flex; flex-direction:column; }
.es-det-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; background:#1b4332; color:#fff; border-radius:12px 12px 0 0; font-size:14px; font-weight:700; }
.es-x { background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer; }
.es-det-tools { display:flex; align-items:center; gap:12px; padding:10px 14px; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.es-det-buscar { border:1px solid #d1d5db; border-radius:7px; padding:7px 10px; font-size:13px; min-width:280px; }
.es-det-cont { font-size:12px; color:#64748b; } .es-det-trunc { font-size:12px; color:#b45309; font-weight:600; }
.es-mes-row { cursor:pointer; } .es-mes-row:hover td { background:#ecfdf5 !important; }
.es-ver-mes { color:#107c41; font-weight:700; font-size:12px; }
.es-volver { background:#e2e8f0; color:#1e293b; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:600; } .es-volver:hover { background:#cbd5e1; }
.es-mes-tit { font-size:14px; color:#1b4332; }
.es-det-wrap { overflow:auto; }
.es-det-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.es-det-tabla th { position:sticky; top:0; background:#1e293b; color:#fff; padding:7px 10px; text-align:left; } .es-det-tabla th.r, .es-det-tabla td.r { text-align:right; }
.es-det-tabla td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; white-space:nowrap; } .es-det-tabla td.b { font-weight:700; }
.es-det-tabla tbody tr:nth-child(even) td { background:#f8fafc; }
.es-det-tabla tfoot td { padding:8px 10px; border-top:2px solid #cbd5e1; background:#f1f5f9; color:#1b4332; }
.es-det-vacio { text-align:center; color:#94a3b8; padding:20px; }
.es-svg { width:100%; height:auto; display:block; }
.grid { stroke:#e1e0d9; stroke-width:1; } .baseline { stroke:#c3c2b7; stroke-width:1; }
.ejeY { fill:#898781; font-size:9px; text-anchor:end; } .ejeX { fill:#898781; font-size:9px; text-anchor:middle; }
.ejeYh { fill:#52514e; font-size:9.5px; text-anchor:end; } .valH { fill:#52514e; font-size:9.5px; font-weight:600; }
.barra { transition:opacity .12s; } .barra:hover { opacity:.82; cursor:default; }
/* fill = barras SVG · background = cuadraditos de la leyenda (spans HTML) */
.c1 { fill:#2a78d6; background:#2a78d6; } .c2 { fill:#eb6834; background:#eb6834; } .c3 { fill:#1baf7a; background:#1baf7a; } .c4 { fill:#eda100; background:#eda100; } .c8 { fill:#e34948; background:#e34948; }
.es-leg { display:flex; align-items:center; gap:6px; margin-top:8px; font-size:11.5px; color:#52514e; flex-wrap:wrap; }
.es-leg .lg { width:11px; height:11px; border-radius:3px; display:inline-block; margin-left:8px; }
.es-leg .lg:first-child { margin-left:0; }
.es-leg-costo { margin-left:auto; font-weight:700; color:#1b4332; }
.es-tip { position:fixed; z-index:9999; background:#1e293b; color:#fff; padding:5px 9px; border-radius:6px; font-size:12px; pointer-events:none; box-shadow:0 4px 14px rgba(0,0,0,.3); white-space:nowrap; }
@media (max-width:900px){ .es-grid { grid-template-columns:1fr; } }
</style>
