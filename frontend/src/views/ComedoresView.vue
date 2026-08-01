<!-- ComedoresView.vue — ABM de Comedores (tabla comedor). -->
<template>
  <div class="cm-view">
    <div class="cm-cab">
      <div class="cm-cab-ico">🍽️</div>
      <div class="cm-cab-tx"><h1>Comedores</h1><p>{{ lista.length }} comedor{{ lista.length === 1 ? '' : 'es' }}</p></div>
      <button class="cm-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cm-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="cm-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ComedoresAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/comedores" titulo="Asistente IA — Comedores"
            subtitulo="Preguntá sobre el ABM de comedores"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego un comedor?','¿Por qué no me deja eliminar uno?','¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="cm-card">
      <div class="cm-buscador"><input v-model="filtro" type="text" placeholder="Buscar por descripción…" /></div>
      <div v-if="cargando" class="cm-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="cm-vacio">Sin comedores cargados</div>
      <table v-else class="cm-tabla">
        <thead><tr><th style="width:90px">Código</th><th>Descripción</th><th style="width:120px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="cm-cod">{{ e.cod }}</td>
            <td>{{ e.descripcion }}</td>
            <td style="text-align:center">
              <button class="cm-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="cm-icono cm-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cm-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="cm-overlay" @click.self="modal = false">
        <div class="cm-modal" v-enter-next>
          <h3>{{ editando ? 'Editar comedor' : 'Nuevo comedor' }}</h3>
          <div class="cm-campo"><label>Código</label><input :value="editando ? form.cod : '(automático)'" disabled /></div>
          <div class="cm-campo"><label>Descripción <span class="req">*</span></label>
            <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="50" placeholder="Ej: J y F" @keydown.enter="guardar" /></div>
          <p v-if="modalError" class="cm-modal-err">⚠️ {{ modalError }}</p>
          <div class="cm-modal-btns">
            <button class="cm-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="cm-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ComedoresAyuda from '@/components/ComedoresAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Comedor { cod: number; descripcion: string }
const lista = ref<Comedor[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; descripcion: string }>({ cod: null, descripcion: '' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.descripcion.toLowerCase().includes(q)) : lista.value
})
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/comedores')).data } catch (e) { console.error(e) } finally { cargando.value = false } }
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 3000) }
const abrirNuevo = async () => { editando.value = false; form.value = { cod: null, descripcion: '' }; modalError.value = ''; modal.value = true; await nextTick(); inputDes.value?.focus() }
const abrirEditar = async (e: Comedor) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputDes.value?.focus() }
const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; modalError.value = ''
  try {
    if (editando.value) { await api.put(`/comedores/${form.value.cod}`, { descripcion: form.value.descripcion }); flash('Comedor actualizado') }
    else { await api.post('/comedores', { descripcion: form.value.descripcion }); flash('Comedor creado') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Comedor) => {
  if (!confirm(`¿Eliminar el comedor "${e.descripcion}"?`)) return
  try { await api.delete(`/comedores/${e.cod}`); flash('Comedor eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
onMounted(cargar)
</script>

<style scoped>
.cm-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cm-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cm-cab-ico { font-size:28px; } .cm-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .cm-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cm-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cm-btn-ia:hover { filter:brightness(1.1); }
.cm-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cm-btn-ayuda:hover { background:#f0faf4; }
.cm-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cm-btn-nuevo:hover { background:#16a34a; }
.cm-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:640px; }
.cm-buscador { padding:6px 6px 10px; }
.cm-buscador input { width:min(300px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.cm-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cm-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.cm-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.cm-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.cm-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.cm-tabla tr:hover td { background:#f8fafc; }
.cm-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.cm-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.cm-icono:hover { background:#f0faf4; transform:scale(1.15); } .cm-del:hover { background:#fee2e2; }
.cm-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.cm-msg.ok { background:#dcfce7; color:#166534; } .cm-msg.err { background:#fee2e2; color:#b91c1c; }
.cm-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cm-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(420px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.cm-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.cm-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.cm-campo label { font-size:12px; font-weight:600; color:#374151; } .cm-campo .req { color:#dc2626; }
.cm-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.cm-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cm-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.cm-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.cm-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.cm-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cm-btn-guardar:hover:not(:disabled){ background:#15803d; } .cm-btn-guardar:disabled { background:#cbd5e1; }
.cm-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.cm-btn-cancel:hover { background:#f8fafc; }
</style>
