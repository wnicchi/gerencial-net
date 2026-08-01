<!--
  SubsectoresView.vue — ABM de Sub-Sectores Laborales (tabla subsector).
  Funciones laborales dentro del sector. Código incremental (lo asigna el backend).
-->
<template>
  <div class="su-view">
    <div class="su-cab">
      <div class="su-cab-ico">🏭</div>
      <div class="su-cab-tx">
        <h1>Sub-Sectores Laborales</h1>
        <p>{{ lista.length }} sub-sector{{ lista.length === 1 ? '' : 'es' }}</p>
      </div>
      <button class="su-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="su-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="su-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <SubsectoresAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/subsectores"
            titulo="Asistente IA — Sub-Sectores Laborales"
            subtitulo="Preguntá sobre el ABM de sub-sectores"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Qué diferencia hay con los sectores?',
              '¿Por qué no me deja eliminar uno?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="su-card">
      <div class="su-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por nombre…" />
      </div>
      <div v-if="cargando" class="su-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="su-vacio">Sin sub-sectores cargados</div>
      <table v-else class="su-tabla">
        <thead>
          <tr>
            <th style="width:90px">Código</th>
            <th>Nombre</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="su-cod">{{ e.cod }}</td>
            <td>{{ e.nombre }}</td>
            <td style="text-align:center">
              <button class="su-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="su-icono su-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['su-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="su-overlay" @click.self="modal = false">
        <div class="su-modal" v-enter-next>
          <h3>{{ editando ? 'Editar sub-sector' : 'Nuevo sub-sector' }}</h3>
          <div class="su-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="su-campo">
            <label>Nombre <span class="req">*</span></label>
            <input ref="inputNom" v-model="form.nombre" type="text" maxlength="30" placeholder="Ej: ADMINISTRATIVO" @keydown.enter="guardar" />
          </div>
          <p v-if="modalError" class="su-modal-err">⚠️ {{ modalError }}</p>
          <div class="su-modal-btns">
            <button class="su-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="su-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import SubsectoresAyuda from '@/components/SubsectoresAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

interface Subsector { cod: number; nombre: string }

const lista = ref<Subsector[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; nombre: string }>({ cod: null, nombre: '' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.nombre.toLowerCase().includes(q)) : lista.value
})

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/subsectores')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = { cod: null, nombre: '' }; modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}
const abrirEditar = async (e: Subsector) => {
  editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}

const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  try {
    if (editando.value) { await api.put(`/subsectores/${form.value.cod}`, { nombre: form.value.nombre }); flash('Sub-sector actualizado') }
    else { await api.post('/subsectores', { nombre: form.value.nombre }); flash('Sub-sector creado') }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: Subsector) => {
  if (!confirm(`¿Eliminar el sub-sector "${e.nombre}"?`)) return
  try { await api.delete(`/subsectores/${e.cod}`); flash('Sub-sector eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.su-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.su-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.su-cab-ico { font-size:28px; }
.su-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.su-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.su-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.su-btn-ia:hover { filter:brightness(1.1); }
.su-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.su-btn-ayuda:hover { background:#f0faf4; }
.su-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.su-btn-nuevo:hover { background:#16a34a; }

.su-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:680px; }
.su-buscador { padding:6px 6px 10px; }
.su-buscador input { width:min(320px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.su-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.su-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.su-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.su-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.su-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.su-tabla tr:hover td { background:#f8fafc; }
.su-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.su-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.su-icono:hover { background:#f0faf4; transform:scale(1.15); }
.su-del:hover { background:#fee2e2; }
.su-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.su-msg.ok { background:#dcfce7; color:#166534; } .su-msg.err { background:#fee2e2; color:#b91c1c; }

.su-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.su-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.su-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.su-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.su-campo label { font-size:12px; font-weight:600; color:#374151; }
.su-campo .req { color:#dc2626; }
.su-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.su-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.su-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.su-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.su-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.su-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.su-btn-guardar:hover:not(:disabled){ background:#15803d; } .su-btn-guardar:disabled { background:#cbd5e1; }
.su-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.su-btn-cancel:hover { background:#f8fafc; }
</style>
