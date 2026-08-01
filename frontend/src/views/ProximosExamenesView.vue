<!-- ProximosExamenesView.vue — Control de Salud · Próximos Exámenes (examenes_proximos.scx). -->
<template>
  <div class="el-view">
    <div class="el-cab">
      <div class="el-ico">📅</div>
      <div class="el-tx"><h1>Próximos Exámenes</h1><p>Vencimientos futuros de exámenes médicos</p></div>
      <button class="el-ia" @click="modalIA = true">🤖 IA</button>
      <button class="el-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/examenes" titulo="Asistente IA — Próximos Exámenes"
            subtitulo="Preguntá sobre los próximos exámenes"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué muestra?','¿Cómo filtro por fechas?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['el-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="el-body">
      <div class="el-filtros">
        <div class="fila"><span class="lbl">Empleados</span>
          <label class="rad"><input type="radio" :value="false" v-model="fEmp.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fEmp.ind" /> Uno</label>
          <div v-if="fEmp.ind" class="el-busc">
            <input v-model="busqueda" type="text" placeholder="Buscar empleado…" @input="buscarEmp" @focus="buscarEmp" />
            <ul v-if="resultados.length" class="el-result">
              <li v-for="r in resultados" :key="r.PER_COD" @click="elegirEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>Cód. {{ r.PER_COD }}</span></li>
            </ul>
          </div>
        </div>
        <div class="fila"><span class="lbl">Período</span>
          <label class="rad"><input type="radio" value="general" v-model="periodo" /> General</label>
          <label class="rad"><input type="radio" value="rango" v-model="periodo" /> Rango</label>
          <template v-if="periodo==='rango'"><input v-model="desde" type="date" /> <span>a</span> <input v-model="hasta" type="date" /></template>
        </div>
        <div class="fila"><span class="lbl">Médicos</span>
          <label class="rad"><input type="radio" :value="false" v-model="fMed.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fMed.ind" /> Uno</label>
          <select v-if="fMed.ind" v-model.number="fMed.cod"><option :value="0">— —</option><option v-for="m in medicos" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select>
        </div>
        <div class="fila">
          <button class="btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳ Consultando…' : '🔎 CONSULTAR' }}</button>
          <button v-if="cargado && items.length" class="btn pdf" @click="generarPdf">🖨 Imprimir</button>
        </div>
      </div>

      <div v-if="cargado" class="el-result-box">
        <div class="el-resumen">{{ items.length }} próximo(s) examen(es)</div>
        <table v-if="items.length" class="el-tabla">
          <thead><tr><th style="width:64px">Cód.</th><th>Empleado</th><th>Examen</th><th style="width:100px">Última fecha</th><th style="width:110px">Próximo</th><th>Médico</th></tr></thead>
          <tbody>
            <tr v-for="(e, i) in items" :key="i" :class="{ venc: vencido(e.proximo) }">
              <td class="el-cod">{{ e.emp_cod }}</td><td>{{ e.emp_nombre }}</td><td>{{ e.examen }}</td><td>{{ fmt(e.fecha) }}</td><td><b>{{ fmt(e.proximo) }}</b></td><td>{{ e.medico }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="el-vacio">No hay próximos exámenes para los filtros seleccionados.</div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="el-ov" @click.self="ayuda = false">
        <div class="el-help-md">
          <h3>❓ Ayuda — Próximos Exámenes</h3>
          <ul>
            <li>Lista los exámenes cuyo <b>próximo vencimiento es futuro</b>, ordenados por fecha de vencimiento.</li>
            <li>Filtrá por empleado, período (general o un rango de vencimientos) y médico.</li>
            <li>Solo empleados <b>activos</b>.</li>
          </ul>
          <div class="fila end"><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="el-pdf-ov" @click.self="cerrarPdf">
        <div class="el-pdf-md">
          <div class="el-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="el-pdf-acc">
              <button class="el-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="el-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="el-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="el-pdf-frame"></iframe>
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

const medicos = ref<{ cod: number; nombre: string }[]>([])
const fEmp = ref({ ind: false, cod: 0, nombre: '' }); const periodo = ref<'general' | 'rango'>('general'); const desde = ref(''); const hasta = ref('')
const fMed = ref({ ind: false, cod: 0 })
const items = ref<any[]>([]); const cargado = ref(false); const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const fmt = (s: string) => s ? s.split('-').reverse().join('/') : '—'
const hoy = new Date().toISOString().slice(0, 10)
const vencido = (p: string) => p && p < hoy
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

onMounted(async () => { try { medicos.value = (await api.get('/examenes/init')).data.medicos ?? [] } catch { flash('No se pudieron cargar los filtros.', true) } })

const busqueda = ref(''); const resultados = ref<any[]>([]); let dq: any = null
const buscarEmp = () => {
  clearTimeout(dq); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const elegirEmp = (r: any) => { fEmp.value.cod = Number(r.PER_COD); fEmp.value.nombre = (r.PER_NOM || '').trim(); busqueda.value = `${r.PER_COD} — ${fEmp.value.nombre}`; resultados.value = [] }

async function consultar () {
  if (fEmp.value.ind && fEmp.value.cod <= 0) { flash('Seleccione un empleado.', true); return }
  if (periodo.value === 'rango' && (!desde.value || !hasta.value)) { flash('Indique el rango de fechas.', true); return }
  if (fMed.value.ind && fMed.value.cod <= 0) { flash('Seleccione un médico.', true); return }
  cargando.value = true
  try {
    const params: any = { periodo: periodo.value }
    if (periodo.value === 'rango') { params.desde = desde.value; params.hasta = hasta.value }
    if (fEmp.value.ind) params.empleado = fEmp.value.cod
    if (fMed.value.ind) params.medico = fMed.value.cod
    const { data } = await api.get('/examenes/proximos', { params })
    items.value = data.items ?? []; cargado.value = true
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar.', true) }
  finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPdf () {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 12, MR = 285, PW = 297, PH = 210; let y = 14
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(20, 60, 40)
  doc.text('PRÓXIMOS EXÁMENES MÉDICOS', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 8
  doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.rect(ML, y - 4, MR - ML, 6, 'F')
  const cx = { c: ML + 2, e: ML + 18, x: ML + 110, f: ML + 190, p: ML + 216, m: ML + 244 }
  doc.text('Cód', cx.c, y); doc.text('Empleado', cx.e, y); doc.text('Examen', cx.x, y); doc.text('Última', cx.f, y); doc.text('Próximo', cx.p, y); doc.text('Médico', cx.m, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  for (const e of items.value) {
    if (y > PH - 14) { doc.addPage(); y = 16 }
    doc.setFontSize(8)
    doc.text(String(e.emp_cod), cx.c, y); doc.text((e.emp_nombre || '').slice(0, 46), cx.e, y); doc.text((doc.splitTextToSize(e.examen, 76)[0] || ''), cx.x, y)
    doc.text(fmt(e.fecha), cx.f, y); doc.text(fmt(e.proximo), cx.p, y); doc.text((e.medico || '').slice(0, 18), cx.m, y)
    doc.setDrawColor(225); doc.line(ML, y + 1.5, MR, y + 1.5); y += 5.5
  }
  cerrarPdf(); pdfNombre.value = 'Proximos_examenes.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.el-view { display:flex; flex-direction:column; min-height:100%; }
.el-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.el-ico { font-size:28px; } .el-tx h1 { margin:0; font-size:19px; color:#1e293b; } .el-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.el-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.el-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.el-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .el-msg.ok { background:#d1fae5; color:#065f46; } .el-msg.err { background:#fee2e2; color:#991b1b; }
.el-body { padding:16px 18px; }
.el-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; max-width:760px; display:flex; flex-direction:column; gap:10px; }
.fila { display:flex; gap:12px; align-items:center; flex-wrap:wrap; } .fila.end { justify-content:flex-end; }
.lbl { width:90px; font-size:13px; font-weight:700; color:#2a4a6a; }
.rad { display:flex; align-items:center; gap:5px; font-size:13px; color:#374151; cursor:pointer; }
.fila select, .fila input[type=date] { border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:13px; color:#1e293b; }
.el-busc { position:relative; flex:1; min-width:220px; } .el-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; box-sizing:border-box; }
.el-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.el-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; color:#1e293b; } .el-result li:hover { background:#f0faf4; } .el-result li b { font-size:13px; } .el-result li span { font-size:11px; color:#6b7280; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; } .btn.ok { background:#1b4332; color:#fff; } .btn.pdf { background:#e0eefc; color:#2d6a9f; } .btn:disabled { opacity:.5; cursor:default; }
.el-result-box { margin-top:16px; } .el-resumen { font-size:13px; color:#1e293b; margin-bottom:8px; font-weight:600; }
.el-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.el-tabla th { background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11.5px; }
.el-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .el-cod { color:#2d6a9f; font-weight:700; } .el-tabla tr.venc td { background:#fef2f2; }
.el-vacio { text-align:center; color:#94a3b8; padding:16px; }
.el-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.el-help-md { background:#fff; border-radius:14px; padding:22px; width:min(480px,94vw); } .el-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .el-help-md ul { margin:0 0 8px; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.el-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.el-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.el-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .el-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.el-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .el-pdf-b.ok { background:#22c55e; color:#fff; } .el-pdf-b.cancel { background:#ef4444; color:#fff; }
.el-pdf-frame { flex:1; border:none; width:100%; }
</style>
