<!-- EmpleadosCostosInformeGralView.vue — Costos Laborales: Informe General (empleados_costos_informe_gral.scx). -->
<template>
  <div class="ig-view">
    <div class="ig-cab">
      <div class="ig-ico">📊</div>
      <div class="ig-tx"><h1>Costos Laborales — Informe General</h1><p>Costo laboral de todos los empleados activos</p></div>
      <button class="ig-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ig-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/costos-informe" titulo="Asistente IA — Informe General de Costos"
            subtitulo="Preguntá sobre el informe general de costos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué muestra el informe?','¿Cómo lo exporto?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ig-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ig-body">
      <div class="ig-periodo">
        <span class="ig-lbl">Calcular período</span>
        <label>Mes</label><input v-model.number="mes" type="number" min="1" max="12" class="ig-num" />
        <label>Año</label><input v-model.number="anio" type="number" min="2021" max="2200" class="ig-num ig-anio" />
        <button class="ig-aceptar" :disabled="cargando" @click="calcular">{{ cargando ? '⟳ Calculando…' : 'ACEPTAR' }}</button>
        <span v-if="filas.length" class="ig-cnt">{{ filas.length }} empleados</span>
      </div>

      <div v-if="filas.length" class="ig-grid-wrap">
        <table class="ig-tabla">
          <thead><tr>
            <th>Sector</th><th>Subsector</th><th class="r">Costo</th><th class="r">Previsión</th>
            <th>Empleado</th><th class="c">Cont.</th><th class="c">Legajo</th><th class="c">Código</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i">
              <td>{{ f.sector }}</td><td>{{ f.subsector }}</td><td class="r">{{ money(f.costo) }}</td><td class="r">{{ money(f.prevision) }}</td>
              <td>{{ f.nombre }}</td><td class="c">{{ f.contratista }}</td><td class="c">{{ f.legajo }}</td><td class="c">{{ f.codigo }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="ig-pie">
        <span class="ig-calc-lbl">COSTO TOTAL:</span>
        <span class="ig-calc">{{ money(total) }}</span>
        <span style="flex:1"></span>
        <button class="ig-excel" @click="exportarExcel">📊 Exportar Excel</button>
      </div>
      <div v-else-if="!cargando" class="ig-elija">Elegí el período y presioná ACEPTAR (el cálculo de todos los empleados puede demorar).</div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ig-ov" @click.self="ayuda = false">
        <div class="ig-help-md">
          <h3>❓ Ayuda — Informe General de Costos</h3>
          <ul>
            <li>Elegí el <b>período</b> (mes/año) y presioná <b>ACEPTAR</b>.</li>
            <li>Se calcula el costo laboral de <b>todos los empleados activos</b>, con su sector, subsector, costo y previsión de despidos.</li>
            <li>El cálculo recorre toda la nómina y puede <b>demorar unos segundos</b>.</li>
            <li><b>Exportar Excel</b> descarga el informe completo.</li>
          </ul>
          <div class="ig-pie"><span style="flex:1"></span><button class="ig-aceptar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import { guardarComo } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

const hoy = new Date()
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const filas = ref<any[]>([]); const total = ref(0); const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

async function calcular () {
  cargando.value = true; filas.value = []; total.value = 0
  try {
    const { data } = await api.get('/costos-informe', { params: { mes: mes.value, anio: anio.value } })
    filas.value = data.empleados ?? []; total.value = data.costo_total ?? 0
    if (!filas.value.length) flash('No hay empleados para el informe.', true)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el informe.', true) }
  finally { cargando.value = false }
}

function exportarExcel () {
  if (!filas.value.length) return
  const l = ['Sector;Subsector;Costo;Prevision;Empleado;Contratista;Legajo;Codigo']
  for (const f of filas.value) l.push([f.sector, f.subsector, f.costo, f.prevision, (f.nombre || '').replace(/;/g, ','), f.contratista, f.legajo, f.codigo].join(';'))
  l.push([';;', total.value, ';TOTAL'].join(';'))
  const blob = new Blob(['﻿' + l.join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  guardarComo(blob, `COSTO_LABORAL_GRAL_${mes.value}_${anio.value}.csv`)
}
</script>

<style scoped>
.ig-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.ig-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ig-ico { font-size:28px; } .ig-tx h1 { margin:0; font-size:18px; color:#1e293b; } .ig-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ig-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ig-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ig-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ig-msg.ok { background:#d1fae5; color:#065f46; } .ig-msg.err { background:#fee2e2; color:#991b1b; }
.ig-body { padding:16px 18px; display:flex; flex-direction:column; min-height:0; flex:1; }
.ig-periodo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.ig-lbl { font-size:13px; font-weight:700; color:#374151; } .ig-periodo label { font-size:13px; color:#475569; }
.ig-num { width:64px; border:1px solid #c8d8ea; border-radius:6px; padding:7px 8px; font-size:14px; font-weight:700; text-align:center; } .ig-anio { width:80px; }
.ig-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:8px 18px; cursor:pointer; font-weight:800; font-size:13px; } .ig-aceptar:disabled { opacity:.5; }
.ig-cnt { font-size:12px; color:#64748b; }
.ig-grid-wrap { margin-top:12px; overflow:auto; flex:1; border:1px solid #e2e8f0; border-radius:8px; }
.ig-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.ig-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; text-align:left; padding:7px 9px; font-size:11.5px; white-space:nowrap; } .ig-tabla th.r { text-align:right; } .ig-tabla th.c { text-align:center; }
.ig-tabla td { border-bottom:1px solid #eef2f7; padding:5px 9px; color:#1e293b; white-space:nowrap; } .ig-tabla td.r { text-align:right; } .ig-tabla td.c { text-align:center; }
.ig-tabla tbody tr:nth-child(even) td { background:#f6faf7; }
.ig-pie { display:flex; align-items:center; gap:10px; margin-top:12px; }
.ig-calc-lbl { font-size:15px; font-weight:800; color:#14532d; } .ig-calc { font-size:20px; font-weight:800; color:#1e293b; }
.ig-excel { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:800; font-size:13px; }
.ig-elija { text-align:center; color:#94a3b8; padding:28px; }
.ig-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ig-help-md { background:#fff; border-radius:14px; padding:22px; width:min(540px,94vw); } .ig-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ig-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
