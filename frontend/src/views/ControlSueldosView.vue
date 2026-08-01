<!-- ControlSueldosView.vue — Planilla Control de Sueldos (planilla_control_sueldos). -->
<template>
  <div class="cs-view">
    <div class="cs-cab">
      <div class="cs-cab-ico">💰</div>
      <div class="cs-cab-tx"><h1>Control de Sueldos</h1><p>Control de sueldos depositados por mes y año</p></div>
      <button class="cs-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cs-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ControlSueldosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/control-sueldos" titulo="Asistente IA — Control de Sueldos"
            subtitulo="Preguntá sobre la planilla de control"
            :sugerencias="['¿Qué muestra cada columna?','¿Cómo se calcula la diferencia?','¿Qué es Tomar planilla grabada?','¿Cómo grabo la planilla?']"
            @close="modalIA = false" />

    <div class="cs-filtros">
      <span class="cs-lbl">Mes</span>
      <select v-model.number="mes" class="cs-sel"><option v-for="m in 12" :key="m" :value="m">{{ meses[m - 1] }}</option></select>
      <span class="cs-lbl">Año</span>
      <input v-model.number="anio" type="number" class="cs-anio" />
      <button class="cs-btn-ok" :disabled="cargando" @click="generar">{{ cargando ? '⟳ Generando…' : 'GENERAR PLANILLA' }}</button>
      <button class="cs-btn-tomar" @click="abrirGuardadas">📂 Tomar planilla grabada</button>
    </div>

    <div v-if="filas.length" class="cs-wrap">
      <table class="cs-grid">
        <thead><tr>
          <th>Legajo</th><th>Nombre</th><th>Banco</th><th class="r">Neto</th><th class="r">Sueldo</th><th class="r">Extras</th>
          <th class="r tn">Total Neto</th><th class="r">Anticipo</th><th class="r">Premio</th><th class="r">Vacaciones</th>
          <th class="r">Ganancia</th><th class="r">Almuerzo</th><th class="r">Sac</th><th class="r">Otros</th>
          <th class="r td">Total Depositado</th><th class="r ct">Control</th>
        </tr></thead>
        <tbody>
          <tr v-for="(f, i) in filas" :key="i">
            <td>{{ f.legajo }}</td><td>{{ f.nombre }}</td><td>{{ f.banco }}</td>
            <td class="r">{{ money(f.neto) }}</td><td class="r">{{ money(f.sueldo) }}</td><td class="r">{{ money(f.extras) }}</td>
            <td class="r tn">{{ money(f.stotal) }}</td><td class="r">{{ money(f.anticipo) }}</td>
            <td class="r" :class="{ hl: f.premio !== 0 }">{{ money(f.premio) }}</td><td class="r">{{ money(f.vacacion) }}</td>
            <td class="r" :class="{ hl: f.ganancia !== 0 }">{{ money(f.ganancia) }}</td><td class="r">{{ money(f.almuerzo) }}</td>
            <td class="r">{{ money(f.sac) }}</td><td class="r" :class="{ hl: f.otros !== 0 }">{{ money(f.otros) }}</td>
            <td class="r td">{{ money(f.deposito) }}</td><td class="r ct" :class="{ neg: f.diferencia < 0 }">{{ money(f.diferencia) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="consultado" class="cs-vacio">No hay empleados activos para generar la planilla.</div>

    <div v-if="filas.length" class="cs-pie">
      <span class="cs-lbl">Nombre de la planilla</span>
      <input v-model="nombrePlanilla" type="text" class="cs-nombre" placeholder="Descripción para guardar" />
      <button class="cs-btn-grabar" :disabled="grabando" @click="grabar">💾 {{ grabando ? 'Guardando…' : 'GRABAR PLANILLA' }}</button>
      <button class="cs-btn-excel" @click="exportarExcel">📊 Exportar a Excel</button>
    </div>
    <p v-if="msg" :class="['cs-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>

    <Teleport to="body">
      <div v-if="modalGuardadas" class="cs-modal-ov" @click.self="modalGuardadas = false">
        <div class="cs-modal">
          <div class="cs-modal-head"><span>Planillas guardadas</span><button class="cs-modal-x" @click="modalGuardadas = false">✕</button></div>
          <ul class="cs-guardadas">
            <li v-for="g in guardadas" :key="g.nro" @click="tomar(g.nro)">
              <b>N° {{ g.nro }} — {{ g.detalle }}</b><span>{{ g.fecha }} · {{ g.filas }} empleados</span>
            </li>
            <li v-if="!guardadas.length" class="cs-vacio">No hay planillas guardadas.</li>
          </ul>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import ControlSueldosAyuda from '@/components/ControlSueldosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
interface Fila { legajo: number; codigo: number; nombre: string; banco: string; neto: number; sueldo: number; extras: number; stotal: number; anticipo: number; premio: number; vacacion: number; ganancia: number; almuerzo: number; sac: number; otros: number; deposito: number; diferencia: number; sector: string; subsector: string }
const hoy = new Date()
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const filas = ref<Fila[]>([]); const cargando = ref(false); const consultado = ref(false)
const nombrePlanilla = ref(''); const grabando = ref(false)
const guardadas = ref<any[]>([]); const modalGuardadas = ref(false)
const msg = ref(''); const msgError = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 4000) }

