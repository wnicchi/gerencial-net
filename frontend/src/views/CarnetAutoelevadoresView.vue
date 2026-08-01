<!--
  CarnetAutoelevadoresView.vue — Impresión del Carnet de Autoelevadores.
  Réplica del formulario FoxPro: se elige un empleado, se cargan otorgamiento,
  vencimiento y clasificación, y se imprime el carnet (PDF) con su foto.
-->
<template>
  <div class="ca-view">
    <!-- Cabecera -->
    <div class="ca-cab">
      <div class="ca-cab-ico">🪪</div>
      <div class="ca-cab-tx">
        <h1>Carnet de Autoelevadores</h1>
        <p>Impresión del carnet de conductor de autoelevador</p>
      </div>
      <button class="ca-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ca-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <CarnetAutoelevadoresAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/carnet"
            titulo="Asistente IA — Carnet de Autoelevadores"
            subtitulo="Preguntá cómo emitir el carnet"
            :sugerencias="[
              '¿Cómo emito un carnet?',
              '¿Qué datos necesito cargar?',
              '¿Qué clasificaciones existen?',
              '¿El carnet queda guardado?']"
            @close="modalIA = false" />

    <div class="ca-card">
      <!-- Selección de empleado -->
      <div class="ca-campo">
        <label>Empleado</label>
        <EmpleadoInput :codigo="emp?.PER_COD ?? 0" :nombre="(emp?.PER_NOM || '').trim()" @select="onSelEmp" />
      </div>

      <!-- Datos del empleado seleccionado -->
      <div v-if="emp" class="ca-emp">
        <div class="ca-foto">
          <img v-if="fotoUrl" :src="fotoUrl" alt="foto" />
          <div v-else class="ca-foto-ph">Sin foto</div>
        </div>
        <div class="ca-emp-datos">
          <div class="ca-dato"><span>Empleado</span><b>{{ emp.PER_COD }} — {{ (emp.PER_NOM || '').trim() }}</b></div>
          <div class="ca-dato"><span>DNI</span><b>{{ dniFmt }}</b></div>
        </div>
      </div>

      <!-- Datos del carnet -->
      <div v-if="emp" class="ca-form">
        <div class="ca-campo">
          <label>Otorgado <span class="req">*</span></label>
          <input v-model="otorgado" type="date" />
        </div>
        <div class="ca-campo">
          <label>Vence <span class="req">*</span></label>
          <input v-model="vence" type="date" />
        </div>
        <div class="ca-campo ca-campo-ancho">
          <label>Clasificación <span class="req">*</span></label>
          <input v-model="clasificacion" type="text" maxlength="40" placeholder="Ej: PRIMERA"
                 style="text-transform:uppercase" />
        </div>
      </div>

      <div v-if="emp" class="ca-acciones">
        <button class="ca-btn-imprimir" :disabled="!puedeImprimir" @click="imprimirCarnet">🖨️ Imprimir Carnet</button>
        <p v-if="error" class="ca-error">⚠️ {{ error }}</p>
      </div>
    </div>

    <!-- Modal de previsualización PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pdf-modal-overlay" @click.self="cerrarPdf">
        <div class="pdf-modal">
          <div class="pdf-modal-head">
            <span>{{ pdfNombre }}</span>
            <div class="pdf-modal-acc">
              <button class="btn-sm btn-ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="btn-sm btn-ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="btn-sm btn-cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="pdf-modal-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import jsPDF from 'jspdf'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import CarnetAutoelevadoresAyuda from '@/components/CarnetAutoelevadoresAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false)
const modalIA = ref(false)

// ── Búsqueda / selección de empleado ──────────────────────
const emp = ref<any | null>(null)
const fotoUrl = ref<string | null>(null)
const onSelEmp = (r: any) => seleccionar(r)

const seleccionar = async (r: any) => {
  fotoUrl.value = null
  error.value = ''
  try {
    const { data } = await api.get(`/empleados/${r.PER_COD}`)
    emp.value = data
  } catch { emp.value = r }
  // Foto (best-effort)
  try {
    const { data } = await api.get(`/empleados/${r.PER_COD}/foto`)
    if (data?.foto?.startsWith('data:image')) fotoUrl.value = data.foto
  } catch { /* sin foto */ }
}

