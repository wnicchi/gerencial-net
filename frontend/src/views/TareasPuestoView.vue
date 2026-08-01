<!-- TareasPuestoView.vue — Tareas por Puesto (ABM plano sobre tareapue) -->
<template>
  <div class="tp-view">
    <div class="tp-cab">
      <div class="tp-ico">📋</div>
      <div class="tp-tx"><h1>Tareas por Puesto</h1><p>Asignación de tareas a puestos, con frecuencia y % de jornada</p></div>
      <button class="tp-nuevo" @click="nuevo">＋ Nueva</button>
      <button class="tp-print" :disabled="!filas.length" @click="imprimir">🖨 Informe</button>
    </div>

    <transition name="fade"><div v-if="msg" :class="['tp-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="tp-layout">
      <div class="tp-list">
        <input v-model="busqueda" class="tp-search" placeholder="🔍 Buscar por tarea o puesto…" @input="cargar" />
        <div class="tp-list-body">
          <button v-for="(r, i) in filas" :key="r.puesto + '|' + r.des + i" class="tp-item" :class="{ on: selKey === r.puesto + '|' + r.des }" @click="elegir(r)">
            <span class="tp-pue">{{ r.puesto_des || r.puesto }}</span>
            <span class="tp-des">{{ r.des }}</span>
            <span class="tp-meta">{{ r.fre_des || 'sin frecuencia' }} · {{ r.min }}%</span>
          </button>
          <p v-if="!filas.length" class="tp-vacio">Sin asignaciones.</p>
        </div>
      </div>

      <div class="tp-detalle" v-if="selKey || modoNuevo">
        <div class="campo"><label>Descripción *</label><input v-model="f.des" maxlength="100" @input="f.des = f.des.toUpperCase()" /></div>
        <div class="campo"><label>Puesto *</label>
          <select v-model="f.puesto"><option value="">— Seleccioná —</option><option v-for="p in cat.puestos" :key="p.codigo" :value="p.codigo">{{ p.descripcion }}</option></select>
        </div>
        <div class="tp-fila">
          <div class="campo"><label>Frecuencia</label>
            <select v-model.number="f.fre"><option :value="0">— —</option><option v-for="x in cat.frecuencias" :key="x.cod" :value="x.cod">{{ x.des }}</option></select>
          </div>
          <div class="campo"><label>% utilizado en la Jornada</label><input v-model.number="f.min" type="number" min="0" max="100" /></div>
        </div>
        <div class="tp-acc">
          <button class="btn ok" :disabled="proc" @click="guardar">💾 {{ modoNuevo ? 'Crear' : 'Guardar' }}</button>
          <button v-if="!modoNuevo" class="btn del" :disabled="proc" @click="eliminar">🗑 Eliminar</button>
        </div>
      </div>
      <div class="tp-detalle tp-vacia" v-else><div class="tp-sinsel">👈 Elegí una asignación o creá una nueva</div></div>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="tp-pdf-ov" @click.self="cerrarPdf">
        <div class="tp-pdf-md">
          <div class="tp-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="tp-pdf-acc">
              <button class="tp-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="tp-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="tp-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="tp-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'

interface Fila { cod: number; des: string; puesto: string; puesto_des: string; fre: number; fre_des: string; min: number }
const filas = ref<Fila[]>([]); const busqueda = ref(''); const proc = ref(false)
const modoNuevo = ref(false); const selKey = ref('')
const cat = reactive<{ puestos: any[]; frecuencias: any[] }>({ puestos: [], frecuencias: [] })
const f = reactive({ des: '', puesto: '', fre: 0, min: 0, des_orig: '' })
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

onMounted(async () => { await Promise.all([cargar(), cargarCat()]) })

let dq: any = null
function cargar () {
  clearTimeout(dq)
  dq = setTimeout(async () => { try { filas.value = (await api.get('/tareas-puesto', { params: { buscar: busqueda.value } })).data ?? [] } catch { /* */ } }, 200)
}
async function cargarCat () {
  try {
    const c = (await api.get('/puestos/catalogos')).data
    cat.frecuencias = c.frecuencias
    cat.puestos = (await api.get('/tareas/puestos')).data ?? []
  } catch { /* */ }
}

function elegir (r: Fila) {
  selKey.value = r.puesto + '|' + r.des; modoNuevo.value = false
  Object.assign(f, { des: r.des, puesto: r.puesto, fre: r.fre, min: r.min, des_orig: r.des })
}
function nuevo () { modoNuevo.value = true; selKey.value = ''; Object.assign(f, { des: '', puesto: '', fre: 0, min: 0, des_orig: '' }) }

