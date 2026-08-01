<!-- ContratistasFaltasView.vue — Listado Exigencias y Faltantes (contra_lista_faltas). -->
<template>
  <div class="cf-view">
    <div class="cf-cab">
      <div class="cf-cab-ico">📊</div>
      <div class="cf-cab-tx"><h1>Listado Exigencias y Faltantes</h1><p>Contratistas con obligaciones pendientes o ART vencida</p></div>
      <button class="cf-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cf-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ContratistasFaltasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/contratistas-faltas" titulo="Asistente IA — Exigencias y Faltantes"
            subtitulo="Preguntá sobre el listado de faltantes"
            :sugerencias="['¿Qué muestra cada opción?','¿Cuándo aparece un empleado?','¿Qué es una exigencia faltante?']"
            @close="modalIA = false" />

    <div class="cf-filtros">
      <div class="cf-grupo"><span class="cf-lbl">Contratista</span>
        <label><input v-model.number="alcance" type="radio" :value="1" /> Todos</label>
        <label><input v-model.number="alcance" type="radio" :value="2" /> Uno</label>
        <select v-if="alcance === 2" v-model.number="contratista" class="cf-sel">
          <option :value="0">— Elegir —</option>
          <option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option>
        </select>
      </div>
      <div class="cf-grupo"><span class="cf-lbl">Obligaciones</span>
        <label><input v-model.number="tipo" type="radio" :value="1" /> Faltantes</label>
        <label><input v-model.number="tipo" type="radio" :value="2" /> Todas</label>
      </div>
      <button class="cf-btn-ok" :disabled="generando || (alcance === 2 && !contratista)" @click="consultar">
        {{ generando ? '⟳ Generando…' : 'CONSULTAR' }}
      </button>
    </div>

    <div v-if="consultado" class="cf-cuerpo">
      <p class="cf-titulo">{{ titulo }}</p>
      <div v-if="!datos.length" class="cf-vacio">No hay contratistas con obligaciones pendientes para estos filtros.</div>
      <div v-for="(c, i) in datos" :key="i" class="cf-card">
        <h3>{{ c.contratista }}</h3>
        <div v-if="c.exigencias.length" class="cf-sub">
          <span class="cf-stit">Exigencias</span>
          <table class="cf-tb"><thead><tr><th>Detalle</th><th>Tipo</th><th>Presentada</th><th>Vence</th></tr></thead>
            <tbody><tr v-for="(e, j) in c.exigencias" :key="j" :class="{ falta: !e.presentada }">
              <td>{{ e.detalle }}</td><td>{{ e.tipo }}</td><td>{{ e.presentada ? 'SÍ' : 'NO' }}</td><td>{{ e.vence }}</td>
            </tr></tbody></table>
        </div>
        <div v-if="c.empleados.length" class="cf-sub">
          <span class="cf-stit cf-rojo">Empleados con ART vencida (acceso permitido)</span>
          <table class="cf-tb"><thead><tr><th>Nombre</th><th>DNI</th><th>Venc. ART</th></tr></thead>
            <tbody><tr v-for="(e, j) in c.empleados" :key="j"><td>{{ e.nombre }}</td><td>{{ e.dni }}</td><td>{{ e.venc_art }}</td></tr></tbody></table>
        </div>
      </div>
      <button v-if="datos.length" class="cf-btn-pdf" @click="generarPdf">📄 Visualizar / Imprimir PDF</button>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="cf-pdf-ov" @click.self="cerrarPdf">
        <div class="cf-pdf-md">
          <div class="cf-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cf-pdf-acc">
              <button class="cf-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cf-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cf-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="cf-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ContratistasFaltasAyuda from '@/components/ContratistasFaltasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Exi { detalle: string; tipo: string; presentada: boolean; vence: string }
interface Emp { nombre: string; dni: string; venc_art: string }
interface Cont { contratista: string; exigencias: Exi[]; empleados: Emp[] }

const alcance = ref(1); const contratista = ref(0); const tipo = ref(1)
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const datos = ref<Cont[]>([]); const titulo = ref(''); const consultado = ref(false); const generando = ref(false)

