<!-- FeriadosView.vue — Liquidaciones / Definición de Feriados (definicion_de_feriados). -->
<template>
  <div class="fr-view">
    <div class="fr-cab">
      <div class="fr-cab-ico">📅</div>
      <div class="fr-cab-tx"><h1>Definición de Feriados</h1><p>Carga de los feriados del calendario</p></div>
      <button class="fr-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="fr-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <FeriadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/feriados" titulo="Asistente IA — Feriados"
            subtitulo="Preguntá sobre la carga de feriados" :sugerencias="['¿Cómo agrego un feriado?','¿Cómo lo elimino?','¿Para qué sirven los feriados?']"
            @close="modalIA = false" />

    <div class="fr-card">
      <div class="fr-top">
        <div class="fr-mesanio">
          <span class="fr-lbl">Ingrese el mes/año a procesar</span>
          <div class="fr-r">
            <label>Mes <select v-model.number="mes" class="fr-inp" @change="consultar"><option v-for="m in 12" :key="m" :value="m">{{ m }} — {{ MESES[m - 1] }}</option></select></label>
            <label>Año <input v-model.number="anio" type="number" class="fr-inp chico" @change="consultar" /></label>
          </div>
        </div>
        <div class="fr-agregar">
          <div class="fr-agregar-tit">Agregar / Modificar</div>
          <div class="fr-r">
            <label>Día <input v-model.number="form.dia" type="number" min="1" max="31" class="fr-inp chico" /></label>
          </div>
          <label class="fr-obs-l">Observaciones</label>
          <input v-model="form.obs" class="fr-inp ancho" placeholder="Razón del feriado…" @input="form.obs = form.obs.toUpperCase()" />
          <button class="fr-btn ok" :disabled="procesando" @click="aceptar">Aceptar</button>
        </div>
      </div>

      <div class="fr-grid-wrap">
        <table class="fr-tabla">
          <thead><tr><th>Día</th><th>Mes</th><th>Año</th><th>Fecha</th><th>Día de la semana</th><th>Observaciones</th></tr></thead>
          <tbody>
            <tr v-for="f in feriados" :key="f.fecha" @dblclick="eliminar(f)" title="Doble clic para eliminar">
              <td class="c">{{ f.dia }}</td><td class="c">{{ f.mes }}</td><td class="c">{{ f.anio }}</td><td>{{ fmt(f.fecha) }}</td><td>{{ f.diaSemana }}</td><td>{{ f.obs }}</td>
            </tr>
            <tr v-if="!feriados.length"><td colspan="6" class="vacio">No hay feriados cargados en {{ MESES[mes - 1] }} {{ anio }}.</td></tr>
          </tbody>
        </table>
      </div>
      <p class="fr-hint">Realice doble clic para eliminar un feriado.</p>

      <div class="fr-pie">
        <input v-model="desde" type="date" class="fr-inp" /> <span>hasta</span> <input v-model="hasta" type="date" class="fr-inp" />
        <button class="fr-btn excel" @click="imprimir">📄 Imprimir Rango</button>
      </div>
      <p v-if="msg" :class="['fr-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="fr-pdf-ov" @click.self="cerrarPdf">
        <div class="fr-pdf-md"><div class="fr-pdf-head"><span>{{ pdfNombre }}</span><div class="fr-pdf-acc">
          <button class="fr-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
          <button class="fr-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
          <button class="fr-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button></div></div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="fr-pdf-frame"></iframe></div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import FeriadosAyuda from '@/components/FeriadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const hoy = new Date()
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const desde = ref(`${hoy.getFullYear()}-01-01`); const hasta = ref(`${hoy.getFullYear()}-12-31`)
const form = reactive({ dia: 1, obs: '' })
const feriados = ref<any[]>([]); const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const fmt = (iso: string) => { const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

onMounted(consultar)
async function consultar () { try { feriados.value = (await api.get('/feriados', { params: { mes: mes.value, anio: anio.value } })).data.feriados ?? [] } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar.', true) } }

const aceptar = async () => {
  if (!form.obs.trim()) return flash('Debe ingresar en observación la razón del feriado.', true)
  procesando.value = true
  try { const { data } = await api.post('/feriados', { mes: mes.value, anio: anio.value, dia: form.dia, obs: form.obs }); flash(data.message); form.obs = ''; await consultar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}
const eliminar = async (f: any) => {
  if (!confirm(`¿Desea eliminar el feriado del día ${fmt(f.fecha)} (${f.obs})?`)) return
  procesando.value = true
  try { await api.post('/feriados/eliminar', { fecha: f.fecha }); flash('Feriado eliminado.'); await consultar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}

const imprimir = async () => {
  let filas: any[] = []
  try { filas = (await api.get('/feriados/rango', { params: { desde: desde.value, hasta: hasta.value } })).data.feriados ?? [] }
  catch (e: any) { return flash(e?.response?.data?.message ?? 'No se pudo obtener el rango.', true) }
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('Lista de Feriados', 14, 16)
  doc.setFontSize(9); doc.text(`Desde ${fmt(desde.value)} hasta ${fmt(hasta.value)}`, 14, 22)
  const cols = ['Fecha', 'Día', 'Observaciones']; const xs = [14, 44, 86]; let y = 32
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
  for (const f of filas) { if (y > 285) { doc.addPage(); y = 20 } [fmt(f.fecha), f.diaSemana, f.obs.slice(0, 60)].forEach((c, i) => doc.text(String(c), xs[i], y)); y += 5 }
  cerrarPdf(); pdfNombre.value = 'LISTA_DE_FERIADOS.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
</script>

<style scoped>
.fr-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.fr-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.fr-cab-ico { font-size:28px; } .fr-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .fr-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.fr-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.fr-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.fr-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.fr-top { display:flex; gap:18px; flex-wrap:wrap; align-items:stretch; }
.fr-mesanio, .fr-agregar { border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; display:flex; flex-direction:column; gap:8px; }
.fr-agregar { background:#f8fafc; }
.fr-lbl, .fr-agregar-tit { font-size:12.5px; font-weight:800; color:#1b4332; }
.fr-r { display:flex; align-items:center; gap:12px; flex-wrap:wrap; font-size:13px; color:#1e293b; }
.fr-inp { border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:13px; color:#1e293b; } .fr-inp.chico { width:80px; } .fr-inp.ancho { min-width:320px; }
.fr-obs-l { font-size:12px; font-weight:700; color:#1b4332; }
.fr-btn { border:none; padding:8px 18px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .fr-btn.excel { background:#107c41; } .fr-btn:disabled { background:#cbd5e1; cursor:default; }
.fr-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:46vh; }
.fr-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.fr-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.fr-tabla td { padding:5px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .fr-tabla td.c { text-align:center; }
.fr-tabla tbody tr { cursor:pointer; } .fr-tabla tbody tr:hover { background:#fef2f2; } .fr-tabla .vacio { text-align:center; color:#94a3b8; padding:16px; cursor:default; }
.fr-hint { font-size:12px; color:#b45309; margin:0; }
.fr-pie { display:flex; align-items:center; gap:10px; font-size:13px; color:#475569; }
.fr-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:720px; } .fr-msg.ok { background:#dcfce7; color:#166534; } .fr-msg.err { background:#fee2e2; color:#b91c1c; }
.fr-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.fr-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.fr-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.fr-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.fr-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .fr-pdf-b.ok { background:#22c55e; color:#fff; } .fr-pdf-b.cancel { background:#ef4444; color:#fff; }
.fr-pdf-frame { flex:1; border:none; width:100%; }
</style>
