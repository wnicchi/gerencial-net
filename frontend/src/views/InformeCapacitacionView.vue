<!-- InformeCapacitacionView.vue — Informes de capacitación (capacitacion_informes.scx). -->
<template>
  <div class="ic-view">
    <div class="ic-cab">
      <div class="ic-ico">📋</div>
      <div class="ic-tx"><h1>Informes de Capacitación</h1><p>Historial, vencidas o próximas, con filtros</p></div>
      <button class="ic-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ic-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/informe-cap" titulo="Asistente IA — Informes de Capacitación"
            subtitulo="Preguntá sobre los informes de capacitación"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué son las vencidas sin cerrar?','¿Qué hace detallada con empleados?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ic-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ic-body">
      <div class="ic-filtros">
        <div class="fila"><span class="lbl">Tema</span>
          <label class="rad"><input type="radio" :value="false" v-model="fTema.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fTema.ind" /> Un tema</label>
          <select v-if="fTema.ind" v-model.number="fTema.cod"><option :value="0">— —</option><option v-for="t in temas" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
        </div>
        <div class="fila"><span class="lbl">Disertante</span>
          <label class="rad"><input type="radio" :value="false" v-model="fDis.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fDis.ind" /> Un disertante</label>
          <select v-if="fDis.ind" v-model.number="fDis.cod"><option :value="0">— —</option><option v-for="d in disertantes" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        </div>
        <div class="fila"><span class="lbl">Eficacia</span>
          <label class="rad"><input type="radio" :value="false" v-model="fEfi.ind" /> Todas</label>
          <label class="rad"><input type="radio" :value="true" v-model="fEfi.ind" /> Una eficacia</label>
          <select v-if="fEfi.ind" v-model.number="fEfi.cod"><option :value="0">— —</option><option v-for="e in eficacias" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
        </div>
        <div class="fila"><span class="lbl">Modalidad</span>
          <label class="rad"><input type="radio" :value="false" v-model="fMod.ind" /> Todas</label>
          <label class="rad"><input type="radio" :value="true" v-model="fMod.ind" /> Una modalidad</label>
          <select v-if="fMod.ind" v-model="fMod.val"><option value="">— —</option><option v-for="m in modalidades" :key="m" :value="m">{{ m }}</option></select>
        </div>
        <div class="fila"><span class="lbl">Período</span>
          <label class="rad"><input type="radio" value="hist" v-model="periodo" /> Histórico</label>
          <label class="rad"><input type="radio" value="rango" v-model="periodo" /> Período</label>
          <template v-if="periodo === 'rango'"><input v-model="desde" type="date" /> <span>a</span> <input v-model="hasta" type="date" /></template>
        </div>
        <div class="fila tipo">
          <label class="rad"><input type="radio" value="historial" v-model="tipo" /> Historial de capacitaciones</label>
          <label class="rad"><input type="radio" value="vencidas" v-model="tipo" /> Sólo las vencidas sin cerrar</label>
          <label class="rad"><input type="radio" value="proximas" v-model="tipo" /> Próximas a realizar</label>
        </div>
        <div class="fila">
          <label class="rad chk"><input type="checkbox" v-model="detallada" /> Detallada con empleados</label>
          <span class="ic-spacer"></span>
          <button class="btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳ Consultando…' : '🔎 CONSULTAR INFORME' }}</button>
          <button v-if="cargado && jornadas.length" class="btn pdf" @click="generarPdf">🖨 Imprimir</button>
        </div>
      </div>

      <div v-if="cargado" class="ic-result">
        <div class="ic-resumen">{{ jornadas.length }} jornada(s){{ detallada ? ' · detallado' : '' }}</div>
        <table class="ic-tabla">
          <thead><tr><th style="width:60px">Cód.</th><th>Capacitación</th><th style="width:100px">Fecha</th><th>Disertante</th><th>Modalidad</th><th>Tema</th><th class="c" style="width:80px">Estado</th></tr></thead>
          <tbody>
            <template v-for="j in jornadas" :key="j.cod">
              <tr>
                <td class="ic-cod">{{ j.cod }}</td><td>{{ j.capacitacion }}</td><td>{{ fmt(j.fecha) }}</td>
                <td>{{ j.disertante }}</td><td>{{ j.modalidad }}</td><td>{{ j.tematica }}</td>
                <td class="c"><span :class="['est', j.cerrada ? 'cerr' : 'abi']">{{ j.cerrada ? 'Cerrada' : 'Abierta' }}</span></td>
              </tr>
              <tr v-if="detallada && j.empleados && j.empleados.length" class="ic-det">
                <td></td>
                <td colspan="6">
                  <div class="ic-emps">
                    <span v-for="(e, k) in j.empleados" :key="k" class="ic-emp">{{ e.nombre }}<em v-if="e.eficacia"> · {{ e.eficacia }}</em><b v-if="e.no_participo"> · NP</b></span>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!jornadas.length"><td colspan="7" class="ic-vacio">No hay jornadas para los filtros seleccionados.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ic-ov" @click.self="ayuda = false">
        <div class="ic-help-md">
          <h3>❓ Ayuda — Informes de Capacitación</h3>
          <ul>
            <li>Filtrá por tema, disertante, eficacia, modalidad y período.</li>
            <li><b>Historial</b>: todas las que cumplen. <b>Vencidas sin cerrar</b>: pasó su período y siguen abiertas. <b>Próximas</b>: aún no empezaron y están abiertas.</li>
            <li><b>Detallada con empleados</b>: lista los participantes de cada jornada (NP = no participó).</li>
          </ul>
          <div class="fila end"><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ic-pdf-ov" @click.self="cerrarPdf">
        <div class="ic-pdf-md">
          <div class="ic-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ic-pdf-acc">
              <button class="ic-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ic-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ic-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ic-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

