<!-- RequerimientosInformesView.vue — Requerimientos Informes (requerimientos_informes.scx).
     Envía requerimientos a clientes generando un .eml (X-Unsent:1) que se abre en el correo del operador. -->
<template>
  <div class="ri-view">
    <div class="ri-cab">
      <div class="ri-ico">📧</div>
      <div class="ri-tx"><h1>Requerimientos — Informes</h1><p>Envío de requerimientos de acceso a los clientes</p></div>
      <button class="ri-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ri-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/requerimientos-informes" titulo="Asistente IA — Requerimientos Informes"
            subtitulo="Preguntá sobre el envío de requerimientos a clientes"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo envío el correo?','¿Qué documentos se adjuntan?','¿Qué hace cada informe?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ri-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ri-body">
      <div class="ri-toolbar">
        <input v-model="filtro" class="ri-search" placeholder="🔍 Filtrar por nombre…" />
        <button class="ri-mini" @click="marcar(true)">Todos</button>
        <button class="ri-mini g" @click="marcar(false)">Nada</button>
        <span style="flex:1"></span>
        <button class="ri-pdf" :disabled="!clientes.length" @click="pdfEmpresas">🏢 Empresas</button>
        <button class="ri-pdf" :disabled="!clientes.length" @click="pdfGeneral">📊 Informe General</button>
        <button class="ri-send" :disabled="enviando || !haySel" @click="enviarSeleccionados">{{ enviando ? `⟳ ${progreso}` : '✉ Enviar Email' }}</button>
      </div>

      <div class="ri-grid-wrap">
        <table class="ri-grid">
          <thead><tr>
            <th style="width:38px;text-align:center">OK</th>
            <th style="width:64px">Código</th>
            <th>Cliente</th>
            <th>Contacto 1</th><th>Teléfono 1</th>
            <th>Contacto 2</th><th>Teléfono 2</th>
          </tr></thead>
          <tbody>
            <tr v-for="(c, i) in clientesFiltrados" :key="i" :class="{ sel: sel === c.cod }" @click="seleccionar(c)">
              <td style="text-align:center" @click.stop><input type="checkbox" v-model="c.elegir" /></td>
              <td class="ri-cod">{{ c.cod }}</td><td>{{ c.nombre }}</td>
              <td>{{ c.contacto1 }}</td><td>{{ c.telefono1 }}</td>
              <td>{{ c.contacto2 }}</td><td>{{ c.telefono2 }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Preview de adjuntos/emails del cliente seleccionado -->
      <div class="ri-prev">
        <div class="ri-prev-tit">{{ selNombre ? `Se adjuntará a ${selNombre}` : 'Seleccione un cliente para ver qué se adjuntará' }}</div>
        <div v-if="cargandoPrev" class="ri-prev-info">⟳ Calculando…</div>
        <template v-else-if="selNombre">
          <div class="ri-prev-row"><b>{{ preview.adjuntos.length }} archivo(s):</b>
            <span v-if="!preview.adjuntos.length" class="ri-no">NO POSEE ADJUNTOS</span>
            <button v-for="(a, i) in preview.adjuntos" :key="i" class="ri-tag ri-tag-btn" title="Visualizar" @click="verAdjunto(a)">📄 {{ a.nombre }} 👁️</button>
          </div>
          <div class="ri-prev-row"><b>Email:</b>
            <span v-if="!preview.emails.length" class="ri-no">SIN EMAIL</span>
            <span v-else>{{ preview.emails.join(', ') }}</span>
          </div>
        </template>
      </div>
    </div>

    <DocViewer ref="docVisor" />

    <Teleport to="body">
      <div v-if="ayuda" class="ri-ov" @click.self="ayuda = false">
        <div class="ri-help-md">
          <h3>❓ Ayuda — Requerimientos Informes</h3>
          <ul>
            <li>Tilde los clientes a los que enviar los requerimientos. Al hacer clic en una fila se ven los <b>adjuntos</b> y <b>emails</b>.</li>
            <li><b>Enviar Email</b> genera un archivo <code>.eml</code> por cliente que se abre en su programa de correo (Outlook) <b>listo para enviar</b>, con los documentos adjuntos.</li>
            <li>La primera vez, marque en el navegador <i>"Abrir siempre este tipo de archivo"</i> para que el <code>.eml</code> se abra solo.</li>
            <li><b>Informe General</b> y <b>Empresas</b> generan listados PDF.</li>
          </ul>
          <div class="ri-acc"><span style="flex:1"></span><button class="ri-send" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ri-pdf-ov" @click.self="cerrarPdf">
        <div class="ri-pdf-md">
          <div class="ri-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ri-pdf-acc">
              <button class="ri-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ri-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ri-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ri-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

const clientes = ref<any[]>([]); const filtro = ref('')
const sel = ref(0); const selNombre = ref(''); const preview = ref<{ adjuntos: { nombre: string; id: number }[]; emails: string[] }>({ adjuntos: [], emails: [] })
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)
const cargandoPrev = ref(false); const enviando = ref(false); const progreso = ref('')
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const haySel = computed(() => clientes.value.some(c => c.elegir))
const clientesFiltrados = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? clientes.value.filter(c => c.nombre.toLowerCase().includes(q)) : clientes.value
})
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }
const marcar = (v: boolean) => clientesFiltrados.value.forEach(c => c.elegir = v)

