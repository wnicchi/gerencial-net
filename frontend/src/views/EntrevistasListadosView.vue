<!-- EntrevistasListadosView.vue — Listado de Entrevistados (entrevistas_listados.scx). Informe PDF. -->
<template>
  <div class="el-view">
    <div class="el-cab">
      <div class="el-ico">📋</div>
      <div class="el-tx"><h1>Listado de Entrevistados</h1><p>Informe de entrevistados en PDF</p></div>
      <button class="el-ia" @click="modalIA = true">🤖 IA</button>
      <button class="el-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/entrevistas" titulo="Asistente IA — Listado de Entrevistados"
            subtitulo="Preguntá sobre el informe de entrevistados"
            :sugerencias="['¿Para qué sirve este listado?','¿Qué filtros puedo usar?','¿Qué muestra el listado con fotografía?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['el-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="el-body">
      <div class="el-card">
        <div class="el-fila">
          <label>Sector</label>
          <label class="el-radio"><input type="radio" :value="1" v-model="xSector" /> Todas</label>
          <label class="el-radio"><input type="radio" :value="2" v-model="xSector" /> Un</label>
          <select v-if="xSector === 2" v-model.number="sector" class="el-sel">
            <option :value="0" disabled>— Sector —</option>
            <option v-for="s in sectores" :key="s.cod" :value="s.cod">{{ s.nombre }}</option>
          </select>
        </div>

        <div class="el-fila">
          <label>Orden</label>
          <label class="el-radio"><input type="radio" value="nombre" v-model="orden" /> Alfabeto</label>
          <label class="el-radio"><input type="radio" value="codigo" v-model="orden" /> Código</label>
          <label class="el-radio"><input type="radio" value="fecha" v-model="orden" /> F. Entrevista</label>
        </div>

        <div class="el-fila">
          <label class="el-check"><input type="checkbox" v-model="conFoto" /> Completo con fotografía</label>
        </div>

        <div class="el-fila">
          <label>Documento</label>
          <label class="el-radio"><input type="radio" :value="1" v-model="xDoc" /> Todos</label>
          <label class="el-radio"><input type="radio" :value="2" v-model="xDoc" /> Uno</label>
          <input v-if="xDoc === 2" v-model.number="documento" type="number" class="el-num" placeholder="Nro." />
        </div>

        <div class="el-acc">
          <button class="el-consultar" :disabled="generando" @click="consultar">{{ generando ? '⟳ Generando…' : '🔎 CONSULTAR' }}</button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="el-ov" @click.self="ayuda = false">
        <div class="el-help-md">
          <h3>❓ Ayuda — Listado de Entrevistados</h3>
          <ul>
            <li>Generá un informe PDF de los entrevistados.</li>
            <li><b>Sector</b>: todos o uno. <b>Orden</b>: alfabético, por código o por fecha de entrevista.</li>
            <li><b>Documento</b>: todos o un número puntual.</li>
            <li><b>Completo con fotografía</b>: incluye la foto de cada entrevistado (una ficha por página-fila).</li>
          </ul>
          <div class="el-acc"><span style="flex:1"></span><button class="el-consultar" @click="ayuda = false">Cerrar</button></div>
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
import { ref } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

const sectores = ref<{ cod: number; nombre: string }[]>([])
const xSector = ref(1); const sector = ref(0)
const orden = ref<'nombre' | 'codigo' | 'fecha'>('nombre')
const conFoto = ref(false)
const xDoc = ref(1); const documento = ref(0)
const generando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const fmt = (s: string) => s ? s.split('-').reverse().join('/') : ''

async function cargarInit () { try { sectores.value = (await api.get('/entrevistas/init')).data.sectores ?? [] } catch { /* */ } }

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

