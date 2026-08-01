<!-- AccesosEmpresaView.vue — Listado de Accesos a la Empresa (PDF/Imprimir/Excel). -->
<template>
  <div class="ae-view">
    <div class="ae-cab">
      <div class="ae-cab-ico">🚪</div>
      <div class="ae-cab-tx"><h1>Listado de Accesos a la Empresa</h1><p>Empleados de contratistas con acceso permitido o denegado</p></div>
      <button class="ae-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ae-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <AccesosEmpresaAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/accesos-empresa" titulo="Asistente IA — Accesos a la Empresa"
            subtitulo="Preguntá sobre el listado de accesos"
            :sugerencias="['¿Cuándo un empleado queda DENEGADO?','¿Qué significa que el contratista está en falta?','¿Puedo verlo de un solo contratista?']"
            @close="modalIA = false" />

    <div class="ae-filtros">
      <div class="ae-grupo">
        <span class="ae-lbl">Contratista</span>
        <label><input v-model.number="alcance" type="radio" :value="1" /> Todos</label>
        <label><input v-model.number="alcance" type="radio" :value="2" /> Uno</label>
        <select v-if="alcance === 2" v-model.number="contratista" class="ae-sel">
          <option :value="0">— Elegir —</option>
          <option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option>
        </select>
      </div>
      <div class="ae-grupo">
        <label><input v-model.number="salida" type="radio" :value="1" /> Visualizar</label>
        <label><input v-model.number="salida" type="radio" :value="2" /> Imprimir</label>
        <label><input v-model.number="salida" type="radio" :value="3" /> Exportar a Excel</label>
      </div>
      <button class="ae-btn-ok" :disabled="generando || (alcance === 2 && !contratista)" @click="generar">
        {{ generando ? '⟳ Generando…' : etiquetaBoton }}
      </button>
    </div>

    <div v-if="rows.length" class="ae-grid-wrap">
      <table class="ae-grid">
        <thead><tr><th>Contratista</th><th>Apellido y Nombre</th><th>DNI</th><th>Acceso</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="i">
            <td>{{ r.contratista }}</td><td>{{ r.nombre }}</td><td>{{ r.dni }}</td>
            <td><span :class="['ae-acc', r.acceso === 'PERMITIDO' ? 'ok' : 'no']">{{ r.acceso }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="consultado" class="ae-vacio">No hay empleados para los filtros seleccionados.</div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ae-pdf-ov" @click.self="cerrarPdf">
        <div class="ae-pdf-md">
          <div class="ae-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ae-pdf-acc">
              <button class="ae-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ae-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ae-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ae-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import AccesosEmpresaAyuda from '@/components/AccesosEmpresaAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl, guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Fila { contratista: string; nombre: string; dni: string; acceso: string }
const alcance = ref(1); const contratista = ref(0); const salida = ref(1)
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const rows = ref<Fila[]>([]); const generando = ref(false); const consultado = ref(false)

const etiquetaBoton = computed(() => salida.value === 1 ? 'VISUALIZAR' : salida.value === 2 ? 'IMPRIMIR' : 'EXPORTAR A EXCEL')

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const cargar = async () => {
  consultado.value = true
  const params: any = {}
  if (alcance.value === 2) params.contratista = contratista.value
  rows.value = (await api.get('/accesos-empresa', { params })).data
}

const generar = async () => {
  generando.value = true
  try {
    await cargar()
    if (!rows.value.length) return
    if (salida.value === 3) { exportarExcel(); return }
    construirPdf(salida.value === 2)
  } catch (e) { console.error(e); alert('No se pudo generar el listado.') }
  finally { generando.value = false }
}

const construirPdf = (imprimir: boolean) => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196, anchoUtil = mR - mL
  const colC = mL, colN = mL + 70, colD = mL + 130, colA = mR
  let y = 0
  const hoy = new Date().toLocaleDateString('es-AR')

  const encabezado = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(14)
    doc.text('LISTADO DE ACCESOS A LA EMPRESA', mL, 16)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    doc.text(`Fecha: ${hoy}`, mR, 16, { align: 'right' })
    doc.setLineWidth(0.4); doc.line(mL, 20, mR, 20)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
    doc.text('Contratista', colC, 26); doc.text('Apellido y Nombre', colN, 26)
    doc.text('DNI', colD, 26); doc.text('Acceso', colA, 26, { align: 'right' })
    doc.setLineWidth(0.2); doc.line(mL, 28, mR, 28)
    y = 33
  }
  encabezado()
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  const corta = (t: string, n: number) => t.length > n ? t.slice(0, n - 1) + '…' : t

  for (const r of rows.value) {
    if (y > 285) { doc.addPage(); encabezado(); doc.setFont('helvetica', 'normal'); doc.setFontSize(8) }
    doc.text(corta(r.contratista, 40), colC, y)
    doc.text(corta(r.nombre, 34), colN, y)
    doc.text(r.dni, colD, y)
    if (r.acceso === 'PERMITIDO') doc.setTextColor(22, 128, 61); else doc.setTextColor(185, 28, 28)
    doc.setFont('helvetica', 'bold'); doc.text(r.acceso, colA, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); doc.setTextColor(0, 0, 0)
    y += 5.5
  }
  // Pie con totales
  const total = rows.value.length
  const permitidos = rows.value.filter(r => r.acceso === 'PERMITIDO').length
  if (y > 280) { doc.addPage(); y = 20 }
  doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 5
  doc.setFontSize(9)
  doc.text(`Total empleados: ${total}   |   Permitidos: ${permitidos}   |   Denegados: ${total - permitidos}`, mL, y)
  void anchoUtil

  cerrarPdf()
  pdfNombre.value = 'Listado_Accesos.pdf'
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  if (imprimir) setTimeout(() => (document.querySelector('iframe') as HTMLIFrameElement)?.contentWindow?.print(), 600)
}

