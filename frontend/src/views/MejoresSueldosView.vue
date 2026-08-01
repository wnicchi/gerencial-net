<!-- MejoresSueldosView.vue — Mejores Sueldos / Promedio (sueldos_mejor_precios). -->
<template>
  <div class="ms-view">
    <div class="ms-cab">
      <div class="ms-cab-ico">📈</div>
      <div class="ms-cab-tx"><h1>Mejores Sueldos / Promedio</h1><p>Mejor bruto, mejor neto y promedio por empleado</p></div>
      <button class="ms-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ms-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <MejoresSueldosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/mejores-sueldos" titulo="Asistente IA — Mejores Sueldos"
            subtitulo="Preguntá sobre los cálculos" :sugerencias="['¿Qué diferencia mejor bruto y mejor sueldo?','¿Cómo se calcula el promedio?','¿Para qué tildo los tipos?']" @close="modalIA = false" />

    <div class="ms-body">
      <div class="ms-filtros">
        <div class="ms-f"><span class="ms-lbl">Empresa</span><select v-model.number="f.empresa" class="ms-sel"><option :value="0">Todas</option><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="ms-f"><span class="ms-lbl">Contratista</span><select v-model.number="f.contratista" class="ms-sel"><option :value="0">Todas</option><option v-for="o in cat.contratistas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="ms-f"><span class="ms-lbl">Lugar</span><select v-model.number="f.lugar" class="ms-sel"><option :value="0">Todos</option><option v-for="o in cat.lugares" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="ms-f"><span class="ms-lbl">Convenio</span><select v-model.number="f.convenio" class="ms-sel"><option :value="0">Todos</option><option v-for="o in cat.convenios" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="ms-f"><span class="ms-lbl">Categoría</span><select v-model.number="f.categoria" class="ms-sel"><option :value="0">Todas</option><option v-for="o in cat.categorias" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="ms-f"><span class="ms-lbl">Banco</span><select v-model.number="f.banco" class="ms-sel"><option :value="0">Todos</option><option v-for="o in cat.bancos" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="ms-f"><span class="ms-lbl">Período</span><label class="ms-chk"><input v-model="f.periodo" type="checkbox" /> Rango</label><template v-if="f.periodo"><input v-model="f.fecha1" type="date" class="ms-fin" /> al <input v-model="f.fecha2" type="date" class="ms-fin" /></template></div>
      </div>

      <div class="ms-tipos">
        <div class="ms-tipos-cab"><span>Tipos de liquidación (para Mejor Sueldo y Promedio)</span>
          <button class="ms-mini" @click="setTodos(true)">Todo</button><button class="ms-mini" @click="setTodos(false)">Nada</button>
        </div>
        <ul class="ms-tipos-list">
          <li v-for="t in tipos" :key="t.cod"><label><input v-model="sel[t.cod]" type="checkbox" /> {{ t.desc }}</label></li>
        </ul>
      </div>
    </div>

    <div class="ms-acc">
      <button class="ms-btn-b" :disabled="cargando" @click="generar('bruto')">OBTENER MEJOR SUELDO BRUTO</button>
      <button class="ms-btn-b" :disabled="cargando" @click="generar('sueldo')">OBTENER MEJOR SUELDO</button>
      <button class="ms-btn-b" :disabled="cargando" @click="generar('promedio')">OBTENER SUELDO PROMEDIO</button>
      <span v-if="info" class="ms-info">{{ info }}</span>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ms-pdf-ov" @click.self="cerrarPdf">
        <div class="ms-pdf-md">
          <div class="ms-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ms-pdf-acc">
              <button class="ms-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ms-pdf-b ok" @click="exportarExcel">📊 Excel</button>
              <button class="ms-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ms-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ms-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import MejoresSueldosAyuda from '@/components/MejoresSueldosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl, guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const tipos = ref<any[]>([]); const sel = reactive<Record<number, boolean>>({})
