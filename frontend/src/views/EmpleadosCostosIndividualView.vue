<!-- EmpleadosCostosIndividualView.vue — Costos Laborales: Calcular Costo Individual (empleados_costos_individual.scx). -->
<template>
  <div class="ci-view">
    <div class="ci-cab">
      <div class="ci-ico">👤</div>
      <div class="ci-tx"><h1>Cálculo de Costo Laboral Individual</h1><p>Costo laboral de un empleado por período</p></div>
      <button class="ci-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ci-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="ci-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/costos-individual" titulo="Asistente IA — Costo Individual"
            subtitulo="Preguntá sobre el cálculo de costo laboral"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué rubros calcula?','¿Cómo cambio el período?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ci-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ci-body">
      <!-- Empleado -->
      <div class="ci-emp">
        <div class="ci-emp-datos">
          <label>Empleado</label>
          <div class="ci-emp-row">
            <EmpleadoInput :codigo="emp || 0" :nombre="empNombre" @select="onLupaEmp" />
          </div>
        </div>
        <div class="ci-foto"><img v-if="empFoto" :src="empFoto" /><div v-else class="ci-foto-ph">👤</div></div>
      </div>

      <!-- Datos del convenio -->
      <div v-if="datos" class="ci-info">
        <div><span>Convenio</span><b>{{ datos.convenio || '—' }}</b></div>
        <div><span>Categoría</span><b>{{ datos.categoria || '—' }}</b></div>
        <div><span>Antigüedad</span><b>{{ datos.antiguedad }}</b></div>
        <div><span>Días de vacaciones</span><b>{{ datos.dias_vacaciones }}</b></div>
      </div>

      <!-- Período -->
      <div class="ci-periodo">
        <span class="ci-lbl">Calcular período</span>
        <label>Mes</label><input v-model.number="mes" type="number" min="1" max="12" class="ci-num" />
        <label>Año</label><input v-model.number="anio" type="number" min="2021" max="2200" class="ci-num ci-anio" />
        <button class="ci-aceptar" :disabled="cargando" @click="calcular">{{ cargando ? '⟳…' : 'ACEPTAR' }}</button>
      </div>

      <template v-if="datos">
        <table class="ci-tabla">
          <thead><tr><th style="width:150px">Rubro</th><th>Detalle</th><th style="width:150px">Importe</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in calculo" :key="i" :class="'r-' + rubroClase(r.rubro)">
              <td class="rub">{{ r.rubro }}</td><td>{{ r.detalle }}</td><td class="imp">{{ money(r.importe) }}</td>
            </tr>
            <tr v-if="!calculo.length"><td colspan="3" class="vacio">Sin datos para calcular.</td></tr>
          </tbody>
        </table>

        <div class="ci-pie">
          <span class="ci-calc-lbl">CÁLCULO:</span>
          <span class="ci-calc">{{ money(total) }}</span>
          <span style="flex:1"></span>
          <button class="ci-excel" :disabled="!calculo.length" @click="exportarExcel">📊 Exportar Excel</button>
        </div>
      </template>
      <div v-else-if="!cargando" class="ci-elija">Ingrese un empleado y el período, y presioná ACEPTAR.</div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ci-ov" @click.self="ayuda = false">
        <div class="ci-help-md">
          <h3>❓ Ayuda — Costo Laboral Individual</h3>
          <ul>
            <li>Elegí el <b>empleado</b> (por código o con la 🔍) y el <b>período</b> (mes/año), y presioná <b>ACEPTAR</b>.</li>
            <li>Se muestran su <b>convenio, categoría, antigüedad y días de vacaciones</b>, y el desglose por rubro.</li>
            <li>El cálculo depende del <b>convenio</b>: remunerativos (sueldo, aguinaldo, vacaciones), cargas sociales, previsión de despidos y gastos varios (costos fijos del período).</li>
            <li><b>Exportar Excel</b> descarga el detalle del cálculo.</li>
          </ul>
          <div class="ci-pie"><span style="flex:1"></span><button class="ci-aceptar" @click="ayuda = false">Cerrar</button></div>
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
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const hoy = new Date()
const emp = ref<number | null>(null); const empNombre = ref(''); const empFoto = ref('')
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const datos = ref<any>(null); const calculo = ref<any[]>([]); const total = ref(0)
const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const rubroClase = (r: string) => ({ 'REMUNERATIVOS': 'rem', 'NO REMUNERATIVOS': 'norem', 'CARGAS': 'car', 'PREVISION': 'pre', 'GASTOS VARIOS': 'gas' } as any)[r] ?? 'gas'
const onLupaEmp = (r: any) => { emp.value = r.cod; calcular() }

