<!--
  EstadoCivilView.vue — ABM de Estados Civiles (tabla estadocivil).
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="ec-view">
    <!-- Cabecera -->
    <div class="ec-cab">
      <div class="ec-cab-ico">📋</div>
      <div class="ec-cab-tx">
        <h1>Estados Civiles</h1>
        <p>{{ lista.length }} registro{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="ec-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ec-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="ec-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <EstadoCivilAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/estado-civil"
            titulo="Asistente IA — Estado Civil"
            subtitulo="Preguntá sobre el módulo de estados civiles"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Cómo se genera el código?',
              '¿Por qué no me deja eliminar un estado civil?',
              '¿Dónde se usan los estados civiles?']"
            @close="modalIA = false" />

    <!-- Tabla -->
    <div class="ec-card">
      <div v-if="cargando" class="ec-vacio">⟳ Cargando...</div>
      <div v-else-if="lista.length === 0" class="ec-vacio">Sin estados civiles cargados</div>
      <table v-else class="ec-tabla">
        <thead>
          <tr>
            <th style="width:90px">Código</th>
            <th>Descripción</th>
            <th style="width:130px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in lista" :key="e.cod">
            <td class="ec-cod">{{ e.cod }}</td>
            <td>{{ e.descripcion }}</td>
            <td style="text-align:center">
              <button class="ec-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="ec-icono ec-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['ec-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="ec-overlay" @click.self="modal = false">
        <div class="ec-modal" v-enter-next>
          <h3>{{ editando ? 'Editar estado civil' : 'Nuevo estado civil' }}</h3>
          <div class="ec-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="ec-campo">
            <label>Descripción <span class="req">*</span></label>
            <input ref="inputDesc" v-model="form.descripcion" type="text" maxlength="50"
                   placeholder="Ej: Soltero/a" @keydown.enter="guardar" />
          </div>
          <p v-if="modalError" class="ec-modal-err">⚠️ {{ modalError }}</p>
          <div class="ec-modal-btns">
            <button class="ec-btn-guardar" :disabled="guardando" @click="guardar">
              💾 {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
            <button class="ec-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import EstadoCivilAyuda from '@/components/EstadoCivilAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

interface EstadoCivil { cod: number; descripcion: string }

const lista = ref<EstadoCivil[]>([])
const cargando = ref(false)
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const form = ref<{ cod: number | null; descripcion: string }>({ cod: null, descripcion: '' })
const inputDesc = ref<HTMLInputElement | null>(null)

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get('/estado-civil')
    lista.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const flash = (texto: string, error = false) => {
  msg.value = texto; msgError.value = error
  setTimeout(() => { msg.value = '' }, 3000)
}

const abrirNuevo = async () => {
  editando.value = false
  form.value = { cod: null, descripcion: '' }
  modalError.value = ''
  modal.value = true
  await nextTick(); inputDesc.value?.focus()
}

const abrirEditar = async (e: EstadoCivil) => {
  editando.value = true
  form.value = { cod: e.cod, descripcion: e.descripcion }
  modalError.value = ''
  modal.value = true
  await nextTick(); inputDesc.value?.focus()
}

const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true
  modalError.value = ''
  try {
    if (editando.value) {
      await api.put(`/estado-civil/${form.value.cod}`, { descripcion: form.value.descripcion })
      flash('Estado civil actualizado')
    } else {
      await api.post('/estado-civil', { descripcion: form.value.descripcion })
      flash('Estado civil creado')
    }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally {
    guardando.value = false
  }
}

const eliminar = async (e: EstadoCivil) => {
  if (!confirm(`¿Eliminar el estado civil "${e.descripcion}"?`)) return
  try {
    await api.delete(`/estado-civil/${e.cod}`)
    flash('Estado civil eliminado')
    await cargar()
  } catch (err: any) {
    flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true)
  }
}

onMounted(cargar)
</script>

<style scoped>
.ec-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ec-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff;
  border-bottom:1px solid #e2e8f0; }
.ec-cab-ico { font-size:28px; }
.ec-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.ec-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ec-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none;
  padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ec-btn-ia:hover { filter:brightness(1.1); }
.ec-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ec-btn-ayuda:hover { background:#f0faf4; }
.ec-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ec-btn-nuevo:hover { background:#16a34a; }

.ec-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:6px; max-width:680px; }
.ec-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.ec-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ec-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px;
  border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.ec-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ec-tabla tr:hover td { background:#f8fafc; }
.ec-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.ec-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.ec-icono:hover { background:#f0faf4; transform:scale(1.15); }
.ec-del:hover { background:#fee2e2; }
.ec-msg { padding:8px 12px; margin:6px 0 0; font-size:13px; border-radius:6px; }
.ec-msg.ok { background:#dcfce7; color:#166534; }
.ec-msg.err { background:#fee2e2; color:#b91c1c; }

/* Modal */
.ec-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000;
  display:flex; align-items:center; justify-content:center; }
.ec-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(420px,92vw);
  box-shadow:0 20px 50px rgba(0,0,0,.4); }
.ec-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.ec-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.ec-campo label { font-size:12px; font-weight:600; color:#374151; }
.ec-campo .req { color:#dc2626; }
.ec-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.ec-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ec-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.ec-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px;
  padding:7px 10px; font-size:13px; margin:4px 0 0; }
.ec-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.ec-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px;
  cursor:pointer; font-size:13px; font-weight:600; }
.ec-btn-guardar:hover:not(:disabled){ background:#15803d; }
.ec-btn-guardar:disabled { background:#cbd5e1; }
.ec-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px;
  border-radius:6px; cursor:pointer; font-size:13px; }
.ec-btn-cancel:hover { background:#f8fafc; }
</style>