interface Combo { cod: number; nombre: string }
const temas = ref<Combo[]>([]); const disertantes = ref<Combo[]>([]); const eficacias = ref<Combo[]>([])
// Modalidad: lista fija canónica (igual que el FoxPro original).
const modalidades = ref<string[]>(['INFORMATIVA', 'PRACTICA', 'PRESENCIAL', 'VIRTUAL'])
const fTema = ref({ ind: false, cod: 0 }); const fDis = ref({ ind: false, cod: 0 }); const fEfi = ref({ ind: false, cod: 0 }); const fMod = ref({ ind: false, val: '' })
const periodo = ref<'hist' | 'rango'>('hist'); const desde = ref(''); const hasta = ref('')
const tipo = ref<'historial' | 'vencidas' | 'proximas'>('historial'); const detallada = ref(false)
const jornadas = ref<any[]>([]); const cargado = ref(false); const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

onMounted(async () => {
  try { const { data } = await api.get('/informe-cap/init'); temas.value = data.temas ?? []; disertantes.value = data.disertantes ?? []; eficacias.value = data.eficacias ?? [] }
  catch { flash('No se pudieron cargar los filtros.', true) }
})

async function consultar () {
  if (periodo.value === 'rango' && (!desde.value || !hasta.value)) { flash('Indique el rango de fechas.', true); return }
  cargando.value = true
  try {
    const params: any = { periodo: periodo.value, tipo: tipo.value, detallada: detallada.value }
    if (periodo.value === 'rango') { params.desde = desde.value; params.hasta = hasta.value }
    if (fTema.value.ind) params.tema = fTema.value.cod
    if (fDis.value.ind) params.disertante = fDis.value.cod
    if (fEfi.value.ind) params.eficacia = fEfi.value.cod
    if (fMod.value.ind) params.modalidad = fMod.value.val
    const { data } = await api.get('/informe-cap', { params })
    jornadas.value = data.jornadas ?? []; cargado.value = true
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar.', true) }
  finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPdf () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, MR = 196, PW = 210, PH = 297; let y = 16
  const titMap: any = { historial: 'HISTORIAL DE CAPACITACIONES', vencidas: 'CAPACITACIONES VENCIDAS SIN CERRAR', proximas: 'CAPACITACIONES PRÓXIMAS A REALIZAR' }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(20, 60, 40)
  doc.text(titMap[tipo.value], PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 9
  doc.setFillColor(27, 67, 50); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
  doc.rect(ML, y - 4, MR - ML, 6, 'F')
  const cx = { c: ML + 2, n: ML + 16, f: ML + 96, d: ML + 116, e: MR - 16 }
  doc.text('Cód', cx.c, y); doc.text('Capacitación', cx.n, y); doc.text('Fecha', cx.f, y); doc.text('Disertante', cx.d, y); doc.text('Estado', cx.e, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  for (const j of jornadas.value) {
    if (y > PH - 18) { doc.addPage(); y = 18 }
    doc.setFontSize(8)
    doc.text(String(j.cod), cx.c, y); doc.text((doc.splitTextToSize(j.capacitacion, 76)[0] || ''), cx.n, y)
    doc.text(fmt(j.fecha), cx.f, y); doc.text((j.disertante || '').slice(0, 30), cx.d, y); doc.text(j.cerrada ? 'Cerrada' : 'Abierta', cx.e, y)
    y += 5
    if (detallada.value && j.empleados && j.empleados.length) {
      doc.setFontSize(7); doc.setTextColor(80)
      const txt = j.empleados.map((e: any) => e.nombre + (e.eficacia ? ` (${e.eficacia})` : '') + (e.no_participo ? ' [NP]' : '')).join(' · ')
      const lns = doc.splitTextToSize(txt, MR - ML - 16)
      for (const ln of lns) { if (y > PH - 14) { doc.addPage(); y = 18 } doc.text(ln, ML + 16, y); y += 3.6 }
      doc.setTextColor(0); y += 1.5
    }
    doc.setDrawColor(225); doc.line(ML, y - 1, MR, y - 1)
  }
  y += 4; doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
  doc.text(`TOTAL: ${jornadas.value.length} jornada(s)`, ML, y)
  cerrarPdf(); pdfNombre.value = 'Informe_capacitaciones.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ic-view { display:flex; flex-direction:column; min-height:100%; }
.ic-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ic-ico { font-size:28px; } .ic-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ic-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ic-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ic-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ic-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ic-msg.ok { background:#d1fae5; color:#065f46; } .ic-msg.err { background:#fee2e2; color:#991b1b; }
.ic-body { padding:16px 18px; }
.ic-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; max-width:840px; display:flex; flex-direction:column; gap:10px; }
.fila { display:flex; gap:12px; align-items:center; flex-wrap:wrap; } .fila.end { justify-content:flex-end; } .fila.tipo { background:#f4f7fc; padding:8px 10px; border-radius:8px; }
.lbl { width:84px; font-size:13px; font-weight:700; color:#2a4a6a; }
.rad { display:flex; align-items:center; gap:5px; font-size:13px; color:#374151; cursor:pointer; } .rad.chk { font-weight:600; color:#1b4332; }
.fila select, .fila input[type=date] { border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:13px; color:#1e293b; }
.ic-spacer { flex:1; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; } .btn.ok { background:#1b4332; color:#fff; } .btn.pdf { background:#e0eefc; color:#2d6a9f; } .btn:disabled { opacity:.5; cursor:default; }
.ic-result { margin-top:16px; } .ic-resumen { font-size:13px; color:#1e293b; margin-bottom:8px; font-weight:600; }
.ic-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.ic-tabla th { background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11.5px; } .ic-tabla th.c { text-align:center; }
.ic-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ic-tabla td.c { text-align:center; } .ic-cod { color:#2d6a9f; font-weight:700; }
.est { font-size:11px; padding:1px 8px; border-radius:10px; } .est.cerr { background:#fee2e2; color:#991b1b; } .est.abi { background:#dcfce7; color:#166534; }
.ic-det td { background:#f8fafc; padding-top:0; } .ic-emps { display:flex; flex-wrap:wrap; gap:8px; } .ic-emp { font-size:11.5px; color:#475569; background:#eef2f7; padding:2px 8px; border-radius:10px; } .ic-emp em { color:#1b7a4b; font-style:normal; } .ic-emp b { color:#991b1b; }
.ic-vacio { text-align:center; color:#94a3b8; padding:16px; }
.ic-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.ic-help-md { background:#fff; border-radius:14px; padding:22px; width:min(520px,94vw); } .ic-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ic-help-md ul { margin:0 0 8px; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.ic-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ic-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ic-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ic-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ic-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ic-pdf-b.ok { background:#22c55e; color:#fff; } .ic-pdf-b.cancel { background:#ef4444; color:#fff; }
.ic-pdf-frame { flex:1; border:none; width:100%; }
</style>
