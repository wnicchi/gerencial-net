<!-- HorasExtrasEmpleadoView.vue — Consulta de horas extras diarias de un empleado. -->
<template>
  <div class="he-view">
    <div class="he-cab">
      <div class="he-cab-ico">🔍</div>
      <div class="he-cab-tx"><h1>Horas Extras Diarias de un Empleado</h1><p>Consulta del detalle diario en un rango de fechas</p></div>
      <button class="he-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="he-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <HorasExtrasEmpleadoAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/horas-extras-diarias" titulo="Asistente IA — Horas Extras por Empleado"
            subtitulo="Preguntá sobre las horas extras del empleado"
            :sugerencias="['¿Cómo se calcula el tiempo extra?','¿Qué muestra el total de abajo?','¿Por qué no aparece un día?']"
            @close="modalIA = false" />

    <div class="he-filtros">
      <label class="he-busc-wrap">Empleado
        <div class="he-buscador">
          <input v-model="busqueda" type="text" placeholder="Buscar por código, legajo o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="he-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">
              <b>{{ (r.PER_NOM || '').trim() }}</b>
              <span>Cód. {{ r.PER_COD }} · Leg. {{ (r.PER_LEG || '').toString().trim() }}</span>
            </li>
          </ul>
        </div>
      </label>
      <label>Desde <input v-model="f.fecha1" type="date" /></label>
      <label>al <input v-model="f.fecha2" type="date" /></label>
      <button class="he-btn-aceptar" :disabled="cargando || !f.codigo" @click="aceptar">{{ cargando ? '⟳ Analizando…' : 'ACEPTAR' }}</button>
    </div>
    <p v-if="f.codigo" class="he-emp-sel">Empleado: <b>{{ f.codigo }} — {{ nombre }}</b></p>

    <div v-if="rows.length" class="he-grid-wrap">
      <table class="he-grid">
        <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Tiempo Extra</th><th>Extra 50%</th><th>Extra 100%</th><th>Adic. Nocturna</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="i">
            <td class="he-num">{{ r.fecha }}</td>
            <td class="he-num">{{ r.entra }}</td>
            <td class="he-num">{{ r.sale }}</td>
            <td class="he-num he-ext">{{ r.horas_extra }}</td>
            <td class="he-num">{{ hhmm(r.m_est50) }}</td>
            <td class="he-num">{{ hhmm(r.m_est100) }}</td>
            <td class="he-num">{{ hhmm(r.m_estnoc50) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!cargando && consultado" class="he-vacio">No hay horas extras para el empleado en ese rango.</div>

    <p v-if="total" class="he-total">{{ total }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import HorasExtrasEmpleadoAyuda from '@/components/HorasExtrasEmpleadoAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)

interface Row { fecha: string; entra: string; sale: string; horas_extra: string; m_est50: number; m_est100: number; m_estnoc50: number; m_calculada: number }
const f = ref({ codigo: 0, fecha1: '', fecha2: '' })
const rows = ref<Row[]>([]); const nombre = ref(''); const total = ref('')
const cargando = ref(false); const consultado = ref(false)

// Buscador de empleados
const busqueda = ref(''); const resultados = ref<any[]>([])
let debounce: any = null
const buscar = () => {
  clearTimeout(debounce)
  const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  debounce = setTimeout(async () => {
    try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] }
    catch (e) { console.error(e); resultados.value = [] }
  }, 250)
}
const seleccionar = (r: any) => {
  resultados.value = []
  f.value.codigo = Number(r.PER_COD)
  nombre.value = (r.PER_NOM || '').trim()
  busqueda.value = `${r.PER_COD} — ${nombre.value}`
}

const hhmm = (n: number): string => {
  const v = Math.max(0, Math.trunc(n))
  return `${String(Math.trunc(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}`
}

const aceptar = async () => {
  if (!f.value.codigo || !f.value.fecha1 || !f.value.fecha2) { alert('Faltan datos.'); return }
  cargando.value = true; consultado.value = true
  try {
    const { data } = await api.get('/horas-extras-diarias/empleado', { params: { codigo: f.value.codigo, fecha1: f.value.fecha1, fecha2: f.value.fecha2 } })
    nombre.value = data.nombre; rows.value = data.rows; total.value = data.total
  } catch (e: any) {
    rows.value = []; total.value = ''; nombre.value = ''
    alert('⚠️ ' + (e?.response?.data?.message ?? 'No se pudo consultar.'))
  } finally { cargando.value = false }
}
</script>

<style scoped>
.he-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.he-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.he-cab-ico { font-size:28px; } .he-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .he-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.he-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.he-btn-ia:hover { filter:brightness(1.1); }
.he-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.he-btn-ayuda:hover { background:#f0faf4; }
.he-filtros { display:flex; flex-wrap:wrap; align-items:end; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.he-filtros label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.he-filtros input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.he-filtros input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.he-busc-wrap { position:relative; }
.he-buscador { position:relative; }
.he-buscador input { width:min(320px,70vw); }
.he-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:260px; overflow:auto; }
.he-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.he-result li:hover { background:#f0faf4; }
.he-result li b { font-size:13px; color:#1e293b; font-weight:600; }
.he-result li span { font-size:11px; color:#6b7280; }
.he-emp-sel { margin:10px 18px 0; padding:8px 12px; background:#f0faf4; border:1px solid #c3e6cb; border-radius:6px; font-size:13px; color:#1b4332; }
.he-btn-aceptar { background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.he-btn-aceptar:hover:not(:disabled){ background:#15803d; } .he-btn-aceptar:disabled { background:#cbd5e1; }
.he-grid-wrap { margin:14px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.he-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.he-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 14px; font-size:12px; }
.he-grid td { padding:5px 14px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.he-grid tr:nth-child(even) td { background:#f8fafc; }
.he-num { text-align:center; font-variant-numeric:tabular-nums; }
.he-ext { font-weight:700; color:#1b4332; }
.he-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.he-total { margin:0 18px 18px; padding:12px 16px; background:#1b4332; color:#fff; border-radius:8px; font-size:14px; font-weight:700; text-align:center; }
</style>
