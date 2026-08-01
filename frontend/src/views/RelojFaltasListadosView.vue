<!-- RelojFaltasListadosView.vue — Reloj / Listados Faltas Diarias (reloj_faltas_diarias_listados). -->
<template>
  <div class="fl-view">
    <div class="fl-cab">
      <div class="fl-cab-ico">📊</div>
      <div class="fl-cab-tx"><h1>Listados Faltas Diarias</h1><p>Listados de faltas y licencias con filtros, agrupación y vacaciones</p></div>
      <button class="fl-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="fl-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojFaltasListadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-faltas-listados" titulo="Asistente IA — Listados de Faltas"
            subtitulo="Preguntá sobre los listados" :sugerencias="['¿Qué hace agrupar por empleado y tipo?','¿Qué incluye el período histórico?','¿Cómo cuento los días?']"
            @close="modalIA = false" />

    <div class="fl-card">
      <div class="fl-filtros">
        <div class="fl-col">
          <div class="fl-row">
            <span class="fl-lbl">Período:</span>
            <label class="fl-rad"><input type="radio" value="historico" v-model="periodo" /> Histórico</label>
            <label class="fl-rad"><input type="radio" value="rango" v-model="periodo" /> Rango</label>
          </div>
          <div class="fl-row" v-if="periodo === 'rango'">
            <input v-model="desde" type="date" class="fl-inp" /> <span>a</span> <input v-model="hasta" type="date" class="fl-inp" />
          </div>
          <div class="fl-row">
            <span class="fl-lbl">Empresa:</span>
            <select v-model.number="empresa" class="fl-inp ancho"><option :value="0">Todas</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
          </div>
          <div class="fl-row">
            <span class="fl-lbl">Empleado:</span>
            <div class="fl-bus"><input v-model="busqueda" type="text" class="fl-inp" placeholder="Todos — o buscar…" @input="buscar" @focus="buscar" />
              <ul v-if="resultados.length" class="fl-result"><li v-for="r in resultados" :key="r.PER_COD" @click="selEmp(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li></ul></div>
            <button v-if="cod" class="fl-clear" @click="cod = 0; busqueda = ''">✕</button>
          </div>
          <div class="fl-row">
            <label class="fl-chk"><input type="checkbox" v-model="agrupar" /> Agrupar por empleado y tipo</label>
            <label class="fl-chk"><input type="checkbox" v-model="incluyeVacas" /> Incluir faltas por vacaciones</label>
          </div>
          <div class="fl-acc">
            <button class="fl-btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'Consultar' }}</button>
            <button class="fl-btn excel" :disabled="!filas.length" @click="exportarExcel">📊 Exportar a Excel</button>
            <button class="fl-btn pdf" :disabled="!filas.length" @click="imprimir">📄 A PDF</button>
          </div>
        </div>

        <div class="fl-lic">
          <div class="fl-lic-head"><span>Licencias</span>
            <div><button class="fl-mini" @click="preset('todas')">Todas</button><button class="fl-mini" @click="preset('enfermedad')">Enfermedad</button><button class="fl-mini" @click="preset('ninguna')">Ninguna</button></div>
          </div>
          <div class="fl-lic-list">
            <label v-for="l in licencias" :key="l.cod" class="fl-lic-row" :class="{ on: l.sel }"><input type="checkbox" v-model="l.sel" /> {{ l.detalle }}</label>
          </div>
        </div>
      </div>

      <div v-if="agrupar && agrupado.length" class="fl-grid-wrap">
        <table class="fl-tabla">
          <thead><tr><th>Código</th><th>Empleado</th><th>Tipo de Licencia</th><th>Cantidad (días)</th></tr></thead>
          <tbody><tr v-for="(r, i) in agrupado" :key="i"><td>{{ r.cod }}</td><td>{{ r.nombre }}</td><td>{{ r.detalle }}</td><td class="c b">{{ r.cantidad }}</td></tr></tbody>
        </table>
      </div>
      <div v-else-if="!agrupar && filas.length" class="fl-grid-wrap">
        <table class="fl-tabla">
          <thead><tr><th>Código</th><th>Empleado</th><th>Detalle</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Observaciones</th></tr></thead>
          <tbody><tr v-for="(r, i) in filas" :key="i" :class="{ doc: r.conDocu }"><td>{{ r.cod }}</td><td>{{ r.nombre }}</td><td>{{ r.detalle }}</td><td>{{ fmt(r.desde) }}</td><td>{{ fmt(r.hasta) }}</td><td class="c b">{{ r.dias }}</td><td>{{ r.obs }}</td></tr></tbody>
        </table>
      </div>
      <p v-else-if="!cargando && consultado" class="fl-vacio">No hay faltas para los filtros indicados.</p>
      <p v-if="msg" class="fl-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="fl-pdf-ov" @click.self="cerrarPdf">
        <div class="fl-pdf-md"><div class="fl-pdf-head"><span>{{ pdfNombre }}</span><div class="fl-pdf-acc">
          <button class="fl-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
          <button class="fl-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
          <button class="fl-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button></div></div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="fl-pdf-frame"></iframe></div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import RelojFaltasListadosAyuda from '@/components/RelojFaltasListadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date(); const ini = new Date(hoy.getFullYear(), 0, 1)
