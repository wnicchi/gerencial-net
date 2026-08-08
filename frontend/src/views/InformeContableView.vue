<template>
  <div class="ic">
    <div class="ic-cab">
      <div class="ic-cab-ico">📒</div>
      <div class="ic-cab-tx">
        <h1>Informe Contable de Ventas</h1>
        <p>IVA Ventas por jurisdicción, cobranzas, compras y pagos del período</p>
      </div>
      <ModuloAyudaIA style="margin-left:auto" modulo="Informe Contable de Ventas" icono="📒"
        descripcion="Informe contable mensual (solo lectura) de LOGÍSTICA. Arma el IVA de Ventas por rubro discriminado por jurisdicción (Todas, Rosario, Alvear y Pérez) con Gravado 21% y 10.5%, IVA 21% y 10.5%, IIBB Santa Fe, percepciones ARBA y CABA, Exento y Total; las notas de crédito restan. Suma además Cobranzas y Retenciones, el Detalle de Compras por rubro y los Pagos del mes."
        :sugerencias="['¿Qué muestra este informe?', '¿Cómo se arman las jurisdicciones?', '¿Qué es la diferencia del resumen?']"
        intro="Réplica del informe contable de ventas: IVA discriminado, cobranzas, compras y pagos."
        :pasos="['<b>Elegí</b> mes y año (o un rango Desde/Hasta).', '<b>Generá</b> el informe.', 'Recorré las secciones; <b>Excel</b> baja el IVA Ventas por jurisdicción.']" />
    </div>

    <div class="ic-filtros">
      <label class="ic-f">Mes<input v-model.number="mes" type="number" min="1" max="12" class="ic-in" /></label>
      <label class="ic-f">Año<input v-model.number="anio" type="number" min="2002" max="2060" class="ic-in" /></label>
      <span class="ic-sep">o rango</span>
      <label class="ic-f">Desde<input v-model="desde" type="date" class="ic-in ic-fecha" /></label>
      <label class="ic-f">Hasta<input v-model="hasta" type="date" class="ic-in ic-fecha" /></label>
      <button class="ic-btn gen" :disabled="cargando" @click="generar">{{ cargando ? 'Generando…' : 'GENERAR INFORME' }}</button>
      <button v-if="data" class="ic-btn" @click="aExcel">📊 Excel</button>
    </div>

    <transition name="msg"><div v-if="msg" class="ic-msg err">{{ msg }}</div></transition>

    <template v-if="data">
      <!-- ── IVA Ventas por jurisdicción ── -->
      <div v-for="j in data.jurisdicciones" :key="j.key" class="ic-bloque">
        <div class="ic-tit">IVA Ventas — {{ j.label }}</div>
        <div class="ic-scroll">
          <table class="ic-tabla">
            <thead>
              <tr>
                <th class="a">Rubro</th>
                <th>Gravado 21%</th><th>Gravado 10.5%</th>
                <th>IVA 21%</th><th>IVA 10.5%</th>
                <th>IIBB Santa Fe</th><th>Percep. ARBA</th><th>Percep. CABA</th>
                <th>Exento</th><th class="t">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in j.filas" :key="f.cod">
                <td class="a">{{ f.cod }} · {{ f.des }}</td>
                <td class="num" :class="{ neg: f.g21 < 0 }">{{ money(f.g21) }}</td>
                <td class="num" :class="{ neg: f.g10 < 0 }">{{ money(f.g10) }}</td>
                <td class="num" :class="{ neg: f.i21 < 0 }">{{ money(f.i21) }}</td>
                <td class="num" :class="{ neg: f.i10 < 0 }">{{ money(f.i10) }}</td>
                <td class="num" :class="{ neg: f.pib < 0 }">{{ money(f.pib) }}</td>
                <td class="num" :class="{ neg: f.arb < 0 }">{{ money(f.arb) }}</td>
                <td class="num" :class="{ neg: f.cab < 0 }">{{ money(f.cab) }}</td>
                <td class="num" :class="{ neg: f.exe < 0 }">{{ money(f.exe) }}</td>
                <td class="num t" :class="{ neg: f.tot < 0 }">{{ money(f.tot) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="ic-tot">
                <td class="a">TOTALES</td>
                <td class="num">{{ money(j.total.g21) }}</td><td class="num">{{ money(j.total.g10) }}</td>
                <td class="num">{{ money(j.total.i21) }}</td><td class="num">{{ money(j.total.i10) }}</td>
                <td class="num">{{ money(j.total.pib) }}</td><td class="num">{{ money(j.total.arb) }}</td><td class="num">{{ money(j.total.cab) }}</td>
                <td class="num">{{ money(j.total.exe) }}</td><td class="num t">{{ money(j.total.tot) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- ── Resumen IVA Ventas ── -->
      <div class="ic-bloque">
        <div class="ic-tit">Resumen I.V.A. Ventas</div>
        <div class="ic-resumen">
          <div class="ic-kpi"><span>Gravado 21%</span><b>{{ money(data.resumen.g21) }}</b></div>
          <div class="ic-kpi"><span>Gravado 10.5%</span><b>{{ money(data.resumen.g10) }}</b></div>
          <div class="ic-kpi"><span>IVA 21%</span><b>{{ money(data.resumen.i21) }}</b></div>
          <div class="ic-kpi"><span>IVA 10.5%</span><b>{{ money(data.resumen.i10) }}</b></div>
          <div class="ic-kpi"><span>Exento</span><b>{{ money(data.resumen.exe) }}</b></div>
          <div class="ic-kpi"><span>IIBB Santa Fe</span><b>{{ money(data.resumen.pib) }}</b></div>
          <div class="ic-kpi"><span>ARBA</span><b>{{ money(data.resumen.arb) }}</b></div>
          <div class="ic-kpi"><span>CABA</span><b>{{ money(data.resumen.cab) }}</b></div>
          <div class="ic-kpi tot"><span>TOTAL</span><b>{{ money(data.resumen.tot) }}</b></div>
          <div class="ic-kpi" :class="{ warn: Math.abs(data.resumen.dif) > 0.5 }"><span>Diferencia</span><b>{{ money(data.resumen.dif) }}</b></div>
        </div>
      </div>

      <!-- ── Cobranzas y Retenciones ── -->
      <div class="ic-bloque">
        <div class="ic-tit">Cobranzas y Retenciones</div>
        <table class="ic-tabla chica">
          <tbody>
            <tr><td class="a">NETO COBRADO</td><td class="num t">{{ money(data.cobranzas.neto) }}</td></tr>
            <tr v-for="(r, i) in data.cobranzas.retenciones" :key="i"><td class="a sub">Ret. {{ r.des }}</td><td class="num">{{ money(r.imp) }}</td></tr>
            <tr><td class="a">TOTAL RETENCIONES</td><td class="num">{{ money(data.cobranzas.totalRet) }}</td></tr>
            <tr class="ic-tot"><td class="a">TOTAL COBRANZAS</td><td class="num t">{{ money(data.cobranzas.totalCobranzas) }}</td></tr>
          </tbody>
        </table>
      </div>

      <!-- ── Detalle de Compras ── -->
      <div class="ic-bloque">
        <div class="ic-tit">Detalle de Compras</div>
        <div class="ic-scroll">
          <table class="ic-tabla">
            <thead>
              <tr>
                <th class="a">Rubro Compras</th>
                <th>Gravado</th><th>IVA 10.5%</th><th>IVA 21%</th><th>IVA 27%</th>
                <th>No Gravado</th><th>Retenciones</th><th class="t">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in data.compras.filas" :key="f.cod">
                <td class="a">{{ f.des }} ({{ f.cod }})</td>
                <td class="num">{{ money(f.gra) }}</td><td class="num">{{ money(f.i10) }}</td>
                <td class="num">{{ money(f.iin) }}</td><td class="num">{{ money(f.i27) }}</td>
                <td class="num">{{ money(f.ino) }}</td><td class="num">{{ money(f.ret) }}</td>
                <td class="num t">{{ money(f.tot) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="ic-tot">
                <td class="a">Total de Compras</td>
                <td class="num">{{ money(data.compras.total.gra) }}</td><td class="num">{{ money(data.compras.total.i10) }}</td>
                <td class="num">{{ money(data.compras.total.iin) }}</td><td class="num">{{ money(data.compras.total.i27) }}</td>
                <td class="num">{{ money(data.compras.total.ino) }}</td><td class="num">{{ money(data.compras.total.ret) }}</td>
                <td class="num t">{{ money(data.compras.total.tot) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- ── Pagos ── -->
      <div class="ic-bloque ic-cols">
        <div>
          <div class="ic-tit">Pagos del período</div>
          <table class="ic-tabla chica">
            <tbody>
              <tr><td class="a">Pagos Total Proveed. x Compras</td><td class="num">{{ money(data.pagos.proveedores) }}</td></tr>
              <tr><td class="a sub">Retenc. Ganancias</td><td class="num">{{ money(data.pagos.ganancias) }}</td></tr>
              <tr><td class="a sub">Retenc. Ing. Brutos</td><td class="num">{{ money(data.pagos.iibb) }}</td></tr>
              <tr><td class="a sub">Retenc. SUSS</td><td class="num">{{ money(data.pagos.suss) }}</td></tr>
              <tr><td class="a sub">Retenc. Limpieza</td><td class="num">{{ money(data.pagos.limpieza) }}</td></tr>
              <tr><td class="a sub">ARBA</td><td class="num">{{ money(data.pagos.arba) }}</td></tr>
              <tr><td class="a sub">CABA</td><td class="num">{{ money(data.pagos.caba) }}</td></tr>
              <tr class="ic-tot"><td class="a">Pagos Netos Proveed. x Compras</td><td class="num t">{{ money(data.pagos.netos) }}</td></tr>
            </tbody>
          </table>
        </div>
        <div>
          <div class="ic-tit">Pagos Cuentas Vs / Impuestos</div>
          <div class="ic-scroll ic-alto">
            <table class="ic-tabla chica">
              <tbody>
                <tr v-for="c in data.pagos.cuentas" :key="c.cod"><td class="a">{{ c.cod }} · {{ c.nom }}</td><td class="num">{{ money(c.imp) }}</td></tr>
                <tr class="ic-tot"><td class="a">Total</td><td class="num t">{{ money(data.pagos.totalCuentas) }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * InformeContableView.vue — Tablero › Gestión › Listados › Informe Contable de Ventas.
 * Réplica (solo lectura) del Fox ventas_informe_contable.scx (LOGÍSTICA).
 */
import { ref } from 'vue'
import api from '@/services/auth'
import * as XLSX from 'xlsx'
import { guardarDesdeUrl } from '@/utils/descargas'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'

const hoy = new Date()
const mes = ref(hoy.getMonth() + 1)
const anio = ref(hoy.getFullYear())
const desde = ref('')
const hasta = ref('')
const data = ref<any>(null)
const cargando = ref(false)
const msg = ref('')

const money = (v: number) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0)

async function generar () {
  cargando.value = true; msg.value = ''
  try {
    const params: any = {}
    if (desde.value && hasta.value) { params.desde = desde.value; params.hasta = hasta.value }
    else { params.mes = mes.value; params.anio = anio.value }
    data.value = (await api.get('/tablero/gestion/informe-contable', { params })).data
  } catch { data.value = null; msg.value = 'No se pudo generar el informe.' }
  finally { cargando.value = false }
}

function aExcel () {
  if (!data.value) return
  const heads = ['Jurisdicción', 'Rubro', 'Subtotal', 'Grav 21%', 'Grav 10.5%', 'IVA', 'IVA 21%', 'IVA 10.5%', 'IIBB', 'ARBA', 'CABA', 'Exento', 'Total']
  const rows: any[] = [heads]
  for (const j of data.value.jurisdicciones) {
    for (const f of j.filas) {
      rows.push([j.label, f.des, f.gra, f.g21, f.g10, f.iin, f.i21, f.i10, f.pib, f.arb, f.cab, f.exe, f.tot])
    }
    const t = j.total
    rows.push([j.label + ' — TOTAL', '', t.gra, t.g21, t.g10, t.iin, t.i21, t.i10, t.pib, t.arb, t.cab, t.exe, t.tot])
    rows.push([])
  }
  const ws = XLSX.utils.aoa_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Informe Contable')
  const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
  const suf = desde.value && hasta.value ? `${desde.value}_a_${hasta.value}` : `${mes.value}_${anio.value}`
  guardarDesdeUrl(url, `INFORME_CONTABLE_${suf}.xlsx`)
  setTimeout(() => URL.revokeObjectURL(url), 4000)
}

generar()
</script>

<style scoped>
.ic { padding: 14px 16px 40px; color: #1e293b; }
.ic-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.ic-cab-ico { font-size: 28px; }
.ic-cab-tx h1 { margin: 0; font-size: 19px; } .ic-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.ic-filtros { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-top: 14px; }
.ic-f { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 700; color: #475569; }
.ic-in { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 8px; font-size: 13px; color: #1e293b; background: #fff; width: 78px; }
.ic-in.ic-fecha { width: 140px; }
.ic-sep { font-size: 11px; color: #94a3b8; padding-bottom: 6px; }
.ic-btn { border: none; border-radius: 7px; padding: 8px 14px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.ic-btn.gen { background: #1b4332; } .ic-btn:disabled { opacity: .6; cursor: default; }
.ic-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 13px; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.ic-bloque { margin-top: 18px; }
.ic-tit { font-size: 13px; font-weight: 800; color: #1b4332; margin-bottom: 6px; }
.ic-scroll { overflow: auto; max-height: calc(100vh - 240px); border: 1px solid #e2e8f0; border-radius: 8px; }
.ic-scroll.ic-alto { max-height: 260px; }
.ic-tabla { width: 100%; border-collapse: collapse; font-size: 11.5px; white-space: nowrap; }
.ic-tabla.chica { font-size: 12px; }
.ic-tabla th { position: sticky; top: 0; background: #2d6a9f; color: #fff; padding: 6px 8px; text-align: right; font-weight: 700; }
.ic-tabla th.a { text-align: left; } .ic-tabla th.t { background: #1b4332; }
.ic-tabla td { padding: 4px 8px; border-bottom: 1px solid #f0f4f9; color: #1e293b; }
.ic-tabla td.a { text-align: left; font-weight: 600; color: #0f3d1f; }
.ic-tabla td.a.sub { padding-left: 22px; font-weight: 500; color: #475569; }
.ic-tabla td.num { text-align: right; font-variant-numeric: tabular-nums; }
.ic-tabla td.num.neg { color: #dc2626; }
.ic-tabla td.t { font-weight: 800; }
.ic-tabla tr.ic-tot td { background: #14532d; color: #fff; font-weight: 800; position: sticky; bottom: 0; }
.ic-tabla tbody tr:hover td:not(.num) { background: #f0f7ff; }
.ic-resumen { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
.ic-kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 12px; display: flex; flex-direction: column; gap: 2px; }
.ic-kpi span { font-size: 11px; color: #64748b; } .ic-kpi b { font-size: 14px; color: #1e293b; font-variant-numeric: tabular-nums; }
.ic-kpi.tot { border-color: #1b4332; background: #f0faf4; } .ic-kpi.tot b { color: #1b4332; }
.ic-kpi.warn { border-color: #f59e0b; background: #fffbeb; } .ic-kpi.warn b { color: #b45309; }
.ic-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
@media (max-width: 820px) { .ic-cols { grid-template-columns: 1fr; } }
</style>
