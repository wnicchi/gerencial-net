<!-- EmpleadosCostosGrupalesView.vue — Costos Laborales: Costo Grupal (empleados_costos_grupales.scx). -->
<template>
  <div class="cg-view">
    <div class="cg-cab">
      <div class="cg-ico">👥</div>
      <div class="cg-tx"><h1>Cálculo de Costos Laborales Grupales</h1><p>Costo laboral de un grupo de empleados</p></div>
      <button class="cg-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cg-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="cg-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/costos-grupales" titulo="Asistente IA — Costo Grupal"
            subtitulo="Preguntá sobre el costo laboral grupal"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego empleados?','¿Cómo calculo el total?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['cg-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="cg-body">
      <div class="cg-periodo">
        <span class="cg-lbl">Calcular período</span>
        <label>Mes</label><input v-model.number="mes" type="number" min="1" max="12" class="cg-num" />
        <label>Año</label><input v-model.number="anio" type="number" min="2021" max="2200" class="cg-num cg-anio" />
        <button class="cg-aceptar" :disabled="cargando" @click="recalcular">{{ cargando ? '⟳…' : 'ACEPTAR' }}</button>
      </div>

      <table class="cg-tabla">
        <thead><tr><th style="width:40px">Ok</th><th style="width:90px">Legajo</th><th>Nombre</th><th style="width:150px">Costo</th></tr></thead>
        <tbody>
          <tr v-for="g in grupo" :key="g.codigo" :class="{ sel: g.sel }">
            <td class="c"><input v-model="g.sel" type="checkbox" /></td>
            <td>{{ g.legajo }}</td><td>{{ g.nombre }}</td><td class="imp">{{ money(g.costo) }}</td>
          </tr>
          <tr v-if="!grupo.length"><td colspan="4" class="vacio">Agregá empleados por código o por sector / subsector.</td></tr>
        </tbody>
      </table>

      <div class="cg-elim">
        <button class="cg-eliminar" :disabled="!grupo.some(g => g.sel)" @click="eliminarSeleccion">🗑️ Eliminar de la lista</button>
      </div>

      <!-- Agregar por empleado -->
      <div class="cg-add">
        <label>Agregar empleado</label>
        <EmpleadoInput :codigo="empCod || 0" @select="onLupaEmp" />
      </div>

      <!-- Agregar por sector / subsector -->
      <div class="cg-add sect">
        <label>Agregar sector / subsector</label>
        <select v-model.number="sectorSel"><option :value="0">Sector…</option><option v-for="s in sectores" :key="s.cod" :value="s.cod">{{ s.des }}</option></select>
        <select v-model.number="subsectorSel"><option :value="0">Subsector…</option><option v-for="s in subsectores" :key="s.cod" :value="s.cod">{{ s.des }}</option></select>
        <button class="cg-btn-add" :disabled="!sectorSel || !subsectorSel" @click="agregarSector">Agregar</button>
      </div>

      <div class="cg-pie">
        <span class="cg-calc-lbl">CÁLCULO:</span>
        <span class="cg-calc">{{ money(total) }}</span>
        <span style="flex:1"></span>
        <button class="cg-excel" :disabled="!grupo.length" @click="exportarExcel">📊 Exportar Excel</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="cg-ov" @click.self="ayuda = false">
        <div class="cg-help-md">
          <h3>❓ Ayuda — Costo Grupal</h3>
          <ul>
            <li>Elegí el <b>período</b> (mes/año).</li>
            <li>Sumá empleados al grupo por <b>código</b> (o con la 🔍) o por <b>sector / subsector</b> (agrega todos los activos).</li>
            <li>Presioná <b>ACEPTAR</b> para recalcular el costo de cada empleado y el total del grupo.</li>
            <li>Marcá y usá <b>Eliminar de la lista</b> para sacar empleados.</li>
            <li><b>Exportar Excel</b> descarga el detalle (legajo, nombre, costo, código).</li>
          </ul>
          <div class="cg-pie"><span style="flex:1"></span><button class="cg-aceptar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import { guardarComo } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const hoy = new Date()
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const grupo = ref<any[]>([]); const total = ref(0)
const empCod = ref<number | null>(null)
const sectores = ref<any[]>([]); const subsectores = ref<any[]>([]); const sectorSel = ref(0); const subsectorSel = ref(0)
const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

onMounted(async () => {
  try { const { data } = await api.get('/costos-grupales/sectores'); sectores.value = data.sectores ?? []; subsectores.value = data.subsectores ?? [] } catch { /* */ }
})

function agregarUno (e: any) {
  if (grupo.value.some(g => g.legajo === e.legajo)) return
  grupo.value.push({ codigo: e.codigo, legajo: e.legajo, nombre: e.nombre, costo: 0, sel: false })
}

async function agregarEmpleado () {
  if (!empCod.value || empCod.value <= 0) { flash('Ingrese el empleado.', true); return }
  try {
    const { data } = await api.get(`/costos-grupales/empleado/${empCod.value}`)
    agregarUno(data); empCod.value = null; await recalcular()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'Código de empleado inexistente.', true) }
}
const onLupaEmp = (r: any) => { empCod.value = r.cod; agregarEmpleado() }

