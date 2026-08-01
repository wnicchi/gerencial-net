<!-- TareasView.vue — Tareas (catálogo) + Puestos en los que aplica -->
<template>
  <div class="ta-view">
    <div class="ta-cab">
      <div class="ta-ico">📋</div>
      <div class="ta-tx"><h1>Tareas</h1><p>Definición de tareas y puestos en los que aplican</p></div>
      <button class="ta-nuevo" @click="nuevo">＋ Nueva tarea</button>
      <button class="ta-print" :disabled="!lista.length" @click="imprimir">🖨 Listado</button>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ta-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ta-layout">
      <!-- Lista -->
      <div class="ta-list">
        <input v-model="busqueda" class="ta-search" placeholder="🔍 Buscar tarea…" @input="cargarLista" />
        <div class="ta-list-body">
          <button v-for="t in lista" :key="t.codigo" class="ta-item" :class="{ on: sel === t.codigo }" @click="elegir(t.codigo)">
            <span class="ta-cod">{{ t.codigo }}</span><span class="ta-des">{{ t.descripcion }}</span>
          </button>
          <p v-if="!lista.length" class="ta-vacio">Sin tareas.</p>
        </div>
      </div>

      <!-- Detalle -->
      <div class="ta-detalle" v-if="sel || modoNuevo">
        <div class="ta-head">
          <div class="campo cod"><label>Código</label><input :value="modoNuevo ? '(nuevo)' : sel" disabled /></div>
          <div class="campo"><label>Descripción *</label><input v-model="descripcion" maxlength="100" @input="descripcion = descripcion.toUpperCase()" /></div>
          <button class="btn ok" :disabled="proc" @click="guardar">💾 {{ modoNuevo ? 'Crear' : 'Guardar' }}</button>
          <button v-if="!modoNuevo" class="btn del" :disabled="proc" @click="eliminar">🗑</button>
        </div>

        <template v-if="!modoNuevo">
          <div class="ta-sub">Puestos en los que Aplica</div>
          <table class="ta-tabla">
            <thead><tr><th class="c">Elegir</th><th>Código</th><th>Puesto</th><th>Reporta</th></tr></thead>
            <tbody>
              <tr v-for="p in puestos" :key="p.codigo">
                <td class="c"><input type="checkbox" :value="p.codigo" v-model="elegidos" /></td>
                <td>{{ p.codigo }}</td><td>{{ p.puesto }}</td><td>{{ p.reporta }}</td>
              </tr>
              <tr v-if="!puestos.length"><td colspan="4" class="ta-tvacio">Esta tarea no está asignada a ningún puesto.</td></tr>
            </tbody>
          </table>

          <div class="ta-asignar">
            <select v-model="puestoSel" class="ta-selp">
              <option value="">— Seleccioná un puesto —</option>
              <option v-for="p in puestosDisp" :key="p.codigo" :value="p.codigo">{{ p.codigo }} — {{ p.descripcion }}</option>
            </select>
            <button class="btn add" :disabled="!puestoSel || proc" @click="asignar">＋ Asignar</button>
            <button class="btn baja" :disabled="!elegidos.length || proc" @click="darBaja">⊘ Dar de baja ({{ elegidos.length }})</button>
          </div>
        </template>
      </div>
      <div class="ta-detalle ta-vacia" v-else><div class="ta-sinsel">👈 Elegí una tarea o creá una nueva</div></div>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ta-pdf-ov" @click.self="cerrarPdf">
        <div class="ta-pdf-md">
          <div class="ta-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ta-pdf-acc">
              <button class="ta-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ta-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ta-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ta-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'

interface Tarea { codigo: number; descripcion: string }
interface PuestoAsig { codigo: string; puesto: string; reporta: string }

const lista = ref<Tarea[]>([]); const busqueda = ref(''); const sel = ref(0); const modoNuevo = ref(false); const proc = ref(false)
const descripcion = ref(''); const puestos = ref<PuestoAsig[]>([]); const elegidos = ref<string[]>([])
const catPuestos = ref<{ codigo: string; descripcion: string }[]>([]); const puestoSel = ref('')
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

// puestos disponibles = catálogo menos los ya asignados
const puestosDisp = computed(() => catPuestos.value.filter(p => !puestos.value.some(a => a.codigo.toUpperCase() === p.codigo.toUpperCase())))

onMounted(async () => { await Promise.all([cargarLista(), cargarPuestos()]) })

let dq: any = null
function cargarLista () {
  clearTimeout(dq)
  dq = setTimeout(async () => { try { lista.value = (await api.get('/tareas', { params: { buscar: busqueda.value } })).data ?? [] } catch { /* */ } }, 200)
}
async function cargarPuestos () { try { catPuestos.value = (await api.get('/tareas/puestos')).data ?? [] } catch { /* */ } }

