<!--
  ConveniosView.vue — ABM de Convenios (tabla convenio) + obligaciones de EPP
  (tabla convenio_epp, catálogo ropa). Editor con 2 solapas.
-->
<template>
  <div class="cv-view">
    <div class="cv-cab">
      <div class="cv-cab-ico">📃</div>
      <div class="cv-cab-tx">
        <h1>Convenios</h1>
        <p>{{ lista.length }} convenio{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="cv-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cv-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="cv-btn-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ConveniosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/convenios"
            titulo="Asistente IA — Convenios"
            subtitulo="Preguntá sobre convenios y obligaciones de EPP"
            :sugerencias="[
              '¿Qué configuro en un convenio?',
              '¿Qué diferencia hay entre Jornal y Mensualizado?',
              '¿Qué son las obligaciones de EPP?',
              '¿Por qué no me deja eliminar uno?']"
            @close="modalIA = false" />

    <div class="cv-card">
      <div v-if="cargando" class="cv-vacio">⟳ Cargando…</div>
      <div v-else-if="lista.length === 0" class="cv-vacio">Sin convenios cargados</div>
      <table v-else class="cv-tabla">
        <thead><tr><th style="width:90px">Código</th><th>Descripción</th><th style="width:150px">Tipo</th><th style="width:120px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in lista" :key="e.cod" :class="{ sel: seleccionado === e.cod }" @click="seleccionar(e)">
            <td class="cv-cod">{{ e.cod }}</td>
            <td><b>{{ e.descripcion }}</b></td>
            <td><span class="cv-chip">{{ e.tipo === 'J' ? 'Jornal' : 'Mensualizado' }}</span></td>
            <td style="text-align:center">
              <button class="cv-icono" title="Editar" @click.stop="abrirEditar(e)">✏️</button>
              <button class="cv-icono cv-del" title="Eliminar" @click.stop="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cv-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Detalle: obligaciones de EPP del convenio seleccionado -->
    <div v-if="seleccionado" class="cv-card cv-detalle">
      <div class="cv-detalle-h">🦺 Obligaciones de entrega de ropa — <b>{{ detalleNombre }}</b></div>
      <table class="cv-tabla">
        <thead><tr><th style="width:100px">Cód. EPP</th><th>Detalle</th><th style="width:180px">Días próxima entrega</th></tr></thead>
        <tbody>
          <tr v-for="it in detalleEpp" :key="it.rop">
            <td class="cv-cod">{{ it.rop }}</td>
            <td>{{ it.detalle }}</td>
            <td>{{ it.dias }}</td>
          </tr>
          <tr v-if="!detalleCargando && !detalleEpp.length"><td colspan="3" class="cv-vacio2">Este convenio no tiene obligaciones de EPP cargadas</td></tr>
          <tr v-if="detalleCargando"><td colspan="3" class="cv-vacio2">⟳ Cargando…</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Editor (2 solapas) -->
    <Teleport to="body">
      <div v-if="editor" class="cv-overlay" @click.self="cerrarEditor">
        <div class="cv-editor">
          <div class="cv-ed-head">
            <span>{{ codActual ? 'Editar convenio' : 'Nuevo convenio' }} {{ codActual ? '— ' + form.descripcion : '' }}</span>
            <button class="cv-ed-x" @click="cerrarEditor">✕</button>
          </div>
          <div class="cv-tabs">
            <button :class="['cv-tab', { activa: tab === 'config' }]" @click="tab = 'config'">Convenio Configuración</button>
            <button :class="['cv-tab', { activa: tab === 'epp' }]" :disabled="!codActual" @click="irEpp">Obligaciones de EPP por convenio</button>
          </div>

          <!-- SOLAPA CONFIG -->
          <div v-show="tab === 'config'" class="cv-ed-body" v-enter-next>
            <div class="cv-grid2">
              <div class="cv-campo"><label>Código</label><input :value="codActual ?? '(automático)'" disabled /></div>
              <div></div>
              <div class="cv-campo cv-col2"><label>Descripción <span class="req">*</span></label>
                <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="20" /></div>

              <div class="cv-campo"><label>Tipo</label>
                <div class="cv-radios">
                  <label><input type="radio" value="J" v-model="form.tipo" /> Jornal</label>
                  <label><input type="radio" value="M" v-model="form.tipo" /> Mensualizado</label>
                </div>
              </div>
              <div class="cv-campo"><label>% Neto Hs. Extras</label>
                <input v-model.number="form.neto_hs_extras" type="number" step="0.01" min="0" /></div>

              <div v-if="form.tipo === 'J'" class="cv-campo"><label>Mínimo Horas O.S.</label>
                <input v-model.number="form.minimo_os" type="number" min="0" /></div>
              <div class="cv-campo cv-check"><label class="cv-checklbl"><input type="checkbox" v-model="form.aplica_dias" /> Aplica días trabajados</label></div>
            </div>

            <div class="cv-horas">
              <div class="cv-horas-t">Cantidad de Horas Normales Máximas por día</div>
              <div class="cv-horas-grid">
                <div class="cv-hora"><span>Lunes</span><input v-model.number="form.lun" type="number" min="0" max="24" /><i>Hs.</i></div>
                <div class="cv-hora"><span>Martes</span><input v-model.number="form.mar" type="number" min="0" max="24" /><i>Hs.</i></div>
                <div class="cv-hora"><span>Miércoles</span><input v-model.number="form.mie" type="number" min="0" max="24" /><i>Hs.</i></div>
                <div class="cv-hora"><span>Jueves</span><input v-model.number="form.jue" type="number" min="0" max="24" /><i>Hs.</i></div>
                <div class="cv-hora"><span>Viernes</span><input v-model.number="form.vie" type="number" min="0" max="24" /><i>Hs.</i></div>
                <div class="cv-hora"><span>Sábado</span><input v-model.number="form.sab" type="number" min="0" max="24" /><i>Hs.</i></div>
                <div class="cv-hora"><span>Domingo</span><input v-model.number="form.dom" type="number" min="0" max="24" /><i>Hs.</i></div>
              </div>
            </div>

            <p v-if="editorError" class="cv-ed-err">⚠️ {{ editorError }}</p>
            <div class="cv-ed-btns">
              <button class="cv-btn-guardar" :disabled="guardando" @click="guardarConfig">💾 {{ guardando ? 'Guardando…' : 'Guardar configuración' }}</button>
              <button class="cv-btn-cancel" @click="cerrarEditor">Cerrar</button>
            </div>
          </div>

          <!-- SOLAPA EPP -->
          <div v-show="tab === 'epp'" class="cv-ed-body">
            <div class="cv-epp-tit">{{ form.descripcion }}</div>
            <table class="cv-tabla cv-epp-tabla">
              <thead><tr><th style="width:60px">Elegir</th><th style="width:90px">Cod. EPP</th><th>Detalles</th><th style="width:160px">Días próxima entrega</th></tr></thead>
              <tbody>
                <tr v-for="it in eppItems" :key="it.rop">
                  <td style="text-align:center"><input type="checkbox" :value="it.rop" v-model="eppMarcados" /></td>
                  <td class="cv-cod">{{ it.rop }}</td>
                  <td>{{ it.detalle }}</td>
                  <td>{{ it.dias }}</td>
                </tr>
                <tr v-if="!eppItems.length"><td colspan="4" class="cv-vacio2">Sin obligaciones de EPP cargadas</td></tr>
              </tbody>
            </table>

            <div class="cv-epp-acc">
              <button class="cv-btn-eliminar" :disabled="!eppMarcados.length" @click="eliminarEpp">🗑️ Eliminar seleccionados</button>
            </div>

            <div class="cv-epp-agregar">
              <div class="cv-campo cv-epp-sel"><label>EPP / Ropa</label>
                <select v-model.number="eppSel"><option :value="0">— Elegir EPP —</option>
                  <option v-for="r in ropaDisponible" :key="r.cod" :value="r.cod">{{ r.cod }} — {{ r.detalle }}</option></select>
              </div>
              <div class="cv-campo"><label>Días Próxima Reposición</label>
                <input v-model.number="eppDias" type="number" min="1" class="cv-in-dias" /></div>
              <button class="cv-btn-agregar" :disabled="!eppSel || !eppDias" @click="agregarEpp">＋ Agregar obligación</button>
            </div>
            <p v-if="eppMsg" :class="['cv-msg', eppMsgError ? 'err' : 'ok']">{{ eppMsg }}</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ConveniosAyuda from '@/components/ConveniosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

