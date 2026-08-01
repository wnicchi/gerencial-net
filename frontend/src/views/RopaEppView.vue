<!-- RopaEppView.vue — ABM del catálogo de Uniforme/EPP (tabla ropa). -->
<template>
  <div class="re-view">
    <div class="re-cab">
      <div class="re-ico">👔</div>
      <div class="re-tx"><h1>Gestión Uniforme-EPP</h1><p>{{ lista.length }} ítem{{ lista.length === 1 ? '' : 's' }} en el catálogo</p></div>
      <button class="re-ia" @click="modalIA = true">🤖 IA</button>
      <button class="re-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="re-print" :disabled="!lista.length" @click="imprimir">🖨 Listado</button>
      <button class="re-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/ropa-epp" titulo="Asistente IA — Uniforme-EPP"
            subtitulo="Preguntá sobre el catálogo de prendas y EPP"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego una prenda?','¿Qué significa EPP inactivo?','¿Para qué es el rubro?']"
            @close="modalIA = false" />

    <div class="re-card">
      <input v-model="filtro" class="re-search" placeholder="🔍 Buscar por descripción…" />
      <div v-if="cargando" class="re-vacio">⟳ Cargando…</div>
      <div v-else-if="!listaFiltrada.length" class="re-vacio">Sin ítems cargados</div>
      <table v-else class="re-tabla">
        <thead><tr>
          <th style="width:70px" class="ord" @click="ordenar('cod')">Cód.{{ flecha('cod') }}</th>
          <th class="ord" @click="ordenar('descripcion')">Descripción{{ flecha('descripcion') }}</th>
          <th class="ord" @click="ordenar('rubro_des')">Rubro{{ flecha('rubro_des') }}</th>
          <th class="c ord" @click="ordenar('inactivo')">Inactivo{{ flecha('inactivo') }}</th>
          <th class="c ord" @click="ordenar('obsequio')">Obsequio{{ flecha('obsequio') }}</th>
          <th style="width:100px;text-align:center">Acciones</th>
        </tr></thead>
        <tbody>
          <tr v-for="(e, i) in listaFiltrada" :key="i" :class="{ inac: e.inactivo }">
            <td class="re-cod">{{ e.cod }}</td><td>{{ e.descripcion }}</td><td>{{ e.rubro_des }}</td>
            <td class="c">{{ e.inactivo ? '🔴' : '' }}</td><td class="c">{{ e.obsequio ? '🎁' : '' }}</td>
            <td style="text-align:center"><button class="re-i" @click="abrirEditar(e)">✏️</button><button class="re-i del" @click="eliminar(e)">🗑️</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['re-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="re-ov" @click.self="modal = false">
        <div class="re-md" v-enter-next>
          <h3>{{ editando ? 'Editar ítem' : 'Nuevo ítem de Uniforme/EPP' }}</h3>
          <label>Descripción *</label>
          <input ref="inputNom" v-model="form.descripcion" maxlength="100" />
          <label>Rubro</label>
          <select v-model.number="form.rubro"><option :value="0">— —</option><option v-for="r in rubros" :key="r.cod" :value="r.cod">{{ r.nombre }}</option></select>
          <label>Notas</label>
          <textarea v-model="form.notas" rows="3" maxlength="255"></textarea>
          <div class="re-checks">
            <label class="chk"><input type="checkbox" v-model="form.inactivo" /> EPP inactivo</label>
            <label class="chk"><input type="checkbox" v-model="form.obsequio" /> Es un obsequio</label>
          </div>
          <p v-if="modalError" class="re-md-err">{{ modalError }}</p>
          <div class="re-md-acc">
            <button class="re-cancel" @click="modal = false">Cancelar</button>
            <button class="re-ok" :disabled="guardando" @click="guardar">{{ guardando ? '⟳' : 'Guardar' }}</button>
          </div>
        </div>
      </div>
      <div v-if="ayuda" class="re-ov" @click.self="ayuda = false">
        <div class="re-md">
          <h3>❓ Ayuda — Gestión Uniforme-EPP</h3>
          <ul class="re-help">
            <li>Administra el <b>catálogo de prendas y EPP</b> que después se entregan a los empleados.</li>
            <li>El <b>rubro</b> clasifica el ítem (vestimenta, elementos de seguridad, etc.).</li>
            <li><b>EPP inactivo</b>: el ítem deja de usarse (no aparece en los listados). <b>Obsequio</b>: marca que es un regalo.</li>
            <li>El <b>código</b> lo asigna el sistema.</li>
          </ul>
          <div class="re-md-acc"><button class="re-ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="re-pdf-ov" @click.self="cerrarPdf">
        <div class="re-pdf-md">
          <div class="re-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="re-pdf-acc">
              <button class="re-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="re-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="re-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="re-pdf-frame"></iframe>
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

interface Item { cod: number; descripcion: string; rubro: number; rubro_des: string; notas: string; inactivo: boolean; obsequio: boolean }
const lista = ref<Item[]>([]); const rubros = ref<{ cod: number; nombre: string }[]>([])
const cargando = ref(false); const filtro = ref(''); const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const ayuda = ref(false); const modalIA = ref(false)
const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; descripcion: string; rubro: number; notas: string; inactivo: boolean; obsequio: boolean }>({ cod: null, descripcion: '', rubro: 0, notas: '', inactivo: false, obsequio: false })

