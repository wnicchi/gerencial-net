<!-- ResumenLiquidacionesView.vue — Exportar Resumen Liquidaciones (sueldos_informes). -->
<template>
  <div class="rl-view">
    <div class="rl-cab">
      <div class="rl-cab-ico">📤</div>
      <div class="rl-cab-tx"><h1>Exportar Resumen Liquidaciones</h1><p>Planilla resumen por empleado y tipo de liquidación</p></div>
      <button class="rl-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="rl-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ResumenLiquidacionesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/resumen-liquidaciones" titulo="Asistente IA — Resumen Liquidaciones"
            subtitulo="Preguntá sobre la planilla resumen" :sugerencias="['¿Qué muestra cada columna?','¿Por qué los adelantos restan?','¿Cómo exporto?']" @close="modalIA = false" />

    <div class="rl-filtros">
      <div class="rl-f"><span class="rl-lbl">Empresa</span><select v-model.number="f.empresa" class="rl-sel"><option :value="0">Todas</option><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="rl-f"><span class="rl-lbl">Contratista</span><select v-model.number="f.contratista" class="rl-sel"><option :value="0">Todas</option><option v-for="o in cat.contratistas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="rl-f"><span class="rl-lbl">Lugar</span><select v-model.number="f.lugar" class="rl-sel"><option :value="0">Todos</option><option v-for="o in cat.lugares" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="rl-f"><span class="rl-lbl">Convenio</span><select v-model.number="f.convenio" class="rl-sel"><option :value="0">Todos</option><option v-for="o in cat.convenios" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="rl-f"><span class="rl-lbl">Categoría</span><select v-model.number="f.categoria" class="rl-sel"><option :value="0">Todas</option><option v-for="o in cat.categorias" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="rl-f"><span class="rl-lbl">Banco</span><select v-model.number="f.banco" class="rl-sel"><option :value="0">Todos</option><option v-for="o in cat.bancos" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="rl-f"><span class="rl-lbl">Período</span><div class="rl-fechas"><input v-model="f.fecha1" type="date" class="rl-fin" /> al <input v-model="f.fecha2" type="date" class="rl-fin" /></div></div>
      <button class="rl-btn-ok" :disabled="cargando || !f.fecha1 || !f.fecha2" @click="generar">{{ cargando ? '⟳…' : 'GENERAR PLANILLA' }}</button>
    </div>

    <div v-if="columnas.length" class="rl-acc">
      <span class="rl-tot">{{ filas.length }} empleados · {{ columnas.length }} tipos</span>
      <button class="rl-btn-excel" @click="exportarExcel">📊 Generar Excel</button>
      <button class="rl-btn-pdf" @click="imprimir">📄 Generar PDF</button>
    </div>

    <div v-if="columnas.length" class="rl-wrap">
      <table class="rl-grid">
        <thead><tr><th>Legajo</th><th>Nombre</th><th v-for="(c, i) in columnas" :key="i" class="r">{{ c }}</th><th class="r tot">TOTAL</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in filas" :key="i">
            <td>{{ r.legajo }}</td><td>{{ r.nombre }}</td>
            <td v-for="(v, j) in r.valores" :key="j" class="r" :class="{ neg: v < 0 }">{{ v ? money(v) : '' }}</td>
            <td class="r tot">{{ money(r.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="consultado" class="rl-vacio">No hay liquidaciones para el período y filtros seleccionados.</div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="rl-pdf-ov" @click.self="cerrarPdf">
        <div class="rl-pdf-md">
          <div class="rl-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="rl-pdf-acc">
              <button class="rl-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="rl-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="rl-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="rl-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ResumenLiquidacionesAyuda from '@/components/ResumenLiquidacionesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl, guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date()
const cat = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], bancos: [] })
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
// Por defecto: mes en curso desde el día 1 hasta hoy; empresa AUTOELEVADORES al cargar catálogos.
const f = reactive({ empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, banco: 0, fecha1: _iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), fecha2: _iso(hoy) })
const columnas = ref<string[]>([]); const filas = ref<any[]>([]); const cargando = ref(false); const consultado = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const generar = async () => {
  cargando.value = true; consultado.value = true
  try { const { data } = await api.get('/resumen-liquidaciones', { params: f }); columnas.value = data.columnas; filas.value = data.filas }
  catch (e) { console.error(e); columnas.value = []; filas.value = [] } finally { cargando.value = false }
}