interface Convenio { cod: number; descripcion: string; tipo: string }

const lista = ref<Convenio[]>([])
const cargando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3000) }

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/convenios')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}

// ── Master-detail en la vista principal (obligaciones EPP read-only) ──
const seleccionado = ref<number | null>(null)
const detalleNombre = ref('')
const detalleEpp = ref<any[]>([])
const detalleCargando = ref(false)

const seleccionar = async (e: Convenio) => {
  seleccionado.value = e.cod
  detalleNombre.value = e.descripcion
  detalleEpp.value = []
  detalleCargando.value = true
  try { detalleEpp.value = (await api.get(`/convenios/${e.cod}/epp`)).data }
  catch (err) { console.error(err) } finally { detalleCargando.value = false }
}

// ── Editor ────────────────────────────────────────────────
const editor = ref(false)
const tab = ref<'config' | 'epp'>('config')
const codActual = ref<number | null>(null)
const guardando = ref(false)
const editorError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = reactive<any>(formVacio())

function formVacio () {
  return { descripcion: '', tipo: 'M', neto_hs_extras: 0, minimo_os: 0, aplica_dias: false,
    lun: 0, mar: 0, mie: 0, jue: 0, vie: 0, sab: 0, dom: 0 }
}

const abrirNuevo = async () => {
  editor.value = true; tab.value = 'config'; codActual.value = null; editorError.value = ''
  Object.assign(form, formVacio())
  eppItems.value = []; eppMarcados.value = []; eppSel.value = 0; eppDias.value = null
  await nextTick(); inputDes.value?.focus()
}
const abrirEditar = async (c: Convenio) => {
  editor.value = true; tab.value = 'config'; codActual.value = c.cod; editorError.value = ''
  try {
    const { data } = await api.get(`/convenios/${c.cod}`)
    Object.assign(form, data)
  } catch (e) { console.error(e) }
  await cargarEpp()
  await nextTick(); inputDes.value?.focus()
}
const cerrarEditor = async () => {
  editor.value = false
  await cargar()
  // Si el convenio editado es el seleccionado abajo, refrescar su detalle
  if (seleccionado.value) {
    const c = lista.value.find(x => x.cod === seleccionado.value)
    if (c) seleccionar(c); else { seleccionado.value = null; detalleEpp.value = [] }
  }
}

