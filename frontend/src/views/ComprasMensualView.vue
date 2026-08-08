<template>
  <div class="cm">
    <div class="cm-cab">
      <div class="cm-cab-ico">🛒</div>
      <div class="cm-cab-tx">
        <h1>Estadística Mensual de Compras</h1>
        <p>Compras por rubro y mes — ventana móvil de 12 meses</p>
      </div>
      <div class="cm-acc">
        <label class="cm-f">Mes
          <input v-model.number="mes" type="number" min="1" max="12" class="cm-in" @change="cargar" />
        </label>
        <label class="cm-f">Año
          <input v-model.number="anio" type="number" min="2002" max="2060" class="cm-in" @change="cargar" />
        </label>
        <button v-if="filas.length" class="cm-btn" @click="aExcel">📊 Generar Archivo Excel</button>
        <ModuloAyudaIA modulo="Estadística Mensual de Compras" icono="🛒"
          descripcion="Muestra una matriz de las compras por rubro y por mes, para una ventana móvil de 12 meses que termina en el mes y año elegidos. Cada celda es la suma de las compras de ese rubro imputadas a ese mes (las notas de crédito restan; se excluyen los comprobantes de impuestos). Cada rubro tiene su Total Anual y el porcentaje que representa sobre el total general, ordenados de mayor a menor. Se puede exportar a Excel."
          intro='Compras por <b>rubro</b> a lo largo de los <b>últimos 12 meses</b>.'
          :pasos="['Elegí el <b>mes</b> y <b>año</b> de cierre de la ventana.', 'La grilla se actualiza sola.', '<b>Generar Archivo Excel</b> exporta la matriz.']"
          :notas="['Las notas de crédito restan.', 'El porcentaje es sobre el total general de compras.']" />
      </div>
    </div>

    <transition name="msg"><div v-if="msg" class="cm-msg">{{ msg }}</div></transition>
    <div v-if="cargando" class="cm-load">Calculando…</div>

    <div v-if="filas.length" class="cm-scroll">
      <table class="cm-tabla">
        <thead>
          <tr>
            <th class="rub">Rubro</th><th class="det">Descripción</th>
            <th v-for="(m, i) in meses" :key="i" class="num">{{ m }}</th>
            <th class="num tot">Total Anual</th><th class="num por">Porcentaje</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, idx) in filas" :key="f.codigo" :class="{ alt: idx % 2 === 1 }">
            <td class="rub">{{ f.codigo }}</td><td class="det">{{ f.nombre }}</td>
            <td v-for="(v, i) in f.montos" :key="i" class="num" :class="{ neg: v < 0 }">{{ v ? money(v) : '' }}</td>
            <td class="num tot">{{ money(f.total) }}</td>
            <td class="num por">{{ f.porcentaje.toFixed(2) }}%</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="cm-tot">
            <td colspan="2">TOTAL</td>
            <td v-for="(v, i) in total.montos" :key="i" class="num">{{ money(v) }}</td>
            <td class="num">{{ money(total.total) }}</td><td class="num">{{ total.porcentaje.toFixed(2) }}%</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div v-else-if="!cargando" class="cm-vacio">Sin compras en el período.</div>
  </div>
</template>

<script setup lang="ts">
/**
 * ComprasMensualView.vue — Estadísticas › Comparativas Mensuales › Compras.
 * Matriz rubro × 12 meses (ventana móvil) con Total Anual y Porcentaje. Migra
 * estadistica_compras_mensual.scx.
 */
import { ref } from 'vue'
import api from '@/services/auth'
import * as XLSX from 'xlsx'
import { guardarDesdeUrl } from '@/utils/descargas'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'

interface Fila { codigo: number; nombre: string; montos: number[]; total: number; porcentaje: number }

const hoy = new Date(); hoy.setMonth(hoy.getMonth() - 1)
const mes = ref(hoy.getMonth() + 1)
const anio = ref(hoy.getFullYear())
const meses = ref<string[]>([])
const filas = ref<Fila[]>([])
const total = ref<{ montos: number[]; total: number; porcentaje: number }>({ montos: [], total: 0, porcentaje: 0 })
const cargando = ref(false)
const msg = ref('')

const money = (v: number) => new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Math.round(v))

async function cargar () {
  if (mes.value < 1 || mes.value > 12) return
  cargando.value = true; msg.value = ''
  try {
    const d = (await api.get('/tablero/gestion/compras-mensual', { params: { mes: mes.value, anio: anio.value } })).data
    meses.value = d.meses; filas.value = d.filas; total.value = d.total
  } catch { filas.value = []; msg.value = 'No se pudo cargar la estadística.' }
  finally { cargando.value = false }
}

function aExcel () {
  const heads = ['Rubro', 'Descripción', ...meses.value, 'Total Anual', 'Porcentaje']
  const rows = filas.value.map(f => [f.codigo, f.nombre, ...f.montos, f.total, f.porcentaje])
  rows.push(['', 'TOTAL', ...total.value.montos, total.value.total, total.value.porcentaje])
  const ws = XLSX.utils.aoa_to_sheet([heads, ...rows])
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Compras Mensuales')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  guardarDesdeUrl(url, `COMPRAS_ANUALES_A_${mes.value}_${anio.value}.xlsx`)
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

cargar()
</script>

<style scoped>
.cm { padding: 14px 16px 40px; color: #1e293b; }
.cm-cab { display: flex; align-items: center; gap: 12px; background: #14532d; color: #fff; padding: 12px 16px; border-radius: 10px; }
.cm-cab-ico { font-size: 28px; } .cm-cab-tx h1 { margin: 0; font-size: 19px; } .cm-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.cm-acc { margin-left: auto; display: flex; gap: 8px; align-items: flex-end; }
.cm-f { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 700; color: #bbf7d0; }
.cm-in { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 8px; font-size: 13px; color: #1e293b; background: #fff; width: 72px; }
.cm-btn { border: none; border-radius: 7px; padding: 8px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #16a34a; color: #fff; }
.cm-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 13px; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.cm-load { margin-top: 16px; color: #64748b; font-size: 13px; }
.cm-vacio { margin-top: 40px; text-align: center; color: #94a3b8; font-size: 14px; }
.cm-scroll { overflow-x: auto; border: 1px solid #cbd5e1; border-radius: 8px; margin-top: 14px; }
.cm-tabla { width: 100%; border-collapse: collapse; font-size: 11.5px; white-space: nowrap; }
.cm-tabla th { position: sticky; top: 0; background: #cfe8cf; color: #14532d; padding: 5px 8px; text-align: right; font-weight: 700; border-bottom: 1px solid #a7d3a7; }
.cm-tabla th.rub, .cm-tabla th.det { text-align: left; }
.cm-tabla td { padding: 3px 8px; border-bottom: 1px solid #e2e8f0; color: #14532d; }
.cm-tabla tr.alt td { background: #eef7ee; }
.cm-tabla td.rub { text-align: center; font-weight: 700; }
.cm-tabla td.det { text-align: left; font-weight: 600; color: #0f3d1f; }
.cm-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cm-tabla td.num.neg { color: #b91c1c; }
.cm-tabla td.tot, .cm-tabla th.tot { font-weight: 800; background: #dcfce7; }
.cm-tabla td.por, .cm-tabla th.por { font-weight: 700; }
.cm-tabla tr.cm-tot td { background: #14532d; color: #fff; font-weight: 800; padding: 6px 8px; position: sticky; bottom: 0; }
</style>
