<!-- ContratistasTipoView.vue — ABM de Tipos de Contratista (tabla contipo). -->
<template>
  <div class="ct-view">
    <div class="ct-cab">
      <div class="ct-cab-ico">🏷️</div>
      <div class="ct-cab-tx"><h1>Contratistas Tipo</h1><p>{{ lista.length }} tipo{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="ct-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ct-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="ct-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ContratistasTipoAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/contratista-tipos" titulo="Asistente IA — Contratistas Tipo"
            subtitulo="Preguntá sobre el ABM de tipos de contratista"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué es la clase Fijo/Eventual?','¿Por qué no me deja eliminar uno?']"
            @close="modalIA = false" />

    <div class="ct-card">
      <div class="ct-buscador"><input v-model="filtro" type="text" placeholder="Buscar por nombre…" /></div>
      <div v-if="cargando" class="ct-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="ct-vacio">Sin tipos cargados</div>
      <table v-else class="ct-tabla">
        <thead><tr><th style="width:90px">Código</th><th>Nombre</th><th style="width:120px">Clase</th><th style="width:120px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="ct-cod">{{ e.cod }}</td>
            <td>{{ e.nombre }}</td>
            <td><span class="ct-chip" :class="e.clase === 'F' ? 'fijo' : 'event'">{{ e.clase === 'F' ? 'FIJO' : 'EVENTUAL' }}</span></td>
            <td style="text-align:center">
              <button class="ct-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="ct-icono ct-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['ct-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="ct-overlay" @click.self="modal = false">
        <div class="ct-modal" v-enter-next>
          <h3>{{ editando ? 'Editar tipo' : 'Nuevo tipo' }}</h3>
          <div class="ct-campo"><label>Código</label><input :value="editando ? form.cod : '(automático)'" disabled /></div>
          <div class="ct-campo"><label>Nombre <span class="req">*</span></label>
            <input ref="inputNom" v-model="form.nombre" type="text" maxlength="100" placeholder="Ej: Servicios de obra" @keydown.enter="guardar" /></div>
          <div class="ct-campo"><label>Clase <span class="req">*</span></label>
            <div class="ct-radios"><label><input v-model="form.clase" type="radio" value="F" /> FIJO</label><label><input v-model="form.clase" type="radio" value="E" /> EVENTUAL</label></div></div>
          <p v-if="modalError" class="ct-modal-err">⚠️ {{ modalError }}</p>
          <div class="ct-modal-btns">
            <button class="ct-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="ct-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ContratistasTipoAyuda from '@/components/ContratistasTipoAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Tipo { cod: number; nombre: string; clase: string }
const lista = ref<Tipo[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; nombre: string; clase: string }>({ cod: null, nombre: '', clase: 'F' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.nombre.toLowerCase().includes(q)) : lista.value
})
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/contratista-tipos')).data } catch (e) { console.error(e) } finally { cargando.value = false } }
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 3000) }
const abrirNuevo = async () => { editando.value = false; form.value = { cod: null, nombre: '', clase: 'F' }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const abrirEditar = async (e: Tipo) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  try {
    const payload = { nombre: form.value.nombre, clase: form.value.clase }
    if (editando.value) { await api.put(`/contratista-tipos/${form.value.cod}`, payload); flash('Tipo actualizado') }
    else { await api.post('/contratista-tipos', payload); flash('Tipo creado') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Tipo) => {
  if (!confirm(`¿Eliminar el tipo "${e.nombre}"?`)) return
  try { await api.delete(`/contratista-tipos/${e.cod}`); flash('Tipo eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
onMounted(cargar)
</script>

<style scoped>
.ct-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ct-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ct-cab-ico { font-size:28px; } .ct-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .ct-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ct-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ct-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ct-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ct-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:680px; }
.ct-buscador { padding:6px 6px 10px; } .ct-buscador input { width:min(300px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.ct-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ct-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.ct-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ct-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.ct-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .ct-tabla tr:hover td { background:#f8fafc; }
.ct-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.ct-chip { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; }
.ct-chip.fijo { background:#dbeafe; color:#1d4ed8; } .ct-chip.event { background:#fef3c7; color:#92400e; }
.ct-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; } .ct-icono:hover { background:#f0faf4; transform:scale(1.15); } .ct-del:hover { background:#fee2e2; }
.ct-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; } .ct-msg.ok { background:#dcfce7; color:#166534; } .ct-msg.err { background:#fee2e2; color:#b91c1c; }
.ct-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ct-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.ct-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.ct-campo { margin-bottom:14px; display:flex; flex-direction:column; gap:5px; } .ct-campo label { font-size:12px; font-weight:600; color:#374151; } .ct-campo .req { color:#dc2626; }
.ct-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.ct-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); } .ct-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.ct-radios { display:flex; gap:18px; padding-top:4px; } .ct-radios label { flex-direction:row; align-items:center; gap:6px; font-size:14px; font-weight:600; color:#1b4332; cursor:pointer; }
.ct-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.ct-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.ct-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; } .ct-btn-guardar:disabled { background:#cbd5e1; }
.ct-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
</style>