const guardarConfig = async () => {
  if (!form.descripcion.trim()) { editorError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; editorError.value = ''
  const payload = { ...form }
  try {
    if (codActual.value) { await api.put(`/convenios/${codActual.value}`, payload); flashEd('Configuración guardada') }
    else {
      const { data } = await api.post('/convenios', payload)
      codActual.value = data.cod
      flashEd('Convenio creado. Ya podés cargar las obligaciones de EPP.')
    }
  } catch (e: any) {
    editorError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}
const flashEd = (t: string) => { editorError.value = ''; flash(t) }

const eliminar = async (c: Convenio) => {
  if (!confirm(`¿Eliminar el convenio "${c.descripcion}"?`)) return
  try { await api.delete(`/convenios/${c.cod}`); flash('Convenio eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

// ── Obligaciones EPP ──────────────────────────────────────
const eppItems = ref<any[]>([])
const eppCatalogo = ref<any[]>([])
const eppMarcados = ref<number[]>([])
const eppSel = ref<number>(0)
const eppDias = ref<number | null>(null)
const eppMsg = ref(''); const eppMsgError = ref(false)
const flashEpp = (t: string, e = false) => { eppMsg.value = t; eppMsgError.value = e; setTimeout(() => eppMsg.value = '', 3500) }

const ropaDisponible = computed(() => {
  const usados = new Set(eppItems.value.map(i => Number(i.rop)))
  return eppCatalogo.value.filter(r => !usados.has(Number(r.cod)))
})

const irEpp = () => { if (codActual.value) { tab.value = 'epp'; if (!eppCatalogo.value.length) cargarCatalogo() } }

const cargarCatalogo = async () => {
  try { eppCatalogo.value = (await api.get('/convenios-ropa')).data } catch (e) { console.error(e) }
}
const cargarEpp = async () => {
  if (!codActual.value) { eppItems.value = []; return }
  try { eppItems.value = (await api.get(`/convenios/${codActual.value}/epp`)).data; eppMarcados.value = [] }
  catch (e) { console.error(e) }
  if (!eppCatalogo.value.length) cargarCatalogo()
}

const agregarEpp = async () => {
  if (!codActual.value || !eppSel.value || !eppDias.value) return
  try {
    await api.post(`/convenios/${codActual.value}/epp`, { rop: eppSel.value, dias: eppDias.value })
    eppSel.value = 0; eppDias.value = null
    await cargarEpp()
    flashEpp('Obligación agregada')
  } catch (e: any) { flashEpp(e?.response?.data?.message ?? 'No se pudo agregar.', true) }
}
const eliminarEpp = async () => {
  if (!codActual.value || !eppMarcados.value.length) return
  if (!confirm(`¿Eliminar ${eppMarcados.value.length} obligación(es)?`)) return
  try {
    await api.delete(`/convenios/${codActual.value}/epp`, { data: { rops: eppMarcados.value } })
    await cargarEpp()
    flashEpp('Obligaciones eliminadas')
  } catch (e: any) { flashEpp(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.cv-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cv-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cv-cab-ico { font-size:28px; }
.cv-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .cv-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cv-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cv-btn-ia:hover { filter:brightness(1.1); }
.cv-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cv-btn-ayuda:hover { background:#f0faf4; }
.cv-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cv-btn-nuevo:hover { background:#16a34a; }

.cv-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:820px; }
.cv-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.cv-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.cv-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.cv-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.cv-tabla tbody tr { cursor:pointer; }
.cv-tabla tr:hover td { background:#f8fafc; }
.cv-tabla tr.sel td { background:#dcfce7; }
.cv-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.cv-detalle { margin-top:0; }
.cv-detalle-h { font-size:14px; color:#1b4332; padding:6px 8px 12px; }
.cv-chip { background:#e0f2fe; color:#075985; border-radius:20px; padding:2px 10px; font-size:12px; font-weight:600; }
.cv-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.cv-icono:hover { background:#f0faf4; transform:scale(1.15); } .cv-del:hover { background:#fee2e2; }
.cv-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.cv-msg.ok { background:#dcfce7; color:#166534; } .cv-msg.err { background:#fee2e2; color:#b91c1c; }
.cv-vacio2 { text-align:center; color:#9ca3af; font-style:italic; padding:14px; }

/* Editor */
.cv-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cv-editor { background:#fff; border-radius:12px; width:min(860px,97vw); max-height:94vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.cv-ed-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; background:#1b4332; color:#fff; font-size:15px; font-weight:600; }
.cv-ed-x { background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.cv-tabs { display:flex; gap:3px; padding:8px 14px 0; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.cv-tab { background:#e8edf2; color:#475569; border:none; border-radius:8px 8px 0 0; padding:9px 16px; cursor:pointer; font-size:13px; font-weight:600; }
.cv-tab.activa { background:#fff; color:#1b4332; box-shadow:0 -2px 0 #16a34a inset; }
.cv-tab:disabled { opacity:.5; cursor:not-allowed; }
.cv-ed-body { padding:18px 22px; overflow:auto; }
.cv-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.cv-col2 { grid-column:1 / -1; }
.cv-campo { display:flex; flex-direction:column; gap:5px; }
.cv-campo label { font-size:12px; font-weight:600; color:#374151; }
.cv-campo .req { color:#dc2626; }
.cv-campo input, .cv-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; background:#fff; }
.cv-campo input:focus, .cv-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cv-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.cv-radios { display:flex; gap:14px; font-size:13px; padding-top:6px; }
.cv-radios label { display:flex; align-items:center; gap:5px; cursor:pointer; }
.cv-check { justify-content:flex-end; }
.cv-checklbl { display:flex; align-items:center; gap:7px; cursor:pointer; padding-top:24px; font-weight:500; }

.cv-horas { margin-top:18px; border:1px solid #facc15; border-radius:10px; padding:14px 16px; background:#fffdf0; }
.cv-horas-t { font-size:13px; font-weight:700; color:#1b4332; text-decoration:underline; margin-bottom:12px; }
.cv-horas-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px 18px; }
.cv-hora { display:flex; align-items:center; gap:8px; }
.cv-hora span { flex:1; font-size:13px; color:#374151; text-align:right; }
.cv-hora input { width:54px; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:14px; font-weight:700; text-align:center; }
.cv-hora i { font-style:normal; font-size:12px; color:#64748b; }

.cv-ed-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:14px 0 0; }
.cv-ed-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.cv-btn-guardar { background:#16a34a; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cv-btn-guardar:hover:not(:disabled){ background:#15803d; } .cv-btn-guardar:disabled { background:#cbd5e1; }
.cv-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:10px 16px; border-radius:6px; cursor:pointer; font-size:13px; }

.cv-epp-tit { background:#fef9c3; border:1px solid #fde68a; border-radius:8px; padding:10px; text-align:center; font-weight:700; color:#1b4332; margin-bottom:12px; }
.cv-epp-tabla { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.cv-epp-acc { margin:12px 0; }
.cv-btn-eliminar { background:#ef4444; color:#fff; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cv-btn-eliminar:hover:not(:disabled){ background:#dc2626; } .cv-btn-eliminar:disabled { background:#cbd5e1; cursor:default; }
.cv-epp-agregar { display:flex; align-items:flex-end; gap:12px; padding:14px; background:#f8fafc; border:1px solid #eef2f7; border-radius:10px; }
.cv-epp-sel { flex:1; }
.cv-in-dias { width:90px; }
.cv-btn-agregar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cv-btn-agregar:hover:not(:disabled){ background:#15803d; } .cv-btn-agregar:disabled { background:#cbd5e1; cursor:default; }
@media (max-width:560px){ .cv-grid2{ grid-template-columns:1fr; } .cv-epp-agregar{ flex-wrap:wrap; } }
</style>
