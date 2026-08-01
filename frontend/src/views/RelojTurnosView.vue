<!-- RelojTurnosView.vue — Reloj / Asignar Turnos Laborales (empleado_asignar_turno_laboral). -->
<template>
  <div class="tu-view">
    <div class="tu-cab">
      <div class="tu-cab-ico">🔄</div>
      <div class="tu-cab-tx"><h1>Asignar Turno Laboral a Personal</h1><p>Reasignar el turno (grupo de horarios) del personal de una empresa</p></div>
      <button class="tu-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="tu-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojTurnosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-turnos" titulo="Asistente IA — Turnos Laborales"
            subtitulo="Preguntá sobre la asignación de turnos" :sugerencias="['¿Cómo cambio el turno de varios?','¿Qué es un turno laboral?','¿Cuándo se guardan los cambios?']"
            @close="modalIA = false" />

    <div class="tu-card">
      <div class="tu-toolbar">
        <div class="tu-f"><span class="tu-lbl">Empresa</span>
          <select v-model.number="empresa" class="tu-inp ancho"><option :value="0">— Seleccione —</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select></div>
        <button class="tu-btn ok" :disabled="!empresa || cargando" @click="aceptar">{{ cargando ? '⟳…' : 'Aceptar' }}</button>
        <input v-model="buscar" type="text" class="tu-inp" placeholder="Buscar en la lista…" />
        <div class="tu-acc" v-if="filas.length">
          <button class="tu-btn-sm" @click="marcarTodos(true)">Todo</button>
          <button class="tu-btn-sm" @click="marcarTodos(false)">Nada</button>
        </div>
      </div>

      <div v-if="filas.length" class="tu-asignar">
        <span class="tu-lbl">Asignar a seleccionados:</span>
        <select v-model.number="turnoBulk" class="tu-inp"><option :value="0">— Turno —</option><option v-for="g in grupos" :key="g.cod" :value="g.cod">{{ g.des }}</option></select>
        <button class="tu-btn ok" :disabled="!turnoBulk || !elegidos" @click="asignarBulk">Asignar</button>
        <span class="tu-cont">{{ elegidos }} seleccionados · {{ cambios.length }} con cambios</span>
      </div>

      <div v-if="filas.length" class="tu-grid-wrap">
        <table class="tu-tabla">
          <thead><tr><th>OK</th><th>Código</th><th>Nombre</th><th>Legajo</th><th>Convenio</th><th>Turno</th></tr></thead>
          <tbody>
            <tr v-for="f in filasFiltradas" :key="f.cod" :class="{ on: f.sel }">
              <td class="c"><input type="checkbox" v-model="f.sel" /></td>
              <td>{{ f.cod }}</td><td>{{ f.nombre }}</td><td>{{ f.legajo }}</td><td>{{ f.convenio }}</td>
              <td :class="{ chg: f.turno !== f.viejo }">
                <select v-model.number="f.turno" class="tu-sel"><option :value="0">— Sin turno —</option><option v-for="g in grupos" :key="g.cod" :value="g.cod">{{ g.des }}</option></select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="tu-pie">
        <button class="tu-btn sec" @click="imprimir">🖨 Imprimir</button>
        <button class="tu-btn excel" @click="exportarExcel">📊 Exportar a Excel</button>
        <button class="tu-btn ok" :disabled="!cambios.length || procesando" @click="confirmar">{{ procesando ? '⟳…' : 'Confirmar' }}</button>
      </div>
      <p v-if="msg" :class="['tu-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="tu-pdf-ov" @click.self="cerrarPdf">
        <div class="tu-pdf-md"><div class="tu-pdf-head"><span>{{ pdfNombre }}</span><div class="tu-pdf-acc">
          <button class="tu-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
          <button class="tu-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
          <button class="tu-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button></div></div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="tu-pdf-frame"></iframe></div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import RelojTurnosAyuda from '@/components/RelojTurnosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const empresas = ref<{ cod: number; nombre: string }[]>([]); const empresa = ref(0)
const grupos = ref<{ cod: number; des: string }[]>([])
interface Fila { cod: number; nombre: string; legajo: number; convenio: string; turno: number; viejo: number; sel: boolean }
const filas = ref<Fila[]>([]); const buscar = ref(''); const turnoBulk = ref(0)
const cargando = ref(false); const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const elegidos = computed(() => filas.value.filter(f => f.sel).length)
const cambios = computed(() => filas.value.filter(f => f.turno !== f.viejo))
const filasFiltradas = computed(() => { const q = buscar.value.trim().toLowerCase(); return q ? filas.value.filter(f => f.nombre.toLowerCase().includes(q) || String(f.legajo).includes(q) || String(f.cod).includes(q)) : filas.value })
const turnoDes = (c: number) => grupos.value.find(g => g.cod === c)?.des ?? ''
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

