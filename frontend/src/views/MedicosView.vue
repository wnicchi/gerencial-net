<!-- MedicosView.vue — ABM de Médicos (tabla medicos). Control de Salud. -->
<template>
  <div class="cs-view">
    <div class="cs-cab">
      <div class="cs-ico">🩺</div>
      <div class="cs-tx"><h1>Médicos</h1><p>{{ lista.length }} médico{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="cs-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cs-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="cs-print" :disabled="!lista.length" @click="imprimir">🖨 Listado</button>
      <button class="cs-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/medicos" titulo="Asistente IA — Médicos"
            subtitulo="Preguntá sobre el ABM de médicos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué datos cargo de un médico?','¿Dónde se usan?','¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="cs-card big">
      <input v-model="filtro" class="cs-search" placeholder="🔍 Buscar por nombre…" />
      <div v-if="cargando" class="cs-vacio">⟳ Cargando…</div>
      <div v-else-if="!listaFiltrada.length" class="cs-vacio">Sin médicos cargados</div>
      <table v-else class="cs-tabla">
        <thead><tr>
          <th style="width:70px" class="ord" @click="ordenar('cod')">Código{{ flecha('cod') }}</th>
          <th class="ord" @click="ordenar('nombre')">Nombre{{ flecha('nombre') }}</th>
          <th class="ord" @click="ordenar('domicilio')">Domicilio{{ flecha('domicilio') }}</th>
          <th class="ord" @click="ordenar('telefono')">Teléfonos{{ flecha('telefono') }}</th>
          <th>Notas</th>
          <th style="width:100px;text-align:center">Acciones</th>
        </tr></thead>
        <tbody>
          <tr v-for="(e, i) in listaFiltrada" :key="i">
            <td class="cs-cod">{{ e.cod }}</td><td>{{ e.nombre }}</td><td>{{ e.domicilio }}</td><td>{{ e.telefono }}</td>
            <td class="cs-notas">{{ e.notas }}</td>
            <td style="text-align:center"><button class="cs-i" @click="abrirEditar(e)">✏️</button><button class="cs-i del" @click="eliminar(e)">🗑️</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cs-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="cs-ov" @click.self="modal = false">
        <div class="cs-md" v-enter-next>
          <h3>{{ editando ? 'Editar médico' : 'Nuevo médico' }}</h3>
          <label>Nombre *</label>
          <input ref="inputNom" v-model="form.nombre" maxlength="50" />
          <label>Domicilio</label>
          <input v-model="form.domicilio" maxlength="30" />
          <label>Teléfonos</label>
          <input v-model="form.telefono" maxlength="30" />
          <label>Notas</label>
          <textarea v-model="form.notas" maxlength="2000" rows="3"></textarea>
          <p v-if="modalError" class="cs-md-err">{{ modalError }}</p>
          <div class="cs-md-acc">
            <button class="cs-cancel" @click="modal = false">Cancelar</button>
            <button class="cs-ok" :disabled="guardando" @click="guardar">{{ guardando ? '⟳' : 'Guardar' }}</button>
          </div>
        </div>
      </div>
      <div v-if="ayuda" class="cs-ov" @click.self="ayuda = false">
        <div class="cs-md">
          <h3>❓ Ayuda — Médicos</h3>
          <ul class="cs-help">
            <li>Administra los <b>médicos</b> que realizan los exámenes de Control de Salud.</li>
            <li>De cada uno se carga nombre (obligatorio), domicilio, teléfonos y notas.</li>
            <li>El <b>código</b> lo asigna el sistema. Se eligen al registrar un examen médico.</li>
          </ul>
          <div class="cs-md-acc"><button class="cs-ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="cs-pdf-ov" @click.self="cerrarPdf">
        <div class="cs-pdf-md">
          <div class="cs-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cs-pdf-acc">
              <button class="cs-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cs-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cs-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="cs-pdf-frame"></iframe>
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

interface Item { cod: number; nombre: string; domicilio: string; telefono: string; notas: string }
type SKey = 'cod' | 'nombre' | 'domicilio' | 'telefono'
const lista = ref<Item[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const ayuda = ref(false); const modalIA = ref(false)
const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const vacio = (): any => ({ cod: null, nombre: '', domicilio: '', telefono: '', notas: '' })
const form = ref<any>(vacio())

const sortKey = ref<SKey>('nombre'); const sortDir = ref<1 | -1>(1)
const ordenar = (k: SKey) => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: SKey) => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''
const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  const base = q ? lista.value.filter(e => e.nombre.toLowerCase().includes(q)) : lista.value.slice()
  const k = sortKey.value, dir = sortDir.value
  return base.slice().sort((a, b) => k === 'cod' ? (a.cod - b.cod) * dir : String((a as any)[k]).localeCompare(String((b as any)[k]), 'es', { numeric: true }) * dir)
})
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3000) }
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/medicos')).data } catch (e) { console.error(e) } finally { cargando.value = false } }

