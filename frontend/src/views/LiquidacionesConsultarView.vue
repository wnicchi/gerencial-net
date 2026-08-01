<!-- LiquidacionesConsultarView.vue — Liquidaciones Consultar (sueldos_consultar). -->
<template>
  <div class="lc-view">
    <div class="lc-cab">
      <div class="lc-cab-ico">🔍</div>
      <div class="lc-cab-tx"><h1>Liquidaciones — Consultar</h1><p>Consulta de una liquidación de sueldo</p></div>
      <button class="lc-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="lc-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <LiquidacionesConsultarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/liquidaciones" titulo="Asistente IA — Liquidaciones"
            subtitulo="Preguntá sobre la consulta de liquidaciones"
            :sugerencias="['¿Cómo busco una liquidación?','¿Qué muestra el total?','¿Qué es el tipo de liquidación?']"
            @close="modalIA = false" />

    <div class="lc-filtros" v-enter-next>
      <div class="lc-busc-wrap">
        <span class="lc-lbl">Nro. Personal</span>
        <input v-model="buscar" type="text" class="lc-busc" placeholder="Código o nombre…" @input="buscarEmp" @focus="buscarEmp" />
        <ul v-if="sugerencias.length" class="lc-sug"><li v-for="e in sugerencias" :key="e.cod" @click="elegir(e)">{{ e.cod }} · {{ e.nombre }}</li></ul>
      </div>
      <span v-if="codigo" class="lc-elegido">{{ codigo }} — {{ nombre }}</span>
      <span class="lc-lbl">Tipo</span>
      <select v-model.number="tipo" class="lc-sel"><option :value="0">— Elegir —</option><option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select>
      <span class="lc-lbl">Mes</span>
      <select v-model.number="mes" class="lc-sel"><option v-for="m in 12" :key="m" :value="m">{{ m }}</option></select>
      <span class="lc-lbl">Año</span>
      <input v-model.number="anio" type="number" class="lc-anio" />
      <button class="lc-btn-ok" :disabled="cargando || !codigo || !tipo" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
    </div>

    <p v-if="aviso" class="lc-aviso">⚠️ {{ aviso }}</p>

    <div v-if="liq" class="lc-cuerpo">
      <div class="lc-info">
        <span><b>Liquidación N°:</b> {{ liq.nro }}</span><span><b>Empleado:</b> {{ liq.nombre }}</span>
        <span><b>CUIL:</b> {{ liq.cuil }}</span><span><b>Tipo:</b> {{ liq.tipo }}</span><span><b>Período:</b> {{ liq.mes }}/{{ liq.anio }}</span>
      </div>
      <div class="lc-wrap">
        <table class="lc-grid">
          <thead><tr><th>Cód</th><th>Concepto</th><th class="r">Cantidad</th><th class="r">Haber</th><th class="r">Deducción</th></tr></thead>
          <tbody>
            <tr v-for="(it, i) in items" :key="i">
              <td>{{ it.codigo }}</td><td>{{ it.detalle }}</td><td class="r">{{ it.cantidad ? money(it.cantidad) : '' }}</td>
              <td class="r">{{ it.haber ? money(it.haber) : '' }}</td><td class="r">{{ it.deduccion ? money(it.deduccion) : '' }}</td>
            </tr>
          </tbody>
          <tfoot><tr class="lc-total"><td colspan="3"></td><td class="r">TOTAL LIQUIDACIÓN</td><td class="r">{{ money(total) }}</td></tr></tfoot>
        </table>
      </div>
      <div class="lc-total-box"><span>TOTAL LIQUIDACIÓN</span><b>{{ money(total) }}</b></div>
      <button class="lc-btn-pdf" @click="imprimir">📄 Visualizar / Imprimir PDF</button>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="lc-pdf-ov" @click.self="cerrarPdf">
        <div class="lc-pdf-md">
          <div class="lc-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="lc-pdf-acc">
              <button class="lc-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="lc-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="lc-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="lc-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import LiquidacionesConsultarAyuda from '@/components/LiquidacionesConsultarAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Item { codigo: number; detalle: string; cantidad: number; haber: number; deduccion: number }
const hoy = new Date()
const buscar = ref(''); const sugerencias = ref<{ cod: number; nombre: string }[]>([])
const codigo = ref(0); const nombre = ref('')
const tipos = ref<{ cod: number; desc: string }[]>([])
const tipo = ref(0); const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const liq = ref<any>(null); const items = ref<Item[]>([]); const total = ref(0)
const cargando = ref(false); const aviso = ref('')
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

let tB: any
const buscarEmp = () => {
  clearTimeout(tB)
  tB = setTimeout(async () => {
    const q = buscar.value.trim()
    if (!q) { sugerencias.value = []; return }
    try { sugerencias.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data.map((e: any) => ({ cod: e.PER_COD ?? e.cod, nombre: (e.PER_NOM ?? e.nombre ?? '').trim() })) }
    catch (e) { console.error(e) }
  }, 250)
}
const elegir = (e: { cod: number; nombre: string }) => { codigo.value = e.cod; nombre.value = e.nombre; buscar.value = ''; sugerencias.value = [] }

