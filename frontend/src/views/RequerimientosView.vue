<!-- RequerimientosView.vue — ABM de Requerimientos (requerimientos.scx) con documentación asociada. -->
<template>
  <div class="rq-view">
    <div class="rq-cab">
      <div class="rq-ico">📋</div>
      <div class="rq-tx"><h1>Requerimientos</h1><p>{{ lista.length }} requerimiento{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="rq-ia" @click="modalIA = true">🤖 IA</button>
      <button class="rq-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="rq-print" :disabled="!lista.length" @click="imprimir">🖨 Listado</button>
      <button class="rq-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/requerimientos" titulo="Asistente IA — Requerimientos"
            subtitulo="Preguntá sobre el módulo de requerimientos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué es la documentación común?','¿Cómo adjunto un documento?','¿Qué pasa al eliminar un documento?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['rq-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="rq-card">
      <input v-model="filtro" class="rq-search" placeholder="🔍 Buscar por descripción…" />
      <div v-if="cargando" class="rq-vacio">⟳ Cargando…</div>
      <div v-else-if="!listaFiltrada.length" class="rq-vacio">Sin requerimientos cargados</div>
      <table v-else class="rq-tabla">
        <thead><tr>
          <th style="width:70px" class="ord" @click="ordenar('cod')">Código{{ flecha('cod') }}</th>
          <th class="ord" @click="ordenar('descripcion')">Descripción{{ flecha('descripcion') }}</th>
          <th style="width:70px;text-align:center" class="ord" @click="ordenar('dias')">Días{{ flecha('dias') }}</th>
          <th style="width:90px;text-align:center">Común</th>
          <th style="width:100px;text-align:center">Acciones</th>
        </tr></thead>
        <tbody>
          <tr v-for="(e, i) in listaFiltrada" :key="i">
            <td class="rq-cod">{{ e.cod }}</td><td>{{ e.descripcion }}</td>
            <td style="text-align:center">{{ e.dias }}</td>
            <td style="text-align:center">{{ e.comun ? '✔️' : '' }}</td>
            <td style="text-align:center"><button class="rq-i" @click="abrirEditar(e)">✏️</button><button class="rq-i del" @click="eliminar(e)">🗑️</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal ABM con 2 solapas -->
    <Teleport to="body">
      <div v-if="modal" class="rq-ov" @click.self="cerrarModal">
        <div class="rq-md">
          <h3>{{ editando ? `Requerimiento Nº ${form.cod}` : 'Nuevo requerimiento' }}</h3>
          <div class="rq-tabs">
            <button :class="['rq-tab', tab === 'datos' && 'on']" @click="tab = 'datos'">Detalles</button>
            <button :class="['rq-tab', tab === 'docs' && 'on']" :disabled="!editando" @click="tab = 'docs'">Historial de documentación</button>
          </div>

          <!-- Solapa Detalles -->
          <div v-show="tab === 'datos'" class="rq-pane" v-enter-next>
            <label>Descripción *</label>
            <input ref="inputDes" v-model="form.descripcion" maxlength="100" />
            <label>Días</label>
            <input v-model.number="form.dias" type="number" min="0" class="rq-corto" />
            <label>Observaciones</label>
            <textarea v-model="form.observaciones" maxlength="2000" rows="3"></textarea>
            <label class="rq-check"><input type="checkbox" v-model="form.comun" /> Documentación común a todos los clientes</label>
            <p v-if="modalError" class="rq-md-err">{{ modalError }}</p>
            <div class="rq-md-acc">
              <button class="rq-cancel" @click="cerrarModal">Cerrar</button>
              <button class="rq-ok" :disabled="guardando" @click="guardarDatos">{{ guardando ? '⟳' : 'Guardar' }}</button>
            </div>

            <!-- Agregar documento (sólo sobre un requerimiento ya guardado) -->
            <div v-if="editando" class="rq-add">
              <div class="rq-add-tit">AGREGAR DOCUMENTO</div>
              <div class="rq-add-grid">
                <label>Tipo documento</label>
                <select v-model="doc.tipo">
                  <option value="">— Seleccione —</option>
                  <option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.nombre }}</option>
                </select>
                <label>Archivo origen</label>
                <input type="file" @change="onFile" ref="fileInput" />
                <label>Observación</label>
                <input v-model="doc.obs" maxlength="60" />
              </div>
              <div class="rq-add-acc">
                <button class="rq-aceptar" :disabled="subiendo" @click="aceptarDoc">{{ subiendo ? '⟳ Subiendo…' : 'ACEPTAR' }}</button>
              </div>
            </div>
          </div>

          <!-- Solapa Historial de documentación -->
          <div v-show="tab === 'docs'" class="rq-pane">
            <h4>Documentos asociados</h4>
            <table v-if="activos.length" class="rq-dt">
              <thead><tr><th style="width:46px">Ver</th><th>Tipo</th><th>Nombre</th><th>Observación</th><th>Cargado</th><th style="width:46px"></th></tr></thead>
              <tbody>
                <tr v-for="d in activos" :key="d.id">
                  <td><button class="btn-ojo" title="Visualizar" @click="ver(d, false)">👁️</button></td>
                  <td>{{ d.detalle || d.tipo }}</td><td>{{ d.nombre }}.{{ (d.ext || '').toLowerCase() }}</td>
                  <td>{{ d.observaciones }}</td><td>{{ d.creado }}</td>
                  <td><button class="rq-i del" title="Eliminar" @click="eliminarDoc(d)">🗑️</button></td>
                </tr>
              </tbody>
            </table>
            <div v-else class="rq-vacio">Sin documentos asociados.</div>

            <h4 class="rq-h-hist">Historial (documentos eliminados)</h4>
            <table v-if="historial.length" class="rq-dt hist">
              <thead><tr><th style="width:46px">Ver</th><th>Tipo</th><th>Nombre</th><th>Observación</th><th>Eliminado</th></tr></thead>
              <tbody>
                <tr v-for="d in historial" :key="d.id">
                  <td><button class="btn-ojo" title="Visualizar" @click="ver(d, true)">👁️</button></td>
                  <td>{{ d.detalle || d.tipo }}</td><td>{{ d.nombre }}.{{ (d.ext || '').toLowerCase() }}</td>
                  <td>{{ d.observaciones }}</td><td>{{ d.eliminado }}</td>
                </tr>
              </tbody>
            </table>
            <div v-else class="rq-vacio">Sin historial.</div>

            <div class="rq-md-acc"><span style="flex:1"></span><button class="rq-cancel" @click="cerrarModal">Cerrar</button></div>
          </div>
        </div>
      </div>

      <div v-if="ayuda" class="rq-ov" @click.self="ayuda = false">
        <div class="rq-md sm">
          <h3>❓ Ayuda — Requerimientos</h3>
          <ul class="rq-help">
            <li>Administra los <b>requerimientos</b> de documentación: descripción, días de validez y observaciones.</li>
            <li>La marca <b>Documentación común a todos los clientes</b> indica que aplica a todos por igual.</li>
            <li>En la solapa <b>Historial de documentación</b> se adjuntan archivos (con el 👁️ se visualizan).</li>
            <li>Al eliminar un documento queda registrado en el <b>historial</b>, no se pierde.</li>
          </ul>
          <div class="rq-md-acc"><span style="flex:1"></span><button class="rq-ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="docVisor" />

    <Teleport to="body">
      <div v-if="pdfUrl" class="rq-pdf-ov" @click.self="cerrarPdf">
        <div class="rq-pdf-md">
          <div class="rq-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="rq-pdf-acc">
              <button class="rq-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="rq-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="rq-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="rq-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

