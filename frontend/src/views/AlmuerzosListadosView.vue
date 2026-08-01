<!-- AlmuerzosListadosView.vue — Listados de Almuerzos del Personal. -->
<template>
  <div class="ll-view">
    <div class="ll-cab">
      <div class="ll-cab-ico">📊</div>
      <div class="ll-cab-tx"><h1>Almuerzos del Personal — Listados</h1><p>Configurá el informe y generá el PDF</p></div>
      <button class="ll-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ll-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <AlmuerzosListadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/almuerzos-listados" titulo="Asistente IA — Listados de Almuerzos"
            subtitulo="Preguntá cómo armar el informe"
            :sugerencias="['¿Cómo saco los almuerzos de una jornada?','¿Qué diferencia hay entre detallado y resumido?','¿Cómo filtro por un comedor?']"
            @close="modalIA = false" />

    <div class="ll-card">
      <!-- Rango -->
      <div class="ll-fila">
        <label class="ll-r"><input v-model.number="f.rango" type="radio" :value="1" /> Un período</label>
        <label class="ll-r"><input v-model.number="f.rango" type="radio" :value="2" /> Una Jornada</label>
        <template v-if="f.rango === 1">
          <span class="ll-sep">Desde <input v-model="f.fecha1" type="date" /></span>
          <span class="ll-sep">al <input v-model="f.fecha2" type="date" /></span>
        </template>
        <span v-else class="ll-sep">Fecha <input v-model="f.fecha1" type="date" /></span>
      </div>

      <!-- Empresa -->
      <div class="ll-fila">
        <label class="ll-r"><input v-model.number="f.empresa_modo" type="radio" :value="1" /> Todas</label>
        <label class="ll-r"><input v-model.number="f.empresa_modo" type="radio" :value="2" /> Una Empresa</label>
        <select v-model.number="f.empresa_id" :disabled="f.empresa_modo !== 2">
          <option :value="0">—</option>
          <option v-for="e in empresas" :key="e.cod" :value="Number(e.cod)">{{ e.nombre }}</option>
        </select>
      </div>

      <!-- Comedor -->
      <div class="ll-fila">
        <label class="ll-r"><input v-model.number="f.comedor_modo" type="radio" :value="1" /> Todos</label>
        <label class="ll-r"><input v-model.number="f.comedor_modo" type="radio" :value="2" /> Un Comedor</label>
        <select v-model.number="f.comedor_id" :disabled="f.comedor_modo !== 2">
          <option :value="0">—</option>
          <option v-for="c in comedores" :key="c.cod" :value="Number(c.cod)">{{ c.descripcion }}</option>
        </select>
      </div>

      <!-- Tipo informe -->
      <div class="ll-fila">
        <label class="ll-r"><input v-model.number="f.detallado" type="radio" :value="1" /> Detallado</label>
        <label class="ll-r"><input v-model.number="f.detallado" type="radio" :value="2" /> Resumido</label>
      </div>

      <!-- Orden -->
      <div class="ll-fila">
        <label class="ll-r"><input v-model.number="f.orden" type="radio" :value="1" /> x Nombre</label>
        <label class="ll-r"><input v-model.number="f.orden" type="radio" :value="2" /> x Legajo</label>
        <label class="ll-r"><input v-model.number="f.orden" type="radio" :value="3" /> x Código</label>
      </div>

      <button class="ll-btn-conf" :disabled="generando" @click="generar">{{ generando ? '⟳ Generando…' : 'CONFIRMAR' }}</button>
      <p v-if="msg" class="ll-msg">{{ msg }}</p>
    </div>

    <!-- Modal PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="ll-pdf-ov" @click.self="cerrarPdf">
        <div class="ll-pdf-md">
          <div class="ll-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ll-pdf-acc">
              <button class="ll-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ll-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ll-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ll-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { useAuthStore } from '@/stores/auth'
import AlmuerzosListadosAyuda from '@/components/AlmuerzosListadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const authStore = useAuthStore()
const modalAyuda = ref(false); const modalIA = ref(false)