const exportarExcel = async () => {
  const head = `<th>LEGAJO</th><th>NOMBRE</th>${columnas.value.map(c => `<th>${esc(c)}</th>`).join('')}<th>TOTAL</th>`
  const fil = filas.value.map(r => `<tr><td>${r.legajo}</td><td>${esc(r.nombre)}</td>${r.valores.map((v: number) => `<td>${v}</td>`).join('')}<td>${r.total}</td></tr>`).join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr style="background:#1b4332;color:#fff;font-weight:bold">${head}</tr>${fil}</table></body></html>`
  await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), 'PLANILLA_LIQUIDACIONES.xls')
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const mL = 8, mR = 289; let y = 0
  const cols = columnas.value
  const wNom = 50; const wCol = Math.max(16, (mR - mL - wNom - 24 - 24) / Math.max(1, cols.length))
  const cab = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.text('PLANILLA DE LIQUIDACIONES', mL, 12)
    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.text(`Del ${fmt(f.fecha1)} al ${fmt(f.fecha2)}`, mR, 12, { align: 'right' })
    doc.setLineWidth(0.3); doc.line(mL, 15, mR, 15); y = 20
    doc.setFont('helvetica', 'bold'); doc.setFontSize(6)
    doc.text('Leg', mL, y); doc.text('Nombre', mL + 12, y)
    cols.forEach((c, i) => doc.text(c.slice(0, 12), mL + wNom + 12 + i * wCol + wCol - 1, y, { align: 'right' }))
    doc.text('TOTAL', mR, y, { align: 'right' }); y += 1; doc.line(mL, y, mR, y); y += 3; doc.setFont('helvetica', 'normal')
  }
  cab()
  for (const r of filas.value) {
    if (y > 200) { doc.addPage(); cab() }
    doc.setFontSize(6); doc.text(String(r.legajo), mL, y); doc.text((r.nombre || '').slice(0, 26), mL + 12, y)
    r.valores.forEach((v: number, i: number) => doc.text(v ? money(v) : '', mL + wNom + 12 + i * wCol + wCol - 1, y, { align: 'right' }))
    doc.setFont('helvetica', 'bold'); doc.text(money(r.total), mR, y, { align: 'right' }); doc.setFont('helvetica', 'normal'); y += 3.6
  }
  cerrarPdf(); pdfNombre.value = 'Resumen_Liquidaciones.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''
const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))

onMounted(async () => {
  const g = async (u: string) => { try { return (await api.get(u)).data } catch { return [] } }
  cat.empresas = mapCat(await g('/empresas')); cat.contratistas = mapCat(await g('/contratistas')); cat.lugares = mapCat(await g('/lugares'))
  cat.convenios = mapCat(await g('/convenios')); cat.categorias = mapCat(await g('/categorias')); cat.bancos = mapCat(await g('/ctas-bancarias'))
  const autoelev = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (autoelev) f.empresa = autoelev.cod
})
</script>

<style scoped>
.rl-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.rl-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.rl-cab-ico { font-size:28px; } .rl-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .rl-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.rl-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rl-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rl-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.rl-f { display:flex; flex-direction:column; gap:4px; } .rl-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.rl-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:150px; }
.rl-fechas { display:flex; align-items:center; gap:6px; font-size:12px; } .rl-fin { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:12px; }
.rl-btn-ok { background:#16a34a; color:#fff; border:none; padding:8px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .rl-btn-ok:disabled { background:#cbd5e1; }
.rl-acc { display:flex; align-items:center; gap:12px; padding:12px 18px 0; }
.rl-tot { font-size:13px; color:#475569; margin-right:auto; }
.rl-btn-excel { background:#fff; border:1px solid #16a34a; color:#15803d; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.rl-btn-pdf { background:#2563eb; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.rl-wrap { margin:12px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:64vh; }
.rl-grid { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.rl-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:6px 9px; font-size:11px; text-align:left; } .rl-grid th.r { text-align:right; } .rl-grid th.tot { background:#a16207; }
.rl-grid td { padding:4px 9px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .rl-grid td.r { text-align:right; } .rl-grid td.neg { color:#b91c1c; }
.rl-grid tr:nth-child(even) td { background:#f8fafc; } .rl-grid td.tot { background:#fef9c3 !important; font-weight:700; }
.rl-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.rl-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.rl-pdf-md { width:min(1100px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.rl-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.rl-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.rl-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .rl-pdf-b.ok { background:#22c55e; color:#fff; } .rl-pdf-b.cancel { background:#ef4444; color:#fff; }
.rl-pdf-frame { flex:1; border:none; width:100%; }
</style>
