<!-- LlegadasTardeView.vue — Reloj / Llegadas Tarde por Período (reloj_llegadas_tardes). -->
<template>
  <div class="ht-view">
    <div class="ht-cab">
      <div class="ht-cab-ico">⏰</div>
      <div class="ht-cab-tx"><h1>Llegadas Tarde por Período</h1><p>Llegadas tarde y salidas anticipadas según el horario del turno</p></div>
      <button class="ht-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ht-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <LlegadasTardeAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/llegadas-tarde" titulo="Asistente IA — Llegadas Tarde"
            subtitulo="Preguntá sobre las tardanzas" :sugerencias="['¿Cómo se calcula la tardanza?','¿Qué son los minutos a favor?','¿Qué muestra el resumido?']"
            @close="modalIA = false" />

    <div class="ht-card">
      <div class="ht-toolbar">
        <div class="ht-f"><span class="ht-lbl">Empresa</span>
          <select v-model.number="filtros.empresa" class="ht-inp"><option :value="0">Todas</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select></div>
        <div class="ht-f"><span class="ht-lbl">Contratista</span>
          <select v-model.number="filtros.contratista" class="ht-inp"><option :value="0">Todos</option><option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option></select></div>
        <div class="ht-f"><span class="ht-lbl">Convenio</span>
          <select v-model.number="filtros.convenio" class="ht-inp"><option :value="0">Todos</option><option v-for="c in convenios" :key="c.cod" :value="c.cod">{{ c.nombre }}</option></select></div>
        <button class="ht-btn ok" :disabled="cargandoEmp" @click="traerEmpleados">{{ cargandoEmp ? '⟳…' : 'Traer Empleados' }}</button>
        <input v-model="buscar" type="text" class="ht-inp" placeholder="Buscar en la lista…" />
      </div>
      <div class="ht-split">
        <div class="ht-emp">
          <div class="ht-emp-head"><span>Empleados ({{ seleccionados }} / {{ empleados.length }})</span>
            <div><button class="ht-btn-sm" @click="marcarTodos(true)">Todos</button><button class="ht-btn-sm" @click="marcarTodos(false)">Nada</button></div></div>
          <div class="ht-emp-list">
            <label v-for="e in empleadosFiltrados" :key="e.cod" class="ht-emp-row" :class="{ on: e.sel }"><input type="checkbox" v-model="e.sel" /><span class="leg">{{ e.legajo }}</span><span class="nom">{{ e.nombre }}</span></label>
            <p v-if="!empleados.length" class="ht-vacio">Usá “Traer Empleados” para listar.</p></div>
        </div>
        <div class="ht-gen">
          <div class="ht-row2">
            <div class="ht-f"><span class="ht-lbl">Desde</span><input v-model="fecha1" type="date" class="ht-inp" /></div>
            <div class="ht-f"><span class="ht-lbl">al</span><input v-model="fecha2" type="date" class="ht-inp" /></div>
          </div>
          <div class="ht-row2">
            <div class="ht-f"><span class="ht-lbl">Min. tarde (llegada)</span><input v-model.number="maxTarde" type="number" class="ht-inp chico" /></div>
            <div class="ht-f"><span class="ht-lbl">Min. tarde (salida)</span><input v-model.number="maxSalida" type="number" class="ht-inp chico" /></div>
          </div>
          <label class="ht-chk"><input type="checkbox" v-model="negativos" /> Mostrar días con negativo (minutos a favor)</label>
          <label class="ht-chk"><input type="checkbox" v-model="resumido" /> Informe resumido</label>
          <button class="ht-btn ok blk" :disabled="!seleccionados || cargando" @click="generar">{{ cargando ? '⟳…' : 'Generar Informe' }}</button>
          <button class="ht-btn ok" v-if="detalle.length" @click="imprimir">🖨 Imprimir / PDF</button>
        </div>
      </div>

      <div v-if="resumido && resumenRows.length" class="ht-grid-wrap">
        <table class="ht-tabla">
          <thead><tr><th>Legajo</th><th>Empleado</th><th>Total minutos tarde</th></tr></thead>
          <tbody><tr v-for="r in resumenRows" :key="r.nombre"><td>{{ r.legajo }}</td><td>{{ r.nombre }}</td><td class="c b">{{ r.difMinutos }}</td></tr></tbody>
        </table>
      </div>
      <div v-else-if="!resumido && detalle.length" class="ht-grid-wrap">
        <table class="ht-tabla">
          <thead><tr><th>Legajo</th><th>Empleado</th><th>Fecha</th><th>Llegada tarde</th><th>Salida tarde</th><th>Diferencia</th></tr></thead>
          <tbody><tr v-for="(r, i) in detalle" :key="i" :class="{ neg: r.difMinutos < 0 }">
            <td>{{ r.legajo }}</td><td>{{ r.nombre }}</td><td>{{ fmt(r.fecha) }}</td>
            <td class="c">{{ r.minLlegadaTarde || '' }}</td><td class="c">{{ r.minSalidaTarde || '' }}</td><td class="c b">{{ r.difMinutos }}</td>
          </tr></tbody>
        </table>
      </div>
      <p v-else-if="!cargando && consultado" class="ht-vacio">No hay tardanzas en el período con esos parámetros.</p>
      <p v-if="msg" class="ht-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ht-pdf-ov" @click.self="cerrarPdf">
        <div class="ht-pdf-md"><div class="ht-pdf-head"><span>{{ pdfNombre }}</span><div class="ht-pdf-acc">
          <button class="ht-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
          <button class="ht-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
          <button class="ht-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button></div></div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ht-pdf-frame"></iframe></div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import LlegadasTardeAyuda from '@/components/LlegadasTardeAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date(); const haceUnMes = new Date(); haceUnMes.setMonth(haceUnMes.getMonth() - 1)