interface Opcion { cod: string | number; nombre?: string; descripcion?: string }
const empresas = ref<Opcion[]>([]); const comedores = ref<Opcion[]>([])
const hoy = new Date().toISOString().slice(0, 10)
const f = ref({ rango: 1, empresa_modo: 1, empresa_id: 0, comedor_modo: 1, comedor_id: 0, detallado: 1, orden: 1, fecha1: hoy, fecha2: hoy })
const generando = ref(false); const msg = ref('')

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const generar = async () => {
  generando.value = true; msg.value = ''
  try {
    const params: any = { rango: f.value.rango, empresa_modo: f.value.empresa_modo, comedor_modo: f.value.comedor_modo, detallado: f.value.detallado, orden: f.value.orden, fecha1: f.value.fecha1, fecha2: f.value.fecha2 }
    if (f.value.empresa_modo === 2) params.empresa_id = f.value.empresa_id
    if (f.value.comedor_modo === 2) params.comedor_id = f.value.comedor_id
    const { data } = await api.get('/almuerzos/listado', { params })
    if (!data.empresas.length) { msg.value = 'No se encontraron almuerzos para el filtro seleccionado.'; return }
    construirPdf(data)
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo generar el listado.' }
  finally { generando.value = false }
}

const construirPdf = (data: any) => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, PH = 297, ML = 12, MR = 12
  const resumido = data.tipo === 'resumido'
  const cols = resumido
    ? [{ t: 'Código', w: 26, n: true }, { t: 'Empleado', w: 110 }, { t: 'Legajo', w: 24, n: true }, { t: 'Cantidad', w: 26, n: true }]
    : [{ t: 'Código', w: 22, n: true }, { t: 'Empleado', w: 86 }, { t: 'Legajo', w: 22, n: true }, { t: 'Fecha', w: 30 }, { t: 'Cantidad', w: 26, n: true }]
  let y = 0
  const tituloPag = () => {
    y = 15
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(27, 67, 50)
    doc.text('LISTADO DE ALMUERZOS', PW / 2, y, { align: 'center' }); y += 5
    doc.setFontSize(9); doc.setTextColor(80, 80, 80); doc.text(data.titulo, PW / 2, y, { align: 'center' }); y += 6
  }
  const cabTabla = (empNom: string) => {
    if (y > PH - 30) { doc.addPage(); tituloPag() }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
    doc.text('EMPRESA ' + empNom, ML, y); y += 5
    doc.setFontSize(8); doc.setTextColor(0, 0, 0); let x = ML
    doc.setFillColor(229, 231, 235); doc.rect(ML, y - 4, PW - ML - MR, 6, 'F')
    for (const c of cols) { doc.text(c.t, c.n ? x + c.w - 1 : x + 1, y, c.n ? { align: 'right' } : {}); x += c.w }
    y += 4; doc.setDrawColor(180); doc.line(ML, y - 2.5, PW - MR, y - 2.5)
  }
  tituloPag()
  doc.setFont('helvetica', 'normal')
  for (const emp of data.empresas) {
    cabTabla(emp.emp_nom)
    let n = 0
    for (const r of emp.rows) {
      if (y > PH - 18) { doc.addPage(); tituloPag(); cabTabla(emp.emp_nom) }
      if (n % 2 === 1) { doc.setFillColor(247, 250, 252); doc.rect(ML, y - 3.5, PW - ML - MR, 5, 'F') }
      let x = ML; doc.setTextColor(30, 41, 59); doc.setFontSize(8); doc.setFont('helvetica', 'normal')
      const cell = (txt: string, c: any) => {
        let t = String(txt ?? ''); while (t && doc.getTextWidth(t) > c.w - 2) t = t.slice(0, -1)
        doc.text(t, c.n ? x + c.w - 1 : x + 1, y, c.n ? { align: 'right' } : {}); x += c.w
      }
      if (resumido) { cell(String(r.cod), cols[0]); cell(r.empleado, cols[1]); cell(r.legajo, cols[2]); cell(String(r.cantidad), cols[3]) }
      else { cell(String(r.cod), cols[0]); cell(r.empleado, cols[1]); cell(r.legajo, cols[2]); cell(r.fecha, cols[3]); cell(String(r.cantidad), cols[4]) }
      y += 5; n++
    }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(27, 67, 50)
    doc.text(`Total Empresa ${emp.emp_nom} :  ${emp.total}`, PW - MR, y + 1, { align: 'right' })
    y += 9
  }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(15, 23, 42)
  doc.text(`Total de Almuerzos :  ${data.total}`, ML, y + 2)

  const tot = doc.getNumberOfPages()
  const pie = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
  for (let p = 1; p <= tot; p++) { doc.setPage(p); doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90); doc.text(pie, ML, PH - 8); doc.text(`Página ${p} de ${tot}`, PW - MR, PH - 8, { align: 'right' }) }
  cerrarPdf(); pdfNombre.value = `Almuerzos_Listado_${data.tipo}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(async () => {
  try {
    const [e, c] = await Promise.all([api.get('/empresas'), api.get('/comedores')])
    empresas.value = e.data; comedores.value = c.data
  } catch (err) { console.error(err) }
})
</script>

<style scoped>
.ll-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ll-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ll-cab-ico { font-size:28px; } .ll-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ll-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ll-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ll-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ll-card { margin:18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px; max-width:680px; }
.ll-fila { display:flex; flex-wrap:wrap; align-items:center; gap:16px; padding:8px 0; border-bottom:1px dashed #e5e7eb; }
.ll-r { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.ll-sep { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#374151; }
.ll-fila input[type=date], .ll-fila select { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; outline:none; }
.ll-fila select:disabled { background:#eef2f7; color:#94a3b8; }
.ll-btn-conf { margin-top:16px; background:#16a34a; color:#fff; border:none; padding:11px 28px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.ll-btn-conf:hover:not(:disabled){ background:#15803d; } .ll-btn-conf:disabled { background:#cbd5e1; }
.ll-msg { margin:12px 0 0; padding:8px 12px; background:#fef3c7; color:#92400e; border-radius:6px; font-size:13px; }
.ll-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ll-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ll-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ll-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ll-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ll-pdf-b.ok { background:#22c55e; color:#fff; } .ll-pdf-b.cancel { background:#ef4444; color:#fff; }
.ll-pdf-frame { flex:1; border:none; width:100%; }
</style>
