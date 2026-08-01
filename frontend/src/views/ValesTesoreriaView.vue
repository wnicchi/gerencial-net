<!-- ValesTesoreriaView.vue — Ingreso de Tesorería (reposición de fondo fijo). -->
<template>
  <div class="vt-view">
    <div class="vt-cab">
      <div class="vt-cab-ico">💰</div>
      <div class="vt-cab-tx"><h1>Ingresos de Tesorería</h1><p>Reposición del fondo fijo</p></div>
      <button class="vt-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vt-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ValesTesoreriaAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vales" titulo="Asistente IA — Tesorería" subtitulo="Preguntá sobre los ingresos de tesorería"
            :sugerencias="['¿Qué es un ingreso de tesorería?','¿Qué es el fondo fijo?','¿Suma o resta del fondo?']" @close="modalIA = false" />

    <div class="vt-form">
      <div class="vt-campo"><label>Fecha VALE</label><input v-model="f.fecha" type="date" /></div>
      <div class="vt-campo"><label>Hora de Entrega</label><input v-model="horaTxt" type="time" /></div>
      <div class="vt-campo vt-ancho"><label>Detalle</label><input v-model="f.detalle" type="text" maxlength="200" /></div>
      <div class="vt-campo"><label>Importe</label><input v-model.number="f.importe" type="number" min="0" step="0.01" /></div>
      <div class="vt-campo vt-fondo">
        <label>Fondo Fijo de</label>
        <div class="vt-radios"><label><input v-model.number="f.fondo" type="radio" :value="1" /> SILCAR</label><label><input v-model.number="f.fondo" type="radio" :value="2" /> LOGÍSTICA</label></div>
      </div>
      <div class="vt-campo vt-ancho"><label>Autorizo</label><input v-model="f.autorizo" type="text" maxlength="100" /></div>
    </div>

    <div class="vt-acc">
      <button class="vt-btn-conf" :disabled="grabando" @click="confirmar">{{ grabando ? '⟳ Grabando…' : '✔ CONFIRMAR' }}</button>
      <p v-if="msg" :class="['vt-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import ValesTesoreriaAyuda from '@/components/ValesTesoreriaAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const f = ref({ fecha: new Date().toISOString().slice(0, 10), detalle: '', importe: 0, fondo: 1, autorizo: '' })
const horaTxt = ref(new Date().toTimeString().slice(0, 5))
const grabando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

const confirmar = async () => {
  if (!f.value.detalle.trim()) { flash('Ingresá un detalle.', true); return }
  if (!f.value.importe || f.value.importe <= 0) { flash('Verifique el importe.', true); return }
  if (!confirm('¿Confirma el ingreso de tesorería?')) return
  grabando.value = true
  try {
    const hora = parseInt(horaTxt.value.replace(':', ''), 10) || 0
    await api.post('/vales/tesoreria', { fecha: f.value.fecha, hora, detalle: f.value.detalle, importe: f.value.importe, fondo: f.value.fondo, autorizo: f.value.autorizo })
    flash('Ingreso registrado en el fondo fijo.')
    f.value = { fecha: new Date().toISOString().slice(0, 10), detalle: '', importe: 0, fondo: f.value.fondo, autorizo: '' }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo registrar.', true) }
  finally { grabando.value = false }
}
</script>

<style scoped>
.vt-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vt-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vt-cab-ico { font-size:28px; } .vt-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vt-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vt-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vt-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vt-form { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin:18px; max-width:620px; }
.vt-campo { display:flex; flex-direction:column; gap:5px; } .vt-campo.vt-ancho { grid-column:1 / -1; }
.vt-campo label { font-size:12px; font-weight:600; color:#374151; }
.vt-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; }
.vt-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.vt-fondo .vt-radios { display:flex; gap:16px; padding-top:6px; } .vt-radios label { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
.vt-acc { margin:0 18px 18px; max-width:620px; }
.vt-btn-conf { background:#2563eb; color:#fff; border:none; padding:11px 28px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.vt-btn-conf:hover:not(:disabled){ background:#1d4ed8; } .vt-btn-conf:disabled { background:#cbd5e1; }
.vt-msg { margin:12px 0 0; padding:8px 12px; font-size:13px; border-radius:6px; } .vt-msg.ok { background:#dcfce7; color:#166534; } .vt-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
