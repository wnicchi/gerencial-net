<!-- ObrasListadosView.vue — Obras - Listados (obras_listados / informe_obras). -->
<template>
  <div class="ol-view">
    <div class="ol-cab">
      <div class="ol-cab-ico">📊</div>
      <div class="ol-cab-tx"><h1>Obras - Listados</h1><p>Listado de obras y sus obreros</p></div>
      <button class="ol-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ol-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ObrasListadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/obras-listados" titulo="Asistente IA — Obras - Listados"
            subtitulo="Preguntá sobre el listado de obras"
            :sugerencias="['¿Qué muestra cada opción?','¿Qué es una obra habilitada?','¿Qué significa el estado P o D?']"
            @close="modalIA = false" />

    <div class="ol-filtros">
      <div class="ol-grupo"><span class="ol-lbl">Obras</span>
        <label><input v-model.number="cuantas" type="radio" :value="1" /> Todas</label>
        <label><input v-model.number="cuantas" type="radio" :value="2" /> Una</label>
        <select v-if="cuantas === 2" v-model.number="obra" class="ol-sel">
          <option :value="0">— Elegir obra —</option>
          <option v-for="o in obras" :key="o.cod" :value="o.cod">{{ o.cod }} · {{ o.descripcion }}</option>
        </select>
      </div>
      <div class="ol-grupo"><span class="ol-lbl">Clasificados</span>
        <label><input v-model.number="estado" type="radio" :value="1" /> Histórico</label>
        <label><input v-model.number="estado" type="radio" :value="2" /> Solo en ejecución</label>
      </div>
      <div class="ol-grupo"><span class="ol-lbl">Orden</span>
        <label><input v-model.number="orden" type="radio" :value="1" /> Contratista</label>
        <label><input v-model.number="orden" type="radio" :value="2" /> Código</label>
      </div>
      <button class="ol-btn-ok" :disabled="generando || (cuantas === 2 && !obra)" @click="consultar">
        {{ generando ? '⟳ Generando…' : 'CONSULTAR' }}
      </button>
    </div>

    <div v-if="consultado" class="ol-cuerpo">
      <div v-if="!datos.length" class="ol-vacio">No hay obras para estos filtros.</div>
      <div v-for="o in datos" :key="o.cod" class="ol-card">
        <div class="ol-ohead">
          <h3>N° {{ o.cod }} — {{ o.descripcion }}</h3>
          <span :class="['ol-est', o.vigente ? 'ok' : 'no']">{{ o.vigente ? 'EN EJECUCIÓN' : 'FINALIZADA' }}</span>
        </div>
        <div class="ol-ometa">
          <span><b>Contratista:</b> {{ o.contratista }}</span>
          <span><b>Inicio:</b> {{ fmt(o.inicio) }}</span>
          <span><b>Final:</b> {{ o.final ? fmt(o.final) : '—' }}</span>
          <span><b>Responsable:</b> {{ o.usuario }}</span>
        </div>
        <p v-if="o.notas" class="ol-notas">{{ o.notas }}</p>
        <table class="ol-tb">
          <thead><tr><th>Código</th><th>Nombre del empleado</th><th>DNI</th><th>CUIL/CUIT</th><th>Teléfono</th><th>Venc. ART</th><th>Estado</th></tr></thead>
          <tbody>
            <tr v-for="(e, ei) in o.empleados" :key="ei">
              <td>{{ e.cod }}</td><td>{{ e.nombre }}</td><td>{{ e.dni }}</td><td>{{ e.cuil }}</td><td>{{ e.telefono }}</td><td>{{ fmt(e.venc_art) }}</td>
              <td><span :class="['ol-pd', e.estado === 'P' ? 'ok' : 'no']">{{ e.estado }}</span></td>
            </tr>
            <tr v-if="!o.empleados.length"><td colspan="7" class="ol-vacio">Sin obreros asignados.</td></tr>
          </tbody>
        </table>
      </div>
      <button v-if="datos.length" class="ol-btn-pdf" @click="generarPdf">📄 Visualizar / Imprimir PDF</button>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ol-pdf-ov" @click.self="cerrarPdf">
        <div class="ol-pdf-md">
          <div class="ol-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ol-pdf-acc">
              <button class="ol-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ol-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ol-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ol-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ObrasListadosAyuda from '@/components/ObrasListadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Emp { cod: number; nombre: string; dni: string; cuil: string; telefono: string; celular: string; venc_art: string; estado: string }
interface Obra { cod: number; contratista: string; descripcion: string; notas: string; inicio: string; final: string; usuario: string; vigente: boolean; empleados: Emp[] }

const cuantas = ref(1); const obra = ref(0); const estado = ref(1); const orden = ref(1)
const obras = ref<{ cod: number; descripcion: string }[]>([])
const datos = ref<Obra[]>([]); const consultado = ref(false); const generando = ref(false)
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''

