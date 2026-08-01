<!-- AgendaTelefonosView.vue — Agenda de Teléfonos (telefonos). -->
<template>
  <div class="at-view">
    <div class="at-cab">
      <div class="at-cab-ico">📞</div>
      <div class="at-cab-tx"><h1>Agenda de Teléfonos</h1><p>{{ filas.length }} contacto{{ filas.length === 1 ? '' : 's' }}</p></div>
      <button class="at-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="at-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <AgendaTelefonosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/telefonos" titulo="Asistente IA — Agenda de Teléfonos"
            subtitulo="Preguntá sobre la agenda"
            :sugerencias="['¿Cómo agrego un contacto?','¿Cómo borro varios?','¿Qué hace Confirmar?']"
            @close="modalIA = false" />

    <div class="at-acc">
      <button class="at-mini" @click="setTodos(true)">Todo</button>
      <button class="at-mini" @click="setTodos(false)">Nada</button>
      <button class="at-mini del" :disabled="!selCount" @click="borrarSeleccionados">🗑️ Borrar seleccionados ({{ selCount }})</button>
    </div>

    <div class="at-wrap">
      <table class="at-grid">
        <thead><tr><th style="width:34px">OK</th><th>Detalle</th><th>Interno</th><th>Celular</th><th>Otros</th></tr></thead>
        <tbody>
          <tr v-for="(f, i) in filas" :key="i" :class="{ sel: f._sel }">
            <td style="text-align:center"><input v-model="f._sel" type="checkbox" /></td>
            <td><input v-model="f.detalle" type="text" class="at-in" /></td>
            <td><input v-model="f.interno" type="text" class="at-in chico" /></td>
            <td><input v-model="f.celular" type="text" class="at-in" /></td>
            <td><input v-model="f.otros" type="text" class="at-in ancho" /></td>
          </tr>
          <tr v-if="!filas.length"><td colspan="5" class="at-vacio">Agenda vacía. Agregá un contacto abajo.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="at-agregar" v-enter-next>
      <span class="at-agtit">Detalle del área o persona a agendar</span>
      <input v-model="nc.detalle" type="text" class="at-ag-det" placeholder="Detalle" />
      <div class="at-agfila">
        <label>Interno <input v-model="nc.interno" type="text" /></label>
        <label>Celular <input v-model="nc.celular" type="text" /></label>
        <label class="at-otros">Otros detalles <textarea v-model="nc.otros" rows="2"></textarea></label>
        <button class="at-btn-ag" @click="agregar">＋ Agregar nuevo teléfono</button>
      </div>
      <p v-if="errAg" class="at-err">⚠️ {{ errAg }}</p>
    </div>

    <div class="at-pie">
      <button class="at-btn-conf" :disabled="guardando" @click="confirmar">💾 {{ guardando ? 'Guardando…' : 'CONFIRMAR TODOS LOS CAMBIOS' }}</button>
    </div>
    <p v-if="msg" :class="['at-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import AgendaTelefonosAyuda from '@/components/AgendaTelefonosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Fila { detalle: string; interno: string; celular: string; otros: string; _sel: boolean }
const filas = ref<Fila[]>([])
const nc = reactive({ detalle: '', interno: '', celular: '', otros: '' })
const guardando = ref(false); const errAg = ref('')
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 3500) }

const selCount = computed(() => filas.value.filter(f => f._sel).length)
const setTodos = (v: boolean) => filas.value.forEach(f => f._sel = v)

const cargar = async () => {
  try { filas.value = (await api.get('/telefonos')).data.map((t: any) => ({ ...t, _sel: false })) }
  catch (e) { console.error(e) }
}

const agregar = () => {
  errAg.value = ''
  if (!nc.detalle.trim()) { errAg.value = 'Falta el detalle.'; return }
  if (!nc.interno.trim() && !nc.celular.trim()) { errAg.value = 'Falta algún teléfono (interno o celular).'; return }
  filas.value.push({ detalle: nc.detalle.toUpperCase(), interno: nc.interno.toUpperCase(), celular: nc.celular.toUpperCase(), otros: nc.otros.toUpperCase(), _sel: false })
  filas.value.sort((a, b) => a.detalle.localeCompare(b.detalle))
  nc.detalle = ''; nc.interno = ''; nc.celular = ''; nc.otros = ''
}
const borrarSeleccionados = () => { filas.value = filas.value.filter(f => !f._sel) }

const confirmar = async () => {
  if (!confirm('¿Confirma todos los cambios de la agenda?')) return
  guardando.value = true
  try { await api.post('/telefonos', { rows: filas.value }); flash('Agenda guardada correctamente.'); await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

onMounted(cargar)
</script>

<style scoped>
.at-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.at-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.at-cab-ico { font-size:28px; } .at-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .at-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.at-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.at-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.at-acc { display:flex; gap:10px; padding:12px 18px 0; }
.at-mini { background:#fff; border:1px solid #cbd5e1; padding:6px 13px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .at-mini:hover { background:#f0faf4; } .at-mini.del:hover { background:#fee2e2; } .at-mini:disabled { opacity:.5; cursor:not-allowed; }
.at-wrap { margin:12px 18px; border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:46vh; }
.at-grid { width:100%; border-collapse:collapse; font-size:13px; }
.at-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 10px; font-size:12px; text-align:left; }
.at-grid td { padding:3px 8px; border-bottom:1px solid #f1f5f9; } .at-grid tr.sel td { background:#fff200; }
.at-in { width:100%; min-width:90px; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:13px; outline:none; } .at-in.chico { min-width:70px; } .at-in.ancho { min-width:180px; }
.at-vacio { padding:24px; text-align:center; color:#9ca3af; }
.at-agregar { margin:0 18px; padding:14px; background:#1b4332; border-radius:8px; color:#fff; }
.at-agtit { font-size:13px; font-weight:600; }
.at-ag-det { width:100%; margin:8px 0; border:1px solid #c3e6cb; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.at-agfila { display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.at-agfila label { font-size:12px; display:flex; flex-direction:column; gap:4px; }
.at-agfila input, .at-agfila textarea { border:1px solid #c3e6cb; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; color:#1e293b; }
.at-otros { flex:1; min-width:200px; } .at-otros textarea { width:100%; }
.at-btn-ag { align-self:center; background:#22c55e; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.at-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:6px 10px; font-size:13px; margin:8px 0 0; }
.at-pie { padding:14px 18px; }
.at-btn-conf { background:#16a34a; color:#fff; border:none; padding:11px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .at-btn-conf:disabled { background:#cbd5e1; }
.at-msg { padding:8px 14px; margin:0 18px 14px; font-size:13px; border-radius:6px; } .at-msg.ok { background:#dcfce7; color:#166534; } .at-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