onMounted(async () => {
  try { const data = (await api.get('/empresas')).data; const arr = Array.isArray(data) ? data : (data.data ?? []); empresas.value = arr.map((e: any) => ({ cod: Number(e.EMP_COD ?? e.cod), nombre: (e.EMP_NOM ?? e.nombre ?? '').toString().trim() })); const ae = empresas.value.find(e => /AUTOELEVADOR/i.test(e.nombre)); if (ae) empresa.value = ae.cod } catch { /* */ }
  try { grupos.value = (await api.get('/reloj/turnos/grupos')).data.grupos ?? [] } catch { /* */ }
})

const aceptar = async () => {
  if (!empresa.value) return
  cargando.value = true; msg.value = ''
  try { filas.value = ((await api.get('/reloj/turnos/personal', { params: { empresa: empresa.value } })).data.personal ?? []).map((p: any) => ({ ...p, viejo: p.turno, sel: false })); if (!filas.value.length) flash('No hay personal activo para esa empresa.', true) }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el personal.', true) }
  finally { cargando.value = false }
}
const marcarTodos = (v: boolean) => filas.value.forEach(f => f.sel = v)
const asignarBulk = () => { if (!turnoBulk.value) return; for (const f of filas.value) if (f.sel) f.turno = turnoBulk.value }

const confirmar = async () => {
  if (!cambios.value.length) return
  if (!confirm('¿Confirma los cambios de turnos laborales?')) return
  procesando.value = true
  try { const { data } = await api.post('/reloj/turnos/confirmar', { cambios: cambios.value.map(f => ({ cod: f.cod, turno: f.turno })) }); flash(data.message); await aceptar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron guardar los turnos.', true) }
  finally { procesando.value = false }
}

const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('Turnos Laborales', 14, 16)
  doc.setFontSize(9); doc.text(`Empresa: ${empresas.value.find(e => e.cod === empresa.value)?.nombre ?? ''}`, 14, 22)
  const cols = ['Código', 'Nombre', 'Legajo', 'Convenio', 'Turno']; const xs = [14, 34, 96, 116, 150]; let y = 30
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
  for (const f of filas.value) { if (y > 285) { doc.addPage(); y = 20 } [String(f.cod), f.nombre.slice(0, 30), String(f.legajo), f.convenio.slice(0, 14), turnoDes(f.turno).slice(0, 24)].forEach((c, i) => doc.text(c, xs[i], y)); y += 5 }
  cerrarPdf(); pdfNombre.value = 'TURNOS_LABORALES.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const exportarExcel = async () => {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const head = ['Código', 'Nombre', 'Legajo', 'Convenio', 'Turno Laboral']
  const rows = filas.value.map(f => [f.cod, f.nombre, f.legajo, f.convenio, turnoDes(f.turno)])
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>' + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), 'TURNOS_LABORALES.xls')
}
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
</script>

<style scoped>
.tu-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.tu-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.tu-cab-ico { font-size:28px; } .tu-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .tu-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.tu-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.tu-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.tu-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.tu-toolbar { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.tu-f { display:flex; flex-direction:column; gap:4px; } .tu-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.tu-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .tu-inp.ancho { min-width:300px; }
.tu-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .tu-btn.excel { background:#107c41; } .tu-btn.sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; } .tu-btn:disabled { background:#cbd5e1; color:#fff; cursor:default; } .tu-btn.sec:disabled { background:#fff; color:#cbd5e1; }
.tu-acc { margin-left:auto; display:flex; gap:8px; }
.tu-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.tu-asignar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; }
.tu-cont { font-size:12px; color:#64748b; margin-left:auto; }
.tu-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:54vh; }
.tu-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.tu-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.tu-tabla td { padding:4px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .tu-tabla td.c { text-align:center; } .tu-tabla td.chg { background:#fff7a8; }
.tu-tabla tbody tr.on td { background:#ffd9bf; } .tu-tabla tbody tr.on td.chg { background:#fff7a8; }
.tu-sel { border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:12.5px; background:transparent; min-width:170px; }
.tu-pie { display:flex; gap:12px; align-items:center; }
.tu-pie .tu-btn.ok { margin-left:auto; }
.tu-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:760px; } .tu-msg.ok { background:#dcfce7; color:#166534; } .tu-msg.err { background:#fee2e2; color:#b91c1c; }
.tu-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.tu-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.tu-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.tu-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.tu-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .tu-pdf-b.ok { background:#22c55e; color:#fff; } .tu-pdf-b.cancel { background:#ef4444; color:#fff; }
.tu-pdf-frame { flex:1; border:none; width:100%; }
</style>
