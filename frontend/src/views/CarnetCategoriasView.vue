<!-- CarnetCategoriasView.vue — ABM de Categorías de Carnet (tabla carn_cat). -->
<template>
  <div class="cn-view">
    <div class="cn-cab">
      <div class="cn-cab-ico">🪪</div>
      <div class="cn-cab-tx"><h1>Categorías de Carnet</h1><p>{{ lista.length }} categoría{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="cn-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cn-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="cn-btn-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <CarnetCategoriasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/carnet-categorias" titulo="Asistente IA — Categorías de Carnet"
            subtitulo="Preguntá sobre el ABM"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego una categoría?','¿El código se repite?','¿Cómo edito una?']"
            @close="modalIA = false" />

    <div class="cn-card">
      <div class="cn-buscador"><input v-model="filtro" type="text" placeholder="Buscar por código o nombre…" /></div>
      <div v-if="cargando" class="cn-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="cn-vacio">Sin categorías de carnet</div>
      <table v-else class="cn-tabla">
        <thead><tr><th style="width:90px">Código</th><th>Nombre</th><th style="width:120px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="cn-cod">{{ e.cod }}</td>
            <td>{{ e.nombre }}</td>
            <td style="text-align:center">
              <button class="cn-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="cn-icono cn-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cn-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="cn-overlay" @click.self="modal = false">
        <div class="cn-modal" v-enter-next>
          <h3>{{ editando ? 'Editar categoría de carnet' : 'Nueva categoría de carnet' }}</h3>
          <div class="cn-campo"><label>Código <span class="req">*</span></label>
            <input v-if="!editando" ref="inputCod" v-model="form.cod" type="text" maxlength="4" placeholder="Ej: A21" style="text-transform:uppercase" />
            <input v-else :value="form.cod" disabled /></div>
          <div class="cn-campo"><label>Nombre <span class="req">*</span></label>
            <input ref="inputNom" v-model="form.nombre" type="text" maxlength="100" placeholder="Ej: MOTOCICLETAS 50 CC HASTA 150 CC" /></div>
          <p v-if="modalError" class="cn-modal-err">⚠️ {{ modalError }}</p>
          <div class="cn-modal-btns">
            <button class="cn-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="cn-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import CarnetCategoriasAyuda from '@/components/CarnetCategoriasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface CarnetCat { cod: string; nombre: string }
const lista = ref<CarnetCat[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputCod = ref<HTMLInputElement | null>(null); const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: string; nombre: string }>({ cod: '', nombre: '' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.cod.toLowerCase().includes(q) || e.nombre.toLowerCase().includes(q)) : lista.value
})
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/carnet-categorias')).data } catch (e) { console.error(e) } finally { cargando.value = false } }
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 3000) }
const abrirNuevo = async () => { editando.value = false; form.value = { cod: '', nombre: '' }; modalError.value = ''; modal.value = true; await nextTick(); inputCod.value?.focus() }
const abrirEditar = async (e: CarnetCat) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const guardar = async () => {
  if (!editando.value && !form.value.cod.trim()) { modalError.value = 'El código es obligatorio.'; return }
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  try {
    if (editando.value) { await api.put(`/carnet-categorias/${form.value.cod}`, { nombre: form.value.nombre }); flash('Categoría actualizada') }
    else { await api.post('/carnet-categorias', { cod: form.value.cod.toUpperCase(), nombre: form.value.nombre }); flash('Categoría creada') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: CarnetCat) => {
  if (!confirm(`¿Eliminar la categoría "${e.nombre}" (${e.cod})?`)) return
  try { await api.delete(`/carnet-categorias/${e.cod}`); flash('Categoría eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
onMounted(cargar)
</script>

<style scoped>
.cn-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cn-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cn-cab-ico { font-size:28px; } .cn-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .cn-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cn-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cn-btn-ia:hover { filter:brightness(1.1); }
.cn-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cn-btn-ayuda:hover { background:#f0faf4; }
.cn-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cn-btn-nuevo:hover { background:#16a34a; }
.cn-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:760px; }
.cn-buscador { padding:6px 6px 10px; }
.cn-buscador input { width:min(340px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.cn-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cn-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.cn-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.cn-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.cn-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.cn-tabla tr:hover td { background:#f8fafc; }
.cn-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.cn-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.cn-icono:hover { background:#f0faf4; transform:scale(1.15); } .cn-del:hover { background:#fee2e2; }
.cn-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.cn-msg.ok { background:#dcfce7; color:#166534; } .cn-msg.err { background:#fee2e2; color:#b91c1c; }
.cn-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cn-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.cn-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.cn-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.cn-campo label { font-size:12px; font-weight:600; color:#374151; } .cn-campo .req { color:#dc2626; }
.cn-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.cn-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cn-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.cn-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.cn-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.cn-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cn-btn-guardar:hover:not(:disabled){ background:#15803d; } .cn-btn-guardar:disabled { background:#cbd5e1; }
.cn-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.cn-btn-cancel:hover { background:#f8fafc; }
</style>
