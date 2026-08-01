<!-- SiniestrosListadosView.vue — ART Siniestros: Listados (art_siniestros_listados.scx). Filtros + informe PDF. -->
<template>
  <div class="sc-view">
    <div class="sc-cab">
      <div class="sc-ico">📋</div>
      <div class="sc-tx"><h1>ART Siniestros — Listados</h1><p>Informe PDF de siniestros por estado y fecha</p></div>
      <button class="sc-ia" @click="modalIA = true">🤖 IA</button>
      <button class="sc-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="sc-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/siniestros-listados" titulo="Asistente IA — Listados de Siniestros"
            subtitulo="Preguntá sobre el informe de siniestros"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo filtro los siniestros?','¿Qué incluye el informe?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['sc-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="sc-body">
      <div class="sc-filtros">
        <div class="sc-checks">
          <label><input v-model="pendiente" type="checkbox" /> Pendiente de resolución</label>
          <label><input v-model="cobrado" type="checkbox" /> Siniestro cobrado</label>
          <label><input v-model="cerrado" type="checkbox" /> Siniestros cerrados</label>
        </div>
        <div class="sc-fechas">
          <div><label>Fecha</label><input v-model="desde" type="date" /></div>
          <div><label>Hasta</label><input v-model="hasta" type="date" /></div>
          <label class="sc-conf"><input v-model="conFotos" type="checkbox" /> con Fotos</label>
        </div>
        <button class="sc-imprimir" :disabled="cargando || generando" @click="consultar">
          {{ cargando ? '⟳ Consultando…' : generando ? '⟳ Generando…' : 'CONSULTAR' }}
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="sc-ov" @click.self="ayuda = false">
        <div class="sc-help-md">
          <h3>❓ Ayuda — Listados de Siniestros</h3>
          <ul>
            <li>Marcá los <b>estados</b> a incluir (pendiente / cobrado / cerrado) — el informe muestra los siniestros que coinciden exactamente con esos estados.</li>
            <li>Elegí el <b>rango de fechas</b>. Se cargan por defecto con el mínimo y máximo existentes.</li>
            <li>Marcá <b>con Fotos</b> para incluir la foto del empleado y las fotos de cada siniestro.</li>
            <li>Presioná <b>CONSULTAR</b> para generar el informe en PDF.</li>
          </ul>
          <div class="sc-acc"><span style="flex:1"></span><button class="sc-buscar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="pdfUrl" class="sc-pdf-ov" @click.self="cerrarPdf">
        <div class="sc-pdf-md">
          <div class="sc-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="sc-pdf-acc">
              <button class="sc-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="sc-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="sc-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="sc-pdf-frame"></iframe>
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

const pendiente = ref(false); const cobrado = ref(false); const cerrado = ref(false); const conFotos = ref(true)
const desde = ref(''); const hasta = ref('')
const cargando = ref(false); const generando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

onMounted(async () => {
  try {
    const { data } = await api.get('/siniestros/listado-rango')
    desde.value = data.desde || ''; hasta.value = data.hasta || ''
  } catch { /* */ }
})

async function consultar () {
  if (!desde.value || !hasta.value) { flash('Ingrese el rango de fechas.', true); return }
  cargando.value = true
  try {
    const { data } = await api.get('/siniestros/listado', {
      params: {
        desde: desde.value, hasta: hasta.value,
        pendiente: pendiente.value ? 1 : 0, cobrado: cobrado.value ? 1 : 0,
        cerrado: cerrado.value ? 1 : 0, con_fotos: conFotos.value ? 1 : 0,
      },
    })
    const lista = data.siniestros ?? []
    if (!lista.length) { flash('No existen siniestros con los parámetros seleccionados.', true); return }
    generarPdf(lista)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el listado.', true) }
  finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function generarPdf (lista: any[]) {
  generando.value = true
  try {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const ML = 15, PW = 210, PH = 297; let y = 16; let primera = true

    for (const s of lista) {
      if (!primera) { doc.addPage(); y = 16 }
      primera = false

      doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(127, 29, 29)
      doc.text(`Siniestro Nro. ${s.nro}`, ML, y); doc.setTextColor(0, 0, 0)
      if (conFotos.value && s.foto_empleado) { try { doc.addImage(s.foto_empleado, 'JPEG', PW - ML - 26, y - 10, 26, 30) } catch { /* */ } }
      y += 10

      const linea = (lbl: string, val: string) => { doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(lbl, ML, y); doc.setFont('helvetica', 'normal'); doc.text(String(val || '—'), ML + 42, y); y += 6 }
      linea('Fecha:', `${fmt(s.fecha)}${s.hora ? '   ' + s.hora + ' hs' : ''}`)
      linea('Empleado:', `${s.empleado} — ${s.empleado_nombre}`)
      linea('Monto reclamado:', money(s.monto_reclamado)); linea('Ofrecimiento:', money(s.ofrecimiento)); linea('Monto cobrado:', money(s.monto_cobrado))
      const est = [s.pendiente_resolucion && 'Pendiente resolución', s.cobrado && 'Cobrado', s.pendiente_judicial && 'Reclamo judicial', s.denuncia_preventiva && 'Denuncia preventiva'].filter(Boolean).join(', ')
      linea('Estado:', est || 'ninguno'); linea('Reclamo:', s.reclamo === 'P' ? 'Propio' : s.reclamo === 'T' ? 'De terceros' : '—')
      y += 2

      const memo = (titulo: string, texto: string) => {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(titulo, ML, y); y += 5
        doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
        for (const ln of doc.splitTextToSize(texto || '—', PW - 2 * ML)) { if (y > PH - 14) { doc.addPage(); y = 16 } doc.text(ln, ML, y); y += 5 }
        y += 3
      }
      memo('DETALLES:', s.detalle); memo('DICTAMEN:', s.dictamen)

      if ((s.documentos ?? []).length) {
        if (y > PH - 24) { doc.addPage(); y = 16 }
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('DOCUMENTOS:', ML, y); y += 6
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
        for (const d of s.documentos) { if (y > PH - 12) { doc.addPage(); y = 16 } doc.text('• ' + String(d), ML + 2, y); y += 5 }
        y += 3
      }

      if (conFotos.value) {
        const conFoto = (s.fotos ?? []).filter((f: any) => f.foto)
        if (conFoto.length) {
          if (y > PH - 70) { doc.addPage(); y = 16 }
          doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('FOTOS:', ML, y); y += 6
          let x = ML
          for (const f of conFoto) {
            if (y > PH - 66) { doc.addPage(); y = 16; x = ML }
            try { doc.addImage(f.foto, 'JPEG', x, y, 85, 60) } catch { /* */ }
            if (f.comentario) { doc.setFont('helvetica', 'italic'); doc.setFontSize(7.5); doc.text(doc.splitTextToSize(f.comentario, 85).slice(0, 2), x, y + 64) }
            if (x === ML) { x = ML + 95 } else { x = ML; y += 72 }
          }
        }
      }
    }

    cerrarPdf(); pdfNombre.value = 'Listado_Siniestros.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } finally { generando.value = false }
}

function reset () { pendiente.value = false; cobrado.value = false; cerrado.value = false; conFotos.value = true }
</script>

<style scoped>
.sc-view { display:flex; flex-direction:column; min-height:100%; }
.sc-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sc-ico { font-size:28px; } .sc-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sc-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sc-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .sc-msg.ok { background:#d1fae5; color:#065f46; } .sc-msg.err { background:#fee2e2; color:#991b1b; }
.sc-body { padding:16px 18px; max-width:620px; }
.sc-filtros { border:1px solid #e2e8f0; border-radius:12px; padding:20px; background:#fafdff; display:flex; flex-direction:column; gap:18px; }
.sc-checks { display:flex; flex-direction:column; gap:10px; }
.sc-checks label, .sc-conf { display:flex; align-items:center; gap:8px; font-size:14px; color:#1e293b; font-weight:600; cursor:pointer; }
.sc-checks input, .sc-conf input { width:16px; height:16px; }
.sc-fechas { display:flex; align-items:flex-end; gap:16px; flex-wrap:wrap; }
.sc-fechas label { font-size:12px; font-weight:600; color:#374151; display:block; }
.sc-fechas input[type=date] { border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; color:#1e293b; margin-top:4px; }
.sc-conf { margin-bottom:4px; }
.sc-imprimir { background:#7f1d1d; color:#fff; border:none; border-radius:8px; padding:12px; cursor:pointer; font-weight:800; font-size:14px; } .sc-imprimir:disabled { opacity:.5; }
.sc-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.sc-buscar { background:#7f1d1d; color:#fff; border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-weight:800; font-size:13px; }
.sc-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.sc-help-md { background:#fff; border-radius:14px; padding:22px; width:min(520px,94vw); } .sc-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .sc-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.sc-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.sc-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.sc-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#7f1d1d; color:#fff; font-size:13px; } .sc-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.sc-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .sc-pdf-b.ok { background:#22c55e; color:#fff; } .sc-pdf-b.cancel { background:#ef4444; color:#fff; }
.sc-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