async function agregarSector () {
  if (!sectorSel.value || !subsectorSel.value) return
  cargando.value = true
  try {
    const { data } = await api.get('/costos-grupales/buscar', { params: { sector: sectorSel.value, subsector: subsectorSel.value } })
    for (const e of data ?? []) agregarUno(e)
    await recalcular()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar el sector.', true) }
  finally { cargando.value = false }
}

async function recalcular () {
  if (!grupo.value.length) { total.value = 0; return }
  cargando.value = true
  try {
    const { data } = await api.post('/costos-grupales/calcular', { mes: mes.value, anio: anio.value, codigos: grupo.value.map(g => g.codigo) })
    const map = new Map((data.empleados ?? []).map((e: any) => [e.codigo, e.costo]))
    for (const g of grupo.value) g.costo = map.get(g.codigo) ?? 0
    total.value = data.total ?? 0
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo calcular.', true) }
  finally { cargando.value = false }
}

function eliminarSeleccion () {
  grupo.value = grupo.value.filter(g => !g.sel)
  total.value = grupo.value.reduce((s, g) => s + (Number(g.costo) || 0), 0)
}

function exportarExcel () {
  if (!grupo.value.length) return
  const filas = ['Legajo;Nombre;Costo;Codigo']
  for (const g of grupo.value) filas.push([g.legajo, (g.nombre || '').replace(/;/g, ','), g.costo, g.codigo].join(';'))
  filas.push([';TOTAL', '', total.value, ''].join(';'))
  const blob = new Blob(['﻿' + filas.join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  guardarComo(blob, 'COSTOS_EMPLEADOS.csv')
}

function reset () { grupo.value = []; total.value = 0; empCod.value = null; sectorSel.value = 0; subsectorSel.value = 0; mes.value = hoy.getMonth() + 1; anio.value = hoy.getFullYear() }
</script>

<style scoped>
.cg-view { display:flex; flex-direction:column; min-height:100%; }
.cg-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cg-ico { font-size:28px; } .cg-tx h1 { margin:0; font-size:18px; color:#1e293b; } .cg-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cg-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cg-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cg-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cg-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cg-msg.ok { background:#d1fae5; color:#065f46; } .cg-msg.err { background:#fee2e2; color:#991b1b; }
.cg-body { padding:16px 18px; max-width:720px; }
.cg-periodo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.cg-lbl { font-size:13px; font-weight:700; color:#374151; } .cg-periodo label { font-size:13px; color:#475569; }
.cg-num { width:64px; border:1px solid #c8d8ea; border-radius:6px; padding:7px 8px; font-size:14px; font-weight:700; text-align:center; } .cg-anio { width:80px; }
.cg-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:8px 18px; cursor:pointer; font-weight:800; font-size:13px; } .cg-aceptar:disabled { opacity:.5; }
.cg-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:14px; }
.cg-tabla th { background:#1b4332; color:#fff; text-align:left; padding:7px 10px; font-size:12px; } .cg-tabla th:last-child { text-align:right; }
.cg-tabla td { border-bottom:1px solid #eef2f7; padding:5px 10px; color:#1e293b; } .cg-tabla td.c { text-align:center; } .cg-tabla td.imp { text-align:right; font-weight:700; }
.cg-tabla tr.sel td { background:#fef9c3; } .cg-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; }
.cg-elim { margin-top:8px; }
.cg-eliminar { background:#dc2626; color:#fff; border:none; border-radius:7px; padding:7px 14px; cursor:pointer; font-weight:700; font-size:13px; } .cg-eliminar:disabled { opacity:.4; }
.cg-add { display:flex; align-items:center; gap:10px; margin-top:14px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:10px 12px; }
.cg-add.sect { background:#eff6ff; border-color:#bfdbfe; }
.cg-add label { font-size:13px; font-weight:700; color:#374151; width:170px; }
.cg-add select { border:1px solid #c8d8ea; border-radius:6px; padding:7px 8px; font-size:13px; flex:1; }
.cg-lupa { background:#394959; color:#fff; border:none; padding:7px 12px; border-radius:7px; cursor:pointer; font-size:14px; }
.cg-btn-add { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:8px 16px; cursor:pointer; font-weight:800; font-size:13px; } .cg-btn-add:disabled { opacity:.5; }
.cg-pie { display:flex; align-items:center; gap:10px; margin-top:16px; }
.cg-calc-lbl { font-size:15px; font-weight:800; color:#14532d; } .cg-calc { font-size:20px; font-weight:800; color:#1e293b; }
.cg-excel { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:800; font-size:13px; } .cg-excel:disabled { opacity:.5; }
.cg-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.cg-help-md { background:#fff; border-radius:14px; padding:22px; width:min(540px,94vw); } .cg-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .cg-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
