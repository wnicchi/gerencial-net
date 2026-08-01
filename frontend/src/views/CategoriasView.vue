<!--
  CategoriasView.vue — ABM de Categorías (tabla categori) con convenio asociado.
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="ca-view">
    <div class="ca-cab">
      <div class="ca-cab-ico">🏷️</div>
      <div class="ca-cab-tx">
        <h1>Categorías</h1>
        <p>{{ lista.length }} categoría{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="ca-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ca-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="ca-btn-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <CategoriasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/categorias"
            titulo="Asistente IA — Categorías"
            subtitulo="Preguntá sobre el ABM de categorías"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Cómo asocio un convenio?',
              '¿Por qué no me deja eliminar una?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="ca-card">
      <div class="ca-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por descripción o convenio…" />
      </div>
      <div v-if="cargando" class="ca-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="ca-vacio">Sin categorías cargadas</div>
      <table v-else class="ca-tabla">
        <thead>
          <tr>
            <th style="width:80px">Código</th>
            <th>Descripción</th>
            <th style="width:200px">Convenio</th>
            <th style="width:140px;text-align:right">Sueldo Básico</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="ca-cod">{{ e.cod }}</td>
            <td><b>{{ e.descripcion }}</b></td>
            <td>{{ e.convenio_desc }}</td>
            <td class="ca-num">{{ money(e.basico) }}</td>
            <td style="text-align:center">
              <button class="ca-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="ca-icono ca-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['ca-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="ca-overlay" @click.self="modal = false">
        <div class="ca-modal" v-enter-next>
          <h3>{{ editando ? 'Editar categoría' : 'Nueva categoría' }}</h3>
          <div class="ca-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="ca-campo">
            <label>Descripción <span class="req">*</span></label>
            <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="20" placeholder="Ej: ENCARGADO COMERCIAL" />
          </div>
          <div class="ca-campo">
            <label>Convenio <span class="req">*</span></label>
            <select v-model.number="form.convenio">
              <option :value="0" disabled>— Elegir convenio —</option>
              <option v-for="c in convenios" :key="c.cod" :value="c.cod">{{ c.descripcion }}</option>
            </select>
          </div>
          <div class="ca-campo">
            <label>Sueldo Básico</label>
            <input v-model.number="form.basico" type="number" step="0.01" min="0" placeholder="0.00" />
          </div>
          <p v-if="modalError" class="ca-modal-err">⚠️ {{ modalError }}</p>
          <div class="ca-modal-btns">
            <button class="ca-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="ca-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import CategoriasAyuda from '@/components/CategoriasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

const money = (v: any) => Number(v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

interface Categoria { cod: number; descripcion: string; convenio: number; convenio_desc: string; basico: number }

const lista = ref<Categoria[]>([])
const convenios = ref<{ cod: number; descripcion: string }[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; descripcion: string; convenio: number; basico: number | null }>({ cod: null, descripcion: '', convenio: 0, basico: null })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return lista.value
  return lista.value.filter(e => e.descripcion.toLowerCase().includes(q) || (e.convenio_desc ?? '').toLowerCase().includes(q))
})

const cargar = async () => {
  cargando.value = true
  try {
    const [cats, cons] = await Promise.all([api.get('/categorias'), api.get('/convenios')])
    lista.value = cats.data
    convenios.value = cons.data
  } catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = { cod: null, descripcion: '', convenio: 0, basico: null }; modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}
const abrirEditar = async (e: Categoria) => {
  editando.value = true; form.value = { cod: e.cod, descripcion: e.descripcion, convenio: Number(e.convenio), basico: Number(e.basico) }; modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}

const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  if (!form.value.convenio) { modalError.value = 'Elegí un convenio.'; return }
  guardando.value = true; modalError.value = ''
  const payload = { descripcion: form.value.descripcion, convenio: form.value.convenio, basico: form.value.basico ?? 0 }
  try {
    if (editando.value) { await api.put(`/categorias/${form.value.cod}`, payload); flash('Categoría actualizada') }
    else { await api.post('/categorias', payload); flash('Categoría creada') }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: Categoria) => {
  if (!confirm(`¿Eliminar la categoría "${e.descripcion}"?`)) return
  try { await api.delete(`/categorias/${e.cod}`); flash('Categoría eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.ca-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ca-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ca-cab-ico { font-size:28px; }
.ca-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .ca-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ca-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-btn-ia:hover { filter:brightness(1.1); }
.ca-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-btn-ayuda:hover { background:#f0faf4; }
.ca-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-btn-nuevo:hover { background:#16a34a; }

.ca-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:880px; }
.ca-buscador { padding:6px 6px 10px; }
.ca-buscador input { width:min(340px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.ca-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ca-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.ca-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ca-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.ca-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ca-tabla tr:hover td { background:#f8fafc; }
.ca-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.ca-num { text-align:right; font-variant-numeric:tabular-nums; }
.ca-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.ca-icono:hover { background:#f0faf4; transform:scale(1.15); } .ca-del:hover { background:#fee2e2; }
.ca-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.ca-msg.ok { background:#dcfce7; color:#166534; } .ca-msg.err { background:#fee2e2; color:#b91c1c; }

.ca-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ca-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.ca-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.ca-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.ca-campo label { font-size:12px; font-weight:600; color:#374151; }
.ca-campo .req { color:#dc2626; }
.ca-campo input, .ca-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; background:#fff; }
.ca-campo input:focus, .ca-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ca-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.ca-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.ca-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.ca-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-btn-guardar:hover:not(:disabled){ background:#15803d; } .ca-btn-guardar:disabled { background:#cbd5e1; }
.ca-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.ca-btn-cancel:hover { background:#f8fafc; }
</style>