const fecha1 = ref(_iso(haceUnMes)); const fecha2 = ref(_iso(hoy))
const maxTarde = ref(30); const maxSalida = ref(60); const negativos = ref(false); const resumido = ref(false)
const empresas = ref<{ cod: number; nombre: string }[]>([])
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const convenios = ref<{ cod: number; nombre: string }[]>([])
const filtros = reactive({ empresa: 0, contratista: 0, convenio: 0 })
interface Emp { cod: number; legajo: number; nombre: string; sel: boolean }
const empleados = ref<Emp[]>([]); const buscar = ref(''); const cargandoEmp = ref(false); const cargando = ref(false); const consultado = ref(false); const msg = ref('')
const detalle = ref<any[]>([]); const resumenRows = ref<any[]>([])
const seleccionados = computed(() => empleados.value.filter(e => e.sel).length)
const empleadosFiltrados = computed(() => { const q = buscar.value.trim().toLowerCase(); return q ? empleados.value.filter(e => e.nombre.toLowerCase().includes(q) || String(e.legajo).includes(q)) : empleados.value })
const fmt = (iso: string) => { const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }

onMounted(async () => {
  try { const data = (await api.get('/empresas')).data; const arr = Array.isArray(data) ? data : (data.data ?? []); empresas.value = arr.map((e: any) => ({ cod: Number(e.EMP_COD ?? e.cod), nombre: (e.EMP_NOM ?? e.nombre ?? '').toString().trim() })) } catch { /* */ }
  // Catálogos para los filtros de Contratista y Convenio.
  try {
    const { data } = await api.get('/empleados/opciones')
    contratistas.value = (data.contratistas ?? []).map((c: any) => ({ cod: Number(c.CONT_COD), nombre: (c.CONT_DET ?? '').toString().trim() }))
    convenios.value = (data.convenios ?? []).map((c: any) => ({ cod: Number(c.CON_COD), nombre: (c.CON_DES ?? '').toString().trim() }))
  } catch { /* */ }
})

const traerEmpleados = async () => {
  cargandoEmp.value = true; msg.value = ''
  try {
    const params: any = {}
    if (filtros.empresa) params.empresa = filtros.empresa
    if (filtros.contratista) params.contratista = filtros.contratista
    if (filtros.convenio) params.convenio = filtros.convenio
    empleados.value = (await api.get('/reloj/horas-trabajadas/empleados', { params })).data.empleados ?? []
    if (!empleados.value.length) msg.value = 'No se encuentran empleados.'
  }
  catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudieron traer los empleados.' } finally { cargandoEmp.value = false }
}
const marcarTodos = (v: boolean) => empleados.value.forEach(e => e.sel = v)

