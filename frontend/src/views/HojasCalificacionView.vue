<!-- HojasCalificacionView.vue — Impresión Hoja de Calificación (hoja en blanco para calificar a mano) -->
<template>
  <div class="hc-view">
    <div class="hc-cab">
      <div class="hc-ico">📄</div>
      <div class="hc-tx"><h1>Impresión Hoja de Calificación</h1><p>Hoja en blanco para calificar el desempeño en el puesto</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['hc-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="hc-body">
      <div class="hc-top">
        <div class="campo grow">
          <label>Empleado</label>
          <EmpleadoInput :codigo="codigo" :nombre="nombre" @select="onSelEmp" @clear="limpiarEmp" />
        </div>
        <div class="hc-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl=''" /><div v-else class="hc-foto-ph">Sin foto</div></div>
      </div>

      <template v-if="codigo">
        <div class="hc-sub">Puestos asignados — elegí el puesto a imprimir</div>
        <table class="hc-tabla">
          <thead><tr><th class="c">Imprimir</th><th>Descripción del Puesto</th><th>Desde</th><th>Hasta</th><th class="c">Activo</th><th>Reporta a…</th></tr></thead>
          <tbody>
            <tr v-for="p in puestos" :key="p.cod" :class="{ on: sel === p.cod }" @click="sel = p.cod">
              <td class="c"><input type="radio" :value="p.cod" v-model="sel" /></td>
              <td>{{ p.des }}</td><td>{{ p.desde }}</td><td>{{ p.hasta || '—' }}</td>
              <td class="c">{{ p.activo ? '✔' : '' }}</td><td>{{ p.reporta }}</td>
            </tr>
            <tr v-if="!puestos.length"><td colspan="6" class="hc-vacio">El empleado no posee puestos asignados.</td></tr>
          </tbody>
        </table>
        <div class="hc-acc">
          <button class="btn reset" @click="reset">↺ Reset</button>
          <button class="btn ok" :disabled="!sel || gen" @click="imprimir">{{ gen ? '⟳ …' : '🖨 IMPRIMIR HOJA CALIFICACIÓN' }}</button>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="hc-pdf-ov" @click.self="cerrarPdf">
        <div class="hc-pdf-md">
          <div class="hc-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="hc-pdf-acc">
              <button class="hc-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="hc-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="hc-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="hc-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'

const codigo = ref(0); const nombre = ref(''); const fotoUrl = ref('')
const puestos = ref<any[]>([]); const sel = ref(''); const gen = ref(false)
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const onSelEmp = (r: any) => seleccionar(r)
const limpiarEmp = () => {
  codigo.value = 0; nombre.value = ''; puestos.value = []; sel.value = ''; fotoUrl.value = ''
}
async function seleccionar (r: any) {
  sel.value = ''
  try {
    const { data } = await api.get(`/calificaciones/hoja-puestos/${r.PER_COD}`)
    codigo.value = Number(r.PER_COD); nombre.value = data.nombre; puestos.value = data.puestos
    sel.value = data.puestos[0]?.cod ?? ''
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
  fotoUrl.value = ''
  try { const { data } = await api.get(`/empleados/${r.PER_COD}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* sin foto */ }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

async function imprimir () {
  if (!sel.value) return
  gen.value = true
  try {
    const p = puestos.value.find(x => x.cod === sel.value)
    const det = (await api.get(`/calificaciones/puesto/${encodeURIComponent(sel.value)}`)).data
    pdf(p, det)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar la hoja.', true) }
  finally { gen.value = false }
}

function pdf (p: any, det: any) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 15, PW = 210, PH = 297; let y = 16
  const salto = (need = 8) => { if (y + need > PH - 14) { doc.addPage(); y = 16 } }
  const linRot = (l: string, v: string) => { salto(); doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(l, ML, y); doc.setFont('helvetica', 'normal'); const ls = doc.splitTextToSize(v || '—', 150); doc.text(ls, ML + 38, y); y += Math.max(1, ls.length) * 5 + 1 }
  const headerBar = (t: string) => { salto(); doc.setFillColor(225, 232, 240); doc.setDrawColor(60); doc.setLineWidth(.3); doc.rect(ML, y, PW - ML * 2, 7, 'FD'); doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(20, 40, 70); doc.text(t, PW / 2, y + 5, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 9 }

  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('Calificación de ' + nombre.value, PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 9
  linRot('Código del Puesto:', p.cod); linRot('Puesto:', p.des); linRot('Departamento:', det.departamento)
  linRot('Reporta:', p.reporta || det.reporta); linRot('Objetivos:', det.objetivos)
  salto(); doc.setFont('helvetica', 'bold'); doc.text('Fecha:', ML, y); doc.setDrawColor(120); doc.line(ML + 16, y, ML + 70, y); y += 7
  doc.setFont('helvetica', 'italic'); doc.setFontSize(8.5); doc.text('Colocar la calificación por Items de 1 a 10 ( 1=Insuficiente, …, 10=Excelente )', ML, y); y += 5

  // Tareas con casilla "Calificar"
  headerBar('Tareas')
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.text('Tarea', ML + 2, y); doc.text('Calificar', PW - ML - 18, y); y += 4
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  for (const t of det.tareas) { salto(); doc.text('• ' + t.des, ML + 2, y); doc.setDrawColor(120); doc.rect(PW - ML - 16, y - 3.5, 14, 5); y += 6 }
  y += 2

  headerBar('Competencias')
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
  for (const c of det.competencias) { salto(); doc.text('• ' + c, ML + 2, y); doc.setDrawColor(120); doc.rect(PW - ML - 16, y - 3.5, 14, 5); y += 6 }
  y += 2

  if (det.educacion.length) { headerBar('Requisitos Educacionales'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const e of det.educacion) { salto(); doc.text('• ' + e.des, ML + 2, y); y += 5 } y += 2 }
  if (det.cualidades.length) { headerBar('Cualidades Necesarias'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const c of det.cualidades) { salto(); doc.text('• ' + c, ML + 2, y); y += 5 } y += 2 }

  const lineaObs = (l: string) => { salto(); doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(l, ML, y); doc.setDrawColor(120); doc.line(ML + 60, y, PW - ML, y); y += 8 }
  y += 2
  lineaObs('Resultado de la Evaluación:')
  lineaObs('Observaciones Positivas:')
  lineaObs('Aspectos a Mejorar:')
  lineaObs('Responsable de la Calificación:')

  cerrarPdf(); pdfNombre.value = `Hoja_calificacion_${p.cod}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

function reset () {
  codigo.value = 0; nombre.value = ''; sel.value = ''; puestos.value = []; fotoUrl.value = ''
}
</script>

<style scoped>
.hc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.hc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.hc-ico { font-size:28px; } .hc-tx h1 { margin:0; font-size:19px; color:#1e293b; } .hc-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.hc-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .hc-msg.ok { background:#d1fae5; color:#065f46; } .hc-msg.err { background:#fee2e2; color:#991b1b; }
.hc-body { padding:16px 18px; max-width:920px; }
.hc-top { display:flex; gap:14px; align-items:flex-end; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo.grow { flex:1; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.hc-busc { position:relative; } .hc-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.hc-busc-lupa { display:flex; gap:8px; position:relative; } .hc-busc-lupa input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.hc-clear { background:#e2e8f0; border:none; border-radius:50%; width:26px; height:26px; align-self:center; cursor:pointer; font-size:12px; color:#475569; }
.hc-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.hc-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.hc-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.hc-result li:hover { background:#f0faf4; } .hc-result li b { font-size:13px; color:#1e293b; } .hc-result li span { font-size:11px; color:#6b7280; }
.hc-foto { width:140px; height:90px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.hc-foto img { width:100%; height:100%; object-fit:cover; } .hc-foto-ph { font-size:11px; color:#9ca3af; }
.hc-sub { margin:16px 0 6px; font-weight:700; color:#2a4a6a; font-size:13px; }
.hc-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.hc-tabla th { background:#1e293b; color:#fff; padding:8px 10px; text-align:left; font-size:12px; } .hc-tabla th.c { text-align:center; width:70px; }
.hc-tabla td { padding:7px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .hc-tabla td.c { text-align:center; }
.hc-tabla tr { cursor:pointer; } .hc-tabla tbody tr:hover { background:#f0f7ff; } .hc-tabla tr.on { background:#e0eefc; }
.hc-vacio { text-align:center; color:#94a3b8; padding:16px; }
.hc-acc { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; }
.btn { border:none; padding:11px 22px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.hc-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.hc-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.hc-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.hc-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.hc-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.hc-pdf-b.ok { background:#22c55e; color:#fff; } .hc-pdf-b.cancel { background:#ef4444; color:#fff; }
.hc-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
