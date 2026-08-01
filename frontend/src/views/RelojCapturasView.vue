<!-- RelojCapturasView.vue — Reloj / Registro de Personal (reloj_capturas). -->
<template>
  <div class="rc-view">
    <div class="rc-cab">
      <div class="rc-cab-ico">🕐</div>
      <div class="rc-cab-tx"><h1>Reloj - Registro de Personal</h1><p>Marcaciones de entrada y salida del reloj de personal</p></div>
      <button class="rc-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="rc-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojCapturasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-capturas" titulo="Asistente IA — Registro de Personal"
            subtitulo="Preguntá sobre las marcaciones del reloj"
            :sugerencias="['¿Qué son los ajustes?','¿Cómo filtro por nombre?','¿Por qué hay filas en amarillo?']"
            @close="modalIA = false" />

    <div class="rc-card">
      <div class="rc-toolbar">
        <div class="rc-f"><span class="rc-lbl">Rango Fecha</span><input v-model="desde" type="date" class="rc-inp" /></div>
        <div class="rc-f"><span class="rc-lbl">al</span><input v-model="hasta" type="date" class="rc-inp" /></div>
        <button class="rc-btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'Aceptar' }}</button>
        <div class="rc-f"><span class="rc-lbl">Filtrar por nombre</span><input v-model="nombre" type="text" class="rc-inp" placeholder="Nombre…" @input="onNombre" /></div>
        <label class="rc-chk"><input type="checkbox" v-model="soloAjustes" @change="consultar" /> Mostrar solo ajustes</label>
        <label class="rc-chk"><input type="checkbox" v-model="conAjustes" @change="consultar" /> Incluir ajustes</label>
        <div class="rc-acc">
          <span class="rc-total">Total de registros: <b>{{ total }}</b></span>
          <button class="rc-btn ok" :disabled="!filas.length" @click="imprimir">🖨 Imprimir Consulta</button>
        </div>
      </div>

      <div v-if="cargando" class="rc-loading">⟳ Cargando marcaciones…</div>
      <div v-else class="rc-grid-wrap">
        <table class="rc-tabla">
          <thead><tr>
            <th>Código</th><th>Registro</th><th>Empleado</th><th>Legajo</th><th>Doc</th><th>Nro. Doc.</th><th>CUIL</th><th>Celular</th><th>Rel</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="f.ajuste ? 'ajuste' : (i % 2 ? 'par' : '')">
              <td>{{ f.codigo }}</td><td class="reg">{{ fmtFecha(f.registro) }}</td><td>{{ f.empleado }}</td>
              <td>{{ f.legajo }}</td><td>{{ f.tdoc }}</td><td>{{ f.ndoc }}</td><td>{{ f.cuil }}</td><td>{{ f.celular }}</td><td class="c">{{ f.rel }}</td>
            </tr>
            <tr v-if="!filas.length"><td colspan="9" class="vacio">No hay marcaciones en ese rango.</td></tr>
          </tbody>
        </table>
      </div>
      <p v-if="msg" class="rc-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="rc-pdf-ov" @click.self="cerrarPdf">
        <div class="rc-pdf-md">
          <div class="rc-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="rc-pdf-acc">
              <button class="rc-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="rc-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="rc-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="rc-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import RelojCapturasAyuda from '@/components/RelojCapturasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date(); const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
const desde = ref(_iso(primero)); const hasta = ref(_iso(hoy))
const nombre = ref(''); const soloAjustes = ref(false); const conAjustes = ref(false)

interface Cap { codigo: number; registro: string; empleado: string; legajo: number; tdoc: string; ndoc: string; cuil: string; celular: string; rel: number; ajuste: boolean }
const filas = ref<Cap[]>([]); const total = ref(0); const cargando = ref(false); const msg = ref('')

const fmtFecha = (iso: string) => {
  if (!iso) return ''
  const [f, h] = iso.split(' '); const [a, m, d] = f.split('-')
  const [hh, mm] = (h || '00:00:00').split(':'); let H = parseInt(hh, 10); const ap = H >= 12 ? 'PM' : 'AM'; H = H % 12 || 12
  return `${d}/${m}/${a} ${String(H).padStart(2, '0')}:${mm} ${ap}`
}

const consultar = async () => {
  cargando.value = true; msg.value = ''
  try {
    const params: any = { desde: desde.value, hasta: hasta.value, soloAjustes: soloAjustes.value ? 1 : 0, conAjustes: conAjustes.value ? 1 : 0 }
    if (nombre.value.trim()) params.nombre = nombre.value.trim()
    const { data } = await api.get('/reloj/capturas', { params })
    filas.value = data.capturas ?? []; total.value = data.total ?? 0
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudieron cargar las marcaciones.' }
  finally { cargando.value = false }
}
onMounted(consultar)

let tN: any = null
const onNombre = () => { clearTimeout(tN); tN = setTimeout(consultar, 400) }

const imprimir = () => {
  if (!filas.value.length) return
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text('Reporte de Reloj de Personal', 14, 14)
  doc.setFontSize(9); doc.text(`Desde el ${fmtFecha(desde + ' 00:00:00').split(' ')[0]} hasta el ${fmtFecha(hasta + ' 00:00:00').split(' ')[0]} — Total: ${total.value}`, 14, 20)
  const cols = ['Código', 'Registro', 'Empleado', 'Legajo', 'Doc', 'Nro. Doc.', 'CUIL', 'Celular', 'Rel']
  const xs = [14, 32, 70, 130, 146, 158, 184, 215, 260]; let y = 30
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 2
  doc.setLineWidth(0.2); doc.line(14, y, 283, y); y += 5
  for (const f of filas.value) {
    if (y > 195) { doc.addPage(); y = 20 }
    const row = [String(f.codigo), fmtFecha(f.registro), f.empleado.slice(0, 30), String(f.legajo), f.tdoc, f.ndoc, f.cuil, f.celular, String(f.rel)]
    row.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'RELOJ_REGISTRO_PERSONAL.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
</script>

<style scoped>
.rc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.rc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.rc-cab-ico { font-size:28px; } .rc-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .rc-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.rc-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rc-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rc-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.rc-toolbar { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.rc-f { display:flex; flex-direction:column; gap:4px; } .rc-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.rc-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; }
.rc-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; padding-bottom:7px; }
.rc-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .rc-btn:disabled { background:#cbd5e1; cursor:default; }
.rc-acc { margin-left:auto; display:flex; align-items:center; gap:14px; }
.rc-total { font-size:13px; color:#475569; } .rc-total b { color:#1e293b; }
.rc-loading { padding:30px; text-align:center; color:#64748b; }
.rc-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:66vh; }
.rc-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.rc-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.rc-tabla td { padding:5px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .rc-tabla td.c { text-align:center; } .rc-tabla td.reg { font-weight:600; color:#1d4ed8; }
.rc-tabla tbody tr.par td { background:#eef5ee; }
.rc-tabla tbody tr.ajuste td { background:#fff7cc; }
.rc-tabla .vacio { text-align:center; color:#94a3b8; padding:16px; }
.rc-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fee2e2; color:#b91c1c; max-width:700px; }
.rc-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.rc-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.rc-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.rc-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.rc-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .rc-pdf-b.ok { background:#22c55e; color:#fff; } .rc-pdf-b.cancel { background:#ef4444; color:#fff; }
.rc-pdf-frame { flex:1; border:none; width:100%; }
</style>
