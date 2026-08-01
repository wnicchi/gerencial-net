<!-- ValesFondoFijoView.vue — Consulta del Fondo Fijo. -->
<template>
  <div class="ff-view">
    <div class="ff-cab">
      <div class="ff-cab-ico">🔍</div>
      <div class="ff-cab-tx"><h1>Consulta Fondo Fijo</h1><p>Movimientos y saldos del fondo de Recursos Humanos</p></div>
      <button class="ff-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ff-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ValesConsultarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vales" titulo="Asistente IA — Fondo Fijo" subtitulo="Preguntá sobre el fondo fijo"
            :sugerencias="['¿Qué es el saldo del fondo?','¿Qué significa Debe y Haber?','¿Cómo se calcula el saldo final?']" @close="modalIA = false" />

    <div class="ff-filtros">
      <div class="ff-radios">
        <label><input v-model.number="f.fondo" type="radio" :value="1" /> SILCAR</label>
        <label><input v-model.number="f.fondo" type="radio" :value="2" /> LOGÍSTICA</label>
      </div>
      <label>Desde el <input v-model="f.fecha1" type="date" /></label>
      <label>al <input v-model="f.fecha2" type="date" /></label>
      <button class="ff-btn-aceptar" :disabled="cargando" @click="aceptar">{{ cargando ? '⟳…' : 'Aceptar' }}</button>
    </div>

    <div v-if="data" class="ff-cuerpo">
      <div class="ff-titulo">FONDO FIJO DE RECURSOS HUMANOS</div>
      <div class="ff-grid-wrap">
        <table class="ff-grid">
          <thead><tr><th>Fecha</th><th>Detalle</th><th style="text-align:right">Debe</th><th style="text-align:right">Haber</th><th>Terminal</th></tr></thead>
          <tbody>
            <tr v-for="(m, i) in data.movimientos" :key="i">
              <td>{{ m.fecha }}</td><td>{{ m.detalle }}</td>
              <td class="ff-imp">{{ m.debe ? money(m.debe) : '' }}</td><td class="ff-imp">{{ m.haber ? money(m.haber) : '' }}</td><td>{{ m.terminal }}</td>
            </tr>
            <tr v-if="!data.movimientos.length"><td colspan="5" class="ff-vacio">Sin movimientos en el período.</td></tr>
          </tbody>
          <tfoot>
            <tr><td colspan="2" style="text-align:right">TOTALES :</td><td class="ff-imp">{{ money(data.total_debe) }}</td><td class="ff-imp">{{ money(data.total_haber) }}</td><td></td></tr>
          </tfoot>
        </table>
      </div>

      <div class="ff-saldos">
        <div class="ff-saldo-tit">FONDO R.R.H.H.</div>
        <div class="ff-saldo-row">
          <div class="ff-saldo"><span>SALDO ANTERIOR</span><b :class="{ neg: data.saldo_anterior < 0 }">{{ money(data.saldo_anterior) }}</b></div>
          <div class="ff-saldo"><span>TOTAL PERÍODO</span><b :class="{ neg: data.total_periodo < 0 }">{{ money(data.total_periodo) }}</b></div>
          <div class="ff-saldo"><span>SALDO FINAL</span><b :class="{ neg: data.saldo_final < 0 }">{{ money(data.saldo_final) }}</b></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import ValesConsultarAyuda from '@/components/ValesConsultarAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date().toISOString().slice(0, 10)
const f = ref({ fondo: 1, fecha1: hoy, fecha2: hoy })
const data = ref<any>(null); const cargando = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const aceptar = async () => {
  cargando.value = true
  try { data.value = (await api.get('/vales/fondo-fijo', { params: { fondo: f.value.fondo, fecha1: f.value.fecha1, fecha2: f.value.fecha2 } })).data }
  catch (e) { console.error(e) }
  finally { cargando.value = false }
}
aceptar()
</script>

<style scoped>
.ff-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ff-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ff-cab-ico { font-size:28px; } .ff-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ff-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ff-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ff-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ff-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:16px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ff-radios { display:flex; gap:14px; } .ff-radios label, .ff-filtros label { display:flex; align-items:center; gap:5px; font-size:13px; font-weight:600; color:#374151; cursor:pointer; }
.ff-filtros input[type=date] { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; }
.ff-btn-aceptar { background:#16a34a; color:#fff; border:none; padding:7px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ff-cuerpo { padding:14px 18px; }
.ff-titulo { font-size:13px; font-weight:700; color:#1b4332; margin-bottom:8px; }
.ff-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:48vh; }
.ff-grid { width:100%; border-collapse:collapse; font-size:13px; }
.ff-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 12px; font-size:12px; }
.ff-grid td { padding:5px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ff-grid tbody tr:nth-child(even) td { background:#f8fafc; }
.ff-grid tfoot td { background:#e5e7eb; font-weight:700; padding:7px 12px; }
.ff-imp { text-align:right; font-variant-numeric:tabular-nums; }
.ff-vacio { text-align:center; color:#9ca3af; padding:20px; }
.ff-saldos { margin-top:16px; background:#1b4332; border-radius:10px; padding:16px; }
.ff-saldo-tit { color:#86efac; font-size:13px; font-weight:700; letter-spacing:.5px; margin-bottom:10px; }
.ff-saldo-row { display:flex; flex-wrap:wrap; gap:14px; }
.ff-saldo { flex:1; min-width:170px; background:rgba(255,255,255,.08); border-radius:8px; padding:12px 16px; display:flex; flex-direction:column; gap:4px; }
.ff-saldo span { color:#cbd5e1; font-size:11px; font-weight:600; letter-spacing:.3px; }
.ff-saldo b { color:#fff; font-size:20px; font-variant-numeric:tabular-nums; } .ff-saldo b.neg { color:#fca5a5; }
</style>