const consultar = async () => {
  generando.value = true; consultado.value = true
  try {
    const params: any = { cuantas: cuantas.value, estado: estado.value, orden: orden.value }
    if (cuantas.value === 2) params.obra = obra.value
    datos.value = (await api.get('/obras/listado', { params })).data.obras
  } catch (e) { console.error(e); datos.value = [] } finally { generando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const generarPdf = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 12, mR = 198; let y = 0
  const hoy = new Date().toLocaleDateString('es-AR')
  const cab = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('INFORME DE OBRAS', mL, 15)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.text(`Fecha: ${hoy}`, mR, 15, { align: 'right' })
    doc.setLineWidth(0.4); doc.line(mL, 18, mR, 18); y = 24
  }
  cab()
  const salto = (h: number) => { if (y + h > 287) { doc.addPage(); cab() } }

  for (const o of datos.value) {
    salto(20)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
    doc.text(`N° ${o.cod} — ${o.descripcion}  [${o.vigente ? 'EN EJECUCIÓN' : 'FINALIZADA'}]`, mL, y)
    doc.setTextColor(0, 0, 0); y += 5
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
    doc.text(`Contratista: ${o.contratista}   Inicio: ${fmt(o.inicio)}   Final: ${o.final ? fmt(o.final) : '—'}   Responsable: ${o.usuario}`, mL, y); y += 5
    // cabecera tabla
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5)
    doc.text('Cód', mL, y); doc.text('Empleado', mL + 14, y); doc.text('DNI', mL + 70, y)
    doc.text('CUIL', mL + 92, y); doc.text('Teléfono', mL + 124, y); doc.text('ART', mL + 152, y); doc.text('Est', mR, y, { align: 'right' })
    y += 1; doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 3.5
    doc.setFont('helvetica', 'normal')
    for (const e of o.empleados) {
      salto(5)
      doc.text(String(e.cod), mL, y); doc.text(corta(e.nombre, 30), mL + 14, y); doc.text(e.dni, mL + 70, y)
      doc.text(e.cuil, mL + 92, y); doc.text(e.telefono, mL + 124, y); doc.text(fmt(e.venc_art), mL + 152, y)
      doc.text(e.estado, mR, y, { align: 'right' }); y += 4.4
    }
    y += 4; doc.setDrawColor(220); doc.line(mL, y - 2, mR, y - 2)
  }
  cerrarPdf(); pdfNombre.value = 'Informe_Obras.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
const corta = (t: string, n: number) => t.length > n ? t.slice(0, n - 1) + '…' : t

onMounted(async () => {
  try { obras.value = (await api.get('/obras')).data.map((o: any) => ({ cod: o.cod, descripcion: o.descripcion })) }
  catch (e) { console.error(e) }
})
</script>

<style scoped>
.ol-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ol-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ol-cab-ico { font-size:28px; } .ol-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ol-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ol-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ol-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ol-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:20px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ol-grupo { display:flex; align-items:center; gap:12px; } .ol-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ol-grupo label { display:flex; align-items:center; gap:5px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.ol-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:220px; }
.ol-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ol-btn-ok:disabled { background:#cbd5e1; }
.ol-cuerpo { padding:16px 18px; }
.ol-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.ol-card { border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:12px; }
.ol-ohead { display:flex; align-items:center; gap:12px; }
.ol-ohead h3 { margin:0; font-size:15px; color:#1b4332; }
.ol-est { font-size:11px; font-weight:700; padding:2px 9px; border-radius:999px; } .ol-est.ok { background:#dcfce7; color:#166534; } .ol-est.no { background:#e2e8f0; color:#475569; }
.ol-ometa { display:flex; flex-wrap:wrap; gap:16px; font-size:12.5px; color:#475569; margin:6px 0; } .ol-ometa b { color:#1e293b; }
.ol-notas { font-size:12.5px; color:#64748b; font-style:italic; margin:4px 0 8px; }
.ol-tb { width:100%; border-collapse:collapse; font-size:12.5px; }
.ol-tb th { background:#1b4332; color:#fff; padding:5px 9px; text-align:left; font-size:11px; font-weight:600; }
.ol-tb td { padding:4px 9px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ol-pd { font-weight:700; padding:1px 8px; border-radius:999px; font-size:11px; } .ol-pd.ok { background:#dcfce7; color:#166534; } .ol-pd.no { background:#fee2e2; color:#b91c1c; }
.ol-btn-pdf { background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; margin-top:6px; }
.ol-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ol-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ol-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ol-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ol-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ol-pdf-b.ok { background:#22c55e; color:#fff; } .ol-pdf-b.cancel { background:#ef4444; color:#fff; }
.ol-pdf-frame { flex:1; border:none; width:100%; }
</style>