interface Item { cod: number; descripcion: string; dias: number; observaciones: string; comun: boolean }
type SKey = 'cod' | 'descripcion' | 'dias'
const lista = ref<Item[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgErr = ref(false)
const modal = ref(false); const ayuda = ref(false); const modalIA = ref(false)
const tab = ref<'datos' | 'docs'>('datos')
const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const vacio = (): any => ({ cod: null, descripcion: '', dias: 0, observaciones: '', comun: false })
const form = ref<any>(vacio())

const tipos = ref<{ cod: string; nombre: string }[]>([])
const activos = ref<any[]>([]); const historial = ref<any[]>([])
const doc = ref<{ tipo: string; obs: string; file: File | null }>({ tipo: '', obs: '', file: null })
const fileInput = ref<HTMLInputElement | null>(null)
const subiendo = ref(false)
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)

const sortKey = ref<SKey>('descripcion'); const sortDir = ref<1 | -1>(1)
const ordenar = (k: SKey) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: SKey) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''
const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  const base = q ? lista.value.filter(e => e.descripcion.toLowerCase().includes(q)) : lista.value.slice()
  const k = sortKey.value, dir = sortDir.value
  return base.slice().sort((a, b) => (k === 'cod' || k === 'dias')
    ? ((a as any)[k] - (b as any)[k]) * dir
    : String((a as any)[k]).localeCompare(String((b as any)[k]), 'es', { numeric: true }) * dir)
})
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/requerimientos')).data } catch (e) { console.error(e) } finally { cargando.value = false } }

