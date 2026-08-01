<!-- EntrevistasConsultaGeneralView.vue — Consulta General de Entrevistas (entrevistas_consulta_general.scx).
     Sólo lectura; une los entrevistados de ambas empresas. -->
<template>
  <div class="cg-view">
    <div class="cg-cab">
      <div class="cg-ico">🔎</div>
      <div class="cg-tx"><h1>Consulta General de Entrevistas</h1><p>Entrevistados de todo el grupo</p></div>
      <button class="cg-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cg-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/entrevistas-consulta" titulo="Asistente IA — Consulta de Entrevistas"
            subtitulo="Preguntá sobre la consulta general de entrevistados"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo busco un entrevistado?','¿Qué significa la columna Origen?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" class="cg-msg err">{{ msg }}</div></transition>

    <div class="cg-body">
      <div class="cg-top">
        <input v-model="filtro" class="cg-search" placeholder="🔍 Buscar por nombre, domicilio, sector, subsector o formación…" />
        <span class="cg-count">{{ filtradas.length }} de {{ lista.length }}</span>
        <span v-if="otraEmpresa === false" class="cg-aviso">⚠️ Sin conexión a la otra empresa (mostrando solo local)</span>
        <span v-else-if="otraEmpresa === null" class="cg-aviso soft">ℹ️ Otra empresa no configurada (solo local)</span>
      </div>

      <div v-if="cargando" class="cg-info">⟳ Cargando…</div>
      <div v-else class="cg-grid-wrap">
        <table class="cg-grid">
          <thead><tr>
            <th style="width:34px"></th>
            <th v-for="col in cols" :key="col.k" class="ord" @click="ordenar(col.k)">{{ col.t }}{{ flecha(col.k) }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="(e, i) in filtradas" :key="i" :class="{ sel: sel === e }" @click="sel = e">
              <td class="c"><button class="cg-ojo" title="Ver la entrevista" @click.stop="verDetalle(e)">👁️</button></td>
              <td class="cg-org">{{ e.origen }}</td>
              <td class="cg-nom">{{ e.nombre }}</td><td>{{ e.domicilio }}</td><td>{{ e.telefono }}</td>
              <td>{{ fmt(e.fecha) }}</td><td>{{ e.sector }}</td><td>{{ e.subsector }}</td>
              <td>{{ e.formacion }}</td><td>{{ e.lugar }}</td><td>{{ e.email }}</td>
              <td>{{ e.tipo_doc }}</td><td>{{ e.numero_doc || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="cg-detalle">
        <div class="cg-det-nom">{{ sel ? sel.nombre : 'Seleccione un entrevistado' }}</div>
        <div class="cg-det-row"><label>Formación Académica</label><div class="cg-det-val">{{ sel?.formacion || '—' }}</div></div>
        <div class="cg-det-row"><label>Notas</label><div class="cg-det-notas">{{ sel ? (sel.nota ? `(${sel.cod}) ${sel.nota}` : '—') : '' }}</div></div>
      </div>
    </div>

    <Teleport to="body">
      <!-- Ficha de la entrevista (ojito de la grilla) -->
      <div v-if="detalle" class="cg-ov" @click.self="detalle = null">
        <div class="cg-det-md">
          <div class="cg-det-head">
            <span>👁️ Entrevista — {{ detalle.nombre }}</span>
            <button class="cg-x" @click="detalle = null">✕</button>
          </div>
          <div class="cg-det-body">
            <div class="cg-campos">
              <div class="cg-c"><label>Origen</label><div>{{ detalle.origen || '—' }}</div></div>
              <div class="cg-c"><label>Fecha</label><div>{{ fmt(detalle.fecha) || '—' }}</div></div>
              <div class="cg-c span2"><label>Nombre</label><div>{{ detalle.nombre || '—' }}</div></div>
              <div class="cg-c span2"><label>Domicilio</label><div>{{ detalle.domicilio || '—' }}</div></div>
              <div class="cg-c"><label>Teléfono</label><div>{{ detalle.telefono || '—' }}</div></div>
              <div class="cg-c"><label>Email</label><div>{{ detalle.email || '—' }}</div></div>
              <div class="cg-c"><label>Tipo Doc.</label><div>{{ detalle.tipo_doc || '—' }}</div></div>
              <div class="cg-c"><label>Documento</label><div>{{ detalle.numero_doc || '—' }}</div></div>
              <div class="cg-c"><label>Sector</label><div>{{ detalle.sector || '—' }}</div></div>
              <div class="cg-c"><label>Sub-Sector / Puesto</label><div>{{ detalle.subsector || '—' }}</div></div>
              <div class="cg-c span2"><label>Entrevistado en</label><div>{{ detalle.lugar || '—' }}</div></div>
              <div class="cg-c span2"><label>Formación Académica</label><div>{{ detalle.formacion || '—' }}</div></div>
            </div>
            <div class="cg-c-notas"><label>Notas</label><div class="cg-notas-box">{{ detalle.nota || '—' }}</div></div>
          </div>
          <div class="cg-det-foot">
            <span style="flex:1"></span>
            <button class="cg-sec" @click="detalle = null">Cerrar</button>
            <button class="cg-ok" @click="imprimir(detalle)">🖨 Imprimir</button>
          </div>
        </div>
      </div>

      <!-- Previsualización del PDF -->
      <div v-if="pdfUrl" class="cg-pdf-ov" @click.self="cerrarPdf">
        <div class="cg-pdf-md">
          <div class="cg-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cg-pdf-acc">
              <button class="cg-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cg-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cg-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="cg-pdf-frame"></iframe>
        </div>
      </div>

      <div v-if="ayuda" class="cg-ov" @click.self="ayuda = false">
        <div class="cg-help-md">
          <h3>❓ Ayuda — Consulta General de Entrevistas</h3>
          <ul>
            <li>Muestra, de <b>sólo lectura</b>, los entrevistados de <b>ambas empresas</b> del grupo (columna <b>Origen</b>).</li>
            <li>Escribí en el <b>buscador</b> para filtrar por nombre, domicilio, sector, subsector o formación.</li>
            <li>Hacé clic en una fila para ver la <b>formación</b> y las <b>notas</b> completas abajo.</li>
            <li>Con el <b>👁️</b> de cada fila abrís la <b>ficha completa</b> del entrevistado, y desde ahí podés <b>imprimirla</b> o descargarla en PDF.</li>
            <li>Los encabezados ordenan la tabla (clic para asc/desc).</li>
          </ul>
          <div class="cg-acc"><span style="flex:1"></span><button class="cg-ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

interface Ent { origen: string; cod: number; nombre: string; domicilio: string; telefono: string; fecha: string; sector: string; subsector: string; formacion: string; lugar: string; email: string; tipo_doc: string; numero_doc: number; nota: string }
const cols = [
  { k: 'origen', t: 'Origen' }, { k: 'nombre', t: 'Nombre' }, { k: 'domicilio', t: 'Domicilio' },
  { k: 'telefono', t: 'Teléfono' }, { k: 'fecha', t: 'Fecha' }, { k: 'sector', t: 'Sector' },
  { k: 'subsector', t: 'Sub-Sector' }, { k: 'formacion', t: 'Formación' }, { k: 'lugar', t: 'Entrevistado en' },
  { k: 'email', t: 'Email' }, { k: 'tipo_doc', t: 'Tipo Doc' }, { k: 'numero_doc', t: 'Documento' },
] as const
type Key = typeof cols[number]['k']

const lista = ref<Ent[]>([]); const cargando = ref(false); const filtro = ref(''); const sel = ref<Ent | null>(null)
const otraEmpresa = ref<boolean | null>(null); const msg = ref(''); const modalIA = ref(false); const ayuda = ref(false)
const sortKey = ref<Key>('nombre'); const sortDir = ref<1 | -1>(1)

const fmt = (s: string) => s ? s.split('-').reverse().join('/') : ''
const ordenar = (k: Key) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: Key) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''

const filtradas = computed(() => {
  const q = filtro.value.trim().toUpperCase()
  let base = lista.value
  if (q) base = base.filter(e => (`${e.nombre} ${e.domicilio} ${e.sector} ${e.subsector} ${e.formacion}`).toUpperCase().includes(q))
  const k = sortKey.value, dir = sortDir.value
  return [...base].sort((a, b) => k === 'numero_doc'
    ? ((a.numero_doc - b.numero_doc) * dir)
    : String(a[k]).localeCompare(String(b[k]), 'es', { numeric: true }) * dir)
})

// ── Ficha de la entrevista (ojito) + impresión ──
const detalle = ref<Ent | null>(null)
const verDetalle = (e: Ent) => { sel.value = e; detalle.value = e }

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

/** PDF A4 de la ficha del entrevistado (previsualiza antes de descargar/imprimir). */
function imprimir (e: Ent) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, PH = 297, ML = 15, W = PW - ML * 2
  let y = 18

  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('ENTREVISTA', PW / 2, y, { align: 'center' })
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  y += 6
  doc.text(`Origen: ${e.origen || '—'}`, ML, y)
  doc.text(`Fecha: ${fmt(e.fecha) || '—'}`, PW - ML, y, { align: 'right' })
  y += 3
  doc.setDrawColor(27, 67, 50); doc.setLineWidth(0.4); doc.line(ML, y, PW - ML, y); y += 7

  // Filas etiqueta/valor; el valor se parte en varias líneas si es largo.
  const fila = (lbl: string, val: string) => {
    if (y > PH - 24) { doc.addPage(); y = 18 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(lbl, ML, y)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(10)
    const lineas = doc.splitTextToSize(String(val || '—'), W - 46)
    doc.text(lineas, ML + 46, y)
    y += Math.max(6, lineas.length * 4.8) + 1.4
  }
  fila('Nombre:', e.nombre)
  fila('Documento:', `${e.tipo_doc || ''} ${e.numero_doc || ''}`.trim())
  fila('Domicilio:', e.domicilio)
  fila('Teléfono:', e.telefono)
  fila('Email:', e.email)
  fila('Sector Laboral:', e.sector)
  fila('Subsector / Puesto:', e.subsector)
  fila('Entrevistado en:', e.lugar)
  fila('Formación Académica:', e.formacion)

  y += 3
  if (y > PH - 40) { doc.addPage(); y = 18 }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('NOTAS', ML, y); y += 2
  doc.setDrawColor(180); doc.setLineWidth(0.3); doc.line(ML, y, PW - ML, y); y += 6
  doc.setFont('helvetica', 'normal'); doc.setFontSize(10)
  for (const ln of doc.splitTextToSize(String(e.nota || '—'), W)) {
    if (y > PH - 18) { doc.addPage(); y = 18 }
    doc.text(ln, ML, y); y += 5
  }

  cerrarPdf()
  pdfNombre.value = `Entrevista_${(e.nombre || '').replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '_')}.pdf`
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

async function cargar () {
  cargando.value = true
  try {
    const { data } = await api.get('/entrevistas-consulta')
    lista.value = data.entrevistas ?? []
    otraEmpresa.value = data.otra_empresa
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo cargar la consulta.'; setTimeout(() => msg.value = '', 4000) }
  finally { cargando.value = false }
}
cargar()
</script>

<style scoped>
.cg-view { display:flex; flex-direction:column; min-height:100%; }
.cg-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cg-ico { font-size:28px; } .cg-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cg-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cg-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cg-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cg-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cg-msg.err { background:#fee2e2; color:#991b1b; }
.cg-body { padding:14px 18px; }
.cg-top { display:flex; align-items:center; gap:12px; margin-bottom:10px; flex-wrap:wrap; }
.cg-search { border:1px solid #d1d5db; border-radius:7px; padding:9px 12px; font-size:14px; min-width:360px; flex:1; max-width:560px; }
.cg-count { font-size:12px; color:#64748b; }
.cg-aviso { font-size:12px; color:#b45309; font-weight:600; } .cg-aviso.soft { color:#64748b; font-weight:500; }
.cg-info { text-align:center; color:#94a3b8; padding:24px; }
.cg-grid-wrap { max-height:52vh; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.cg-grid { width:100%; border-collapse:collapse; font-size:12px; white-space:nowrap; }
.cg-grid th { position:sticky; top:0; background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11px; cursor:pointer; user-select:none; }
.cg-grid th.ord:hover { background:#334155; }
.cg-grid td { padding:4px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; max-width:220px; overflow:hidden; text-overflow:ellipsis; }
.cg-grid tbody tr:nth-child(even) td { background:#f4faf4; }
.cg-grid tbody tr:hover td { background:#e6f2e6; } .cg-grid tbody tr.sel td { background:#fef08a; }
.cg-grid td.c { text-align:center; }
.cg-ojo { background:transparent; border:none; cursor:pointer; font-size:15px; padding:1px 4px; border-radius:5px; line-height:1; }
.cg-ojo:hover { background:#dbeafe; }

/* ── Ficha de la entrevista ── */
.cg-det-md { width:min(720px,96vw); max-height:92vh; background:#fff; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,.4); display:flex; flex-direction:column; }
.cg-det-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; background:#1b4332; color:#fff; border-radius:12px 12px 0 0; font-size:14.5px; font-weight:700; }
.cg-x { background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer; }
.cg-det-body { padding:16px; overflow:auto; }
.cg-campos { display:grid; grid-template-columns:1fr 1fr; gap:10px 18px; }
.cg-c.span2 { grid-column:1 / -1; }
.cg-c label, .cg-c-notas label { display:block; font-size:11.5px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.02em; }
.cg-c div { font-size:13.5px; color:#1e293b; padding:2px 0 3px; border-bottom:1px solid #eef2f7; word-break:break-word; }
.cg-c-notas { margin-top:14px; }
.cg-notas-box { margin-top:4px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; padding:10px 12px; font-size:13.5px; color:#1e293b; white-space:pre-wrap; min-height:120px; max-height:280px; overflow:auto; }
.cg-det-foot { display:flex; gap:10px; padding:12px 16px; border-top:1px solid #e2e8f0; }
.cg-sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 18px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; }

/* ── Visor PDF ── */
.cg-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cg-pdf-md { width:min(1000px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.cg-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.cg-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cg-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.cg-pdf-b.ok { background:#22c55e; color:#fff; } .cg-pdf-b.cancel { background:#ef4444; color:#fff; }
.cg-pdf-frame { flex:1; border:none; width:100%; }
.cg-org { color:#2d6a9f; font-weight:700; } .cg-nom { font-weight:700; }
.cg-detalle { margin-top:14px; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fff7f2; }
.cg-det-nom { font-size:16px; font-weight:800; color:#14532d; text-align:center; margin-bottom:10px; }
.cg-det-row { display:flex; gap:10px; margin-bottom:8px; align-items:flex-start; }
.cg-det-row label { width:150px; flex-shrink:0; font-size:12px; font-weight:700; color:#374151; text-align:right; padding-top:2px; }
.cg-det-val { flex:1; border:1px solid #e2d5cc; border-radius:6px; padding:8px 10px; background:#fff; font-size:13px; color:#1e293b; min-height:20px; }
.cg-det-notas { flex:1; border:1px solid #e2d5cc; border-radius:6px; padding:8px 10px; background:#fff; font-size:13px; color:#1e293b; min-height:70px; white-space:pre-wrap; }
.cg-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.cg-ok { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:700; }
.cg-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.cg-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .cg-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .cg-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
