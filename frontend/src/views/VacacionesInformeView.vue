<!-- VacacionesInformeView.vue — Vacaciones Informe General (vacaciones_informe). -->
<template>
  <div class="vp-view">
    <div class="vp-cab">
      <div class="vp-cab-ico">📊</div>
      <div class="vp-cab-tx"><h1>Vacaciones - Informe General</h1><p>Vacaciones cuyo inicio o fin cae en un rango de fechas</p></div>
      <button class="vp-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vp-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesInformeAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-informe" titulo="Asistente IA — Informe General"
            subtitulo="Preguntá sobre las vacaciones del período"
            :sugerencias="['¿Qué vacaciones entran en el rango?','¿Cómo exporto a Excel?','¿Qué son los días que corresponden?']"
            @close="modalIA = false" />

    <div class="vp-card">
      <div class="vp-toolbar">
        <div class="vp-f"><span class="vp-lbl">Desde</span><input v-model="desde" type="date" class="vp-inp" /></div>
        <div class="vp-f"><span class="vp-lbl">Hasta</span><input v-model="hasta" type="date" class="vp-inp" /></div>
        <button class="vp-btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
        <div class="vp-acc" v-if="filas.length">
          <button class="vp-btn-sm" @click="marcarTodos(true)">Todo</button>
          <button class="vp-btn-sm" @click="marcarTodos(false)">Nada</button>
          <button class="vp-btn ok" :disabled="!elegidos" @click="imprimir">🖨 Imprimir Consulta Completa</button>
          <button class="vp-btn excel" :disabled="!elegidos" @click="exportarExcel">📊 Excel</button>
        </div>
      </div>

      <div v-if="filas.length" class="vp-grid-wrap">
        <table class="vp-tabla">
          <thead><tr>
            <th>OK</th><th>Legajo</th><th>Personal</th><th>Días Corresp.</th><th>Nro.</th><th>Fecha</th><th>Año</th>
            <th>Desde</th><th>Hasta</th><th>Días</th><th>Se Presenta</th>
          </tr></thead>
          <tbody>
            <tr v-for="v in filas" :key="v.nro" :class="{ on: v.ok }">
              <td class="c"><input type="checkbox" v-model="v.ok" /></td>
              <td>{{ v.legajo }}</td><td>{{ v.nombre }}</td><td class="c">{{ v.corresponden }}</td>
              <td>{{ v.nro }}</td><td>{{ fmt(v.fechaPago) }}</td><td>{{ v.anio }}</td>
              <td>{{ fmt(v.fechaDesde) }}</td><td>{{ fmt(v.fechaHasta) }}</td><td class="c">{{ v.dias }}</td><td>{{ fmt(v.presenta) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else-if="!cargando && consultado" class="vp-vacio">No hay vacaciones en ese rango de fechas.</p>
      <p v-if="msg" class="vp-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="vp-pdf-ov" @click.self="cerrarPdf">
        <div class="vp-pdf-md">
          <div class="vp-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="vp-pdf-acc">
              <button class="vp-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="vp-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="vp-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="vp-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import VacacionesInformeAyuda from '@/components/VacacionesInformeAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date(); const haceUnMes = new Date(); haceUnMes.setMonth(haceUnMes.getMonth() - 1)
const desde = ref(_iso(haceUnMes)); const hasta = ref(_iso(hoy))

interface Vac { nro: number; nombre: string; legajo: number; corresponden: number; anio: number; fechaPago: string; fechaDesde: string; fechaHasta: string; dias: number; presenta: string; ok: boolean }
const filas = ref<Vac[]>([]); const cargando = ref(false); const consultado = ref(false); const msg = ref('')
const elegidos = computed(() => filas.value.filter(f => f.ok).length)
const fmt = (iso: string) => { if (!iso) return ''; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }

const consultar = async () => {
  cargando.value = true; msg.value = ''
  try { filas.value = ((await api.get('/vacaciones/informe', { params: { desde: desde.value, hasta: hasta.value } })).data.vacaciones ?? []).map((v: any) => ({ ...v, ok: true })); consultado.value = true }
  catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudieron cargar las vacaciones.' }
  finally { cargando.value = false }
}
const marcarTodos = (v: boolean) => filas.value.forEach(f => f.ok = v)

const imprimir = () => {
  const sel = filas.value.filter(f => f.ok); if (!sel.length) return
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text('Vacaciones — Informe General', 14, 14)
  doc.setFontSize(9); doc.text(`Desde el ${fmt(desde)} hasta el ${fmt(hasta)}`, 14, 20)
  const cols = ['Legajo', 'Personal', 'Corr.', 'Nro.', 'Fecha', 'Año', 'Desde', 'Hasta', 'Días', 'Se Presenta']
  const xs = [14, 32, 92, 105, 120, 142, 154, 178, 202, 214]; let y = 30
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 2
  doc.setLineWidth(0.2); doc.line(14, y, 283, y); y += 5
  for (const v of sel) {
    if (y > 195) { doc.addPage(); y = 20 }
    const row = [String(v.legajo), v.nombre.slice(0, 34), String(v.corresponden), String(v.nro), fmt(v.fechaPago), String(v.anio), fmt(v.fechaDesde), fmt(v.fechaHasta), String(v.dias), fmt(v.presenta)]
    row.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'VACACIONES_INFORME.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const exportarExcel = async () => {
  const sel = filas.value.filter(f => f.ok); if (!sel.length) return
  const head = ['Legajo', 'Personal', 'Días Corresp.', 'Nro.', 'Fecha', 'Año', 'Desde', 'Hasta', 'Días', 'Se Presenta']
  const rows = sel.map(v => [v.legajo, v.nombre, v.corresponden, v.nro, fmt(v.fechaPago), v.anio, fmt(v.fechaDesde), fmt(v.fechaHasta), v.dias, fmt(v.presenta)])
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), 'VACACIONES_INFORME.xls')
}
</script>

<style scoped>
.vp-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vp-cab-ico { font-size:28px; } .vp-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vp-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vp-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vp-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vp-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.vp-toolbar { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.vp-f { display:flex; flex-direction:column; gap:4px; } .vp-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.vp-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; }
.vp-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; } .vp-btn.ok { background:#16a34a; } .vp-btn.excel { background:#107c41; } .vp-btn:disabled { background:#cbd5e1; cursor:default; }
.vp-acc { margin-left:auto; display:flex; align-items:flex-end; gap:8px; }
.vp-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.vp-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:65vh; }
.vp-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.vp-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.vp-tabla td { padding:5px 8px; border-bottom:1px solid #f1f5f9; white-space:nowrap; color:#1e293b; } .vp-tabla td.c { text-align:center; }
.vp-tabla tbody tr.on { background:#fff7cc; }
.vp-vacio { color:#94a3b8; padding:16px; } .vp-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fee2e2; color:#b91c1c; max-width:700px; }
.vp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vp-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.vp-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.vp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.vp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .vp-pdf-b.ok { background:#22c55e; color:#fff; } .vp-pdf-b.cancel { background:#ef4444; color:#fff; }
.vp-pdf-frame { flex:1; border:none; width:100%; }
</style>
