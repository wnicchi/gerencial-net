<!--
  AsignacionesView.vue — ABM de Asignaciones Familiares (tabla asignaci).
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="af-view">
    <div class="af-cab">
      <div class="af-cab-ico">👨‍👩‍👧</div>
      <div class="af-cab-tx">
        <h1>Asignaciones Familiares</h1>
        <p>{{ lista.length }} concepto{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="af-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="af-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="af-btn-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <AsignacionesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/asignaciones"
            titulo="Asistente IA — Asignaciones Familiares"
            subtitulo="Preguntá sobre el ABM de asignaciones"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Cómo cargo un importe?',
              '¿Cómo edito una asignación?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="af-card">
      <div class="af-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por descripción…" />
      </div>
      <div v-if="cargando" class="af-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="af-vacio">Sin asignaciones cargadas</div>
      <table v-else class="af-tabla">
        <thead>
          <tr>
            <th style="width:90px">Código</th>
            <th>Descripción</th>
            <th style="width:160px;text-align:right">Importe</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="af-cod">{{ e.cod }}</td>
            <td>{{ e.descripcion }}</td>
            <td class="af-num">{{ money(e.importe) }}</td>
            <td style="text-align:center">
              <button class="af-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="af-icono af-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['af-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="af-overlay" @click.self="modal = false">
        <div class="af-modal" v-enter-next>
          <h3>{{ editando ? 'Editar asignación' : 'Nueva asignación' }}</h3>
          <div class="af-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="af-campo">
            <label>Descripción <span class="req">*</span></label>
            <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="50" placeholder="Ej: AJUST.RETR.PRENATAL" />
          </div>
          <div class="af-campo">
            <label>Importe</label>
            <input v-model.number="form.importe" type="number" step="0.01" min="0" placeholder="0.00" />
          </div>
          <p v-if="modalError" class="af-modal-err">⚠️ {{ modalError }}</p>
          <div class="af-modal-btns">
            <button class="af-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="af-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import AsignacionesAyuda from '@/components/AsignacionesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

const money = (v: any) => Number(v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

interface Asignacion { cod: number; descripcion: string; importe: number }

const lista = ref<Asignacion[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; descripcion: string; importe: number | null }>({ cod: null, descripcion: '', importe: null })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.descripcion.toLowerCase().includes(q)) : lista.value
})

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/asignaciones')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = { cod: null, descripcion: '', importe: null }; modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}
const abrirEditar = async (e: Asignacion) => {
  editando.value = true; form.value = { cod: e.cod, descripcion: e.descripcion, importe: Number(e.importe) }; modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}

const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; modalError.value = ''
  const payload = { descripcion: form.value.descripcion, importe: form.value.importe ?? 0 }
  try {
    if (editando.value) { await api.put(`/asignaciones/${form.value.cod}`, payload); flash('Asignación actualizada') }
    else { await api.post('/asignaciones', payload); flash('Asignación creada') }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: Asignacion) => {
  if (!confirm(`¿Eliminar la asignación "${e.descripcion}"?`)) return
  try { await api.delete(`/asignaciones/${e.cod}`); flash('Asignación eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.af-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.af-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.af-cab-ico { font-size:28px; }
.af-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .af-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.af-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.af-btn-ia:hover { filter:brightness(1.1); }
.af-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.af-btn-ayuda:hover { background:#f0faf4; }
.af-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.af-btn-nuevo:hover { background:#16a34a; }

.af-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:760px; }
.af-buscador { padding:6px 6px 10px; }
.af-buscador input { width:min(320px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.af-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.af-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.af-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.af-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.af-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.af-tabla tr:hover td { background:#f8fafc; }
.af-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.af-num { text-align:right; font-variant-numeric:tabular-nums; }
.af-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.af-icono:hover { background:#f0faf4; transform:scale(1.15); } .af-del:hover { background:#fee2e2; }
.af-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.af-msg.ok { background:#dcfce7; color:#166534; } .af-msg.err { background:#fee2e2; color:#b91c1c; }

.af-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.af-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.af-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.af-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.af-campo label { font-size:12px; font-weight:600; color:#374151; }
.af-campo .req { color:#dc2626; }
.af-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.af-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.af-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.af-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.af-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.af-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.af-btn-guardar:hover:not(:disabled){ background:#15803d; } .af-btn-guardar:disabled { background:#cbd5e1; }
.af-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.af-btn-cancel:hover { background:#f8fafc; }
</style>