const periodo = ref<'historico' | 'rango'>('historico'); const desde = ref(_iso(ini)); const hasta = ref(_iso(hoy))
const empresas = ref<{ cod: number; nombre: string }[]>([]); const empresa = ref(0)
const licencias = ref<{ cod: number; detalle: string; sel: boolean }[]>([])
const agrupar = ref(false); const incluyeVacas = ref(true)
const cod = ref(0); const busqueda = ref('')
const filas = ref<any[]>([]); const agrupado = ref<any[]>([]); const cargando = ref(false); const consultado = ref(false); const msg = ref('')
const fmt = (iso: string) => { const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }

onMounted(async () => {
  try { const data = (await api.get('/empresas')).data; const arr = Array.isArray(data) ? data : (data.data ?? []); empresas.value = arr.map((e: any) => ({ cod: Number(e.EMP_COD ?? e.cod), nombre: (e.EMP_NOM ?? e.nombre ?? '').toString().trim() })); const ae = empresas.value.find(e => /AUTOELEVADOR/i.test(e.nombre)); if (ae) empresa.value = ae.cod } catch { /* */ }
  try { licencias.value = ((await api.get('/reloj/faltas/licencias')).data.licencias ?? []).map((l: any) => ({ ...l, sel: true })) } catch { /* */ }
})

const preset = (p: string) => { for (const l of licencias.value) l.sel = p === 'todas' ? true : p === 'ninguna' ? false : /ENFERMEDAD/i.test(l.detalle) }

const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => { clearTimeout(tB); const q = busqueda.value.trim(); if (q.length < 2) { resultados.value = []; return } tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250) }
const selEmp = (r: any) => { cod.value = r.PER_COD; busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = [] }