async function elegir (cod: number) {
  try {
    const { data } = await api.get(`/tareas/${cod}`)
    sel.value = cod; modoNuevo.value = false; descripcion.value = data.descripcion
    puestos.value = data.puestos; elegidos.value = []; puestoSel.value = ''
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la tarea.', true) }
}
function nuevo () { modoNuevo.value = true; sel.value = 0; descripcion.value = ''; puestos.value = []; elegidos.value = [] }

async function guardar () {
  if (!descripcion.value.trim()) return flash('Indicá la descripción.', true)
  proc.value = true
  try {
    if (modoNuevo.value) { const { data } = await api.post('/tareas', { descripcion: descripcion.value }); cargarLista(); await elegir(data.codigo); flash('Tarea creada.') }
    else { await api.put(`/tareas/${sel.value}`, { descripcion: descripcion.value }); cargarLista(); flash('Tarea guardada.') }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}

async function eliminar () {
  if (!confirm(`¿Elimina la tarea "${descripcion.value}"?`)) return
  proc.value = true
  try { await api.delete(`/tareas/${sel.value}`); sel.value = 0; cargarLista(); flash('Tarea eliminada.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { proc.value = false }
}

async function asignar () {
  if (!puestoSel.value) return
  proc.value = true
  try { await api.post(`/tareas/${sel.value}/asignar`, { puesto: puestoSel.value }); puestoSel.value = ''; await elegir(sel.value); flash('Puesto asignado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo asignar.', true) }
  finally { proc.value = false }
}

async function darBaja () {
  if (!elegidos.value.length) return
  if (!confirm('¿Procede a dar de baja los puestos seleccionados?')) return
  proc.value = true
  try { await api.post(`/tareas/${sel.value}/baja`, { puestos: elegidos.value }); await elegir(sel.value); flash('Puestos dados de baja.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo dar de baja.', true) }
  finally { proc.value = false }
}

// ── PDF listado del catálogo ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
async function imprimir () {
  const todas = (await api.get('/tareas')).data ?? []
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 16, PW = 210, PH = 297; let y = 18
  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('TAREAS', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  doc.setFillColor(27, 67, 50); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
  doc.rect(ML, y - 4, PW - ML * 2, 6, 'F'); doc.text('Código', ML + 2, y); doc.text('Descripción de las Tareas', ML + 30, y); doc.setTextColor(0, 0, 0); y += 6
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  for (const t of todas) {
    if (y > PH - 16) { doc.addPage(); y = 18 }
    doc.text(String(t.codigo), ML + 2, y); doc.text(doc.splitTextToSize(t.descripcion, 150)[0] || '', ML + 30, y); y += 5.2
  }
  cerrarPdf(); pdfNombre.value = 'Tareas.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ta-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.ta-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ta-ico { font-size:28px; } .ta-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ta-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ta-nuevo { margin-left:auto; background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; }
.ta-print { background:#e0eefc; color:#2d6a9f; border:none; padding:9px 14px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; }
.ta-print:disabled { opacity:.5; cursor:default; }
.ta-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ta-msg.ok { background:#d1fae5; color:#065f46; } .ta-msg.err { background:#fee2e2; color:#991b1b; }
.ta-layout { display:flex; gap:14px; flex:1; min-height:0; padding:14px 18px; }
.ta-list { width:300px; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
.ta-search { border:none; border-bottom:1px solid #e2e8f0; padding:10px 12px; font-size:13px; outline:none; }
.ta-list-body { overflow:auto; flex:1; }
.ta-item { display:flex; align-items:center; gap:10px; width:100%; padding:9px 12px; border:none; background:transparent; border-bottom:1px solid #f0f4f9; cursor:pointer; text-align:left; }
.ta-item:hover { background:#f0f7ff; } .ta-item.on { background:#e0eefc; border-left:3px solid #2d6a9f; }
.ta-cod { font-size:12px; color:#2d6a9f; font-weight:700; min-width:34px; } .ta-des { font-size:13px; color:#1a3a5c; }
.ta-vacio { color:#94a3b8; padding:14px; font-size:13px; }
.ta-detalle { flex:1; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:auto; padding:16px; }
.ta-vacia { justify-content:center; align-items:center; } .ta-sinsel { color:#8aacca; }
.ta-head { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
.campo { display:flex; flex-direction:column; gap:4px; flex:1; } .campo.cod { flex:0 0 90px; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; color:#1e293b; }
.campo input:disabled { background:#f1f5f9; }
.btn { border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.del { background:#fee2e2; color:#991b1b; } .btn.add { background:#d1fae5; color:#065f46; } .btn.baja { background:#fee2e2; color:#991b1b; }
.btn:disabled { opacity:.5; cursor:default; }
.ta-sub { text-align:center; font-weight:700; color:#2a4a6a; margin:16px 0 8px; font-size:13px; }
.ta-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.ta-tabla th { background:#2d6a9f; color:#fff; padding:8px 10px; text-align:left; font-size:12px; } .ta-tabla th.c { text-align:center; width:60px; }
.ta-tabla td { padding:6px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ta-tabla td.c { text-align:center; }
.ta-tvacio { text-align:center; color:#94a3b8; padding:14px; }
.ta-asignar { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; align-items:center; }
.ta-selp { flex:1; min-width:240px; border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; }
.ta-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ta-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ta-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ta-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ta-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ta-pdf-b.ok { background:#22c55e; color:#fff; } .ta-pdf-b.cancel { background:#ef4444; color:#fff; }
.ta-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