async function cargar () {
  try { clientes.value = ((await api.get('/requerimientos-informes/clientes')).data ?? []).map((c: any) => ({ ...c, elegir: false })) }
  catch { flash('No se pudieron cargar los clientes.', true) }
}
async function seleccionar (c: any) {
  sel.value = c.cod; selNombre.value = c.nombre; cargandoPrev.value = true
  preview.value = { adjuntos: [], emails: [] }
  try { preview.value = (await api.get(`/requerimientos-informes/cliente/${c.cod}/preview`)).data }
  catch { /* */ } finally { cargandoPrev.value = false }
}
async function verAdjunto (a: { nombre: string; id: number }) {
  try {
    const resp = await api.get(`/requerimientos-informes/documento/${a.id}/ver`, { responseType: 'blob' })
    docVisor.value?.open(resp.data as Blob, a.nombre)
  } catch { flash('No se pudo abrir el documento.', true) }
}

async function enviarSeleccionados () {
  const elegidos = clientes.value.filter(c => c.elegir)
  if (!elegidos.length) { flash('Marque al menos un cliente.', true); return }
  if (!confirm(`Se generará un correo (.eml) por cada uno de los ${elegidos.length} cliente(s) marcado(s). Cada uno se abrirá en su programa de correo listo para enviar. ¿Continuar?`)) return
  enviando.value = true; let ok = 0; const errores: string[] = []
  for (let i = 0; i < elegidos.length; i++) {
    const c = elegidos[i]; progreso.value = `${i + 1}/${elegidos.length}`
    try {
      const resp = await api.post(`/requerimientos-informes/cliente/${c.cod}/email`, {}, { responseType: 'blob' })
      const url = URL.createObjectURL(resp.data as Blob)
      const a = document.createElement('a'); a.href = url
      a.download = `Requerimientos_${c.nombre.replace(/[^A-Za-z0-9]+/g, '_')}.eml`
      document.body.appendChild(a); a.click(); a.remove()
      setTimeout(() => URL.revokeObjectURL(url), 4000)
      ok++; await new Promise(r => setTimeout(r, 600)) // pausa para no atascar la apertura en Outlook
    } catch (e: any) {
      let m = 'error'
      try { m = JSON.parse(await (e?.response?.data as Blob).text())?.message ?? 'error' } catch { /* */ }
      errores.push(`${c.nombre}: ${m}`)
    }
  }
  enviando.value = false; progreso.value = ''
  await cargar()
  if (errores.length) flash(`Generados ${ok}. Con problemas: ${errores.join(' · ')}`, true)
  else flash(`Se generaron ${ok} correo(s). Revíselos en su programa de correo y presione Enviar en cada uno.`)
}

// ── PDFs ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function abrirPdf (doc: jsPDF, nombre: string) { cerrarPdf(); pdfNombre.value = nombre; pdfUrl.value = URL.createObjectURL(doc.output('blob')) }

function pdfEmpresas () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 15, PW = 210, PH = 297; let y = 18
  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('REQUERIMIENTOS — LISTADO DE EMPRESAS', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.rect(ML, y - 4, 130, 6, 'F')
  doc.text('Código', ML + 2, y); doc.text('Razón Social', ML + 26, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  for (const c of [...clientes.value].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))) {
    if (y > PH - 16) { doc.addPage(); y = 18 }
    doc.setFontSize(9); doc.text(String(c.cod), ML + 2, y); doc.text((c.nombre || '').slice(0, 80), ML + 26, y); y += 5
  }
  abrirPdf(doc, 'Requerimientos_empresas.pdf')
}

