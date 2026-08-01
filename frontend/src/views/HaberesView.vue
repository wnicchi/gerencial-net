<!--
  HaberesView.vue — ABM de Haberes (tabla haberes) con convenio asociado.
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="hb-view">
    <div class="hb-cab">
      <div class="hb-cab-ico">💰</div>
      <div class="hb-cab-tx">
        <h1>Haberes</h1>
        <p>{{ lista.length }} haber{{ lista.length === 1 ? '' : 'es' }}</p>
      </div>
      <button class="hb-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="hb-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="hb-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <HaberesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/haberes"
            titulo="Asistente IA — Haberes"
            subtitulo="Preguntá sobre el ABM de haberes"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Qué significa Multiplica Antigüedad?',
              '¿Cuándo uso alícuota o importe?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="hb-card">
      <div class="hb-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por descripción o convenio…" />
      </div>
      <div v-if="cargando" class="hb-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="hb-vacio">Sin haberes cargados</div>
      <table v-else class="hb-tabla">
        <thead>
          <tr>
            <th style="width:80px">Código</th>
            <th>Descripción</th>
            <th style="width:180px">Convenio</th>
            <th style="width:100px;text-align:right">Alícuota</th>
            <th style="width:110px;text-align:right">Importe</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="hb-cod">{{ e.cod }}</td>
            <td><b>{{ e.descripcion }}</b></td>
            <td>{{ e.convenio_desc }}</td>
            <td class="hb-num">{{ num(e.alicuota) }}</td>
            <td class="hb-num">{{ num(e.importe) }}</td>
            <td style="text-align:center">
              <button class="hb-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="hb-icono hb-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['hb-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="hb-overlay" @click.self="modal = false">
        <div class="hb-modal" v-enter-next>
          <h3>{{ editando ? 'Editar haber' : 'Nuevo haber' }}</h3>
          <div class="hb-grid2">
            <div class="hb-campo"><label>Código</label><input :value="editando ? form.cod : '(automático)'" disabled /></div>
            <div></div>
            <div class="hb-campo hb-col2"><label>Descripción <span class="req">*</span></label>
              <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="100" /></div>
            <div class="hb-campo"><label>Alícuota</label><input v-model.number="form.alicuota" type="number" step="0.01" placeholder="0.00" /></div>
            <div class="hb-campo"><label>Importe</label><input v-model.number="form.importe" type="number" step="0.01" placeholder="0.00" /></div>
          </div>

          <div class="hb-flags">
            <label v-for="f in flags" :key="f.k" class="hb-flag">
              <span>{{ f.label }}</span>
              <span class="hb-sn">
                <label><input type="radio" value="S" v-model="form[f.k]" /> SI</label>
                <label><input type="radio" value="N" v-model="form[f.k]" /> NO</label>
              </span>
            </label>
          </div>

          <div class="hb-campo">
            <label>Convenio <span class="req">*</span></label>
            <select v-model.number="form.convenio">
              <option :value="0" disabled>— Elegir convenio —</option>
              <option v-for="c in convenios" :key="c.cod" :value="c.cod">{{ c.descripcion }}</option>
            </select>
          </div>

          <p v-if="modalError" class="hb-modal-err">⚠️ {{ modalError }}</p>
          <div class="hb-modal-btns">
            <button class="hb-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="hb-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import HaberesAyuda from '@/components/HaberesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)
const num = (v: any) => Number(v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const flags = [
  { k: 'mult_antiguedad', label: 'Multiplica Antigüedad' },
  { k: 'mult_valor_hora', label: 'Multiplica Valor Hora' },
  { k: 'sujeto_retenciones', label: 'Sujeto a Retenciones' },
  { k: 'tiene_cantidad', label: 'Tiene cantidad' },
  { k: 'basico30', label: 'Básico / 30' },
  { k: 'cambia_texto', label: 'Cambia el Texto' },
] as const

const lista = ref<any[]>([])
const convenios = ref<{ cod: number; descripcion: string }[]>([])
const cargando = ref(false)
const filtro = ref('')
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = ref<any>(vacio())

function vacio () {
  return { cod: null, descripcion: '', alicuota: null, importe: null, convenio: 0,
    mult_antiguedad: 'N', mult_valor_hora: 'N', sujeto_retenciones: 'N', tiene_cantidad: 'N', basico30: 'N', cambia_texto: 'N' }
}

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return lista.value
  return lista.value.filter(e => e.descripcion.toLowerCase().includes(q) || (e.convenio_desc ?? '').toLowerCase().includes(q))
})

