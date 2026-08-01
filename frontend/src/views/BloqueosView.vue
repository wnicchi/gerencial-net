<!--
  BloqueosView.vue — Bloqueos Varios. Toggle por tipo (Novedades/Horas Extras),
  mes y año. Marca/desmarca "Bloqueado" e impide cambios sobre el período.
-->
<template>
  <div class="bq-view">
    <div class="bq-cab">
      <div class="bq-cab-ico">🔒</div>
      <div class="bq-cab-tx"><h1>Bloqueos Varios</h1><p>Bloquear o liberar la carga de un período</p></div>
      <button class="bq-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="bq-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <BloqueosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/bloqueos" titulo="Asistente IA — Bloqueos Varios"
            subtitulo="Preguntá sobre los bloqueos"
            :sugerencias="['¿Para qué sirve bloquear un período?','¿Qué bloqueo: Novedades u Horas Extras?','¿Cómo libero un mes?','¿Qué pasa si está bloqueado?']"
            @close="modalIA = false" />

    <div class="bq-card">
      <div class="bq-tipo">
        <label :class="{ act: tipo === 'N' }"><input type="radio" value="N" v-model="tipo" /> Novedades</label>
        <label :class="{ act: tipo === 'H' }"><input type="radio" value="H" v-model="tipo" /> Horas Extras</label>
      </div>

      <div class="bq-periodo">
        <div class="bq-campo"><label>Mes</label><input v-model.number="mes" type="number" min="1" max="12" /></div>
        <div class="bq-campo"><label>Año</label><input v-model.number="anio" type="number" min="2000" max="2099" /></div>
      </div>

      <label class="bq-check" :class="{ on: bloqueado }">
        <input type="checkbox" v-model="bloqueado" />
        <span>{{ bloqueado ? '🔒 BLOQUEADO' : '🔓 No bloqueado' }}</span>
      </label>

      <div class="bq-acciones">
        <button class="bq-btn-grabar" :disabled="guardando || cargando" @click="grabar">💾 {{ guardando ? 'Grabando…' : 'Grabar' }}</button>
        <p v-if="msg" :class="['bq-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import api from '@/services/auth'
import BloqueosAyuda from '@/components/BloqueosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date()
const tipo = ref<'N' | 'H'>('N')
const mes = ref(hoy.getMonth() + 1)
const anio = ref(hoy.getFullYear())
const bloqueado = ref(false)
const cargando = ref(false); const guardando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

const consultar = async () => {
  if (!mes.value || !anio.value) return
  cargando.value = true; msg.value = ''
  try {
    const { data } = await api.get('/bloqueos/estado', { params: { tipo: tipo.value, mes: mes.value, anio: anio.value } })
    bloqueado.value = !!data.bloqueado
  } catch (e) { console.error(e) } finally { cargando.value = false }
}

const grabar = async () => {
  guardando.value = true
  try {
    await api.post('/bloqueos', { tipo: tipo.value, mes: mes.value, anio: anio.value, bloqueado: bloqueado.value })
    flash(`Período ${mes.value}/${anio.value} (${tipo.value === 'N' ? 'Novedades' : 'Horas Extras'}) ${bloqueado.value ? 'bloqueado' : 'liberado'}.`)
  } catch (e: any) {
    flash(e?.response?.data?.message ?? 'No se pudo grabar.', true)
  } finally { guardando.value = false }
}

// Al cambiar tipo/mes/año, refrescar el estado actual (como el FoxPro)
watch([tipo, mes, anio], consultar)
onMounted(consultar)
</script>

<style scoped>
.bq-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.bq-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.bq-cab-ico { font-size:28px; } .bq-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .bq-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.bq-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.bq-btn-ia:hover { filter:brightness(1.1); }
.bq-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.bq-btn-ayuda:hover { background:#f0faf4; }

.bq-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:22px 24px; max-width:460px; }
.bq-tipo { display:flex; gap:10px; justify-content:center; margin-bottom:18px; }
.bq-tipo label { flex:1; text-align:center; border:1px solid #d1d5db; border-radius:8px; padding:10px; cursor:pointer; font-size:14px; font-weight:600; color:#475569; display:flex; align-items:center; justify-content:center; gap:6px; }
.bq-tipo label.act { background:#1b4332; color:#fff; border-color:#1b4332; }
.bq-tipo input { display:none; }
.bq-periodo { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px; }
.bq-campo { display:flex; flex-direction:column; gap:5px; }
.bq-campo label { font-size:12px; font-weight:600; color:#374151; }
.bq-campo input { border:1px solid #d1d5db; border-radius:6px; padding:9px 10px; font-size:16px; font-weight:700; text-align:center; color:#1e293b; outline:none; }
.bq-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.bq-check { display:flex; align-items:center; justify-content:center; gap:10px; border:2px solid #e2e8f0; border-radius:10px; padding:14px; cursor:pointer; font-size:16px; font-weight:700; color:#64748b; margin-bottom:18px; transition:all .15s; }
.bq-check.on { border-color:#dc2626; background:#fef2f2; color:#b91c1c; }
.bq-check input { width:20px; height:20px; cursor:pointer; }
.bq-acciones { display:flex; flex-direction:column; align-items:center; gap:10px; }
.bq-btn-grabar { background:#22c55e; color:#0f172a; border:none; padding:12px 40px; border-radius:8px; cursor:pointer; font-size:15px; font-weight:800; }
.bq-btn-grabar:hover:not(:disabled){ background:#16a34a; color:#fff; } .bq-btn-grabar:disabled { background:#cbd5e1; }
.bq-msg { font-size:13px; padding:8px 14px; border-radius:6px; margin:0; text-align:center; }
.bq-msg.ok { background:#dcfce7; color:#166534; } .bq-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
