<!--
  ValoresView.vue — Pantalla de Valores (parámetros varios). No es un ABM:
  se consulta y se actualiza un único conjunto de valores.
-->
<template>
  <div class="vl-view">
    <div class="vl-cab">
      <div class="vl-cab-ico">💱</div>
      <div class="vl-cab-tx"><h1>Valores</h1><p>Parámetros generales del sistema</p></div>
      <button class="vl-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vl-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ValoresAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/valores" titulo="Asistente IA — Valores"
            subtitulo="Preguntá sobre estos parámetros"
            :sugerencias="['¿Qué son estos valores?','¿Para qué sirven las asignaciones por hijo?','¿Qué es el Decreto 1273/02?','¿Cómo guardo los cambios?']"
            @close="modalIA = false" />

    <div class="vl-card" v-enter-next>
      <div v-if="cargando" class="vl-vacio">⟳ Cargando…</div>
      <template v-else>
        <div class="vl-seccion">Datos depósito anterior</div>
        <div class="vl-grid">
          <div class="vl-campo vl-col2"><label>Depositado Banco</label><input v-model="f.deposito_banco" type="text" maxlength="50" /></div>
          <div class="vl-campo"><label>Fecha Depósito</label><input v-model="f.fecha_deposito" type="date" /></div>
          <div class="vl-campo vl-col3"><label>Observación</label><input v-model="f.observacion" type="text" maxlength="50" /></div>
        </div>

        <div class="vl-seccion">Asignaciones familiares por hijo</div>
        <div class="vl-asig">
          <div v-for="i in 4" :key="i" class="vl-asig-row">
            <span>Promedio último semestre hasta</span>
            <input v-model.number="(f as any)['ha'+i]" type="number" min="0" class="vl-in-num" />
            <span>pesos:</span>
            <input v-model.number="(f as any)['hi'+i]" type="number" step="0.01" min="0" class="vl-in-num" />
          </div>
        </div>

        <div class="vl-seccion">Datos fijos DGI SIJP</div>
        <div class="vl-grid">
          <div class="vl-campo"><label>Porcentaje de Reducción (%)</label><input v-model.number="f.porc_reduccion" type="number" step="0.01" /></div>
          <div class="vl-campo"><label>Importe Adicional O.S. (%)</label><input v-model.number="f.adicional_os" type="number" step="0.01" /></div>
        </div>

        <div class="vl-seccion">Decreto 1273/02</div>
        <div class="vl-grid">
          <div class="vl-campo"><label>Decreto 1273/02</label><input v-model.number="f.decreto" type="number" step="0.01" /></div>
          <div class="vl-campo"><label>Obra Social Decreto (%)</label><input v-model.number="f.os_decreto" type="number" step="0.01" /></div>
          <div class="vl-campo"><label>INSSJP Decreto (%)</label><input v-model.number="f.inssjp_decreto" type="number" step="0.01" /></div>
        </div>

        <div class="vl-acciones">
          <button class="vl-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar cambios' }}</button>
          <p v-if="msg" :class="['vl-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import api from '@/services/auth'
import ValoresAyuda from '@/components/ValoresAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const cargando = ref(true); const guardando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

const f = reactive<any>({
  deposito_banco: '', fecha_deposito: '', observacion: '',
  ha1: 0, ha2: 0, ha3: 0, ha4: 0, hi1: 0, hi2: 0, hi3: 0, hi4: 0,
  porc_reduccion: 0, adicional_os: 0, decreto: 0, os_decreto: 0, inssjp_decreto: 0,
})

onMounted(async () => {
  try { Object.assign(f, (await api.get('/valores')).data) }
  catch (e) { console.error(e) } finally { cargando.value = false }
})

const guardar = async () => {
  guardando.value = true
  try { await api.put('/valores', { ...f }); flash('Valores guardados') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}
</script>

<style scoped>
.vl-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vl-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vl-cab-ico { font-size:28px; } .vl-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; } .vl-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vl-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vl-btn-ia:hover { filter:brightness(1.1); }
.vl-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vl-btn-ayuda:hover { background:#f0faf4; }

.vl-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:18px 20px; max-width:720px; }
.vl-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.vl-seccion { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.6px; margin:16px 0 12px; padding-bottom:5px; border-bottom:1px solid #eef2f7; }
.vl-seccion:first-of-type { margin-top:0; }
.vl-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.vl-col2 { grid-column:span 2; } .vl-col3 { grid-column:1 / -1; }
.vl-campo { display:flex; flex-direction:column; gap:5px; }
.vl-campo label { font-size:12px; font-weight:600; color:#374151; }
.vl-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.vl-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.vl-asig { display:flex; flex-direction:column; gap:8px; }
.vl-asig-row { display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; flex-wrap:wrap; }
.vl-in-num { width:100px; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:14px; text-align:right; outline:none; }
.vl-in-num:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.vl-acciones { display:flex; align-items:center; gap:12px; margin-top:20px; padding-top:16px; border-top:1px solid #f1f5f9; }
.vl-btn-guardar { background:#16a34a; color:#fff; border:none; padding:11px 22px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.vl-btn-guardar:hover:not(:disabled){ background:#15803d; } .vl-btn-guardar:disabled { background:#cbd5e1; }
.vl-msg { font-size:13px; padding:7px 12px; border-radius:6px; margin:0; }
.vl-msg.ok { background:#dcfce7; color:#166534; } .vl-msg.err { background:#fee2e2; color:#b91c1c; }
@media (max-width:560px){ .vl-grid{ grid-template-columns:1fr; } .vl-col2,.vl-col3{ grid-column:auto; } }
</style>