const consultar = async () => {
  cargando.value = true; msg.value = ''
  try {
    const params: any = { periodo: periodo.value, agrupar: agrupar.value ? 1 : 0, incluyeVacas: incluyeVacas.value ? 1 : 0 }
    if (periodo.value === 'rango') { params.desde = desde.value; params.hasta = hasta.value }
    if (empresa.value) params.empresa = empresa.value
    if (cod.value) params.cod = cod.value
    const lics = licencias.value.filter(l => l.sel).map(l => l.cod); if (lics.length) params['licencias[]'] = lics
    const { data } = await api.get('/reloj/faltas/listados', { params })
    filas.value = data.detalle ?? []; agrupado.value = data.agrupado ?? []; consultado.value = true
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo consultar.' }
  finally { cargando.value = false }
}

const imprimir = () => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('Informe de Faltas Diarias' + (agrupar.value ? ' (Agrupado)' : ''), 14, 14)
  doc.setFontSize(9); doc.text(periodo.value === 'rango' ? `Desde ${fmt(desde.value)} hasta ${fmt(hasta.value)}` : 'Histórico', 14, 20)
  let y = 30
  if (agrupar.value) {
    const xs = [14, 32, 110, 250]; doc.setFont('helvetica', 'bold');['Cód.', 'Empleado', 'Tipo Licencia', 'Días'].forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
    for (const r of agrupado.value) { if (y > 195) { doc.addPage(); y = 20 } [String(r.cod), r.nombre.slice(0, 36), r.detalle.slice(0, 40), String(r.cantidad)].forEach((c, i) => doc.text(c, xs[i], y)); y += 5 }
  } else {
    const xs = [14, 30, 92, 180, 200, 220, 234]; doc.setFont('helvetica', 'bold');['Cód.', 'Empleado', 'Detalle', 'Desde', 'Hasta', 'Días', 'Obs.'].forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
    for (const r of filas.value) { if (y > 195) { doc.addPage(); y = 20 } [String(r.cod), r.nombre.slice(0, 30), r.detalle.slice(0, 32), fmt(r.desde), fmt(r.hasta), String(r.dias), r.obs.slice(0, 24)].forEach((c, i) => doc.text(c, xs[i], y)); y += 5 }
  }
  cerrarPdf(); pdfNombre.value = 'FALTAS_DIARIAS.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const exportarExcel = async () => {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  let head: string[], rows: any[][]
  if (agrupar.value) { head = ['Código', 'Empleado', 'Tipo de Licencia', 'Cantidad']; rows = agrupado.value.map(r => [r.cod, r.nombre, r.detalle, r.cantidad]) }
  else { head = ['Código', 'Legajo', 'Empleado', 'Detalle', 'Desde', 'Hasta', 'Días', 'Observaciones']; rows = filas.value.map(r => [r.cod, r.legajo, r.nombre, r.detalle, fmt(r.desde), fmt(r.hasta), r.dias, r.obs]) }
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>' + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), agrupar.value ? 'FALTAS_DIARIAS_AGRUPADAS.xls' : 'FALTAS_DIARIAS.xls')
}
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
</script>

<style scoped>
.fl-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.fl-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.fl-cab-ico { font-size:28px; } .fl-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .fl-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.fl-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.fl-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.fl-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.fl-filtros { display:flex; gap:18px; flex-wrap:wrap; }
.fl-col { flex:1; min-width:340px; display:flex; flex-direction:column; gap:10px; }
.fl-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.fl-lbl { font-size:12.5px; font-weight:700; color:#1b4332; min-width:70px; }
.fl-rad, .fl-chk { display:flex; align-items:center; gap:5px; font-size:13px; color:#1e293b; }
.fl-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .fl-inp.ancho { min-width:280px; }
.fl-bus { position:relative; } .fl-bus .fl-inp { min-width:240px; }
.fl-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:200px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.fl-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .fl-result li:hover { background:#f0faf4; }
.fl-clear { border:none; background:#ef4444; color:#fff; border-radius:50%; width:24px; height:24px; cursor:pointer; }
.fl-acc { display:flex; gap:10px; margin-top:4px; }
.fl-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .fl-btn.excel { background:#107c41; } .fl-btn.pdf { background:#b91c1c; } .fl-btn:disabled { background:#cbd5e1; cursor:default; }
.fl-lic { width:340px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:300px; }
.fl-lic-head { display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:#1b4332; color:#fff; font-size:12.5px; }
.fl-mini { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:3px 8px; border-radius:5px; cursor:pointer; font-size:11px; font-weight:600; margin-left:4px; }
.fl-lic-list { overflow:auto; }
.fl-lic-row { display:flex; align-items:center; gap:8px; padding:4px 10px; border-bottom:1px solid #f1f5f9; font-size:12.5px; color:#1e293b; cursor:pointer; } .fl-lic-row.on { background:#fff7cc; }
.fl-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:50vh; }
.fl-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.fl-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.fl-tabla td { padding:5px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .fl-tabla td.c { text-align:center; } .fl-tabla td.b { font-weight:700; }
.fl-tabla tbody tr.doc td { background:#e8fbe8; }
.fl-vacio { color:#94a3b8; padding:14px; } .fl-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fee2e2; color:#b91c1c; max-width:760px; }
.fl-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.fl-pdf-md { width:min(1000px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.fl-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.fl-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.fl-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .fl-pdf-b.ok { background:#22c55e; color:#fff; } .fl-pdf-b.cancel { background:#ef4444; color:#fff; }
.fl-pdf-frame { flex:1; border:none; width:100%; }
</style>