const generar = async () => {
  cargando.value = true; consultado.value = true
  try { filas.value = (await api.get('/control-sueldos/generar', { params: { mes: mes.value, anio: anio.value } })).data.filas }
  catch (e) { console.error(e); filas.value = [] } finally { cargando.value = false }
}

const abrirGuardadas = async () => {
  try { guardadas.value = (await api.get('/control-sueldos/guardadas')).data; modalGuardadas.value = true }
  catch (e) { console.error(e) }
}
const tomar = async (nro: number) => {
  try { filas.value = (await api.get(`/control-sueldos/${nro}`)).data.filas; consultado.value = true; modalGuardadas.value = false; flash(`Planilla N° ${nro} cargada.`) }
  catch (e) { console.error(e); flash('No se pudo cargar la planilla.', true) }
}

const grabar = async () => {
  if (!nombrePlanilla.value.trim()) { flash('Ingresá una descripción de la planilla a guardar.', true); return }
  grabando.value = true
  try { const { data } = await api.post('/control-sueldos', { nombre: nombrePlanilla.value, rows: filas.value }); flash(`Planilla N° ${data.nro} guardada correctamente.`); nombrePlanilla.value = '' }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo grabar.', true) }
  finally { grabando.value = false }
}

const exportarExcel = async () => {
  const cols = ['Legajo', 'Nombre', 'Banco', 'Neto', 'Sueldo', 'Extras', 'Total Neto', 'Anticipo', 'Premio', 'Vacaciones', 'Ganancia', 'Almuerzo', 'Sac', 'Otros', 'Total Depositado', 'Control', 'Sector', 'SubSector']
  const filasH = filas.value.map(f =>
    `<tr><td>${f.legajo}</td><td>${esc(f.nombre)}</td><td>${esc(f.banco)}</td><td>${f.neto}</td><td>${f.sueldo}</td><td>${f.extras}</td><td>${f.stotal}</td><td>${f.anticipo}</td><td>${f.premio}</td><td>${f.vacacion}</td><td>${f.ganancia}</td><td>${f.almuerzo}</td><td>${f.sac}</td><td>${f.otros}</td><td>${f.deposito}</td><td>${f.diferencia}</td><td>${esc(f.sector)}</td><td>${esc(f.subsector)}</td></tr>`).join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>
    <table border="1"><tr style="background:#1b4332;color:#fff;font-weight:bold">${cols.map(c => `<th>${c}</th>`).join('')}</tr>${filasH}</table></body></html>`
  await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), `Control_Sueldos_${mes.value}_${anio.value}.xls`)
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

onMounted(() => {})
</script>

<style scoped>
.cs-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cs-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cs-cab-ico { font-size:28px; } .cs-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cs-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cs-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.cs-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.cs-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; }
.cs-anio { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; width:90px; }
.cs-btn-ok { background:#16a34a; color:#fff; border:none; padding:9px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .cs-btn-ok:disabled { background:#cbd5e1; }
.cs-btn-tomar { margin-left:auto; background:#fff; border:1px solid #cbd5e1; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-wrap { margin:12px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:64vh; }
.cs-grid { width:100%; border-collapse:collapse; font-size:12px; white-space:nowrap; }
.cs-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:6px 8px; font-size:11px; text-align:left; } .cs-grid th.r { text-align:right; }
.cs-grid th.tn { background:#a16207; } .cs-grid th.td { background:#9a3412; } .cs-grid th.ct { background:#3730a3; }
.cs-grid td { padding:4px 8px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .cs-grid td.r { text-align:right; }
.cs-grid tr:nth-child(even) td { background:#f8fafc; }
.cs-grid td.tn { background:#fef9c3 !important; font-weight:600; } .cs-grid td.td { background:#ffedd5 !important; font-weight:600; } .cs-grid td.ct { background:#e0e7ff !important; font-weight:600; } .cs-grid td.ct.neg { color:#b91c1c; }
.cs-grid td.hl { background:#fff200 !important; }
.cs-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.cs-pie { display:flex; align-items:center; gap:12px; padding:0 18px 16px; flex-wrap:wrap; }
.cs-nombre { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; min-width:260px; }
.cs-btn-grabar { background:#16a34a; color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .cs-btn-grabar:disabled { background:#cbd5e1; }
.cs-btn-excel { background:#fff; border:1px solid #16a34a; color:#15803d; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cs-msg { padding:8px 14px; margin:0 18px 14px; font-size:13px; border-radius:6px; } .cs-msg.ok { background:#dcfce7; color:#166534; } .cs-msg.err { background:#fee2e2; color:#b91c1c; }
.cs-modal-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cs-modal { width:min(560px,96vw); max-height:80vh; background:#fff; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.4); }
.cs-modal-head { display:flex; align-items:center; padding:12px 16px; background:#1b4332; color:#fff; font-size:14px; font-weight:600; } .cs-modal-x { margin-left:auto; background:transparent; border:none; color:#fff; font-size:16px; cursor:pointer; }
.cs-guardadas { list-style:none; margin:0; padding:0; overflow:auto; }
.cs-guardadas li { padding:11px 16px; border-bottom:1px solid #f1f5f9; cursor:pointer; display:flex; flex-direction:column; gap:3px; }
.cs-guardadas li:hover { background:#eef6ee; } .cs-guardadas li b { font-size:13px; color:#1e293b; } .cs-guardadas li span { font-size:12px; color:#6b7280; }
</style>
