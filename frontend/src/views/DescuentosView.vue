<!--
  DescuentosView.vue — ABM de Descuentos (tabla descuent) con convenio asociado.
  Estado Variable (alícuota) / Fijo (importe). Según el convenio (mensual/jornal)
  se usan "va mensuales" o "va 1ra/2da quincena". Código incremental.
-->
<template>
  <div class="dc-view">
    <div class="dc-cab">
      <div class="dc-cab-ico">➖</div>
      <div class="dc-cab-tx">
        <h1>Descuentos</h1>
        <p>{{ lista.length }} descuento{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="dc-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="dc-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="dc-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <DescuentosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/descuentos"
            titulo="Asistente IA — Descuentos"
            subtitulo="Preguntá sobre el ABM de descuentos"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Qué diferencia hay entre Variable y Fijo?',
              '¿Qué es Va SAC?',
              '¿Cuándo se usan las quincenas?']"
            @close="modalIA = false" />

    <div class="dc-card">
      <div class="dc-buscador">
        <input v-model="filtro" type="text" placeholder="Buscar por descripción o convenio…" />
      </div>
      <div v-if="cargando" class="dc-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="dc-vacio">Sin descuentos cargados</div>
      <table v-else class="dc-tabla">
        <thead>
          <tr>
            <th style="width:80px">Código</th>
            <th>Descripción</th>
            <th style="width:170px">Convenio</th>
            <th style="width:90px">Estado</th>
            <th style="width:120px;text-align:right">Alíc. / Imp.</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="dc-cod">{{ e.cod }}</td>
            <td><b>{{ e.descripcion }}</b></td>
            <td>{{ e.convenio_desc }}</td>
            <td><span class="dc-chip">{{ e.estado === 'F' ? 'Fijo' : 'Variable' }}</span></td>
            <td class="dc-num">{{ e.estado === 'F' ? num(e.importe) : num(e.alicuota) + ' %' }}</td>
            <td style="text-align:center">
              <button class="dc-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="dc-icono dc-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['dc-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="dc-overlay" @click.self="modal = false">
        <div class="dc-modal" v-enter-next>
          <h3>{{ editando ? 'Editar descuento' : 'Nuevo descuento' }}</h3>
          <div class="dc-grid2">
            <div class="dc-campo"><label>Código</label><input :value="editando ? form.cod : '(automático)'" disabled /></div>
            <div></div>
            <div class="dc-campo dc-col2"><label>Descripción <span class="req">*</span></label>
              <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="100" /></div>
          </div>

          <div class="dc-flags">
            <label v-for="f in flags" :key="f.k" class="dc-flag">
              <span>{{ f.label }}</span>
              <span class="dc-sn">
                <label><input type="radio" value="S" v-model="form[f.k]" /> SI</label>
                <label><input type="radio" value="N" v-model="form[f.k]" /> NO</label>
              </span>
            </label>
          </div>

          <div class="dc-grid2">
            <div class="dc-campo"><label>Estado</label>
              <div class="dc-sn">
                <label><input type="radio" value="V" v-model="form.estado" /> Variable</label>
                <label><input type="radio" value="F" v-model="form.estado" /> Fijo</label>
              </div>
            </div>
            <div class="dc-campo" v-if="form.estado === 'V'"><label>Alícuota (%)</label>
              <input v-model.number="form.alicuota" type="number" step="0.01" placeholder="0.00" /></div>
            <div class="dc-campo" v-else><label>Importe</label>
              <input v-model.number="form.importe" type="number" step="0.01" placeholder="0.00" /></div>
          </div>

          <div class="dc-campo">
            <label>Convenio <span class="req">*</span></label>
            <select v-model.number="form.convenio">
              <option :value="0" disabled>— Elegir convenio —</option>
              <option v-for="c in convenios" :key="c.cod" :value="c.cod">{{ c.descripcion }}</option>
            </select>
            <small v-if="tipoConvenio" class="dc-tipo">Tipo: {{ tipoConvenio === 'M' ? 'MENSUALIZADO' : 'JORNAL' }}</small>
          </div>

          <!-- Condicionales según el tipo de convenio -->
          <div v-if="tipoConvenio === 'M'" class="dc-flag dc-flag-solo">
            <span>Va L. Mensuales</span>
            <span class="dc-sn"><label><input type="radio" value="S" v-model="form.va_mensuales" /> SI</label><label><input type="radio" value="N" v-model="form.va_mensuales" /> NO</label></span>
          </div>
          <template v-if="tipoConvenio === 'J'">
            <div class="dc-flag dc-flag-solo"><span>Va 1ra. Quincena</span>
              <span class="dc-sn"><label><input type="radio" value="S" v-model="form.va_1q" /> SI</label><label><input type="radio" value="N" v-model="form.va_1q" /> NO</label></span></div>
            <div class="dc-flag dc-flag-solo"><span>Va 2da. Quincena</span>
              <span class="dc-sn"><label><input type="radio" value="S" v-model="form.va_2q" /> SI</label><label><input type="radio" value="N" v-model="form.va_2q" /> NO</label></span></div>
          </template>

          <div class="dc-flag dc-flag-solo">
            <span>Va SAC</span>
            <span class="dc-sn">
              <label><input type="radio" value="S" v-model="form.va_sac" /> SI</label>
              <label><input type="radio" value="N" v-model="form.va_sac" /> NO</label>
              <label><input type="radio" value="M" v-model="form.va_sac" /> M</label>
            </span>
          </div>

          <p v-if="modalError" class="dc-modal-err">⚠️ {{ modalError }}</p>
          <div class="dc-modal-btns">
            <button class="dc-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="dc-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import DescuentosAyuda from '@/components/DescuentosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)
const num = (v: any) => Number(v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const flags = [
  { k: 'es_obra_social', label: 'Es Obra Social' },
  { k: 'es_ansaal', label: 'Es ANSAAL' },
  { k: 'multiplica_coef', label: 'Multiplica Coeficiente' },
  { k: 'es_sindicato', label: 'Es Sindicato' },
  { k: 'es_acuerdo', label: 'Es acuerdo' },
] as const

const lista = ref<any[]>([])
const convenios = ref<{ cod: number; descripcion: string; tipo: string }[]>([])
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
  return { cod: null, descripcion: '', convenio: 0, estado: 'V', alicuota: null, importe: null,
    es_obra_social: 'N', es_ansaal: 'N', multiplica_coef: 'N', es_sindicato: 'N', es_acuerdo: 'N',
    va_mensuales: 'N', va_1q: 'N', va_2q: 'N', va_sac: 'N' }
}

const tipoConvenio = computed(() => convenios.value.find(c => c.cod === form.value.convenio)?.tipo ?? '')

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return lista.value
  return lista.value.filter(e => e.descripcion.toLowerCase().includes(q) || (e.convenio_desc ?? '').toLowerCase().includes(q))
})