const abrirNuevo = async () => { editando.value = false; form.value = vacio(); modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const abrirEditar = async (e: Item) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }

const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  const body = { nombre: form.value.nombre, domicilio: form.value.domicilio, telefono: form.value.telefono, notas: form.value.notas }
  try {
    if (editando.value) { await api.put(`/medicos/${form.value.cod}`, body); flash('Médico actualizado') }
    else { await api.post('/medicos', body); flash('Médico creado') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Item) => {
  if (!confirm(`¿Eliminar al médico "${e.nombre}"?`)) return
  try { await api.delete(`/medicos/${e.cod}`); flash('Médico eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function imprimir () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 15, MR = 195, PW = 210, PH = 297; let y = 18
  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('MÉDICOS', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.rect(ML, y - 4, MR - ML, 6, 'F')
  const cx = { c: ML + 2, n: ML + 16, d: ML + 96, t: ML + 150 }
  doc.text('Cód', cx.c, y); doc.text('Nombre', cx.n, y); doc.text('Domicilio', cx.d, y); doc.text('Teléfono', cx.t, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  const ordenada = [...lista.value].sort((a, b) => a.cod - b.cod)
  for (const e of ordenada) {
    if (y > PH - 16) { doc.addPage(); y = 18 }
    doc.setFontSize(9)
    doc.text(String(e.cod), cx.c, y); doc.text((doc.splitTextToSize(e.nombre, 78)[0] || ''), cx.n, y)
    doc.text((e.domicilio || '').slice(0, 30), cx.d, y); doc.text((e.telefono || '').slice(0, 24), cx.t, y); y += 5
  }
  cerrarPdf(); pdfNombre.value = 'Lista_medicos.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
onMounted(cargar)
</script>

<style scoped>
.cs-view { display:flex; flex-direction:column; min-height:100%; }
.cs-cab { display:flex; align-items:center; gap:10px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.cs-ico { font-size:28px; } .cs-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cs-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cs-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-print { background:#e0eefc; color:#2d6a9f; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .cs-print:disabled { opacity:.5; cursor:default; }
.cs-nuevo { background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cs-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; max-width:680px; } .cs-card.big { max-width:1000px; }
.cs-search { width:100%; max-width:420px; border:1px solid #d1d5db; border-radius:7px; padding:9px 12px; font-size:14px; margin-bottom:12px; outline:none; }
.cs-vacio { text-align:center; color:#94a3b8; padding:24px; }
.cs-tabla { width:100%; border-collapse:collapse; font-size:13.5px; }
.cs-tabla th { background:#f4f7fc; color:#2a4a6a; padding:8px 10px; text-align:left; font-size:12px; border-bottom:1px solid #e2e8f0; }
.cs-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .cs-cod { color:#2d6a9f; font-weight:700; }
.cs-notas { color:#64748b; font-size:12.5px; max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cs-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; } .cs-i.del:hover { filter:saturate(2); }
.cs-tabla th.ord { cursor:pointer; user-select:none; white-space:nowrap; } .cs-tabla th.ord:hover { background:#e6eefc; color:#1a3a5c; }
.cs-msg { margin:12px 0 0; padding:8px 12px; border-radius:6px; font-size:13px; } .cs-msg.ok { background:#d1fae5; color:#065f46; } .cs-msg.err { background:#fee2e2; color:#991b1b; }
.cs-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cs-md { background:#fff; border-radius:14px; padding:22px; width:min(460px,94vw); }
.cs-md h3 { margin:0 0 14px; color:#1a3a5c; } .cs-md label { font-size:12px; font-weight:600; color:#374151; display:block; margin-top:10px; }
.cs-md input, .cs-md textarea { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; margin-top:4px; outline:none; box-sizing:border-box; color:#1e293b; font-family:inherit; resize:vertical; }
.cs-md-err { color:#991b1b; font-size:13px; margin:8px 0 0; }
.cs-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.cs-md-acc { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
.cs-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:600; }
.cs-ok { background:#2d6a9f; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:700; } .cs-ok:disabled { opacity:.5; }
.cs-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cs-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.cs-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .cs-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cs-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cs-pdf-b.ok { background:#22c55e; color:#fff; } .cs-pdf-b.cancel { background:#ef4444; color:#fff; }
.cs-pdf-frame { flex:1; border:none; width:100%; }
</style>