async function guardar () {
  if (!f.des.trim()) return flash('Indicá la descripción.', true)
  if (!f.puesto) return flash('Seleccioná el puesto.', true)
  proc.value = true
  try {
    if (modoNuevo.value) { await api.post('/tareas-puesto', { des: f.des, puesto: f.puesto, fre: f.fre, min: f.min }); flash('Asignación creada.') }
    else { await api.put('/tareas-puesto', { des: f.des, puesto: f.puesto, fre: f.fre, min: f.min, des_orig: f.des_orig }); flash('Asignación guardada.') }
    selKey.value = ''; modoNuevo.value = false; cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}

async function eliminar () {
  if (!confirm(`¿Elimina la tarea "${f.des}" del puesto?`)) return
  proc.value = true
  try { await api.post('/tareas-puesto/eliminar', { puesto: f.puesto, des: f.des_orig }); selKey.value = ''; cargar(); flash('Asignación eliminada.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { proc.value = false }
}

// ── Informe de Tareas por Puesto (agrupado) ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const agrupado = computed(() => {
  const g: Record<string, { nombre: string; rows: Fila[] }> = {}
  for (const r of filas.value) {
    const k = r.puesto
    if (!g[k]) g[k] = { nombre: r.puesto_des || r.puesto, rows: [] }
    g[k].rows.push(r)
  }
  return Object.entries(g).map(([puesto, v]) => ({ puesto, ...v })).sort((a, b) => a.nombre.localeCompare(b.nombre))
})

async function imprimir () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 16, PW = 210, PH = 297; let y = 18
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(27, 67, 50)
  doc.text('INFORME DE TAREAS POR PUESTO', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  const cab = () => {
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    doc.rect(ML, y - 4, PW - ML * 2, 6, 'F')
    doc.text('Cód.', ML + 1, y); doc.text('Descripción de las Tareas', ML + 14, y); doc.text('Frecuencia', ML + 118, y); doc.text('Min.', PW - ML - 2, y, { align: 'right' })
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  }
  for (const g of agrupado.value) {
    if (y > PH - 24) { doc.addPage(); y = 18 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
    doc.text(`${g.nombre}  ( ${g.puesto} )`, ML, y); doc.setTextColor(0, 0, 0); y += 5
    cab()
    doc.setFontSize(8.5)
    for (const r of g.rows) {
      if (y > PH - 14) { doc.addPage(); y = 18; cab() }
      doc.text(String(r.cod), ML + 1, y)
      doc.text(doc.splitTextToSize(r.des, 98)[0] || '', ML + 14, y)
      doc.text(r.fre_des || '—', ML + 118, y)
      doc.text(String(r.min), PW - ML - 2, y, { align: 'right' }); y += 4.8
    }
    y += 4
  }
  cerrarPdf(); pdfNombre.value = 'Tareas_por_puesto.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.tp-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.tp-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.tp-ico { font-size:28px; } .tp-tx h1 { margin:0; font-size:19px; color:#1e293b; } .tp-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.tp-nuevo { margin-left:auto; background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; }
.tp-print { background:#e0eefc; color:#2d6a9f; border:none; padding:9px 14px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; } .tp-print:disabled { opacity:.5; cursor:default; }
.tp-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .tp-msg.ok { background:#d1fae5; color:#065f46; } .tp-msg.err { background:#fee2e2; color:#991b1b; }
.tp-layout { display:flex; gap:14px; flex:1; min-height:0; padding:14px 18px; }
.tp-list { width:340px; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
.tp-search { border:none; border-bottom:1px solid #e2e8f0; padding:10px 12px; font-size:13px; outline:none; }
.tp-list-body { overflow:auto; flex:1; }
.tp-item { display:flex; flex-direction:column; gap:1px; width:100%; padding:9px 12px; border:none; background:transparent; border-bottom:1px solid #f0f4f9; cursor:pointer; text-align:left; }
.tp-item:hover { background:#f0f7ff; } .tp-item.on { background:#e0eefc; border-left:3px solid #2d6a9f; }
.tp-pue { font-size:11px; color:#2d6a9f; font-weight:700; } .tp-des { font-size:13px; color:#1a3a5c; } .tp-meta { font-size:11px; color:#64748b; }
.tp-vacio { color:#94a3b8; padding:14px; font-size:13px; }
.tp-detalle { flex:1; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; gap:14px; overflow:auto; padding:18px; max-width:640px; }
.tp-vacia { justify-content:center; align-items:center; } .tp-sinsel { color:#8aacca; }
.campo { display:flex; flex-direction:column; gap:5px; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.campo input, .campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; color:#1e293b; }
.tp-fila { display:flex; gap:14px; } .tp-fila .campo { flex:1; }
.tp-acc { display:flex; gap:8px; margin-top:6px; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.del { background:#fee2e2; color:#991b1b; } .btn:disabled { opacity:.5; cursor:default; }
.tp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.tp-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.tp-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.tp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.tp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.tp-pdf-b.ok { background:#22c55e; color:#fff; } .tp-pdf-b.cancel { background:#ef4444; color:#fff; }
.tp-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