async function consultar () {
  if (xSector.value === 2 && !sector.value) { flash('Elija un sector.', true); return }
  if (xDoc.value === 2 && !documento.value) { flash('Ingrese el número de documento.', true); return }
  generando.value = true
  try {
    const params: any = { orden: orden.value, con_foto: conFoto.value ? 1 : 0 }
    if (xSector.value === 2) params.sector = sector.value
    if (xDoc.value === 2) params.documento = documento.value
    const { data } = await api.get('/entrevistas/listado', { params })
    const items = data.items ?? []
    if (!items.length) { flash('No se encuentran datos en el esquema consultado.', true); return }
    conFoto.value ? pdfConFoto(items) : pdfSimple(items)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el listado.', true) }
  finally { generando.value = false }
}

function cabecera (doc: jsPDF, PW: number) {
  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('LISTA DE ENTREVISTADOS', PW / 2, 16, { align: 'center' }); doc.setTextColor(0, 0, 0)
}

function pdfSimple (items: any[]) {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 10, PW = 297, PH = 210; let y = 24
  cabecera(doc, PW)
  doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.setFont('helvetica', 'bold')
  doc.rect(ML, y - 4, PW - 2 * ML, 6, 'F')
  const cx = { c: ML + 1, n: ML + 12, f: ML + 82, s: ML + 100, ss: ML + 150, t: ML + 200, fo: ML + 235 }
  doc.text('Cód', cx.c, y); doc.text('Nombre', cx.n, y); doc.text('Fecha', cx.f, y); doc.text('Sector', cx.s, y)
  doc.text('Sub-Sector', cx.ss, y); doc.text('Teléfono', cx.t, y); doc.text('Formación', cx.fo, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  for (const e of items) {
    if (y > PH - 12) { doc.addPage(); y = 16 }
    doc.setFontSize(8)
    doc.text(String(e.cod), cx.c, y); doc.text((e.nombre || '').slice(0, 40), cx.n, y)
    doc.text(fmt(e.fecha), cx.f, y); doc.text((e.sector || '').slice(0, 28), cx.s, y)
    doc.text((e.subsector || '').slice(0, 26), cx.ss, y); doc.text((e.telefono || '').slice(0, 20), cx.t, y)
    doc.text((e.formacion || '').slice(0, 32), cx.fo, y); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'Entrevistados.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

function pdfConFoto (items: any[]) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, PW = 210, PH = 297; let y = 24
  cabecera(doc, PW)
  for (const e of items) {
    if (y > PH - 46) { doc.addPage(); y = 16 }
    // Foto
    if (e.foto) { try { doc.addImage(e.foto, 'JPEG', ML, y, 32, 38) } catch { /* */ } }
    else { doc.setDrawColor(200); doc.rect(ML, y, 32, 38); doc.setFontSize(7); doc.setTextColor(150); doc.text('sin foto', ML + 16, y + 20, { align: 'center' }); doc.setTextColor(0) }
    // Datos
    const tx = ML + 38
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.text(`${e.nombre}  (${e.cod})`, tx, y + 5)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
    const linea = (lbl: string, val: string, yy: number) => { doc.setFont('helvetica', 'bold'); doc.text(lbl, tx, yy); doc.setFont('helvetica', 'normal'); doc.text((val || '').slice(0, 90), tx + 26, yy) }
    linea('Documento:', `${e.tipo_doc} ${e.numero_doc || ''}`, y + 12)
    linea('Fecha:', fmt(e.fecha), y + 17)
    linea('Sector:', `${e.sector}${e.subsector ? ' / ' + e.subsector : ''}`, y + 22)
    linea('Formación:', e.formacion, y + 27)
    linea('Teléfono:', `${e.telefono}   ${e.email || ''}`, y + 32)
    if (e.notas) { doc.setFont('helvetica', 'italic'); doc.setFontSize(8); doc.text(doc.splitTextToSize(`Notas: ${e.notas}`, PW - tx - ML).slice(0, 2), tx, y + 37) }
    doc.setDrawColor(220); doc.line(ML, y + 42, PW - ML, y + 42)
    y += 46
  }
  cerrarPdf(); pdfNombre.value = 'Entrevistados_con_foto.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

cargarInit()
</script>

<style scoped>
.el-view { display:flex; flex-direction:column; min-height:100%; }
.el-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.el-ico { font-size:28px; } .el-tx h1 { margin:0; font-size:19px; color:#1e293b; } .el-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.el-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.el-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.el-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .el-msg.ok { background:#d1fae5; color:#065f46; } .el-msg.err { background:#fee2e2; color:#991b1b; }
.el-body { padding:16px 18px; }
.el-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; max-width:600px; }
.el-fila { display:flex; align-items:center; gap:14px; margin-bottom:14px; flex-wrap:wrap; }
.el-fila > label:first-child { width:90px; font-size:13px; font-weight:700; color:#374151; }
.el-radio, .el-check { display:flex; align-items:center; gap:5px; font-size:13px; color:#1e293b; cursor:pointer; }
.el-sel { border:1px solid #c8d8ea; border-radius:6px; padding:7px 10px; font-size:13px; min-width:200px; }
.el-num { border:1px solid #c8d8ea; border-radius:6px; padding:7px 10px; font-size:13px; width:110px; }
.el-acc { display:flex; align-items:center; gap:8px; margin-top:6px; }
.el-consultar { background:#1b4332; color:#fff; border:none; border-radius:8px; padding:10px 26px; cursor:pointer; font-weight:800; font-size:13px; } .el-consultar:disabled { opacity:.5; }
.el-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.el-help-md { background:#fff; border-radius:14px; padding:22px; width:min(540px,94vw); } .el-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .el-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.el-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.el-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.el-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .el-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.el-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .el-pdf-b.ok { background:#22c55e; color:#fff; } .el-pdf-b.cancel { background:#ef4444; color:#fff; }
.el-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
