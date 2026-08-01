<!-- VacacionesDefinicionView.vue — Vacaciones Definición de Antigüedad (vacaciones_definicion). -->
<template>
  <div class="vd-view">
    <div class="vd-cab">
      <div class="vd-cab-ico">⚖️</div>
      <div class="vd-cab-tx"><h1>Vacaciones - Definición de Antigüedad</h1><p>Tramos de antigüedad y los días de vacaciones que corresponden</p></div>
      <button class="vd-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vd-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesDefinicionAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-definicion" titulo="Asistente IA — Definición de Antigüedad"
            subtitulo="Preguntá sobre los tramos de antigüedad"
            :sugerencias="['¿Para qué sirve esta pantalla?','¿Cómo agrego un tramo?','¿Cómo se calculan los días?']"
            @close="modalIA = false" />

    <div class="vd-card">
      <div class="vd-grid-wrap">
        <table class="vd-tabla">
          <thead><tr>
            <th>OK</th><th>Año Ini.</th><th>Mes Ini.</th><th>Año Fin.</th><th>Mes Fin.</th><th>Días Vacaciones</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i">
              <td class="c"><input type="checkbox" v-model="marcados" :value="i" /></td>
              <td :class="{ hl: f.anioIni }">{{ f.anioIni }}</td>
              <td :class="{ hl: f.mesIni }">{{ f.mesIni }}</td>
              <td :class="{ hl: f.anioFin }">{{ f.anioFin }}</td>
              <td :class="{ hl: f.mesFin }">{{ f.mesFin }}</td>
              <td class="hl">{{ f.dias }}</td>
            </tr>
            <tr v-if="!filas.length"><td colspan="6" class="vacio">No hay tramos definidos.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="vd-panel">
        <button class="vd-btn-del" :disabled="!marcados.length || procesando" @click="eliminar">🗑 Eliminar</button>
        <div class="vd-form">
          <div class="vd-row">
            <span class="vd-lbl">Antigüedad MAYOR A:</span>
            <label class="vd-mini">Años <input v-model.number="form.anioIni" type="number" min="0" class="vd-inp" /></label>
            <label class="vd-mini">Meses <input v-model.number="form.mesIni" type="number" min="0" class="vd-inp" /></label>
          </div>
          <div class="vd-row">
            <span class="vd-lbl">MENOR A:</span>
            <label class="vd-mini">Años <input v-model.number="form.anioFin" type="number" min="0" class="vd-inp" /></label>
            <label class="vd-mini">Meses <input v-model.number="form.mesFin" type="number" min="0" class="vd-inp" /></label>
          </div>
          <div class="vd-row">
            <span class="vd-lbl">DÍAS DE VACACIONES CON DERECHO:</span>
            <input v-model.number="form.dias" type="number" min="0" class="vd-inp" />
            <button class="vd-btn-ok" :disabled="procesando" @click="agregar">Agregar</button>
          </div>
        </div>
      </div>
      <p v-if="msg" :class="['vd-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import VacacionesDefinicionAyuda from '@/components/VacacionesDefinicionAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Def { anioIni: number; mesIni: number; anioFin: number; mesFin: number; dias: number }
const filas = ref<Def[]>([]); const marcados = ref<number[]>([])
const form = reactive({ anioIni: 0, mesIni: 0, anioFin: 0, mesFin: 0, dias: 0 })
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

const cargar = async () => {
  try { filas.value = (await api.get('/vacaciones/definicion')).data.definiciones ?? []; marcados.value = [] }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la tabla.', true) }
}
onMounted(cargar)

const agregar = async () => {
  if (!form.dias || form.dias <= 0) return flash('Indicá los días de vacaciones (mayor a cero).', true)
  if (!confirm('¿Agrega la relación de días de vacaciones?')) return
  procesando.value = true
  try {
    const { data } = await api.post('/vacaciones/definicion', { ...form })
    flash(data.message)
    form.anioIni = 0; form.mesIni = 0; form.anioFin = 0; form.mesFin = 0; form.dias = 0
    await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar.', true) }
  finally { procesando.value = false }
}

const eliminar = async () => {
  if (!marcados.value.length) return
  if (!confirm(`¿Elimina ${marcados.value.length} tramo(s) seleccionado(s)?`)) return
  procesando.value = true
  try {
    const filasSel = marcados.value.map(i => filas.value[i]).map(f => ({ anioIni: f.anioIni, mesIni: f.mesIni, anioFin: f.anioFin, mesFin: f.mesFin }))
    const { data } = await api.post('/vacaciones/definicion/eliminar', { filas: filasSel })
    flash(data.message); await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.vd-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vd-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vd-cab-ico { font-size:28px; } .vd-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vd-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vd-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vd-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vd-card { margin:16px 18px; max-width:680px; display:flex; flex-direction:column; gap:14px; }
.vd-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:46vh; }
.vd-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.vd-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 10px; text-align:center; white-space:nowrap; }
.vd-tabla td { padding:5px 10px; border-bottom:1px solid #f1f5f9; text-align:center; color:#1e293b; } .vd-tabla td.c { text-align:center; } .vd-tabla td.hl { background:#fff7cc; font-weight:600; }
.vd-tabla .vacio { color:#94a3b8; padding:16px; }
.vd-panel { display:flex; gap:16px; align-items:flex-start; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; }
.vd-btn-del { background:#ef4444; color:#fff; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; } .vd-btn-del:disabled { background:#fca5a5; cursor:default; }
.vd-form { display:flex; flex-direction:column; gap:10px; flex:1; }
.vd-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.vd-lbl { font-size:12.5px; font-weight:700; color:#1b4332; }
.vd-mini { display:flex; align-items:center; gap:5px; font-size:12px; color:#475569; }
.vd-inp { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; width:70px; text-align:right; }
.vd-btn-ok { background:#16a34a; color:#fff; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; } .vd-btn-ok:disabled { background:#cbd5e1; }
.vd-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:660px; } .vd-msg.ok { background:#dcfce7; color:#166534; } .vd-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
