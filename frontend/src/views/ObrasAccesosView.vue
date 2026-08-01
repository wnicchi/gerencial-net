<!-- ObrasAccesosView.vue — Obras - Habilitados a Ingresar (obras_accesos). -->
<template>
  <div class="oa-view">
    <div class="oa-cab">
      <div class="oa-cab-ico">🚪</div>
      <div class="oa-cab-tx"><h1>Obras - Habilitados a Ingresar</h1><p>Empleados de obras con acceso permitido o denegado</p></div>
      <button class="oa-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="oa-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ObrasAccesosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/obras-accesos" titulo="Asistente IA — Obras - Habilitados a Ingresar"
            subtitulo="Preguntá sobre el listado de accesos"
            :sugerencias="['¿Cuándo un empleado queda PERMITIDO?','¿Qué es una obra vigente?','¿Puedo exportarlo a Excel?']"
            @close="modalIA = false" />

    <div class="oa-filtros">
      <span class="oa-lbl">Salida</span>
      <label><input v-model.number="salida" type="radio" :value="1" /> Visualizar</label>
      <label><input v-model.number="salida" type="radio" :value="2" /> Imprimir</label>
      <label><input v-model.number="salida" type="radio" :value="3" /> Exportar a Excel</label>
      <button class="oa-btn-ok" :disabled="generando" @click="generar">{{ generando ? '⟳ Generando…' : etiqueta }}</button>
    </div>

    <div v-if="rows.length" class="oa-grid-wrap">
      <table class="oa-grid">
        <thead><tr><th>Contratista</th><th>Apellido y Nombre</th><th>DNI</th><th>Acceso</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="i">
            <td>{{ r.contratista }}</td><td>{{ r.nombre }}</td><td>{{ r.dni }}</td>
            <td><span :class="['oa-acc', r.acceso === 'PERMITIDO' ? 'ok' : 'no']">{{ r.acceso }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="consultado" class="oa-vacio">No hay empleados en obras.</div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="oa-pdf-ov" @click.self="cerrarPdf">
        <div class="oa-pdf-md">
          <div class="oa-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="oa-pdf-acc">
              <button class="oa-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="oa-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="oa-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="oa-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ObrasAccesosAyuda from '@/components/ObrasAccesosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl, guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Fila { contratista: string; nombre: string; dni: string; acceso: string }
const salida = ref(1)
const rows = ref<Fila[]>([]); const generando = ref(false); const consultado = ref(false)
const etiqueta = computed(() => salida.value === 1 ? 'VISUALIZAR' : salida.value === 2 ? 'IMPRIMIR' : 'EXPORTAR A EXCEL')

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const generar = async () => {
  generando.value = true; consultado.value = true
  try {
    rows.value = (await api.get('/obras/accesos')).data
    if (!rows.value.length) return
    if (salida.value === 3) { exportarExcel(); return }
    construirPdf(salida.value === 2)
  } catch (e) { console.error(e); alert('No se pudo generar el listado.') } finally { generando.value = false }
}

const construirPdf = (imprimir: boolean) => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; const colN = mL + 70, colD = mL + 130
  let y = 0
  const hoy = new Date().toLocaleDateString('es-AR')
  const cab = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('OBRAS — HABILITADOS A INGRESAR', mL, 16)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.text(`Fecha: ${hoy}`, mR, 16, { align: 'right' })
    doc.setLineWidth(0.4); doc.line(mL, 20, mR, 20)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
    doc.text('Contratista', mL, 26); doc.text('Apellido y Nombre', colN, 26); doc.text('DNI', colD, 26); doc.text('Acceso', mR, 26, { align: 'right' })
    doc.setLineWidth(0.2); doc.line(mL, 28, mR, 28); y = 33
  }
  cab(); doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  const corta = (t: string, n: number) => t.length > n ? t.slice(0, n - 1) + '…' : t
  for (const r of rows.value) {
    if (y > 285) { doc.addPage(); cab(); doc.setFont('helvetica', 'normal'); doc.setFontSize(8) }
    doc.text(corta(r.contratista, 40), mL, y); doc.text(corta(r.nombre, 34), colN, y); doc.text(r.dni, colD, y)
    if (r.acceso === 'PERMITIDO') doc.setTextColor(22, 128, 61); else doc.setTextColor(185, 28, 28)
    doc.setFont('helvetica', 'bold'); doc.text(r.acceso, mR, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); doc.setTextColor(0, 0, 0); y += 5.5
  }
  const total = rows.value.length, permit = rows.value.filter(r => r.acceso === 'PERMITIDO').length
  if (y > 280) { doc.addPage(); y = 20 }
  doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 5; doc.setFontSize(9)
  doc.text(`Total: ${total}   |   Permitidos: ${permit}   |   Denegados: ${total - permit}`, mL, y)
  cerrarPdf(); pdfNombre.value = 'Obras_Habilitados_Ingresar.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  if (imprimir) setTimeout(() => (document.querySelector('iframe') as HTMLIFrameElement)?.contentWindow?.print(), 600)
}

const exportarExcel = async () => {
  const filas = rows.value.map(r => `<tr><td>${esc(r.contratista)}</td><td>${esc(r.nombre)}</td><td>${esc(r.dni)}</td><td>${r.acceso}</td></tr>`).join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>
    <table border="1"><thead><tr style="background:#1b4332;color:#fff;font-weight:bold">
    <th>Contratista</th><th>Apellido y Nombre</th><th>DNI</th><th>Acceso</th></tr></thead><tbody>${filas}</tbody></table></body></html>`
  await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), 'Planilla_Accesos_Obras.xls')
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
</script>

<style scoped>
.oa-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.oa-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.oa-cab-ico { font-size:28px; } .oa-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .oa-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.oa-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.oa-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.oa-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:18px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.oa-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.oa-filtros label { display:flex; align-items:center; gap:5px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.oa-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .oa-btn-ok:disabled { background:#cbd5e1; }
.oa-grid-wrap { margin:14px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:66vh; }
.oa-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.oa-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 12px; font-size:12px; text-align:left; }
.oa-grid td { padding:6px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.oa-grid tr:nth-child(even) td { background:#f8fafc; }
.oa-acc { font-weight:700; padding:2px 10px; border-radius:999px; font-size:11px; } .oa-acc.ok { background:#dcfce7; color:#166534; } .oa-acc.no { background:#fee2e2; color:#b91c1c; }
.oa-vacio { padding:50px; text-align:center; color:#9ca3af; font-size:14px; }
.oa-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.oa-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.oa-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.oa-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.oa-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .oa-pdf-b.ok { background:#22c55e; color:#fff; } .oa-pdf-b.cancel { background:#ef4444; color:#fff; }
.oa-pdf-frame { flex:1; border:none; width:100%; }
</style>