const exportarExcel = async () => {
  const filas = rows.value.map(r =>
    `<tr><td>${esc(r.contratista)}</td><td>${esc(r.nombre)}</td><td>${esc(r.dni)}</td><td>${r.acceso}</td></tr>`).join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>
    <table border="1"><thead><tr style="background:#1b4332;color:#fff;font-weight:bold">
    <th>Contratista</th><th>Apellido y Nombre</th><th>DNI</th><th>Acceso</th></tr></thead>
    <tbody>${filas}</tbody></table></body></html>`
  const blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' })
  await guardarComo(blob, 'Planilla_Accesos.xls')
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

onMounted(async () => {
  try { contratistas.value = (await api.get('/contratistas-externos')).data.filter((c: any) => c.estado === 'A').map((c: any) => ({ cod: c.cod, nombre: c.nombre })) }
  catch (e) { console.error(e) }
})
</script>

<style scoped>
.ae-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ae-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ae-cab-ico { font-size:28px; } .ae-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ae-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ae-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ae-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ae-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:24px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ae-grupo { display:flex; align-items:center; gap:14px; }
.ae-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ae-grupo label { display:flex; align-items:center; gap:5px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.ae-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:220px; }
.ae-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ae-btn-ok:disabled { background:#cbd5e1; cursor:not-allowed; }
.ae-grid-wrap { margin:14px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:64vh; }
.ae-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.ae-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 12px; font-size:12px; text-align:left; }
.ae-grid td { padding:6px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ae-grid tr:nth-child(even) td { background:#f8fafc; }
.ae-acc { font-weight:700; padding:2px 10px; border-radius:999px; font-size:11px; }
.ae-acc.ok { background:#dcfce7; color:#166534; } .ae-acc.no { background:#fee2e2; color:#b91c1c; }
.ae-vacio { padding:50px; text-align:center; color:#9ca3af; font-size:14px; }
.ae-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ae-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ae-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ae-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ae-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ae-pdf-b.ok { background:#22c55e; color:#fff; } .ae-pdf-b.cancel { background:#ef4444; color:#fff; }
.ae-pdf-frame { flex:1; border:none; width:100%; }
</style>
