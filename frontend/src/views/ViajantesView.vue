<!-- ViajantesView.vue — Planilla Libro de Viajantes (planilla_libro_viajantes). -->
<template>
  <div class="vj-view">
    <div class="vj-cab">
      <div class="vj-cab-ico">🧳</div>
      <div class="vj-cab-tx"><h1>Planilla Libro de Viajantes</h1><p>Comisiones por ventas y cobros de un vendedor</p></div>
      <button class="vj-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vj-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ViajantesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/viajantes" titulo="Asistente IA — Libro de Viajantes"
            subtitulo="Preguntá sobre las comisiones del viajante"
            :sugerencias="['¿Cómo se calcula la comisión por venta?','¿Y la de cobros?','¿Por qué dice que no es vendedor?','¿Qué hace el filtro por rubro?']"
            @close="modalIA = false" />

    <div class="vj-filtros">
      <div class="vj-busc-wrap">
        <span class="vj-lbl">Vendedor</span>
        <input v-model="buscar" type="text" class="vj-busc" placeholder="Buscar por nombre o legajo…" @input="buscarEmp" @focus="buscarEmp" />
        <ul v-if="sugerencias.length" class="vj-sug">
          <li v-for="e in sugerencias" :key="e.cod" @click="elegir(e)">{{ e.cod }} · {{ e.nombre }}</li>
        </ul>
      </div>
      <div v-if="vendedorCod" class="vj-elegido">{{ vendedorCod }} — {{ vendedorNom }}</div>
      <span class="vj-lbl">Período</span>
      <input v-model="fecha1" type="date" class="vj-fin" /> al <input v-model="fecha2" type="date" class="vj-fin" />
      <button class="vj-btn-ok" :disabled="cargando || !vendedorCod" @click="generar">{{ cargando ? '⟳ Generando…' : 'GENERAR PLANILLA' }}</button>
    </div>

    <p v-if="aviso" class="vj-aviso">⚠️ {{ aviso }}</p>

    <div v-if="filas.length" class="vj-body">
      <div class="vj-grid-wrap">
        <table class="vj-grid">
          <thead><tr>
            <th>Cliente</th><th>Razón Social</th><th>Factura N°</th><th>Fecha</th>
            <th class="r">Monto Factura</th><th class="r">% Vta.</th><th class="r">Com. Venta</th>
            <th class="r">% Cobro</th><th class="r">Com. Cobro</th><th>Rubro</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in filasFiltradas" :key="i">
              <td>{{ f.cli_cod }}</td><td>{{ f.cli_nom }}</td><td>{{ f.comprob }}</td><td>{{ fmt(f.fecha) }}</td>
              <td class="r">{{ money(f.monto) }}</td><td class="r">{{ f.por_vta }}</td><td class="r">{{ money(f.mon_vta) }}</td>
              <td class="r">{{ f.por_cob }}</td><td class="r">{{ money(f.mon_cob) }}</td><td>{{ f.rubro }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="vj-lateral">
        <div class="vj-totales">COMISIÓN POR VENTAS <b>{{ money(totVtas) }}</b> · COMISIÓN POR COBROS <b>{{ money(totCob) }}</b></div>
        <div class="vj-rubros">
          <div class="vj-rubros-cab"><span>Mostrar rubro</span>
            <button class="vj-mini" @click="setTodos(true)">Todo</button>
            <button class="vj-mini" @click="setTodos(false)">Nada</button>
          </div>
          <ul class="vj-rubros-list">
            <li v-for="r in rubros" :key="r.cod"><label><input v-model="rubSel[r.cod]" type="checkbox" /> {{ r.desc }}</label></li>
          </ul>
        </div>
        <div class="vj-acc">
          <button class="vj-btn-pdf" @click="imprimir">🖨 Imprimir libro</button>
          <button class="vj-btn-excel" @click="exportarExcel">📊 Exportar a Excel</button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="vj-pdf-ov" @click.self="cerrarPdf">
        <div class="vj-pdf-md">
          <div class="vj-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="vj-pdf-acc">
              <button class="vj-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="vj-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="vj-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="vj-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ViajantesAyuda from '@/components/ViajantesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl, guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Fila { cli_cod: number; cli_nom: string; comprob: string; fecha: string; monto: number; por_vta: number; mon_vta: number; por_cob: number; mon_cob: number; rubro: string; rubcod: number }
interface Vend { nombre: string; cuil: string; legajo: string; zona: string; emp_nom: string; emp_dom: string; emp_cuit: string }

const hoy = new Date(); const aIso = (d: Date) => d.toISOString().slice(0, 10)
const buscar = ref(''); const sugerencias = ref<{ cod: number; nombre: string }[]>([])
const vendedorCod = ref(0); const vendedorNom = ref('')
const fecha1 = ref(aIso(new Date(hoy.getFullYear(), 0, 1))); const fecha2 = ref(aIso(hoy))
const filas = ref<Fila[]>([]); const rubros = ref<{ cod: number; desc: string }[]>([])
const rubSel = reactive<Record<number, boolean>>({})
const vendedor = ref<Vend | null>(null)
const cargando = ref(false); const aviso = ref('')
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''

let tBusc: any
const buscarEmp = () => {
  clearTimeout(tBusc)
  tBusc = setTimeout(async () => {
    try { sugerencias.value = (await api.get('/viajantes/vendedores', { params: { buscar: buscar.value.trim() } })).data }
    catch (e) { console.error(e) }
  }, 250)
}
const elegir = (e: { cod: number; nombre: string }) => { vendedorCod.value = e.cod; vendedorNom.value = e.nombre; buscar.value = ''; sugerencias.value = [] }

const generar = async () => {
  cargando.value = true; aviso.value = ''; filas.value = []
  try {
    const { data } = await api.get('/viajantes/planilla', { params: { legajo: vendedorCod.value, fecha1: fecha1.value, fecha2: fecha2.value } })
    filas.value = data.filas; rubros.value = data.rubros; vendedor.value = data.vendedor
    Object.keys(rubSel).forEach(k => delete rubSel[+k]); for (const r of data.rubros) rubSel[r.cod] = true
  } catch (e: any) {
    if (e?.response?.status === 422) aviso.value = e.response.data.message
    else { console.error(e); aviso.value = 'No se pudo generar la planilla.' }
  } finally { cargando.value = false }
}

const filasFiltradas = computed(() => filas.value.filter(f => rubSel[f.rubcod]))
const totVtas = computed(() => filasFiltradas.value.reduce((s, f) => s + f.mon_vta, 0))
const totCob = computed(() => filasFiltradas.value.reduce((s, f) => s + f.mon_cob, 0))
const setTodos = (v: boolean) => { for (const r of rubros.value) rubSel[r.cod] = v }

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'legal' })
  const mL = 8, mR = 348; let y = 0
  const v = vendedor.value
  const cab = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.text('LIBRO DE VIAJANTES', mL, 12)
    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.text('LEY 14546 ART. 10', mL, 17)
    doc.text(`Período: ${fmt(fecha1.value)} al ${fmt(fecha2.value)}`, mR, 12, { align: 'right' })
    if (v) {
      doc.text(`Empleador: ${v.emp_nom}  ·  CUIT: ${v.emp_cuit}  ·  ${v.emp_dom}`, mL, 23)
      doc.text(`Viajante: ${v.nombre}  ·  CUIL: ${v.cuil}  ·  Legajo: ${v.legajo}  ·  Zona: ${v.zona}`, mL, 28)
    }
    doc.setLineWidth(0.3); doc.line(mL, 31, mR, 31)
    const h = ['Cliente', 'Razón Social', 'Factura', 'Fecha', 'Monto', '% Vta', 'Com.Venta', '% Cob', 'Com.Cobro', 'Rubro']
    const x = [mL, mL + 18, mL + 92, mL + 132, mL + 158, mL + 192, mL + 206, mL + 244, mL + 258, mL + 296]
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7)
    h.forEach((t, i) => doc.text(t, [4, 5, 7, 8].includes(i) ? x[i] + 26 : x[i], 35, [4, 5, 7, 8].includes(i) ? { align: 'right' } : {}))
    y = 39; doc.setFont('helvetica', 'normal')
  }
  cab()
  const x = [mL, mL + 18, mL + 92, mL + 132, mL + 158, mL + 192, mL + 206, mL + 244, mL + 258, mL + 296]
  for (const f of filasFiltradas.value) {
    if (y > 200) { doc.addPage(); cab() }
    doc.text(String(f.cli_cod), x[0], y); doc.text(f.cli_nom.slice(0, 34), x[1], y); doc.text(f.comprob, x[2], y); doc.text(fmt(f.fecha), x[3], y)
    doc.text(money(f.monto), x[4] + 26, y, { align: 'right' }); doc.text(String(f.por_vta), x[5] + 26, y, { align: 'right' }); doc.text(money(f.mon_vta), x[6] + 26, y, { align: 'right' })
    doc.text(String(f.por_cob), x[7] + 26, y, { align: 'right' }); doc.text(money(f.mon_cob), x[8] + 26, y, { align: 'right' }); doc.text(f.rubro.slice(0, 24), x[9], y); y += 4
  }
  doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
  doc.text(`COMISIÓN POR VENTAS: ${money(totVtas.value)}     COMISIÓN POR COBROS: ${money(totCob.value)}`, mR, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = 'Libro_Viajante.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const exportarExcel = async () => {
  const filasH = filasFiltradas.value.map(f =>
    `<tr><td>${f.cli_cod}</td><td>${esc(f.cli_nom)}</td><td>${esc(f.comprob)}</td><td>${fmt(f.fecha)}</td><td>${f.monto}</td><td>${f.por_vta}</td><td>${f.mon_vta}</td><td>${f.por_cob}</td><td>${f.mon_cob}</td><td>${esc(f.rubro)}</td></tr>`).join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>
    <table border="1"><tr style="background:#1b4332;color:#fff;font-weight:bold"><th>Cliente</th><th>Razón Social</th><th>Factura</th><th>Fecha</th><th>Monto</th><th>% Vta</th><th>Com.Venta</th><th>% Cobro</th><th>Com.Cobro</th><th>Rubro</th></tr>${filasH}</table></body></html>`
  await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), `Planilla_Viajante_${vendedorCod.value}.xls`)
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
</script>

<style scoped>
.vj-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vj-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vj-cab-ico { font-size:28px; } .vj-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vj-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vj-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vj-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vj-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.vj-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.vj-busc-wrap { position:relative; }
.vj-busc { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; width:240px; outline:none; }
.vj-sug { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; list-style:none; margin:0; padding:0; max-height:240px; overflow:auto; z-index:20; box-shadow:0 8px 20px rgba(0,0,0,.12); }
.vj-sug li { padding:7px 10px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:#1e293b; } .vj-sug li:hover { background:#eef6ee; color:#1b4332; }
.vj-elegido { font-size:13px; font-weight:600; color:#1b4332; background:#dcfce7; padding:5px 10px; border-radius:6px; }
.vj-fin { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; }
.vj-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .vj-btn-ok:disabled { background:#cbd5e1; }
.vj-aviso { margin:12px 18px; background:#fef9c3; border:1px solid #facc15; color:#854d0e; padding:9px 14px; border-radius:6px; font-size:13px; }
.vj-body { display:flex; gap:14px; padding:14px 18px; align-items:flex-start; }
.vj-grid-wrap { flex:1; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:70vh; }
.vj-grid { width:100%; border-collapse:collapse; font-size:12.5px; white-space:nowrap; }
.vj-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 9px; font-size:11px; text-align:left; } .vj-grid th.r { text-align:right; }
.vj-grid td { padding:4px 9px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .vj-grid td.r { text-align:right; }
.vj-grid tr:nth-child(even) td { background:#f8fafc; }
.vj-lateral { width:280px; flex-shrink:0; display:flex; flex-direction:column; gap:12px; }
.vj-totales { background:#1b4332; color:#fff; padding:12px; border-radius:8px; font-size:12px; line-height:1.7; } .vj-totales b { font-size:14px; }
.vj-rubros { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.vj-rubros-cab { display:flex; align-items:center; gap:8px; padding:8px 10px; background:#f1f5f9; font-size:12px; font-weight:700; color:#1b4332; }
.vj-mini { margin-left:auto; background:#fff; border:1px solid #cbd5e1; padding:3px 9px; border-radius:5px; cursor:pointer; font-size:11px; } .vj-mini:last-child { margin-left:0; }
.vj-rubros-list { list-style:none; margin:0; padding:0; max-height:34vh; overflow:auto; }
.vj-rubros-list li { padding:6px 10px; border-bottom:1px solid #f1f5f9; } .vj-rubros-list label { display:flex; align-items:center; gap:7px; font-size:12.5px; color:#1e293b; cursor:pointer; }
.vj-acc { display:flex; flex-direction:column; gap:8px; }
.vj-btn-pdf { background:#2563eb; color:#fff; border:none; padding:10px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.vj-btn-excel { background:#fff; border:1px solid #16a34a; color:#15803d; padding:10px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.vj-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vj-pdf-md { width:min(1100px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.vj-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.vj-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.vj-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .vj-pdf-b.ok { background:#22c55e; color:#fff; } .vj-pdf-b.cancel { background:#ef4444; color:#fff; }
.vj-pdf-frame { flex:1; border:none; width:100%; }
</style>