// Ordenamiento por columna
const sortKey = ref<'cod' | 'descripcion' | 'rubro_des' | 'inactivo' | 'obsequio'>('descripcion')
const sortDir = ref<1 | -1>(1)
const ordenar = (k: typeof sortKey.value) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: typeof sortKey.value) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  const base = q ? lista.value.filter(e => e.descripcion.toLowerCase().includes(q)) : lista.value.slice()
  const k = sortKey.value, dir = sortDir.value
  return base.slice().sort((a: any, b: any) => {
    let va = a[k], vb = b[k]
    if (typeof va === 'boolean') { va = va ? 1 : 0; vb = vb ? 1 : 0 }
    if (typeof va === 'number') return (va - vb) * dir
    return String(va).localeCompare(String(vb), 'es') * dir
  })
})
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3000) }
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/ropa-epp')).data } catch (e) { console.error(e) } finally { cargando.value = false } }

onMounted(async () => { await Promise.all([cargar(), (async () => { try { rubros.value = (await api.get('/rubros-ropa')).data ?? [] } catch { /* */ } })()]) })

const abrirNuevo = async () => { editando.value = false; form.value = { cod: null, descripcion: '', rubro: 0, notas: '', inactivo: false, obsequio: false }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const abrirEditar = async (e: Item) => { editando.value = true; form.value = { cod: e.cod, descripcion: e.descripcion, rubro: e.rubro, notas: e.notas, inactivo: e.inactivo, obsequio: e.obsequio }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }

const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; modalError.value = ''
  const body = { descripcion: form.value.descripcion, rubro: form.value.rubro, notas: form.value.notas, inactivo: form.value.inactivo, obsequio: form.value.obsequio }
  try {
    if (editando.value) { await api.put(`/ropa-epp/${form.value.cod}`, body); flash('Ítem actualizado') }
    else { await api.post('/ropa-epp', body); flash('Ítem creado') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Item) => {
  if (!confirm(`¿Eliminar "${e.descripcion}"?`)) return
  try { await api.delete(`/ropa-epp/${e.cod}`); flash('Ítem eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

// ── Listado PDF (activos, ordenado por rubro) ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function imprimir () {
  const activos = lista.value.filter(e => !e.inactivo).slice().sort((a, b) => (a.rubro_des || '').localeCompare(b.rubro_des || '') || a.descripcion.localeCompare(b.descripcion))
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 16, PW = 210, PH = 297; let y = 18
  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('LISTADO DE ROPA / EPP', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  let rubroActual = ' '
  for (const e of activos) {
    if (y > PH - 16) { doc.addPage(); y = 18 }
    if ((e.rubro_des || '') !== rubroActual) {
      rubroActual = e.rubro_des || ''
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50); y += 2
      doc.text(rubroActual || '(sin rubro)', ML, y); doc.setTextColor(0, 0, 0); y += 5
      doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.rect(ML, y - 4, PW - ML * 2, 6, 'F')
      doc.text('Código', ML + 2, y); doc.text('Descripción', ML + 28, y); doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
    }
    doc.setFontSize(9); doc.text(String(e.cod), ML + 2, y); doc.text(doc.splitTextToSize(e.descripcion, 150)[0] || '', ML + 28, y); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'Listado_ropa_epp.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.re-view { display:flex; flex-direction:column; min-height:100%; }
.re-cab { display:flex; align-items:center; gap:10px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.re-ico { font-size:28px; } .re-tx h1 { margin:0; font-size:19px; color:#1e293b; } .re-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.re-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.re-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.re-print { background:#e0eefc; color:#2d6a9f; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .re-print:disabled { opacity:.5; cursor:default; }
.re-nuevo { background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.re-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; max-width:880px; }
.re-search { width:100%; border:1px solid #d1d5db; border-radius:7px; padding:9px 12px; font-size:14px; margin-bottom:12px; outline:none; }
.re-vacio { text-align:center; color:#94a3b8; padding:24px; }
.re-tabla { width:100%; border-collapse:collapse; font-size:13.5px; }
.re-tabla th { background:#f4f7fc; color:#2a4a6a; padding:8px 10px; text-align:left; font-size:12px; border-bottom:1px solid #e2e8f0; } .re-tabla th.c { text-align:center; }
.re-tabla th.ord { cursor:pointer; user-select:none; white-space:nowrap; } .re-tabla th.ord:hover { background:#e6eefc; color:#1a3a5c; }
.re-tabla td { padding:7px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .re-tabla td.c { text-align:center; } .re-cod { color:#2d6a9f; font-weight:700; }
.re-tabla tr.inac td { color:#94a3b8; }
.re-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; } .re-i.del:hover { filter:saturate(2); }
.re-msg { margin:12px 0 0; padding:8px 12px; border-radius:6px; font-size:13px; } .re-msg.ok { background:#d1fae5; color:#065f46; } .re-msg.err { background:#fee2e2; color:#991b1b; }
.re-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.re-md { background:#fff; border-radius:14px; padding:22px; width:min(480px,94vw); }
.re-md h3 { margin:0 0 14px; color:#1a3a5c; } .re-md label { font-size:12px; font-weight:600; color:#374151; display:block; margin-top:10px; }
.re-md input, .re-md select, .re-md textarea { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; margin-top:4px; outline:none; box-sizing:border-box; color:#1e293b; }
.re-checks { display:flex; gap:20px; margin-top:12px; } .re-checks .chk { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#1b4332; } .re-checks input { width:auto; margin:0; }
.re-md-err { color:#991b1b; font-size:13px; margin:8px 0 0; }
.re-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.re-md-acc { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
.re-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:600; }
.re-ok { background:#2d6a9f; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:700; } .re-ok:disabled { opacity:.5; }
.re-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.re-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.re-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .re-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.re-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .re-pdf-b.ok { background:#22c55e; color:#fff; } .re-pdf-b.cancel { background:#ef4444; color:#fff; }
.re-pdf-frame { flex:1; border:none; width:100%; }
</style>
