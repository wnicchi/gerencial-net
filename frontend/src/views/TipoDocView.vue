<!--
  TipoDocView.vue — ABM de Tipos de Documentación (tabla tipo_doc).
  Alta, edición y baja. El código es alfanumérico y lo ingresa el usuario.
-->
<template>
  <div class="td-view">
    <div class="td-cab">
      <div class="td-cab-ico">📄</div>
      <div class="td-cab-tx">
        <h1>Tipos de Documentación</h1>
        <p>{{ lista.length }} tipo{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="td-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="td-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="td-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <TipoDocAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/tipo-doc"
            titulo="Asistente IA — Tipo de Documentación"
            subtitulo="Preguntá sobre los tipos de documentación"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Qué significa el Tipo?',
              '¿Cómo agrego un tipo nuevo?',
              '¿El código se repite?']"
            @close="modalIA = false" />

    <div class="td-card">
      <div class="td-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por código o detalle…" />
      </div>
      <div v-if="cargando" class="td-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="td-vacio">Sin tipos de documentación</div>
      <table v-else class="td-tabla">
        <thead>
          <tr>
            <th style="width:90px">Código</th>
            <th>Detalle</th>
            <th style="width:220px">Tipo</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="td-cod">{{ e.cod }}</td>
            <td>{{ e.detalle }}</td>
            <td><span class="td-chip">{{ tipoLabel(e.tipo) }}</span></td>
            <td style="text-align:center">
              <button class="td-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="td-icono td-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['td-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="td-overlay" @click.self="modal = false">
        <div class="td-modal" v-enter-next>
          <h3>{{ editando ? 'Editar tipo de documentación' : 'Nuevo tipo de documentación' }}</h3>
          <div class="td-campo">
            <label>Código <span class="req">*</span></label>
            <input v-if="!editando" ref="inputCod" v-model="form.cod" type="text" maxlength="2"
                   placeholder="Ej: AU" style="text-transform:uppercase" />
            <input v-else :value="form.cod" disabled />
          </div>
          <div class="td-campo">
            <label>Detalle <span class="req">*</span></label>
            <input ref="inputDet" v-model="form.detalle" type="text" maxlength="50" placeholder="Ej: AUSENTE SIN AVISO" />
          </div>
          <div class="td-campo">
            <label>Tipo <span class="req">*</span></label>
            <select v-model="form.tipo">
              <option v-for="t in TIPOS" :key="t.val" :value="t.val">{{ t.label }}</option>
            </select>
          </div>
          <p v-if="modalError" class="td-modal-err">⚠️ {{ modalError }}</p>
          <div class="td-modal-btns">
            <button class="td-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="td-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import TipoDocAyuda from '@/components/TipoDocAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

// Etiquetas funcionales del Tipo (el operador no ve el código interno)
const TIPOS = [
  { val: 'E', label: 'Empleados' },
  { val: 'L', label: 'Licencias' },
  { val: 'X', label: 'Exámenes y Certificados' },
  { val: 'K', label: 'Capacitación' },
  { val: 'C', label: 'Carnet Autoelevadores' },
  { val: 'M', label: 'Postulantes' },
]
const tipoLabel = (v: string) => TIPOS.find(t => t.val === v)?.label ?? v

interface TipoDoc { cod: string; detalle: string; tipo: string }

const lista = ref<TipoDoc[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputCod = ref<HTMLInputElement | null>(null)
const inputDet = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: string; detalle: string; tipo: string }>({ cod: '', detalle: '', tipo: 'E' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return lista.value
  return lista.value.filter(e => e.cod.toLowerCase().includes(q) || e.detalle.toLowerCase().includes(q))
})

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/tipo-doc')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = { cod: '', detalle: '', tipo: 'E' }; modalError.value = ''; modal.value = true
  await nextTick(); inputCod.value?.focus()
}
const abrirEditar = async (e: TipoDoc) => {
  editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true
  await nextTick(); inputDet.value?.focus()
}

const guardar = async () => {
  if (!editando.value && !form.value.cod.trim()) { modalError.value = 'El código es obligatorio.'; return }
  if (!form.value.detalle.trim()) { modalError.value = 'El detalle es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  try {
    if (editando.value) {
      await api.put(`/tipo-doc/${form.value.cod}`, { detalle: form.value.detalle, tipo: form.value.tipo })
      flash('Tipo de documentación actualizado')
    } else {
      await api.post('/tipo-doc', { cod: form.value.cod.toUpperCase(), detalle: form.value.detalle, tipo: form.value.tipo })
      flash('Tipo de documentación creado')
    }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: TipoDoc) => {
  if (!confirm(`¿Eliminar el tipo de documentación "${e.detalle}" (${e.cod})?`)) return
  try { await api.delete(`/tipo-doc/${e.cod}`); flash('Tipo de documentación eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.td-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.td-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.td-cab-ico { font-size:28px; }
.td-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.td-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.td-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.td-btn-ia:hover { filter:brightness(1.1); }
.td-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.td-btn-ayuda:hover { background:#f0faf4; }
.td-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.td-btn-nuevo:hover { background:#16a34a; }

.td-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:820px; }
.td-buscador { padding:6px 6px 10px; }
.td-buscador input { width:min(340px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.td-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.td-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.td-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.td-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.td-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.td-tabla tr:hover td { background:#f8fafc; }
.td-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.td-chip { background:#e0f2fe; color:#075985; border-radius:20px; padding:2px 10px; font-size:12px; font-weight:600; }
.td-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.td-icono:hover { background:#f0faf4; transform:scale(1.15); }
.td-del:hover { background:#fee2e2; }
.td-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.td-msg.ok { background:#dcfce7; color:#166534; } .td-msg.err { background:#fee2e2; color:#b91c1c; }

.td-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.td-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(440px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.td-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.td-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.td-campo label { font-size:12px; font-weight:600; color:#374151; }
.td-campo .req { color:#dc2626; }
.td-campo input, .td-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; background:#fff; }
.td-campo input:focus, .td-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.td-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.td-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.td-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.td-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.td-btn-guardar:hover:not(:disabled){ background:#15803d; } .td-btn-guardar:disabled { background:#cbd5e1; }
.td-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.td-btn-cancel:hover { background:#f8fafc; }
</style>