const consultar = async () => {
  generando.value = true; consultado.value = true
  try {
    const params: any = { tipo: tipo.value }
    if (alcance.value === 2) params.contratista = contratista.value
    const { data } = await api.get('/contratistas-faltas', { params })
    datos.value = data.contratistas; titulo.value = data.titulo
  } catch (e) { console.error(e); datos.value = [] } finally { generando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const generarPdf = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; let y = 0
  const hoy = new Date().toLocaleDateString('es-AR')
  const cab = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
    doc.text('LISTADO DE EXIGENCIAS Y FALTANTES', mL, 15)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    doc.text(titulo.value, mL, 21); doc.text(`Fecha: ${hoy}`, mR, 15, { align: 'right' })
    doc.setLineWidth(0.4); doc.line(mL, 24, mR, 24); y = 30
  }
  cab()
  const salto = (h: number) => { if (y + h > 285) { doc.addPage(); cab() } }

  for (const c of datos.value) {
    salto(14)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50)
    doc.text(c.contratista, mL, y); doc.setTextColor(0, 0, 0); y += 6
    if (c.exigencias.length) {
      doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.text('Exigencias', mL, y); y += 4
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
      for (const e of c.exigencias) {
        salto(5)
        doc.text(`• ${e.detalle}  [${e.tipo}]  Pres: ${e.presentada ? 'SÍ' : 'NO'}${e.vence ? '  Vence: ' + e.vence : ''}`, mL + 3, y); y += 4.6
      }
    }
    if (c.empleados.length) {
      salto(5); doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.setTextColor(185, 28, 28)
      doc.text('Empleados con ART vencida (acceso permitido)', mL, y); doc.setTextColor(0, 0, 0); y += 4
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
      for (const e of c.empleados) {
        salto(5); doc.text(`• ${e.nombre}   DNI ${e.dni}   ART vencida: ${e.venc_art}`, mL + 3, y); y += 4.6
      }
    }
    y += 4; doc.setDrawColor(220); doc.line(mL, y - 2, mR, y - 2)
  }
  cerrarPdf(); pdfNombre.value = 'Exigencias_Faltantes.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(async () => {
  try { contratistas.value = (await api.get('/contratistas-externos')).data.filter((c: any) => c.estado === 'A').map((c: any) => ({ cod: c.cod, nombre: c.nombre })) }
  catch (e) { console.error(e) }
})
</script>

<style scoped>
.cf-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cf-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cf-cab-ico { font-size:28px; } .cf-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cf-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cf-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cf-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cf-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:24px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.cf-grupo { display:flex; align-items:center; gap:14px; } .cf-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.cf-grupo label { display:flex; align-items:center; gap:5px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.cf-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:220px; }
.cf-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .cf-btn-ok:disabled { background:#cbd5e1; }
.cf-cuerpo { padding:16px 18px; }
.cf-titulo { font-size:15px; font-weight:700; color:#1b4332; margin:0 0 12px; }
.cf-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.cf-card { border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:12px; }
.cf-card h3 { margin:0 0 8px; font-size:15px; color:#1b4332; }
.cf-sub { margin-bottom:10px; } .cf-stit { font-size:12px; font-weight:700; color:#475569; } .cf-stit.cf-rojo { color:#b91c1c; }
.cf-tb { width:100%; border-collapse:collapse; font-size:12.5px; margin-top:5px; }
.cf-tb th { background:#1b4332; color:#fff; padding:5px 9px; text-align:left; font-size:11px; font-weight:600; }
.cf-tb td { padding:4px 9px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .cf-tb tr.falta td { background:#fef2f2; }
.cf-btn-pdf { background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; margin-top:6px; }
.cf-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cf-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.cf-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.cf-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cf-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cf-pdf-b.ok { background:#22c55e; color:#fff; } .cf-pdf-b.cancel { background:#ef4444; color:#fff; }
.cf-pdf-frame { flex:1; border:none; width:100%; }
</style>
