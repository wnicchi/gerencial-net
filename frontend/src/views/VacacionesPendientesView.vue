<!-- VacacionesPendientesView.vue — Vacaciones Pendientes (vacaciones_pendientes). -->
<template>
  <div class="vp-view">
    <div class="vp-cab">
      <div class="vp-cab-ico">⏳</div>
      <div class="vp-cab-tx"><h1>Vacaciones Pendientes</h1><p>Días que le quedan pendientes a cada empleado por año</p></div>
      <button class="vp-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vp-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesPendientesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-pendientes" titulo="Asistente IA — Vacaciones Pendientes"
            subtitulo="Preguntá sobre los días pendientes"
            :sugerencias="['¿Cómo se calculan los pendientes?','¿Qué es Analizar Pasivos?','¿Qué significan los colores?']"
            @close="modalIA = false" />

    <div class="vp-card">
      <div class="vp-toolbar">
        <div class="vp-f"><span class="vp-lbl">Desde</span><input v-model.number="anio1" type="number" class="vp-inp chico" /></div>
        <div class="vp-f"><span class="vp-lbl">Hasta</span><input v-model.number="anio2" type="number" class="vp-inp chico" /></div>
        <label class="vp-chk"><input type="checkbox" v-model="unEmpleado" /> Un Empleado</label>
        <div class="vp-f bus" v-if="unEmpleado">
          <span class="vp-lbl">Empleado</span>
          <input v-model="busqueda" type="text" class="vp-inp" placeholder="Código o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="vp-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
          </ul>
        </div>
        <label class="vp-chk"><input type="checkbox" v-model="conPasivos" /> Analizar Pasivos</label>
        <button class="vp-btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
        <button class="vp-btn ok" v-if="filas.length" @click="imprimir">🖨 Imprimir Consulta Completa</button>
      </div>

      <div v-if="filas.length" class="vp-grid-wrap">
        <table class="vp-tabla">
          <thead><tr>
            <th>Código</th><th>Legajo</th><th>Nombre</th><th>Ingreso</th><th>Año</th>
            <th>Días</th><th>Tomados</th><th>Liquidadas</th><th>Pendientes</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ pasivo: f.estado === 'P' }">
              <td>{{ f.cod }}</td><td>{{ f.legajo }}</td><td>{{ f.nombre }}</td><td>{{ f.ingreso }}</td><td class="c">{{ f.anio }}</td>
              <td class="c">{{ f.dias }}</td><td class="c">{{ f.tomados }}</td><td class="c">{{ f.liquidadas }}</td>
              <td class="c pend">{{ f.pendientes }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else-if="!cargando && consultado" class="vp-vacio">No hay empleados con días pendientes en ese rango.</p>

      <div class="vp-total" v-if="filas.length">DÍAS PENDIENTE: <b>{{ totalPendiente }}</b></div>
      <p v-if="msg" class="vp-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="vp-pdf-ov" @click.self="cerrarPdf">
        <div class="vp-pdf-md">
          <div class="vp-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="vp-pdf-acc">
              <button class="vp-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="vp-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="vp-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="vp-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import VacacionesPendientesAyuda from '@/components/VacacionesPendientesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const anioActual = new Date().getFullYear()
const anio1 = ref(anioActual); const anio2 = ref(anioActual)
const unEmpleado = ref(false); const conPasivos = ref(false); const codSel = ref(0)

interface Fila { cod: number; legajo: number; nombre: string; ingreso: string; anio: number; dias: number; tomados: number; liquidadas: number; pendientes: number; estado: string }
const filas = ref<Fila[]>([]); const totalPendiente = ref(0); const cargando = ref(false); const consultado = ref(false); const msg = ref('')

const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionar = (r: any) => { busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; codSel.value = r.PER_COD; resultados.value = [] }

const consultar = async () => {
  cargando.value = true; msg.value = ''
  try {
    const params: any = { anio1: anio1.value, anio2: anio2.value, conPasivos: conPasivos.value ? 1 : 0 }
    if (unEmpleado.value && codSel.value) params.cod = codSel.value
    const { data } = await api.get('/vacaciones/pendientes', { params })
    filas.value = data.pendientes ?? []; totalPendiente.value = data.totalPendiente ?? 0; consultado.value = true
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo realizar el análisis.' }
  finally { cargando.value = false }
}

const imprimir = () => {
  if (!filas.value.length) return
  const sorted = [...filas.value].sort((a, b) => a.anio - b.anio || a.nombre.localeCompare(b.nombre))
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text('Vacaciones Pendientes', 14, 14)
  doc.setFontSize(9); doc.text(`Desde el año ${anio1.value} hasta el ${anio2.value}`, 14, 20)
  const cols = ['Cód.', 'Leg.', 'Nombre', 'Ingreso', 'Año', 'Días', 'Tom.', 'Liq.', 'Pend.']
  const xs = [14, 28, 42, 108, 132, 144, 156, 168, 182]; let y = 30
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 2
  doc.setLineWidth(0.2); doc.line(14, y, 196, y); y += 5
  for (const f of sorted) {
    if (y > 280) { doc.addPage(); y = 20 }
    const row = [String(f.cod), String(f.legajo), f.nombre.slice(0, 30), f.ingreso, String(f.anio), String(f.dias), String(f.tomados), String(f.liquidadas), String(f.pendientes)]
    row.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
  }
  y += 4; doc.setFont('helvetica', 'bold'); doc.text(`Días pendiente: ${totalPendiente.value}`, 14, y)
  cerrarPdf(); pdfNombre.value = 'VACACIONES_PENDIENTES.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
</script>

<style scoped>
.vp-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vp-cab-ico { font-size:28px; } .vp-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vp-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vp-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vp-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vp-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.vp-toolbar { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.vp-f { display:flex; flex-direction:column; gap:4px; position:relative; } .vp-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.vp-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .vp-inp.chico { width:100px; }
.vp-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; padding-bottom:7px; }
.vp-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.vp-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .vp-result li:hover { background:#f0faf4; }
.vp-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .vp-btn:disabled { background:#cbd5e1; cursor:default; }
.vp-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:62vh; }
.vp-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.vp-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.vp-tabla td { padding:5px 8px; border-bottom:1px solid #f1f5f9; white-space:nowrap; color:#1e293b; } .vp-tabla td.c { text-align:center; }
.vp-tabla td.pend { background:#fff7cc; font-weight:700; }
.vp-tabla tbody tr.pasivo td { background:#ffd6d6; }
.vp-total { align-self:flex-end; background:#fff7cc; border:1px solid #fde68a; border-radius:8px; padding:8px 16px; font-size:14px; color:#854d0e; } .vp-total b { font-size:18px; }
.vp-vacio { color:#94a3b8; padding:16px; } .vp-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fee2e2; color:#b91c1c; max-width:700px; }
.vp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vp-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.vp-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.vp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.vp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .vp-pdf-b.ok { background:#22c55e; color:#fff; } .vp-pdf-b.cancel { background:#ef4444; color:#fff; }
.vp-pdf-frame { flex:1; border:none; width:100%; }
</style>