const cargar = async () => {
  cargando.value = true
  try {
    const [d, c] = await Promise.all([api.get('/descuentos'), api.get('/convenios')])
    lista.value = d.data; convenios.value = c.data
  } catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = vacio(); modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}
const abrirEditar = async (e: any) => {
  editando.value = true
  form.value = { ...e, convenio: Number(e.convenio), alicuota: Number(e.alicuota), importe: Number(e.importe),
    va_sac: e.va_sac || 'N' }
  modalError.value = ''; modal.value = true
  await nextTick(); inputDes.value?.focus()
}

const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  if (!form.value.convenio) { modalError.value = 'Elegí un convenio.'; return }
  guardando.value = true; modalError.value = ''
  const f = form.value
  const payload = { descripcion: f.descripcion, convenio: f.convenio, estado: f.estado,
    alicuota: f.alicuota ?? 0, importe: f.importe ?? 0,
    es_obra_social: f.es_obra_social, es_ansaal: f.es_ansaal, multiplica_coef: f.multiplica_coef,
    es_sindicato: f.es_sindicato, es_acuerdo: f.es_acuerdo,
    va_mensuales: f.va_mensuales, va_1q: f.va_1q, va_2q: f.va_2q, va_sac: f.va_sac }
  try {
    if (editando.value) { await api.put(`/descuentos/${f.cod}`, payload); flash('Descuento actualizado') }
    else { await api.post('/descuentos', payload); flash('Descuento creado') }
    modal.value = false; await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: any) => {
  if (!confirm(`¿Eliminar el descuento "${e.descripcion}"?`)) return
  try { await api.delete(`/descuentos/${e.cod}`); flash('Descuento eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.dc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.dc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.dc-cab-ico { font-size:28px; }
.dc-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .dc-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.dc-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.dc-btn-ia:hover { filter:brightness(1.1); }
.dc-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.dc-btn-ayuda:hover { background:#f0faf4; }
.dc-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.dc-btn-nuevo:hover { background:#16a34a; }

.dc-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:940px; }
.dc-buscador { padding:6px 6px 10px; }
.dc-buscador input { width:min(340px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.dc-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.dc-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.dc-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.dc-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.dc-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.dc-tabla tr:hover td { background:#f8fafc; }
.dc-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.dc-num { text-align:right; font-variant-numeric:tabular-nums; }
.dc-chip { background:#e0f2fe; color:#075985; border-radius:20px; padding:2px 10px; font-size:12px; font-weight:600; }
.dc-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.dc-icono:hover { background:#f0faf4; transform:scale(1.15); } .dc-del:hover { background:#fee2e2; }
.dc-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.dc-msg.ok { background:#dcfce7; color:#166534; } .dc-msg.err { background:#fee2e2; color:#b91c1c; }

.dc-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.dc-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(520px,95vw); box-shadow:0 20px 50px rgba(0,0,0,.4); max-height:92vh; overflow:auto; }
.dc-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.dc-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.dc-col2 { grid-column:1 / -1; }
.dc-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.dc-campo label { font-size:12px; font-weight:600; color:#374151; }
.dc-campo .req { color:#dc2626; }
.dc-campo input, .dc-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; background:#fff; }
.dc-campo input:focus, .dc-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.dc-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.dc-tipo { color:#0d9488; font-weight:600; font-size:12px; }
.dc-flags { border:1px solid #eef2f7; border-radius:10px; padding:8px 14px; margin:6px 0 14px; background:#f8fafc; }
.dc-flag { display:flex; align-items:center; justify-content:space-between; padding:5px 0; font-size:13px; color:#374151; }
.dc-flags .dc-flag + .dc-flag { border-top:1px solid #eef2f7; }
.dc-flag-solo { border:1px solid #eef2f7; border-radius:8px; padding:8px 14px; margin-bottom:10px; background:#f8fafc; }
.dc-sn { display:flex; gap:14px; }
.dc-sn label { display:flex; align-items:center; gap:5px; cursor:pointer; }
.dc-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.dc-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.dc-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.dc-btn-guardar:hover:not(:disabled){ background:#15803d; } .dc-btn-guardar:disabled { background:#cbd5e1; }
.dc-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.dc-btn-cancel:hover { background:#f8fafc; }
@media (max-width:520px){ .dc-grid2{ grid-template-columns:1fr; } }
</style>