// ── Datos del carnet ──────────────────────────────────────
const hoy = new Date()
const otorgado = ref(hoy.toISOString().slice(0, 10))
const vence = ref(`${hoy.getFullYear()}-12-31`)
const clasificacion = ref('PRIMERA')
const error = ref('')

const dniFmt = computed(() => formatDni(emp.value?.PER_NDO))
const puedeImprimir = computed(() => !!emp.value && !!otorgado.value && !!vence.value && !!clasificacion.value.trim())

// ── Helpers ───────────────────────────────────────────────
function formatDni (v: any): string {
  const n = String(v ?? '').replace(/\D/g, '')
  if (!n) return ''
  return n.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}
const fFecha = (v: string): string => {
  if (!v) return ''
  const m = v.match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[3]}/${m[2]}/${m[1]}` : v
}

// ── Modal PDF ─────────────────────────────────────────────
const pdfUrl = ref('')
const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

/** Mide una imagen (dataURL) y devuelve sus dimensiones naturales. */
function medirImagen (src: string): Promise<{ w: number; h: number }> {
  return new Promise((res) => {
    const img = new Image()
    img.onload = () => res({ w: img.naturalWidth, h: img.naturalHeight })
    img.onerror = () => res({ w: 0, h: 0 })
    img.src = src
  })
}

// ── Generar carnet ────────────────────────────────────────
const imprimirCarnet = async () => {
  if (!emp.value) { error.value = 'El código del empleado no es válido.'; return }
  if (!puedeImprimir.value) { error.value = 'Completá otorgamiento, vencimiento y clasificación.'; return }
  error.value = ''

  // Hoja A4 (igual que el reporte FoxPro) con el carnet tamaño bolsillo dibujado
  // arriba a la izquierda. Así, al imprimir la A4 al 100%, el carnet sale a su
  // tamaño real (no se escala a toda la hoja como pasaría con una página chica).
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const CW = 95, CH = 60          // tamaño del carnet (bolsillo)
  const ox = 18, oy = 18          // posición del carnet en la hoja
  // Coordenadas relativas al carnet → absolutas en la hoja
  const X = (x: number) => ox + x
  const Y = (y: number) => oy + y

  // Marco
  doc.setDrawColor(27, 67, 50); doc.setLineWidth(0.8); doc.roundedRect(X(2), Y(2), CW - 4, CH - 4, 2.5, 2.5)
  doc.setLineWidth(0.2); doc.setDrawColor(120)

  // Título
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(27, 67, 50)
  doc.text('CONDUCTOR DE AUTOELEVADOR', X(CW / 2), Y(9), { align: 'center' })
  doc.setLineWidth(0.3); doc.setDrawColor(27, 67, 50); doc.line(X(6), Y(11), X(CW - 6), Y(11))

  // Foto (derecha) — se dibuja manteniendo la proporción (contain), centrada
  const fw = 24, fh = 28, fx = X(CW - 6 - fw), fy = Y(14)
  doc.setDrawColor(120); doc.setLineWidth(0.3); doc.rect(fx, fy, fw, fh)
  if (fotoUrl.value) {
    try {
      const ext = fotoUrl.value.startsWith('data:image/png') ? 'PNG' : 'JPEG'
      const pad = 0.6
      const maxW = fw - pad * 2, maxH = fh - pad * 2
      const { w: iw, h: ih } = await medirImagen(fotoUrl.value)
      let dw = maxW, dh = maxH
      if (iw > 0 && ih > 0) {
        const escala = Math.min(maxW / iw, maxH / ih)   // contain: entra completa sin deformar
        dw = iw * escala; dh = ih * escala
      }
      const dx = fx + (fw - dw) / 2, dy = fy + (fh - dh) / 2   // centrada en el recuadro
      doc.addImage(fotoUrl.value, ext, dx, dy, dw, dh)
    } catch { /* sin foto */ }
  }

  // Datos (izquierda)
  const lx = X(7)
  let y = Y(17)
  const linea = (lbl: string, val: string, big = false) => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(6.5); doc.setTextColor(90, 90, 90)
    doc.text(lbl, lx, y)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(big ? 9 : 7.5); doc.setTextColor(20, 20, 20)
    doc.text(val, lx, y + (big ? 5 : 4))
    y += big ? 9.5 : 8
  }
  linea('Apellido y Nombre:', (emp.value.PER_NOM || '').trim().toUpperCase(), true)
  linea('DNI:', dniFmt.value)
  linea('Otorgamiento:', fFecha(otorgado.value))
  linea('Vencimiento:', fFecha(vence.value))
  linea('Clasificación:', clasificacion.value.trim().toUpperCase())

  // Pie derecha (debajo de la foto)
  doc.setFont('helvetica', 'normal'); doc.setFontSize(5.5); doc.setTextColor(60, 60, 60)
  let py = fy + fh + 4
  doc.text('Apto Médico:', fx, py); py += 4
  doc.setDrawColor(150); doc.line(fx, py, fx + fw, py); py += 3
  doc.setFont('helvetica', 'bold'); doc.setFontSize(5.5)
  doc.text('Representante Legal', fx + fw / 2, py, { align: 'center' }); py += 3
  doc.setFont('helvetica', 'normal')
  doc.text('Firma y Sello', fx + fw / 2, py, { align: 'center' })

  cerrarPdf()
  pdfNombre.value = `Carnet_${emp.value.PER_COD}.pdf`
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ca-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ca-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ca-cab-ico { font-size:28px; }
.ca-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.ca-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ca-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none;
  padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-btn-ia:hover { filter:brightness(1.1); }
.ca-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-btn-ayuda:hover { background:#f0faf4; }

.ca-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; max-width:620px; }
.ca-campo { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.ca-campo label { font-size:12px; font-weight:600; color:#374151; }
.ca-campo .req { color:#dc2626; }
.ca-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.ca-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }

.ca-buscador { position:relative; }
.ca-buscador-lupa { display:flex; gap:8px; } .ca-buscador-lupa input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.ca-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ca-result { list-style:none; margin:4px 0 0; padding:4px; position:absolute; z-index:20; left:0; right:0;
  background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 30px rgba(0,0,0,.18); max-height:260px; overflow:auto; }
.ca-result li { padding:8px 10px; border-radius:6px; cursor:pointer; display:flex; flex-direction:column; gap:2px; }
.ca-result li:hover { background:#f0faf4; }
.ca-result li b { font-size:13px; color:#1e293b; }
.ca-result li span { font-size:11px; color:#6b7280; }

.ca-emp { display:flex; gap:16px; align-items:center; background:#f8fafc; border:1px solid #eef2f7;
  border-radius:10px; padding:14px; margin-bottom:16px; }
.ca-foto { width:90px; height:108px; flex-shrink:0; border:1px solid #cbd5e1; border-radius:6px; overflow:hidden; background:#fff; }
.ca-foto img { width:100%; height:100%; object-fit:cover; }
.ca-foto-ph { width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; }
.ca-emp-datos { display:flex; flex-direction:column; gap:10px; }
.ca-dato { display:flex; flex-direction:column; gap:2px; }
.ca-dato span { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.ca-dato b { font-size:15px; color:#1e293b; }

.ca-form { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.ca-campo-ancho { grid-column:1 / -1; }

.ca-acciones { margin-top:18px; padding-top:16px; border-top:1px solid #f1f5f9; }
.ca-btn-imprimir { background:#16a34a; color:#fff; border:none; padding:11px 22px; border-radius:6px;
  cursor:pointer; font-size:14px; font-weight:700; }
.ca-btn-imprimir:hover:not(:disabled) { background:#15803d; }
.ca-btn-imprimir:disabled { background:#cbd5e1; cursor:default; }
.ca-error { color:#b91c1c; font-size:13px; margin:10px 0 0; }

/* Modal PDF */
.pdf-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:9000;
  display:flex; align-items:center; justify-content:center; padding:20px; }
.pdf-modal { background:#fff; border-radius:10px; width:min(820px,96vw); height:88vh; display:flex; flex-direction:column;
  overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.pdf-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:10px 16px; background:#1b4332; color:#fff; font-size:14px; }
.pdf-modal-acc { display:flex; gap:8px; }
.pdf-modal-frame { flex:1; border:none; width:100%; }
.btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-block; }
.btn-ok { background:#22c55e; color:#fff; }
.btn-ok:hover { background:#16a34a; }
.btn-cancel { background:rgba(255,255,255,.2); color:#fff; }
.btn-cancel:hover { background:rgba(255,255,255,.35); }
</style>
