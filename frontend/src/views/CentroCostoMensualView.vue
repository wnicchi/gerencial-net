<template>
  <div class="cm">
    <div class="cm-cab">
      <div class="cm-cab-ico">🏭</div>
      <div class="cm-cab-tx">
        <h1>Estadística Centros de Costo — Mensual</h1>
        <p>Compras y ventas por centro de costo y mes — ventana móvil de 12 meses</p>
      </div>
      <div class="cm-acc">
        <label class="cm-f">Mes
          <input v-model.number="mes" type="number" min="1" max="12" class="cm-in" @change="cargar" />
        </label>
        <label class="cm-f">Año
          <input v-model.number="anio" type="number" min="2015" max="2060" class="cm-in" @change="cargar" />
        </label>
        <button v-if="filas.length" class="cm-btn" @click="aExcel">📊 Excel</button>
        <ModuloAyudaIA modulo="Estadística Centros de Costo Mensual" icono="🏭"
          descripcion="Muestra una matriz de los movimientos por centro de costo y por mes, para una ventana móvil de 12 meses que termina en el mes y año elegidos, tomando los ítems por su fecha. Cada centro puede aparecer en dos filas: CPRAS (compras y pagos varios) y VENTAS. El importe se puede ver en pesos o en dólares (según la cotización del comprobante o la del día). Se puede filtrar por un centro puntual o verlos todos, y elegir mostrar Todos los tipos, sólo Compras o sólo Ventas. Cada fila trae su Total Anual y el porcentaje sobre el total general, ordenados de mayor a menor. Se puede exportar a Excel."
          intro='Movimientos por <b>centro de costo</b> a lo largo de <b>12 meses</b>.'
          :pasos="['Elegí <b>mes</b> y <b>año</b> de cierre de la ventana.', 'Ajustá <b>moneda</b>, <b>tipo</b> y, si querés, un <b>centro</b> puntual.', '<b>Excel</b> exporta la matriz.']"
          :notas="['CPRAS junta compras y pagos varios; VENTAS es facturación.', 'El % es sobre el total general de lo que se está mostrando.']" />
      </div>
    </div>

    <div class="cm-filtros">
      <div class="cm-fg">
        <span class="cm-fg-lbl">Moneda</span>
        <button :class="{ act: moneda === 'P' }" @click="setMoneda('P')">Pesos</button>
        <button :class="{ act: moneda === 'D' }" @click="setMoneda('D')">Dólares</button>
      </div>
      <div class="cm-fg">
        <span class="cm-fg-lbl">Tipo</span>
        <button :class="{ act: tipo === 'T' }" @click="setTipo('T')">Todos</button>
        <button :class="{ act: tipo === 'C' }" @click="setTipo('C')">Compras</button>
        <button :class="{ act: tipo === 'V' }" @click="setTipo('V')">Ventas</button>
      </div>
      <div class="cm-fg">
        <span class="cm-fg-lbl">Centro</span>
        <select v-model.number="ccosto" class="cm-sel" @change="cargar">
          <option :value="0">Todos</option>
          <option v-for="c in catalogo" :key="c.codigo" :value="c.codigo">{{ c.nombre }} ({{ c.codigo }})</option>
        </select>
      </div>
    </div>

    <transition name="msg"><div v-if="msg" class="cm-msg">{{ msg }}</div></transition>
    <div v-if="cargando" class="cm-load">Calculando…</div>

    <div v-if="filas.length" class="cm-scroll">
      <table class="cm-tabla">
        <thead>
          <tr>
            <th class="det">Centro de Costo</th><th class="tip">Tipo</th>
            <th v-for="(m, i) in meses" :key="i" class="num">{{ m }}</th>
            <th class="num tot">Total Anual</th><th class="num por">%</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, idx) in filas" :key="f.codigo + '-' + f.tipo" :class="{ alt: idx % 2 === 1 }">
            <td class="det">{{ f.nombre }} <span class="cm-cod">({{ f.codigo }})</span></td>
            <td class="tip"><span class="cm-badge" :class="f.tipo === 'VENTAS' ? 'v' : 'c'">{{ f.tipo }}</span></td>
            <td v-for="(v, i) in f.montos" :key="i" class="num" :class="{ neg: v < 0 }">{{ v ? money(v) : '' }}</td>
            <td class="num tot">{{ money(f.total) }}</td>
            <td class="num por">{{ f.porcentaje.toFixed(2) }}%</td>
          </tr>
        </tbody>
        <tfoot v-if="mostrarTotal">
          <tr class="cm-tot">
            <td colspan="2">TOTAL</td>
            <td v-for="(v, i) in total.montos" :key="i" class="num">{{ money(v) }}</td>
            <td class="num">{{ money(total.total) }}</td><td class="num">100%</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div v-else-if="!cargando" class="cm-vacio">Sin movimientos en el período.</div>
  </div>
</template>

<script setup lang="ts">
/**
 * CentroCostoMensualView.vue — Centros Costos › Estadística Compras Centro Costo Mensual.
 * Matriz (centro, tipo) × 12 meses con filtros de moneda, tipo y centro. Total Anual + %.
 * Migra estadistica_compras_ccostos_mensual.scx.
 */
import { ref } from 'vue'
import api from '@/services/auth'
import * as XLSX from 'xlsx'
import { guardarDesdeUrl } from '@/utils/descargas'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'

interface Fila { codigo: number; nombre: string; tipo: string; montos: number[]; total: number; porcentaje: number }