const cerrarModal = () => { modal.value = false }
const abrirNuevo = async () => { editando.value = false; form.value = vacio(); modalError.value = ''; tab.value = 'datos'; modal.value = true; await nextTick(); inputDes.value?.focus() }
const abrirEditar = async (e: Item) => { editando.value = true; form.value = { ...e }; modalError.value = ''; tab.value = 'datos'; modal.value = true; await cargarDocs(e.cod); await nextTick(); inputDes.value?.focus() }

const guardarDatos = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; modalError.value = ''
  const body = { descripcion: form.value.descripcion, dias: form.value.dias || 0, observaciones: form.value.observaciones, comun: form.value.comun }
  try {
    if (editando.value) { await api.put(`/requerimientos/${form.value.cod}`, body); flash('Requerimiento actualizado') }
    else { const { data } = await api.post('/requerimientos', body); form.value.cod = data.cod; editando.value = true; flash('Requerimiento creado') }
    await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Item) => {
  if (!confirm(`¿Eliminar el requerimiento "${e.descripcion}"?`)) return
  try { await api.delete(`/requerimientos/${e.cod}`); flash('Requerimiento eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

const cargarDocs = async (cod: number) => {
  try { const { data } = await api.get(`/requerimientos/${cod}/documentos`); activos.value = data.activos ?? []; historial.value = data.historial ?? [] }
  catch { activos.value = []; historial.value = [] }
}
const onFile = (ev: Event) => { doc.value.file = (ev.target as HTMLInputElement).files?.[0] ?? null }
const aceptarDoc = async () => {
  if (!doc.value.tipo) { flash('Debe ingresar el tipo de documento.', true); return }
  if (!doc.value.file) { flash('Debe seleccionar el documento.', true); return }
  if (!confirm(`¿Desea agregar el archivo "${doc.value.file.name}"?`)) return
  subiendo.value = true
  const fd = new FormData()
  fd.append('tipo', doc.value.tipo); fd.append('obs', doc.value.obs); fd.append('archivo', doc.value.file)
  try {
    const { data } = await api.post(`/requerimientos/${form.value.cod}/documento`, fd)
    activos.value = data.activos ?? []; historial.value = data.historial ?? []
    doc.value = { tipo: '', obs: '', file: null }; if (fileInput.value) fileInput.value.value = ''
    flash('Documento agregado'); tab.value = 'docs'
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo agregar el documento.', true) }
  finally { subiendo.value = false }
}
async function ver (d: any, hist: boolean) {
  try {
    const resp = await api.get(`/requerimientos/documento/${d.id}/ver`, { params: { historial: hist ? 1 : 0 }, responseType: 'blob' })
    docVisor.value?.open(resp.data as Blob, `${d.nombre}.${(d.ext || '').toLowerCase()}`)
  } catch { flash('No se pudo abrir el documento.', true) }
}
const eliminarDoc = async (d: any) => {
  if (!confirm(`¿Elimina el documento "${d.nombre}.${(d.ext || '').toLowerCase()}"? Quedará en el historial.`)) return
  try { const { data } = await api.delete(`/requerimientos/documento/${d.id}`); activos.value = data.activos ?? []; historial.value = data.historial ?? []; flash('Documento eliminado') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function imprimir () {
  const d = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 15, PW = 210, PH = 297; let y = 18
  d.setFont('helvetica', 'bold'); d.setFontSize(15); d.setTextColor(27, 67, 50)
  d.text('REQUERIMIENTOS', PW / 2, y, { align: 'center' }); d.setTextColor(0, 0, 0); y += 10
  d.setFillColor(45, 106, 159); d.setTextColor(255, 255, 255); d.setFontSize(8); d.rect(ML, y - 4, 180, 6, 'F')
  d.text('Cód', ML + 2, y); d.text('Descripción', ML + 18, y); d.text('Días', ML + 130, y); d.text('Común', ML + 150, y)
  d.setTextColor(0, 0, 0); d.setFont('helvetica', 'normal'); y += 6
  for (const e of [...lista.value].sort((a, b) => a.cod - b.cod)) {
    if (y > PH - 16) { d.addPage(); y = 18 }
    d.setFontSize(9)
    d.text(String(e.cod), ML + 2, y); d.text((e.descripcion || '').slice(0, 70), ML + 18, y)
    d.text(String(e.dias), ML + 130, y); d.text(e.comun ? 'Sí' : '', ML + 150, y); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'Requerimientos.pdf'; pdfUrl.value = URL.createObjectURL(d.output('blob'))
}

onMounted(async () => {
  await cargar()
  try { tipos.value = (await api.get('/requerimientos/init')).data.tipos ?? [] } catch { /* */ }
})
</script>

<style scoped>
.rq-view { display:flex; flex-direction:column; min-height:100%; }
.rq-cab { display:flex; align-items:center; gap:10px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.rq-ico { font-size:28px; } .rq-tx h1 { margin:0; font-size:19px; color:#1e293b; } .rq-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.rq-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rq-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rq-print { background:#e0eefc; color:#2d6a9f; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .rq-print:disabled { opacity:.5; cursor:default; }
.rq-nuevo { background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.rq-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .rq-msg.ok { background:#d1fae5; color:#065f46; } .rq-msg.err { background:#fee2e2; color:#991b1b; }
.rq-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; max-width:1000px; }
.rq-search { width:100%; max-width:420px; border:1px solid #d1d5db; border-radius:7px; padding:9px 12px; font-size:14px; margin-bottom:12px; outline:none; }
.rq-vacio { text-align:center; color:#94a3b8; padding:20px; }
.rq-tabla { width:100%; border-collapse:collapse; font-size:13.5px; }
.rq-tabla th { background:#f4f7fc; color:#2a4a6a; padding:8px 10px; text-align:left; font-size:12px; border-bottom:1px solid #e2e8f0; }
.rq-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .rq-cod { color:#2d6a9f; font-weight:700; }
.rq-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; } .rq-i.del:hover { filter:saturate(2); }
.rq-tabla th.ord { cursor:pointer; user-select:none; white-space:nowrap; } .rq-tabla th.ord:hover { background:#e6eefc; color:#1a3a5c; }
.rq-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:40px 18px; overflow:auto; }
.rq-md { background:#fff; border-radius:14px; padding:22px; width:min(680px,96vw); } .rq-md.sm { width:min(480px,94vw); }
.rq-md h3 { margin:0 0 14px; color:#1a3a5c; }
.rq-tabs { display:flex; gap:4px; border-bottom:2px solid #e2e8f0; margin-bottom:14px; }
.rq-tab { background:none; border:none; padding:9px 16px; cursor:pointer; font-size:13px; font-weight:600; color:#64748b; border-bottom:2px solid transparent; margin-bottom:-2px; }
.rq-tab.on { color:#1b4332; border-bottom-color:#40916c; } .rq-tab:disabled { opacity:.4; cursor:default; }
.rq-pane label { font-size:12px; font-weight:600; color:#374151; display:block; margin-top:10px; }
.rq-pane input, .rq-pane textarea, .rq-pane select { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; margin-top:4px; outline:none; box-sizing:border-box; color:#1e293b; font-family:inherit; resize:vertical; }
.rq-pane input.rq-corto { width:120px; }
.rq-check { display:flex !important; align-items:center; gap:8px; margin-top:14px; font-weight:600; color:#1e293b; }
.rq-check input { width:auto !important; margin:0 !important; }
.rq-md-err { color:#991b1b; font-size:13px; margin:8px 0 0; }
.rq-md-acc { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
.rq-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:600; }
.rq-ok { background:#2d6a9f; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:700; } .rq-ok:disabled { opacity:.5; }
.rq-add { margin-top:18px; border:1px solid #d7e3f0; border-radius:10px; padding:14px; background:#f8fbff; }
.rq-add-tit { font-size:12px; font-weight:800; color:#2d6a9f; letter-spacing:.5px; margin-bottom:8px; }
.rq-add-grid { display:grid; grid-template-columns:130px 1fr; gap:8px 10px; align-items:center; }
.rq-add-grid label { margin:0; }
.rq-add-grid input, .rq-add-grid select { margin:0; }
.rq-add-acc { display:flex; justify-content:center; margin-top:12px; }
.rq-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:9px 28px; cursor:pointer; font-weight:700; font-size:13px; } .rq-aceptar:disabled { opacity:.5; }
.rq-pane h4 { margin:6px 0 6px; color:#1a3a5c; font-size:14px; } .rq-h-hist { margin-top:18px !important; color:#64748b !important; }
.rq-dt { width:100%; border-collapse:collapse; font-size:12.5px; border:1px solid #e2e8f0; }
.rq-dt th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11px; }
.rq-dt td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.rq-dt.hist th { background:#64748b; }
.btn-ojo { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 4px; }
.rq-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.rq-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.rq-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.rq-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .rq-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.rq-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .rq-pdf-b.ok { background:#22c55e; color:#fff; } .rq-pdf-b.cancel { background:#ef4444; color:#fff; }
.rq-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
