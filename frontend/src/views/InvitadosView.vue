<!--
  InvitadosView.vue — ABM de Invitados (tabla invitados).
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="iv-view">
    <div class="iv-cab">
      <div class="iv-cab-ico">🙋</div>
      <div class="iv-cab-tx">
        <h1>Invitados</h1>
        <p>{{ lista.length }} invitado{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="iv-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="iv-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="iv-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <InvitadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/invitados"
            titulo="Asistente IA — Invitados"
            subtitulo="Preguntá sobre la agenda de invitados"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Cómo agrego un invitado?',
              '¿Cómo edito o elimino uno?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="iv-card">
      <div class="iv-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por nombre, domicilio o teléfono…" />
      </div>
      <div v-if="cargando" class="iv-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="iv-vacio">Sin invitados cargados</div>
      <table v-else class="iv-tabla">
        <thead>
          <tr>
            <th style="width:80px">Código</th>
            <th>Nombre</th>
            <th>Domicilio</th>
            <th style="width:130px">Teléfono</th>
            <th style="width:130px">Celular</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="iv-cod">{{ e.cod }}</td>
            <td><b>{{ e.nombre }}</b><div v-if="e.notas" class="iv-notas">📝 {{ e.notas }}</div></td>
            <td>{{ e.domicilio }}</td>
            <td>{{ e.telefono }}</td>
            <td>{{ e.celular }}</td>
            <td style="text-align:center">
              <button class="iv-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="iv-icono iv-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['iv-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="iv-overlay" @click.self="modal = false">
        <div class="iv-modal" v-enter-next>
          <h3>{{ editando ? 'Editar invitado' : 'Nuevo invitado' }}</h3>
          <div class="iv-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="iv-campo">
            <label>Nombre <span class="req">*</span></label>
            <input ref="inputNom" v-model="form.nombre" type="text" maxlength="50" placeholder="Apellido y nombre" />
          </div>
          <div class="iv-campo">
            <label>Domicilio</label>
            <input v-model="form.domicilio" type="text" maxlength="50" />
          </div>
          <div class="iv-fila2">
            <div class="iv-campo"><label>Teléfono</label><input v-model="form.telefono" type="text" maxlength="20" /></div>
            <div class="iv-campo"><label>Celular</label><input v-model="form.celular" type="text" maxlength="20" /></div>
          </div>
          <div class="iv-campo">
            <label>Notas</label>
            <textarea v-model="form.notas" maxlength="2000" rows="3"></textarea>
          </div>
          <p v-if="modalError" class="iv-modal-err">⚠️ {{ modalError }}</p>
          <div class="iv-modal-btns">
            <button class="iv-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="iv-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import InvitadosAyuda from '@/components/InvitadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

interface Invitado { cod: number; nombre: string; domicilio: string; telefono: string; celular: string; notas: string }

const lista = ref<Invitado[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; nombre: string; domicilio: string; telefono: string; celular: string; notas: string }>(
  { cod: null, nombre: '', domicilio: '', telefono: '', celular: '', notas: '' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return lista.value
  return lista.value.filter(e =>
    e.nombre.toLowerCase().includes(q) || e.domicilio.toLowerCase().includes(q) ||
    e.telefono.toLowerCase().includes(q) || e.celular.toLowerCase().includes(q))
})

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/invitados')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}

const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const vacio = () => ({ cod: null, nombre: '', domicilio: '', telefono: '', celular: '', notas: '' })

const abrirNuevo = async () => {
  editando.value = false; form.value = vacio(); modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}
const abrirEditar = async (e: Invitado) => {
  editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}

const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  const payload = {
    nombre: form.value.nombre, domicilio: form.value.domicilio, telefono: form.value.telefono,
    celular: form.value.celular, notas: form.value.notas,
  }
  try {
    if (editando.value) { await api.put(`/invitados/${form.value.cod}`, payload); flash('Invitado actualizado') }
    else { await api.post('/invitados', payload); flash('Invitado creado') }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: Invitado) => {
  if (!confirm(`¿Eliminar al invitado "${e.nombre}"?`)) return
  try { await api.delete(`/invitados/${e.cod}`); flash('Invitado eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.iv-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.iv-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.iv-cab-ico { font-size:28px; }
.iv-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.iv-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.iv-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.iv-btn-ia:hover { filter:brightness(1.1); }
.iv-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.iv-btn-ayuda:hover { background:#f0faf4; }
.iv-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.iv-btn-nuevo:hover { background:#16a34a; }

.iv-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:920px; }
.iv-buscador { padding:6px 6px 10px; }
.iv-buscador input { width:min(360px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.iv-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.iv-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.iv-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.iv-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.iv-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; vertical-align:top; }
.iv-tabla tr:hover td { background:#f8fafc; }
.iv-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.iv-notas { font-size:12px; color:#64748b; margin-top:3px; }
.iv-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.iv-icono:hover { background:#f0faf4; transform:scale(1.15); }
.iv-del:hover { background:#fee2e2; }
.iv-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.iv-msg.ok { background:#dcfce7; color:#166534; } .iv-msg.err { background:#fee2e2; color:#b91c1c; }

.iv-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.iv-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(460px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); max-height:92vh; overflow:auto; }
.iv-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.iv-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.iv-campo label { font-size:12px; font-weight:600; color:#374151; }
.iv-campo .req { color:#dc2626; }
.iv-campo input, .iv-campo textarea { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; font-family:inherit; resize:vertical; }
.iv-campo input:focus, .iv-campo textarea:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.iv-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.iv-fila2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.iv-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.iv-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.iv-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.iv-btn-guardar:hover:not(:disabled){ background:#15803d; } .iv-btn-guardar:disabled { background:#cbd5e1; }
.iv-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.iv-btn-cancel:hover { background:#f8fafc; }
</style>
