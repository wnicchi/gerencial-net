<!-- ExigenciasView.vue — ABM de Exigencias Legales a Contratados (tabla exigencias). -->
<template>
  <div class="ex-view">
    <div class="ex-cab">
      <div class="ex-cab-ico">⚖️</div>
      <div class="ex-cab-tx"><h1>Exigencias Legales a Contratados</h1><p>{{ lista.length }} exigencia{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="ex-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ex-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="ex-btn-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <ExigenciasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/exigencias" titulo="Asistente IA — Exigencias"
            subtitulo="Preguntá sobre las exigencias a contratistas"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué es por única vez?','¿Qué son los meses de vigencia?','¿Por qué no me deja eliminar una?']"
            @close="modalIA = false" />

    <div class="ex-card">
      <div class="ex-buscador"><input v-model="filtro" type="text" placeholder="Buscar por descripción…" /></div>
      <div v-if="cargando" class="ex-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="ex-vacio">Sin exigencias cargadas</div>
      <table v-else class="ex-tabla">
        <thead><tr><th style="width:70px">Cód.</th><th>Descripción</th><th style="width:90px;text-align:center">Única vez</th><th style="width:80px;text-align:center">Meses</th><th style="width:70px;text-align:center">ART</th><th style="width:110px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="ex-cod">{{ e.cod }}</td>
            <td>{{ e.descripcion }}</td>
            <td style="text-align:center"><span :class="['ex-chip', e.unica ? 'si' : 'no']">{{ e.unica ? 'SÍ' : 'NO' }}</span></td>
            <td style="text-align:center">{{ e.unica ? '—' : e.meses }}</td>
            <td style="text-align:center">{{ e.art ? '✅' : '—' }}</td>
            <td style="text-align:center">
              <button class="ex-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="ex-icono ex-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['ex-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="ex-overlay" @click.self="modal = false">
        <div class="ex-modal" v-enter-next>
          <h3>{{ editando ? 'Editar exigencia' : 'Nueva exigencia' }}</h3>
          <div class="ex-campo"><label>Código</label><input :value="editando ? form.cod : '(automático)'" disabled /></div>
          <div class="ex-campo"><label>Descripción <span class="req">*</span></label>
            <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="150" placeholder="Ej: Póliza de seguro de vida…" @keydown.enter="guardar" /></div>
          <div class="ex-fila">
            <div class="ex-campo"><label>Por única vez</label>
              <div class="ex-radios"><label><input v-model="form.unica" type="radio" :value="true" /> SÍ</label><label><input v-model="form.unica" type="radio" :value="false" /> NO</label></div></div>
            <div class="ex-campo" :class="{ dis: form.unica }"><label>Meses de Vigencia</label>
              <input v-model.number="form.meses" type="number" min="0" :disabled="form.unica" /></div>
            <div class="ex-campo"><label>&nbsp;</label>
              <label class="ex-check"><input v-model="form.art" type="checkbox" /> Exigencia A.R.T.</label></div>
          </div>
          <div class="ex-campo"><label>Notas</label><input v-model="form.notas" type="text" maxlength="200" placeholder="Notas y observaciones…" /></div>
          <p v-if="modalError" class="ex-modal-err">⚠️ {{ modalError }}</p>
          <div class="ex-modal-btns">
            <button class="ex-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="ex-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ExigenciasAyuda from '@/components/ExigenciasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Exi { cod: number; descripcion: string; unica: boolean; meses: number; art: boolean; notas: string }
const lista = ref<Exi[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; descripcion: string; unica: boolean; meses: number; art: boolean; notas: string }>({ cod: null, descripcion: '', unica: false, meses: 1, art: false, notas: '' })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.descripcion.toLowerCase().includes(q)) : lista.value
})
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/exigencias')).data } catch (e) { console.error(e) } finally { cargando.value = false } }
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 3000) }
const abrirNuevo = async () => { editando.value = false; form.value = { cod: null, descripcion: '', unica: false, meses: 1, art: false, notas: '' }; modalError.value = ''; modal.value = true; await nextTick(); inputDes.value?.focus() }
const abrirEditar = async (e: Exi) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputDes.value?.focus() }
const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; modalError.value = ''
  try {
    const payload = { descripcion: form.value.descripcion, unica: form.value.unica, meses: form.value.meses, art: form.value.art, notas: form.value.notas }
    if (editando.value) { await api.put(`/exigencias/${form.value.cod}`, payload); flash('Exigencia actualizada') }
    else { await api.post('/exigencias', payload); flash('Exigencia creada') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Exi) => {
  if (!confirm(`¿Eliminar la exigencia "${e.descripcion}"?`)) return
  try { await api.delete(`/exigencias/${e.cod}`); flash('Exigencia eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
onMounted(cargar)
</script>

<style scoped>
.ex-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ex-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ex-cab-ico { font-size:28px; } .ex-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ex-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ex-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; }
.ex-buscador { padding:6px 6px 10px; } .ex-buscador input { width:min(340px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.ex-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ex-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.ex-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ex-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.ex-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .ex-tabla tr:hover td { background:#f8fafc; }
.ex-cod { font-family:monospace; font-weight:700; color:#1b4332; text-align:center; }
.ex-chip { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; } .ex-chip.si { background:#dbeafe; color:#1d4ed8; } .ex-chip.no { background:#f1f5f9; color:#64748b; }
.ex-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; } .ex-icono:hover { background:#f0faf4; transform:scale(1.15); } .ex-del:hover { background:#fee2e2; }
.ex-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; } .ex-msg.ok { background:#dcfce7; color:#166534; } .ex-msg.err { background:#fee2e2; color:#b91c1c; }
.ex-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ex-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(540px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.ex-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.ex-fila { display:flex; gap:14px; flex-wrap:wrap; } .ex-fila .ex-campo { flex:1; min-width:130px; }
.ex-campo { margin-bottom:14px; display:flex; flex-direction:column; gap:5px; } .ex-campo label { font-size:12px; font-weight:600; color:#374151; } .ex-campo .req { color:#dc2626; }
.ex-campo input[type=text], .ex-campo input[type=number] { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.ex-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); } .ex-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.ex-campo.dis label { color:#94a3b8; }
.ex-radios { display:flex; gap:16px; padding-top:4px; } .ex-radios label { flex-direction:row; align-items:center; gap:5px; font-size:14px; font-weight:600; color:#1b4332; cursor:pointer; }
.ex-check { flex-direction:row !important; align-items:center; gap:6px; font-size:14px; font-weight:600; color:#1b4332; cursor:pointer; padding-top:6px; }
.ex-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.ex-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.ex-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; } .ex-btn-guardar:disabled { background:#cbd5e1; }
.ex-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
</style>
