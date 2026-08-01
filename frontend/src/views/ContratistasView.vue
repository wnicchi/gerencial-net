<!--
  ContratistasView.vue — ABM de Contratistas (tabla contratista).
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="co-view">
    <div class="co-cab">
      <div class="co-cab-ico">🤝</div>
      <div class="co-cab-tx">
        <h1>Contratistas</h1>
        <p>{{ lista.length }} contratista{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="co-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="co-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="co-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ContratistasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/contratistas"
            titulo="Asistente IA — Contratistas"
            subtitulo="Preguntá sobre el ABM de contratistas"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Qué es el código base de empleados?',
              '¿Por qué no me deja eliminar uno?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="co-card">
      <div class="co-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por nombre…" />
      </div>
      <div v-if="cargando" class="co-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="co-vacio">Sin contratistas cargados</div>
      <table v-else class="co-tabla">
        <thead>
          <tr>
            <th style="width:90px">Código</th>
            <th>Nombre</th>
            <th style="width:200px">Cód. Base Empleados Nuevo</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="co-cod">{{ e.cod }}</td>
            <td><b>{{ e.nombre }}</b></td>
            <td class="co-num">{{ e.base_empleados }}</td>
            <td style="text-align:center">
              <button class="co-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="co-icono co-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['co-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="co-overlay" @click.self="modal = false">
        <div class="co-modal" v-enter-next>
          <h3>{{ editando ? 'Editar contratista' : 'Nuevo contratista' }}</h3>
          <div class="co-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="co-campo">
            <label>Nombre <span class="req">*</span></label>
            <input ref="inputNom" v-model="form.nombre" type="text" maxlength="100" placeholder="Ej: ADECCO ARGENTINA" />
          </div>
          <div class="co-campo">
            <label>Código Base Empleados Nuevo</label>
            <input v-model.number="form.base_empleados" type="number" min="0" placeholder="Ej: 30001" />
          </div>
          <p v-if="modalError" class="co-modal-err">⚠️ {{ modalError }}</p>
          <div class="co-modal-btns">
            <button class="co-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="co-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ContratistasAyuda from '@/components/ContratistasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

interface Contratista { cod: number; nombre: string; base_empleados: number }

const lista = ref<Contratista[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; nombre: string; base_empleados: number | null }>({ cod: null, nombre: '', base_empleados: null })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.nombre.toLowerCase().includes(q)) : lista.value
})

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/contratistas')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = { cod: null, nombre: '', base_empleados: null }; modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}
const abrirEditar = async (e: Contratista) => {
  editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}

const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  const payload = { nombre: form.value.nombre, base_empleados: form.value.base_empleados ?? 0 }
  try {
    if (editando.value) { await api.put(`/contratistas/${form.value.cod}`, payload); flash('Contratista actualizado') }
    else { await api.post('/contratistas', payload); flash('Contratista creado') }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: Contratista) => {
  if (!confirm(`¿Eliminar el contratista "${e.nombre}"?`)) return
  try { await api.delete(`/contratistas/${e.cod}`); flash('Contratista eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.co-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.co-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.co-cab-ico { font-size:28px; }
.co-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.co-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.co-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.co-btn-ia:hover { filter:brightness(1.1); }
.co-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.co-btn-ayuda:hover { background:#f0faf4; }
.co-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.co-btn-nuevo:hover { background:#16a34a; }

.co-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:760px; }
.co-buscador { padding:6px 6px 10px; }
.co-buscador input { width:min(320px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.co-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.co-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.co-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.co-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.co-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.co-tabla tr:hover td { background:#f8fafc; }
.co-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.co-num { font-family:monospace; color:#475569; }
.co-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.co-icono:hover { background:#f0faf4; transform:scale(1.15); }
.co-del:hover { background:#fee2e2; }
.co-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.co-msg.ok { background:#dcfce7; color:#166534; } .co-msg.err { background:#fee2e2; color:#b91c1c; }

.co-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.co-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.co-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.co-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.co-campo label { font-size:12px; font-weight:600; color:#374151; }
.co-campo .req { color:#dc2626; }
.co-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.co-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.co-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.co-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.co-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.co-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.co-btn-guardar:hover:not(:disabled){ background:#15803d; } .co-btn-guardar:disabled { background:#cbd5e1; }
.co-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.co-btn-cancel:hover { background:#f8fafc; }
</style>