const cat = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], bancos: [] })
const _hoy = new Date()
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const _anioAnt = _hoy.getFullYear() - 1
const _ini = new Date(_anioAnt, 0, 1)    // 1 de enero del año anterior
const _fin = new Date(_anioAnt, 11, 31)  // 31 de diciembre del año anterior
// Por defecto: período del último año completo (año calendario anterior); empresa AUTOELEVADORES al cargar catálogos.
const f = reactive({ empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, banco: 0, periodo: true, fecha1: _iso(_ini), fecha2: _iso(_fin) })
const cargando = ref(false); const info = ref('')
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const setTodos = (v: boolean) => tipos.value.forEach(t => sel[t.cod] = v)

let ultModo = ''; let ultRows: any[] = []
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const generar = async (modo: string) => {
  cargando.value = true; info.value = ''
  try {
    const params: any = { ...f, periodo: f.periodo ? 1 : 0 }
    if (!f.periodo) { delete params.fecha1; delete params.fecha2 }
    if (modo !== 'bruto') params.tipos = Object.keys(sel).filter(k => sel[+k]).map(Number)
    const url = modo === 'bruto' ? '/mejores-sueldos/bruto' : (modo === 'sueldo' ? '/mejores-sueldos/sueldo' : '/mejores-sueldos/promedio')
    const data = (await api.get(url, { params })).data
    if (!data.length) { info.value = 'No hay datos para los filtros.'; return }
    ultModo = modo; ultRows = data; construirPdf(modo, data)
  } catch (e) { console.error(e); info.value = 'No se pudo generar.' } finally { cargando.value = false }
}

const construirPdf = (modo: string, rows: any[]) => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; let y = 0
  const tit = modo === 'bruto' ? 'MEJOR SUELDO BRUTO' : (modo === 'sueldo' ? 'MEJOR SUELDO' : 'SUELDO PROMEDIO')
  const cab = () => { doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text(tit, mL, 14); doc.setLineWidth(0.4); doc.line(mL, 17, mR, 17); y = 23; head() }
  const head = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    if (modo === 'bruto') { doc.text('Legajo', mL, y); doc.text('CUIT', mL + 18, y); doc.text('Nombre', mL + 56, y); doc.text('Mejor Bruto', mL + 130, y, { align: 'right' }); doc.text('Mes/Año', mR, y, { align: 'right' }) }
    else if (modo === 'sueldo') { doc.text('CUIL', mL, y); doc.text('Nombre', mL + 40, y); doc.text('Mes/Año', mL + 130, y, { align: 'right' }); doc.text('Monto', mR, y, { align: 'right' }) }
    else { doc.text('CUIL', mL, y); doc.text('Nombre', mL + 40, y); doc.text('Promedio', mR, y, { align: 'right' }) }
    y += 1; doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 4; doc.setFont('helvetica', 'normal')
  }
  cab()
  for (const r of rows) {
    if (y > 285) { doc.addPage(); cab() }
    if (modo === 'bruto') { doc.text(String(r.legajo), mL, y); doc.text(r.cuit, mL + 18, y); doc.text((r.nombre || '').slice(0, 34), mL + 56, y); doc.text(money(r.mejor_bruto), mL + 130, y, { align: 'right' }); doc.text(`${r.mes}/${r.anio}`, mR, y, { align: 'right' }) }
    else if (modo === 'sueldo') { doc.text(r.cuil, mL, y); doc.text((r.nombre || '').slice(0, 40), mL + 40, y); doc.text(`${r.mes}/${r.anio}`, mL + 130, y, { align: 'right' }); doc.text(money(r.monto), mR, y, { align: 'right' }) }
    else { doc.text(r.cuil, mL, y); doc.text((r.nombre || '').slice(0, 40), mL + 40, y); doc.text(money(r.promedio), mR, y, { align: 'right' }) }
    y += 4.6
  }
  cerrarPdf(); pdfNombre.value = `${tit.replace(/\s/g, '_')}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const exportarExcel = async () => {
  if (!ultRows.length) return
  let header = '', filas = ''
  if (ultModo === 'bruto') { header = '<th>Legajo</th><th>CUIT</th><th>Nombre</th><th>Mejor Bruto</th><th>Mes</th><th>Año</th>'; filas = ultRows.map(r => `<tr><td>${r.legajo}</td><td>${esc(r.cuit)}</td><td>${esc(r.nombre)}</td><td>${r.mejor_bruto}</td><td>${r.mes}</td><td>${r.anio}</td></tr>`).join('') }
  else if (ultModo === 'sueldo') { header = '<th>CUIL</th><th>Nombre</th><th>Mes</th><th>Año</th><th>Monto</th>'; filas = ultRows.map(r => `<tr><td>${r.cuil}</td><td>${esc(r.nombre)}</td><td>${r.mes}</td><td>${r.anio}</td><td>${r.monto}</td></tr>`).join('') }
  else { header = '<th>CUIL</th><th>Nombre</th><th>Promedio</th>'; filas = ultRows.map(r => `<tr><td>${r.cuil}</td><td>${esc(r.nombre)}</td><td>${r.promedio}</td></tr>`).join('') }
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr style="background:#1b4332;color:#fff;font-weight:bold">${header}</tr>${filas}</table></body></html>`
  await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), `${ultModo === 'bruto' ? 'INFORME_SUELDOS' : 'SUELDOS_PROMEDIO'}.xls`)
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))