const cargar = async () => {
  cargando.value = true
  try {
    const [h, c] = await Promise.all([api.get('/haberes'), api.get('/convenios')])
    lista.value = h.data; convenios.value = c.data
  } catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = vacio(); modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}
const abrirEditar = async (e: any) => {
  editando.value = true
  form.value = { ...e, convenio: Number(e.convenio), alicuota: Number(e.alicuota), importe: Number(e.importe) }
  modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}

const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  if (!form.value.convenio) { modalError.value = 'Elegí un convenio.'; return }
  guardando.value = true; modalError.value = ''
  const f = form.value
  const payload = { descripcion: f.descripcion, convenio: f.convenio, alicuota: f.alicuota ?? 0, importe: f.importe ?? 0,
    mult_antiguedad: f.mult_antiguedad, mult_valor_hora: f.mult_valor_hora, sujeto_retenciones: f.sujeto_retenciones,
    tiene_cantidad: f.tiene_cantidad, basico30: f.basico30, cambia_texto: f.cambia_texto }
  try {
    if (editando.value) { await api.put(`/haberes/${f.cod}`, payload); flash('Haber actualizado') }
    else { await api.post('/haberes', payload); flash('Haber creado') }
    modal.value = false; await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: any) => {
  if (!confirm(`¿Eliminar el haber "${e.descripcion}"?`)) return
  try { await api.delete(`/haberes/${e.cod}`); flash('Haber eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.hb-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.hb-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.hb-cab-ico { font-size:28px; }
.hb-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .hb-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.hb-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.hb-btn-ia:hover { filter:brightness(1.1); }
.hb-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.hb-btn-ayuda:hover { background:#f0faf4; }
.hb-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.hb-btn-nuevo:hover { background:#16a34a; }

.hb-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:920px; }
.hb-buscador { padding:6px 6px 10px; }
.hb-buscador input { width:min(340px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.hb-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.hb-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.hb-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.hb-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.hb-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.hb-tabla tr:hover td { background:#f8fafc; }
.hb-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.hb-num { text-align:right; font-variant-numeric:tabular-nums; }
.hb-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.hb-icono:hover { background:#f0faf4; transform:scale(1.15); } .hb-del:hover { background:#fee2e2; }
.hb-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.hb-msg.ok { background:#dcfce7; color:#166534; } .hb-msg.err { background:#fee2e2; color:#b91c1c; }

.hb-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.hb-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(520px,95vw); box-shadow:0 20px 50px rgba(0,0,0,.4); max-height:92vh; overflow:auto; }
.hb-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.hb-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.hb-col2 { grid-column:1 / -1; }
.hb-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.hb-campo label { font-size:12px; font-weight:600; color:#374151; }
.hb-campo .req { color:#dc2626; }
.hb-campo input, .hb-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; background:#fff; }
.hb-campo input:focus, .hb-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.hb-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.hb-flags { border:1px solid #eef2f7; border-radius:10px; padding:10px 14px; margin:6px 0 14px; background:#f8fafc; }
.hb-flag { display:flex; align-items:center; justify-content:space-between; padding:5px 0; font-size:13px; color:#374151; }
.hb-flag + .hb-flag { border-top:1px solid #eef2f7; }
.hb-sn { display:flex; gap:14px; }
.hb-sn label { display:flex; align-items:center; gap:5px; cursor:pointer; }
.hb-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.hb-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.hb-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.hb-btn-guardar:hover:not(:disabled){ background:#15803d; } .hb-btn-guardar:disabled { background:#cbd5e1; }
.hb-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.hb-btn-cancel:hover { background:#f8fafc; }
@media (max-width:520px){ .hb-grid2{ grid-template-columns:1fr; } }
</style>