const generar = async () => {
  const codigos = empleados.value.filter(e => e.sel).map(e => e.cod); if (!codigos.length) return
  cargando.value = true; msg.value = ''; detalle.value = []; resumenRows.value = []
  try { const { data } = await api.post('/reloj/llegadas-tarde', { codigos, fecha1: fecha1.value, fecha2: fecha2.value, maxTarde: maxTarde.value, maxSalida: maxSalida.value, negativos: negativos.value }); detalle.value = data.detalle ?? []; resumenRows.value = data.resumen ?? []; consultado.value = true }
  catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo generar el informe.' } finally { cargando.value = false }
}

const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('Informe de Llegadas Tardes al Trabajo' + (resumido.value ? ' (Resumen)' : ''), 14, 14)
  doc.setFontSize(9); doc.text(`Desde ${fmt(fecha1.value)} hasta ${fmt(fecha2.value)} (llegadas ${maxTarde.value} min. y salidas ${maxSalida.value} min.)`, 14, 20)
  let y = 28
  if (resumido.value) {
    const xs = [14, 40, 150]; doc.setFont('helvetica', 'bold');['Legajo', 'Empleado', 'Min. tarde'].forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
    for (const r of resumenRows.value) { if (y > 285) { doc.addPage(); y = 20 } [String(r.legajo), r.nombre.slice(0, 44), String(r.difMinutos)].forEach((c, i) => doc.text(c, xs[i], y)); y += 5 }
  } else {
    const xs = [14, 36, 110, 138, 166, 190]; doc.setFont('helvetica', 'bold');['Legajo', 'Empleado', 'Fecha', 'Lleg.tarde', 'Sal.tarde', 'Dif.'].forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
    for (const r of detalle.value) { if (y > 285) { doc.addPage(); y = 20 } [String(r.legajo), r.nombre.slice(0, 38), fmt(r.fecha), String(r.minLlegadaTarde || ''), String(r.minSalidaTarde || ''), String(r.difMinutos)].forEach((c, i) => doc.text(c, xs[i], y)); y += 5 }
  }
  cerrarPdf(); pdfNombre.value = 'LLEGADAS_TARDE.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
</script>

<style scoped>
.ht-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ht-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ht-cab-ico { font-size:28px; } .ht-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ht-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ht-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ht-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ht-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.ht-toolbar { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.ht-f { display:flex; flex-direction:column; gap:4px; } .ht-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.ht-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .ht-inp.chico { width:90px; }
.ht-row2 { display:flex; gap:12px; }
.ht-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; }
.ht-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .ht-btn.blk { background:#1b4332; } .ht-btn:disabled { background:#cbd5e1; cursor:default; }
.ht-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; margin-left:6px; }
.ht-split { display:flex; gap:14px; flex-wrap:wrap; }
.ht-emp { flex:1; min-width:320px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:320px; }
.ht-emp-head { display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:#1b4332; color:#fff; font-size:12.5px; }
.ht-emp-list { overflow:auto; }
.ht-emp-row { display:flex; align-items:center; gap:8px; padding:4px 10px; border-bottom:1px solid #f1f5f9; font-size:12.5px; color:#1e293b; cursor:pointer; }
.ht-emp-row.on { background:#fff7cc; } .ht-emp-row .leg { width:48px; color:#64748b; } .ht-emp-row .nom { flex:1; }
.ht-gen { flex:1; min-width:280px; display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
.ht-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:50vh; }
.ht-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.ht-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 8px; text-align:left; white-space:nowrap; }
.ht-tabla td { padding:5px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .ht-tabla td.c { text-align:center; } .ht-tabla td.b { font-weight:700; }
.ht-tabla tbody tr.neg td { background:#dcfce7; }
.ht-vacio { color:#94a3b8; padding:14px; } .ht-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fef9c3; color:#854d0e; max-width:760px; }
.ht-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ht-pdf-md { width:min(1000px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ht-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.ht-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ht-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ht-pdf-b.ok { background:#22c55e; color:#fff; } .ht-pdf-b.cancel { background:#ef4444; color:#fff; }
.ht-pdf-frame { flex:1; border:none; width:100%; }
</style>
