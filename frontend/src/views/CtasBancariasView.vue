<!-- CtasBancariasView.vue — ABM de Cuentas Bancarias (tabla ctas_ban). -->
<template>
  <div class="cb-view">
    <div class="cb-cab">
      <div class="cb-cab-ico">🏦</div>
      <div class="cb-cab-tx"><h1>Cuentas Bancarias</h1><p>{{ lista.length }} cuenta{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="cb-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cb-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="cb-btn-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <CtasBancariasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/ctas-bancarias" titulo="Asistente IA — Cuentas Bancarias"
            subtitulo="Preguntá sobre el ABM"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué son los códigos SILCAR y Logística?','¿Cómo agrego un banco?','¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="cb-card">
      <div class="cb-buscador"><input v-model="filtro" type="text" placeholder="Buscar por descripción…" /></div>
      <div v-if="cargando" class="cb-vacio">⟳ Cargando…</div>
      <div v-else-if="listaFiltrada.length === 0" class="cb-vacio">Sin cuentas bancarias</div>
      <table v-else class="cb-tabla">
        <thead><tr><th style="width:80px">Código</th><th>Descripción</th><th style="width:140px;text-align:right">Cód. SILCAR</th><th style="width:150px;text-align:right">Cód. Logística</th><th style="width:120px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="cb-cod">{{ e.cod }}</td>
            <td><b>{{ e.descripcion }}</b></td>
            <td class="cb-num">{{ e.cod_silcar }}</td>
            <td class="cb-num">{{ e.cod_logistica }}</td>
            <td style="text-align:center">
              <button class="cb-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="cb-icono cb-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cb-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="cb-overlay" @click.self="modal = false">
        <div class="cb-modal" v-enter-next>
          <h3>{{ editando ? 'Editar cuenta bancaria' : 'Nueva cuenta bancaria' }}</h3>
          <div class="cb-campo"><label>Código</label><input :value="editando ? form.cod : '(automático)'" disabled /></div>
          <div class="cb-campo"><label>Descripción <span class="req">*</span></label>
            <input ref="inputDes" v-model="form.descripcion" type="text" maxlength="50" placeholder="Ej: BANCO DE LA NACION ARGENTINA" /></div>
          <div class="cb-fila2">
            <div class="cb-campo"><label>Cód. prov. banco en SILCAR</label><input v-model.number="form.cod_silcar" type="number" min="0" /></div>
            <div class="cb-campo"><label>Cód. prov. banco en Logística</label><input v-model.number="form.cod_logistica" type="number" min="0" /></div>
          </div>
          <p v-if="modalError" class="cb-modal-err">⚠️ {{ modalError }}</p>
          <div class="cb-modal-btns">
            <button class="cb-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="cb-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import CtasBancariasAyuda from '@/components/CtasBancariasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Cuenta { cod: number; descripcion: string; cod_silcar: number; cod_logistica: number }
const lista = ref<Cuenta[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputDes = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; descripcion: string; cod_silcar: number | null; cod_logistica: number | null }>({ cod: null, descripcion: '', cod_silcar: null, cod_logistica: null })

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(e => e.descripcion.toLowerCase().includes(q)) : lista.value
})
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/ctas-bancarias')).data } catch (e) { console.error(e) } finally { cargando.value = false } }
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 3000) }
const abrirNuevo = async () => { editando.value = false; form.value = { cod: null, descripcion: '', cod_silcar: null, cod_logistica: null }; modalError.value = ''; modal.value = true; await nextTick(); inputDes.value?.focus() }
const abrirEditar = async (e: Cuenta) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputDes.value?.focus() }
const guardar = async () => {
  if (!form.value.descripcion.trim()) { modalError.value = 'La descripción es obligatoria.'; return }
  guardando.value = true; modalError.value = ''
  const payload = { descripcion: form.value.descripcion, cod_silcar: form.value.cod_silcar ?? 0, cod_logistica: form.value.cod_logistica ?? 0 }
  try {
    if (editando.value) { await api.put(`/ctas-bancarias/${form.value.cod}`, payload); flash('Cuenta actualizada') }
    else { await api.post('/ctas-bancarias', payload); flash('Cuenta creada') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Cuenta) => {
  if (!confirm(`¿Eliminar la cuenta "${e.descripcion}"?`)) return
  try { await api.delete(`/ctas-bancarias/${e.cod}`); flash('Cuenta eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
onMounted(cargar)
</script>

<style scoped>
.cb-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cb-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cb-cab-ico { font-size:28px; } .cb-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .cb-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cb-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cb-btn-ia:hover { filter:brightness(1.1); }
.cb-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cb-btn-ayuda:hover { background:#f0faf4; }
.cb-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cb-btn-nuevo:hover { background:#16a34a; }
.cb-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:880px; }
.cb-buscador { padding:6px 6px 10px; }
.cb-buscador input { width:min(320px,100%); border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.cb-buscador input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cb-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.cb-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.cb-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.cb-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.cb-tabla tr:hover td { background:#f8fafc; }
.cb-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.cb-num { text-align:right; font-family:monospace; color:#475569; }
.cb-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.cb-icono:hover { background:#f0faf4; transform:scale(1.15); } .cb-del:hover { background:#fee2e2; }
.cb-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.cb-msg.ok { background:#dcfce7; color:#166534; } .cb-msg.err { background:#fee2e2; color:#b91c1c; }
.cb-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cb-modal { background:#fff; border-radius:12px; padding:22px 24px; width:min(460px,94vw); box-shadow:0 20px 50px rgba(0,0,0,.4); }
.cb-modal h3 { margin:0 0 16px; color:#1b4332; font-size:17px; }
.cb-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.cb-campo label { font-size:12px; font-weight:600; color:#374151; } .cb-campo .req { color:#dc2626; }
.cb-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.cb-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cb-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.cb-fila2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.cb-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0 0; }
.cb-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.cb-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cb-btn-guardar:hover:not(:disabled){ background:#15803d; } .cb-btn-guardar:disabled { background:#cbd5e1; }
.cb-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.cb-btn-cancel:hover { background:#f8fafc; }
@media (max-width:480px){ .cb-fila2{ grid-template-columns:1fr; } }
</style>
