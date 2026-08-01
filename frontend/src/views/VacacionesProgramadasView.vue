<!-- VacacionesProgramadasView.vue — Vacaciones Programadas (vacaciones_programadas). -->
<template>
  <div class="vp-view">
    <div class="vp-cab">
      <div class="vp-cab-ico">📅</div>
      <div class="vp-cab-tx"><h1>Vacaciones Programadas</h1><p>Vacaciones de mañana en adelante, con los días que corresponden</p></div>
      <button class="vp-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vp-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesProgramadasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-programadas" titulo="Asistente IA — Vacaciones Programadas"
            subtitulo="Preguntá sobre las vacaciones próximas"
            :sugerencias="['¿Qué vacaciones se ven acá?','¿Cómo exporto a Excel?','¿Qué significan los días que corresponden?']"
            @close="modalIA = false" />

    <div class="vp-card">
      <div class="vp-toolbar">
        <div class="vp-sel">
          <span class="vp-lbl">Seleccionar:</span>
          <button class="vp-btn-sm" @click="marcarTodos(true)">Todos</button>
          <button class="vp-btn-sm" @click="marcarTodos(false)">Ninguno</button>
          <span class="vp-cont">{{ elegidos }} de {{ filas.length }} seleccionadas</span>
        </div>
        <div class="vp-acc">
          <button class="vp-btn ok" :disabled="!elegidos" @click="imprimir">🖨 Imprimir Seleccionados</button>
          <button class="vp-btn excel" :disabled="!elegidos" @click="exportarExcel">📊 Excel</button>
        </div>
      </div>

      <div v-if="cargando" class="vp-loading">⟳ Cargando…</div>
      <div v-else class="vp-grid-wrap">
        <table class="vp-tabla">
          <thead><tr>
            <th>OK</th><th>Legajo</th><th>Personal</th><th>Días Corresp.</th><th>Nro.</th><th>Fecha</th><th>Año</th>
            <th>Comienza el día</th><th>Hasta el día</th><th>Días</th><th>Se Presentará</th><th>Liq.</th>
          </tr></thead>
          <tbody>
            <tr v-for="v in filas" :key="v.nro" :class="{ on: v.ok }">
              <td class="c"><input type="checkbox" v-model="v.ok" /></td>
              <td>{{ v.legajo }}</td><td>{{ v.nombre }}</td><td class="c">{{ v.corresponden }}</td>
              <td>{{ v.nro }}</td><td>{{ fmt(v.fechaPago) }}</td><td>{{ v.anio }}</td>
              <td>{{ fmt(v.fechaDesde) }}</td><td>{{ fmt(v.fechaHasta) }}</td><td class="c">{{ v.dias }}</td>
              <td>{{ fmt(v.presenta) }}</td><td class="c">{{ v.liquidada ? '✔' : '' }}</td>
            </tr>
            <tr v-if="!filas.length"><td colspan="12" class="vacio">No hay vacaciones programadas a futuro.</td></tr>
          </tbody>
        </table>
      </div>
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
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import VacacionesProgramadasAyuda from '@/components/VacacionesProgramadasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Vac { nro: number; nombre: string; legajo: number; corresponden: number; anio: number; fechaPago: string; fechaDesde: string; fechaHasta: string; dias: number; presenta: string; liquidada: boolean; ok: boolean }
const filas = ref<Vac[]>([]); const cargando = ref(true); const msg = ref('')
const elegidos = computed(() => filas.value.filter(f => f.ok).length)
const fmt = (iso: string) => { if (!iso) return ''; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }

const cargar = async () => {
  cargando.value = true
  try { filas.value = ((await api.get('/vacaciones/programadas')).data.vacaciones ?? []).map((v: any) => ({ ...v, ok: true })) }
  catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudieron cargar las vacaciones.' }
  finally { cargando.value = false }
}
onMounted(cargar)
const marcarTodos = (v: boolean) => filas.value.forEach(f => f.ok = v)

const imprimir = () => {
  const sel = filas.value.filter(f => f.ok); if (!sel.length) return
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text('Vacaciones Programadas', 14, 14)
  doc.setFontSize(9); doc.text(`Desde el ${fmt(manana())} en adelante`, 14, 20)
  const cols = ['Legajo', 'Personal', 'Corr.', 'Nro.', 'Fecha', 'Año', 'Comienza', 'Hasta', 'Días', 'Se Presenta', 'Liq.']
  const xs = [14, 32, 92, 105, 120, 142, 154, 178, 202, 214, 250]; let y = 30
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 2
  doc.setLineWidth(0.2); doc.line(14, y, 283, y); y += 5
  for (const v of sel) {
    if (y > 195) { doc.addPage(); y = 20 }
    const row = [String(v.legajo), v.nombre.slice(0, 34), String(v.corresponden), String(v.nro), fmt(v.fechaPago), String(v.anio), fmt(v.fechaDesde), fmt(v.fechaHasta), String(v.dias), fmt(v.presenta), v.liquidada ? 'Sí' : 'No']
    row.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'VACACIONES_PROGRAMADAS.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const exportarExcel = async () => {
  const sel = filas.value.filter(f => f.ok); if (!sel.length) return
  const head = ['Legajo', 'Personal', 'Días Corresp.', 'Nro.', 'Fecha', 'Año', 'Comienza el día', 'Hasta el día', 'Días', 'Se Presentará', 'Liquidada']
  const rows = sel.map(v => [v.legajo, v.nombre, v.corresponden, v.nro, fmt(v.fechaPago), v.anio, fmt(v.fechaDesde), fmt(v.fechaHasta), v.dias, fmt(v.presenta), v.liquidada ? 'Sí' : 'No'])
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), 'VACACIONES_PROGRAMADAS.xls')
}

const manana = () => { const d = new Date(); d.setDate(d.getDate() + 1); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` }
</script>

<style scoped>
.vp-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vp-cab-ico { font-size:28px; } .vp-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vp-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vp-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vp-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vp-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.vp-toolbar { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.vp-sel { display:flex; align-items:center; gap:8px; } .vp-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.vp-cont { font-size:12px; color:#64748b; margin-left:6px; }
.vp-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.vp-acc { margin-left:auto; display:flex; gap:8px; }
.vp-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; } .vp-btn.ok { background:#16a34a; } .vp-btn.excel { background:#107c41; } .vp-btn:disabled { background:#cbd5e1; cursor:default; }
.vp-loading { padding:30px; text-align:center; color:#64748b; }
.vp-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:65vh; }
.vp-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.vp-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.vp-tabla td { padding:5px 8px; border-bottom:1px solid #f1f5f9; white-space:nowrap; color:#1e293b; } .vp-tabla td.c { text-align:center; }
.vp-tabla tbody tr.on { background:#fff7cc; }
.vp-tabla .vacio { text-align:center; color:#94a3b8; padding:16px; }
.vp-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fee2e2; color:#b91c1c; max-width:700px; }
.vp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vp-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.vp-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.vp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.vp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .vp-pdf-b.ok { background:#22c55e; color:#fff; } .vp-pdf-b.cancel { background:#ef4444; color:#fff; }
.vp-pdf-frame { flex:1; border:none; width:100%; }
</style>