const hoy = new Date(); hoy.setMonth(hoy.getMonth() - 1)
const mes = ref(hoy.getMonth() + 1)
const anio = ref(hoy.getFullYear())
const moneda = ref<'P' | 'D'>('P')
const tipo = ref<'T' | 'C' | 'V'>('T')
const ccosto = ref(0)
const meses = ref<string[]>([])
const filas = ref<Fila[]>([])
const total = ref<{ montos: number[]; total: number }>({ montos: [], total: 0 })
const mostrarTotal = ref(false)
const catalogo = ref<{ codigo: number; nombre: string }[]>([])
const cargando = ref(false)
const msg = ref('')

const money = (v: number) => new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Math.round(v))
const setMoneda = (m: 'P' | 'D') => { moneda.value = m; cargar() }
const setTipo = (t: 'T' | 'C' | 'V') => { tipo.value = t; cargar() }

async function cargar () {
  if (mes.value < 1 || mes.value > 12) return
  cargando.value = true; msg.value = ''
  try {
    const d = (await api.get('/tablero/gestion/ccosto-mensual', {
      params: { mes: mes.value, anio: anio.value, moneda: moneda.value, tipo: tipo.value, ccosto: ccosto.value },
    })).data
    meses.value = d.meses; filas.value = d.filas; total.value = d.total; mostrarTotal.value = d.mostrar_total
    // Catálogo de centros: se arma con la vista "Todos" (distintos codigo→nombre).
    if (ccosto.value === 0) {
      const vistos = new Map<number, string>()
      for (const f of d.filas as Fila[]) if (!vistos.has(f.codigo)) vistos.set(f.codigo, f.nombre)
      catalogo.value = [...vistos.entries()].map(([codigo, nombre]) => ({ codigo, nombre }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre))
    }
  } catch { filas.value = []; msg.value = 'No se pudo cargar la estadística.' }
  finally { cargando.value = false }
}

function aExcel () {
  const heads = ['Centro de Costo', 'Código', 'Tipo', ...meses.value, 'Total Anual', 'Porcentaje']
  const rows: (string | number)[][] = filas.value.map(f => [f.nombre, f.codigo, f.tipo, ...f.montos, f.total, f.porcentaje])
  if (mostrarTotal.value) rows.push(['TOTAL', '', '', ...total.value.montos, total.value.total, 100])
  const ws = XLSX.utils.aoa_to_sheet([heads, ...rows])
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'CCosto Mensual')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  guardarDesdeUrl(url, `CCOSTO_MENSUAL_${moneda.value}_${tipo.value}_${mes.value}_${anio.value}.xlsx`)
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

cargar()
</script>

<style scoped>
.cm { padding: 14px 16px 40px; color: #1e293b; }
.cm-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.cm-cab-ico { font-size: 28px; } .cm-cab-tx h1 { margin: 0; font-size: 19px; } .cm-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.cm-acc { margin-left: auto; display: flex; gap: 8px; align-items: flex-end; }
.cm-f { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 700; color: #d1fae5; }
.cm-in { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 8px; font-size: 13px; color: #1e293b; background: #fff; width: 72px; }
.cm-btn { border: none; border-radius: 7px; padding: 8px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #16a34a; color: #fff; }
.cm-filtros { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
.cm-fg { display: flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 5px 8px; border-radius: 8px; }
.cm-fg-lbl { font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .3px; }
.cm-fg button { border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 11px; font-weight: 700; font-size: 12px; cursor: pointer; background: #fff; color: #334155; }
.cm-fg button.act { background: #16a34a; color: #fff; border-color: #16a34a; }
.cm-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 8px; font-size: 12.5px; color: #1e293b; background: #fff; max-width: 260px; }
.cm-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 13px; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.cm-load { margin-top: 16px; color: #64748b; font-size: 13px; }
.cm-vacio { margin-top: 40px; text-align: center; color: #94a3b8; font-size: 14px; }
.cm-scroll { overflow-x: auto; border: 1px solid #cbd5e1; border-radius: 8px; margin-top: 14px; }
.cm-tabla { width: 100%; border-collapse: collapse; font-size: 11.5px; white-space: nowrap; }
.cm-tabla th { position: sticky; top: 0; background: #cfe8cf; color: #14532d; padding: 5px 8px; text-align: right; font-weight: 700; border-bottom: 1px solid #a7d3a7; }
.cm-tabla th.det, .cm-tabla th.tip { text-align: left; }
.cm-tabla td { padding: 3px 8px; border-bottom: 1px solid #e2e8f0; color: #14532d; }
.cm-tabla tr.alt td { background: #eef7ee; }
.cm-tabla td.det { text-align: left; font-weight: 600; color: #0f3d1f; }
.cm-tabla td.det .cm-cod { color: #64748b; font-weight: 500; }
.cm-tabla td.tip { text-align: left; }
.cm-badge { display: inline-block; font-size: 10px; font-weight: 800; padding: 1px 7px; border-radius: 20px; }
.cm-badge.c { background: #dbeafe; color: #1e40af; } .cm-badge.v { background: #dcfce7; color: #166534; }
.cm-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cm-tabla td.num.neg { color: #b91c1c; }
.cm-tabla td.tot, .cm-tabla th.tot { font-weight: 800; background: #dcfce7; }
.cm-tabla td.por, .cm-tabla th.por { font-weight: 700; }
.cm-tabla tr.cm-tot td { background: #14532d; color: #fff; font-weight: 800; padding: 6px 8px; position: sticky; bottom: 0; }
</style>