const consultar = async () => {
  cargando.value = true; aviso.value = ''; liq.value = null
  try {
    const { data } = await api.get('/liquidaciones/consultar', { params: { codigo: codigo.value, tipo: tipo.value, mes: mes.value, anio: anio.value } })
    liq.value = data.liquidacion; items.value = data.items; total.value = data.total
  } catch (e: any) {
    if (e?.response?.status === 404) aviso.value = e.response.data.message
    else { console.error(e); aviso.value = 'No se pudo consultar.' }
  } finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.text('LIQUIDACIÓN', mL, y); y += 7
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  doc.text(`N° ${liq.value.nro}   Empleado: ${liq.value.empleado_cod} ${liq.value.nombre}   CUIL: ${liq.value.cuil}`, mL, y); y += 5
  doc.text(`Tipo: ${liq.value.tipo}   Período: ${liq.value.mes}/${liq.value.anio}`, mL, y); y += 4
  doc.setLineWidth(0.4); doc.line(mL, y, mR, y); y += 5
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5)
  doc.text('Cód', mL, y); doc.text('Concepto', mL + 16, y); doc.text('Haber', mL + 130, y, { align: 'right' }); doc.text('Deducción', mR, y, { align: 'right' }); y += 1
  doc.line(mL, y, mR, y); y += 4; doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  for (const it of items.value) {
    if (y > 280) { doc.addPage(); y = 20 }
    doc.text(String(it.codigo), mL, y); doc.text(it.detalle.slice(0, 60), mL + 16, y)
    doc.text(it.haber ? money(it.haber) : '', mL + 130, y, { align: 'right' }); doc.text(it.deduccion ? money(it.deduccion) : '', mR, y, { align: 'right' }); y += 4.6
  }
  doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
  doc.text(`TOTAL LIQUIDACIÓN: ${money(total.value)}`, mR, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = `Liquidacion_${liq.value.nro}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(async () => { try { tipos.value = (await api.get('/liquidaciones/tipos')).data } catch (e) { console.error(e) } })
</script>

<style scoped>
.lc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.lc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.lc-cab-ico { font-size:28px; } .lc-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .lc-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.lc-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.lc-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.lc-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.lc-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.lc-busc-wrap { position:relative; }
.lc-busc { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; width:200px; outline:none; }
.lc-sug { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; list-style:none; margin:0; padding:0; max-height:240px; overflow:auto; z-index:20; box-shadow:0 8px 20px rgba(0,0,0,.12); }
.lc-sug li { padding:7px 10px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:#1e293b; } .lc-sug li:hover { background:#eef6ee; color:#1b4332; }
.lc-elegido { font-size:13px; font-weight:600; color:#1b4332; background:#dcfce7; padding:5px 10px; border-radius:6px; }
.lc-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; } .lc-anio { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; width:80px; }
.lc-btn-ok { background:#16a34a; color:#fff; border:none; padding:9px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .lc-btn-ok:disabled { background:#cbd5e1; }
.lc-aviso { margin:12px 18px; background:#fef9c3; border:1px solid #facc15; color:#854d0e; padding:9px 14px; border-radius:6px; font-size:13px; }
.lc-cuerpo { padding:14px 18px; }
.lc-info { display:flex; flex-wrap:wrap; gap:16px; font-size:13px; color:#475569; margin-bottom:10px; } .lc-info b { color:#1e293b; }
.lc-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:60vh; margin-bottom:12px; }
.lc-grid { width:100%; border-collapse:collapse; font-size:13px; }
.lc-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .lc-grid th.r { text-align:right; }
.lc-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .lc-grid td.r { text-align:right; }
.lc-grid tr:nth-child(even) td { background:#f8fafc; }
.lc-grid tfoot tr.lc-total td { background:#1b4332; color:#fff; font-weight:800; font-size:15px; padding:10px; border-top:3px solid #14532d; position:sticky; bottom:0; }
.lc-total-box { display:flex; align-items:center; justify-content:flex-end; gap:18px; background:linear-gradient(120deg,#1b4332,#16a34a); color:#fff; border-radius:10px; padding:14px 22px; margin-bottom:12px; box-shadow:0 4px 14px rgba(22,101,52,.3); }
.lc-total-box span { font-size:14px; font-weight:600; letter-spacing:.5px; } .lc-total-box b { font-size:24px; font-weight:800; }
.lc-btn-pdf { background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.lc-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.lc-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.lc-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.lc-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.lc-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .lc-pdf-b.ok { background:#22c55e; color:#fff; } .lc-pdf-b.cancel { background:#ef4444; color:#fff; }
.lc-pdf-frame { flex:1; border:none; width:100%; }
</style>