async function calcular () {
  if (!emp.value || emp.value <= 0) { flash('Ingrese el empleado.', true); return }
  cargando.value = true; datos.value = null; calculo.value = []; total.value = 0
  try {
    const { data } = await api.get('/costos-individual', { params: { empleado: emp.value, mes: mes.value, anio: anio.value } })
    datos.value = data.empleado; empNombre.value = data.empleado.nombre
    calculo.value = data.calculo ?? []; total.value = data.total ?? 0
    try { const f = await api.get(`/empleados/${emp.value}/foto`); empFoto.value = f.data?.foto || '' } catch { /* */ }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo calcular el costo.', true); empNombre.value = ''; empFoto.value = '' }
  finally { cargando.value = false }
}

function exportarExcel () {
  if (!calculo.value.length) return
  const filas = ['Rubro;Detalle;Importe']
  for (const r of calculo.value) filas.push([r.rubro, (r.detalle || '').replace(/;/g, ','), r.importe].join(';'))
  filas.push([';TOTAL', '', total.value].join(';'))
  const blob = new Blob(['﻿' + filas.join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  guardarComo(blob, `COSTO_EMPLEADO_${emp.value}.csv`)
}

function reset () {
  emp.value = null; empNombre.value = ''; empFoto.value = ''; datos.value = null; calculo.value = []; total.value = 0
  mes.value = hoy.getMonth() + 1; anio.value = hoy.getFullYear()
}
</script>

<style scoped>
.ci-view { display:flex; flex-direction:column; min-height:100%; }
.ci-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ci-ico { font-size:28px; } .ci-tx h1 { margin:0; font-size:18px; color:#1e293b; } .ci-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ci-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ci-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ci-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ci-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ci-msg.ok { background:#d1fae5; color:#065f46; } .ci-msg.err { background:#fee2e2; color:#991b1b; }
.ci-body { padding:16px 18px; max-width:720px; }
.ci-emp { display:flex; gap:16px; align-items:flex-start; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fafdff; }
.ci-emp-datos { flex:1; } .ci-emp-datos label { font-size:12px; font-weight:700; color:#374151; }
.ci-emp-row { display:flex; gap:10px; margin-top:5px; }
.ci-emp-row input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:15px; color:#1e293b; }
.ci-emp-row input[type=number] { width:110px; font-weight:800; } .ci-nom { flex:1; background:#f1f5f9; }
.ci-lupa { background:#394959; color:#fff; border:none; padding:9px 13px; border-radius:7px; cursor:pointer; font-size:14px; }
.ci-foto { width:96px; height:78px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#eef2f7; display:flex; align-items:center; justify-content:center; }
.ci-foto img { width:100%; height:100%; object-fit:cover; } .ci-foto-ph { font-size:32px; color:#94a3b8; }
.ci-info { display:grid; grid-template-columns:1fr 1fr; gap:8px 18px; margin-top:14px; border:2px solid #93c5fd; border-radius:10px; padding:12px 16px; background:#eff6ff; }
.ci-info span { font-size:11px; color:#6b7280; display:block; } .ci-info b { font-size:14px; color:#1e293b; }
.ci-periodo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:14px; }
.ci-lbl { font-size:13px; font-weight:700; color:#374151; } .ci-periodo label { font-size:13px; color:#475569; }
.ci-num { width:64px; border:1px solid #c8d8ea; border-radius:6px; padding:7px 8px; font-size:14px; font-weight:700; text-align:center; } .ci-anio { width:80px; }
.ci-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:8px 18px; cursor:pointer; font-weight:800; font-size:13px; } .ci-aceptar:disabled { opacity:.5; }
.ci-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:14px; }
.ci-tabla th { background:#1b4332; color:#fff; text-align:left; padding:7px 10px; font-size:12px; } .ci-tabla th:last-child { text-align:right; }
.ci-tabla td { border-bottom:1px solid #eef2f7; padding:5px 10px; color:#1e293b; } .ci-tabla td.rub { font-weight:700; } .ci-tabla td.imp { text-align:right; font-weight:700; }
.ci-tabla tr.r-rem td.rub { color:#1d4ed8; } .ci-tabla tr.r-norem td.rub { color:#7c3aed; } .ci-tabla tr.r-car td.rub { color:#b45309; } .ci-tabla tr.r-pre td.rub { color:#be123c; } .ci-tabla tr.r-gas td.rub { color:#0f766e; }
.ci-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; }
.ci-pie { display:flex; align-items:center; gap:10px; margin-top:12px; }
.ci-calc-lbl { font-size:15px; font-weight:800; color:#14532d; } .ci-calc { font-size:20px; font-weight:800; color:#1e293b; }
.ci-excel { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:800; font-size:13px; } .ci-excel:disabled { opacity:.5; }
.ci-elija { text-align:center; color:#94a3b8; padding:28px; }
.ci-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ci-help-md { background:#fff; border-radius:14px; padding:22px; width:min(540px,94vw); } .ci-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ci-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