onMounted(async () => {
  const g = async (u: string) => { try { return (await api.get(u)).data } catch { return [] } }
  tipos.value = await g('/liquidaciones/tipos'); for (const t of tipos.value) sel[t.cod] = true
  cat.empresas = mapCat(await g('/empresas')); cat.contratistas = mapCat(await g('/contratistas')); cat.lugares = mapCat(await g('/lugares'))
  cat.convenios = mapCat(await g('/convenios')); cat.categorias = mapCat(await g('/categorias')); cat.bancos = mapCat(await g('/ctas-bancarias'))
  const autoelev = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (autoelev) f.empresa = autoelev.cod
})
</script>

<style scoped>
.ms-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ms-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ms-cab-ico { font-size:28px; } .ms-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ms-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ms-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ms-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ms-body { display:flex; gap:16px; padding:14px 18px; align-items:flex-start; flex-wrap:wrap; }
.ms-filtros { display:flex; flex-wrap:wrap; gap:12px; flex:1; min-width:300px; }
.ms-f { display:flex; flex-direction:column; gap:4px; } .ms-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.ms-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:160px; }
.ms-fin { border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:12px; } .ms-chk { font-size:12px; display:flex; align-items:center; gap:5px; cursor:pointer; }
.ms-tipos { width:320px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.ms-tipos-cab { display:flex; align-items:center; gap:8px; padding:8px 10px; background:#f1f5f9; font-size:12px; font-weight:700; color:#1b4332; }
.ms-mini { background:#fff; border:1px solid #cbd5e1; padding:3px 9px; border-radius:5px; cursor:pointer; font-size:11px; } .ms-tipos-cab .ms-mini:nth-of-type(1) { margin-left:auto; }
.ms-tipos-list { list-style:none; margin:0; padding:0; max-height:38vh; overflow:auto; }
.ms-tipos-list li { padding:6px 10px; border-bottom:1px solid #f1f5f9; } .ms-tipos-list label { display:flex; align-items:center; gap:7px; font-size:12.5px; color:#1e293b; cursor:pointer; }
.ms-acc { display:flex; align-items:center; gap:12px; padding:0 18px 18px; flex-wrap:wrap; }
.ms-btn-b { background:#16a34a; color:#fff; border:none; padding:11px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ms-btn-b:disabled { background:#cbd5e1; }
.ms-info { font-size:13px; color:#b91c1c; }
.ms-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ms-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ms-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ms-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ms-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ms-pdf-b.ok { background:#22c55e; color:#fff; } .ms-pdf-b.cancel { background:#ef4444; color:#fff; }
.ms-pdf-frame { flex:1; border:none; width:100%; }
</style>