async function pdfGeneral () {
  let rows: any[] = []
  try { rows = (await api.get('/requerimientos-informes/general')).data ?? [] } catch { flash('No se pudo generar el informe.', true); return }
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 12, PW = 297, PH = 210; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(27, 67, 50)
  doc.text('REQUERIMIENTOS PARA ACCESOS A EMPRESAS', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 8
  // Las filas vienen ordenadas por nombre de empresa y luego requerimiento.
  // Recorro en orden y abro un encabezado cada vez que cambia el cliente.
  const cabecera = (r: any) => {
    if (y > PH - 24) { doc.addPage(); y = 16 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
    doc.text(`${r.nombre}  (Cód. ${r.cliente})`, ML, y); doc.setTextColor(0, 0, 0); y += 5
    const em = r.emails?.length ? r.emails.join(', ') : ''
    if (em) { doc.setFont('helvetica', 'italic'); doc.setFontSize(7.5); doc.text(`Email: ${em}`.slice(0, 150), ML, y); y += 4 }
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5)
    doc.rect(ML, y - 3.5, 200, 5, 'F')
    doc.text('Cód', ML + 1, y); doc.text('Requerimiento', ML + 14, y); doc.text('Tipo', ML + 110, y); doc.text('Días', ML + 150, y); doc.text('Últ. envío', ML + 168, y)
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 5
  }
  let actual: number | null = null
  for (const r of rows) {
    if (r.cliente !== actual) { if (actual !== null) y += 4; cabecera(r); actual = r.cliente }
    if (y > PH - 12) { doc.addPage(); y = 16 }
    doc.setFontSize(8)
    doc.text(String(r.req), ML + 1, y); doc.text((r.requerimiento || '').slice(0, 70), ML + 14, y)
    doc.text(r.comun ? 'COMÚN' : 'EXCLUSIVO', ML + 110, y); doc.text(String(r.dias), ML + 150, y)
    doc.text(r.ult_envio ? r.ult_envio.split('-').reverse().join('/') : '—', ML + 168, y); y += 4.5
  }
  abrirPdf(doc, 'Requerimientos_informe_general.pdf')
}

cargar()
</script>

<style scoped>
.ri-view { display:flex; flex-direction:column; min-height:100%; }
.ri-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ri-ico { font-size:28px; } .ri-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ri-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ri-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ri-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ri-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ri-msg.ok { background:#d1fae5; color:#065f46; } .ri-msg.err { background:#fee2e2; color:#991b1b; }
.ri-body { padding:16px 18px; }
.ri-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.ri-search { border:1px solid #d1d5db; border-radius:7px; padding:8px 12px; font-size:14px; min-width:240px; }
.ri-mini { background:#eef2f7; color:#334155; border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ri-mini.g { background:#e2e8f0; }
.ri-pdf { background:#e0eefc; color:#2d6a9f; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ri-pdf:disabled { opacity:.5; }
.ri-send { background:#1b4332; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:800; } .ri-send:disabled { opacity:.5; cursor:default; }
.ri-grid-wrap { max-height:360px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.ri-grid { width:100%; border-collapse:collapse; font-size:12.5px; }
.ri-grid th { position:sticky; top:0; background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11px; }
.ri-grid td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; cursor:pointer; }
.ri-grid tr:hover td { background:#f0faf4; } .ri-grid tr.sel td { background:#dbeafe; }
.ri-cod { color:#2d6a9f; font-weight:700; }
.ri-grid input[type=checkbox] { width:15px; height:15px; accent-color:#1b4332; }
.ri-prev { margin-top:12px; border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fbff; }
.ri-prev-tit { font-size:13px; font-weight:800; color:#14532d; margin-bottom:8px; }
.ri-prev-info { color:#64748b; font-size:13px; }
.ri-prev-row { display:flex; flex-wrap:wrap; gap:6px; align-items:center; font-size:12.5px; color:#1e293b; margin-bottom:6px; }
.ri-tag { background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px; padding:2px 8px; font-size:12px; }
.ri-tag-btn { cursor:pointer; color:#1e293b; font-family:inherit; } .ri-tag-btn:hover { background:#dbeafe; border-color:#93c5fd; }
.ri-no { color:#991b1b; font-weight:700; font-size:12px; }
.ri-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.ri-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ri-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .ri-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ri-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; } .ri-help-md code { background:#eef2f7; padding:1px 5px; border-radius:4px; }
.ri-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ri-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ri-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ri-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ri-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ri-pdf-b.ok { background:#22c55e; color:#fff; } .ri-pdf-b.cancel { background:#ef4444; color:#fff; }
.ri-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
