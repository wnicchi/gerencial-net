<!-- SecretariaTrabajoView.vue — Reloj / Planilla para Secretaría de Trabajo (reloj_horarios_secretaria_trabajo). -->
<template>
  <div class="ht-view">
    <div class="ht-cab">
      <div class="ht-cab-ico">📑</div>
      <div class="ht-cab-tx"><h1>Planilla para Secretaría de Trabajo</h1><p>Fichadas por día y total trabajado por empleado en el período</p></div>
      <button class="ht-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ht-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <SecretariaTrabajoAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/secretaria-trabajo" titulo="Asistente IA — Secretaría de Trabajo"
            subtitulo="Preguntá sobre la planilla de horarios" :sugerencias="['¿Qué muestra la planilla?','¿Cómo se calcula el total?','¿Qué pasa con turnos de noche?']"
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
          <div class="ht-f"><span class="ht-lbl">Período</span><input v-model="fecha1" type="date" class="ht-inp" /></div>
          <div class="ht-f"><span class="ht-lbl">Hasta</span><input v-model="fecha2" type="date" class="ht-inp" /></div>
          <button class="ht-btn ok blk" :disabled="!seleccionados || cargando" @click="generar">{{ cargando ? '⟳…' : 'Planilla General' }}</button>
          <button class="ht-btn ok" v-if="filas.length" @click="imprimir">🖨 Imprimir / PDF</button>
        </div>
      </div>

      <div v-if="filas.length" class="ht-grid-wrap">
        <table class="ht-tabla">
          <thead><tr><th>Legajo</th><th>Empleado</th><th>Día</th><th>Entra 1</th><th>Sale 1</th><th>Entra 2</th><th>Sale 2</th><th>Tiempo</th><th>Total Emp.</th><th>Obs.</th></tr></thead>
          <tbody><tr v-for="(f, i) in filas" :key="i" :class="{ obs: f.observa, aj: f.ajuste }">
            <td>{{ f.legajo }}</td><td>{{ f.nombre }}</td><td>{{ f.dia }}</td>
            <td class="c">{{ f.entra1 }}</td><td class="c">{{ f.sale1 }}</td><td class="c">{{ f.entra2 }}</td><td class="c">{{ f.sale2 }}</td>
            <td class="c">{{ f.tiempo }}</td><td class="c b">{{ f.supertotal }}</td><td>{{ f.observa }}</td>
          </tr></tbody>
        </table>
      </div>
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
import SecretariaTrabajoAyuda from '@/components/SecretariaTrabajoAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date(); const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
const fecha1 = ref(_iso(primero)); const fecha2 = ref(_iso(hoy))
const empresas = ref<{ cod: number; nombre: string }[]>([])
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const convenios = ref<{ cod: number; nombre: string }[]>([])
const filtros = reactive({ empresa: 0, contratista: 0, convenio: 0 })
interface Emp { cod: number; legajo: number; nombre: string; sel: boolean }
const empleados = ref<Emp[]>([]); const buscar = ref(''); const cargandoEmp = ref(false); const cargando = ref(false); const msg = ref('')
const filas = ref<any[]>([])
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
  cargando.value = true; msg.value = ''; filas.value = []
  try { const { data } = await api.post('/reloj/secretaria-trabajo', { codigos, fecha1: fecha1.value, fecha2: fecha2.value }); filas.value = data.planilla ?? []; if (!filas.value.length) msg.value = 'Sin datos para el período.' }
  catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo generar la planilla.' } finally { cargando.value = false }
}

/** CUIL con guiones (20229514033 → 20-22951403-3); si no tiene 11 dígitos se deja como vino. */
const cuilFmt = (v: any): string => {
  const d = String(v ?? '').replace(/\D/g, '')
  return d.length === 11 ? `${d.slice(0, 2)}-${d.slice(2, 10)}-${d.slice(10)}` : String(v ?? '')
}

/**
 * PDF de la planilla: UNA HOJA POR EMPLEADO, con el formato del sistema anterior
 * (encabezado con empresa/legajo/CUIL/período, grilla de días y firmas al pie).
 * Al pie van las dos firmas: la del empleador y la del empleado.
 */
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, ML = 12, MR = PW - 12, W = MR - ML

  // Agrupar las filas por empleado, respetando el orden en que vienen.
  const grupos: Record<string, any[]> = {}; const orden: string[] = []
  for (const f of filas.value) {
    const k = String(f.cod ?? f.legajo)
    if (!grupos[k]) { grupos[k] = []; orden.push(k) }
    grupos[k].push(f)
  }

  // Bordes verticales de la grilla: Día | Netas | Entra | Sale | Entra | Sale | Observaciones
  // (la columna Firma se quitó: las firmas van al pie de la hoja).
  const bx = [ML, 30, 47, 64, 81, 98, 115, MR]
  const B = (i: number): number => bx[i] as number   // acceso al borde i (índices fijos y conocidos)
  const cx = (i: number) => (B(i) + B(i + 1)) / 2
  const rowH = 6.4

  orden.forEach((k, idx) => {
    if (idx > 0) doc.addPage()
    const rows = grupos[k] ?? [], p = rows[0] ?? {}
    let y = 14

    // ── Encabezado: empresa y CUIT ──
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(0, 0, 0)
    doc.text((p.empresa || '').toUpperCase(), ML, y)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    doc.text(`Cuit: ${p.empresaCuit || ''}`, ML, y + 5)
    y += 11

    // ── Datos del empleado ──
    doc.setDrawColor(0); doc.setLineWidth(0.3)
    const boxTop = y
    doc.setFontSize(9)
    const dato = (lbl: string, val: string, x: number, yy: number) => {
      doc.setFont('helvetica', 'bold'); doc.text(lbl, x, yy)
      doc.setFont('helvetica', 'normal'); doc.text(String(val ?? ''), x + doc.getTextWidth(lbl) + 2, yy)
    }
    dato('Legajo:', String(p.legajo ?? ''), ML + 2, y + 5)
    dato('DNI/CUIL:', cuilFmt(p.cuil), ML + 40, y + 5)
    dato('Período:', (p.periodo || '').toUpperCase(), ML + 110, y + 5)
    dato('Empleado:', p.nombre || '', ML + 2, y + 11)
    dato('Horario:', '', ML + 110, y + 11)
    doc.rect(ML, boxTop, W, 14)
    y = boxTop + 14

    // ── Encabezado de la grilla (dos niveles, como el formato viejo) ──
    const cabGrilla = (yy: number) => {
      doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setDrawColor(0); doc.setLineWidth(0.3)
      doc.text('HORARIOS TRABAJADOS', (B(2) + B(6)) / 2, yy + 4, { align: 'center' })
      doc.text('DIA', cx(0), yy + 7, { align: 'center' })
      doc.text('NETAS', cx(1), yy + 7, { align: 'center' })
      doc.text('ENTRA', cx(2), yy + 8.5, { align: 'center' })
      doc.text('SALE', cx(3), yy + 8.5, { align: 'center' })
      doc.text('ENTRA', cx(4), yy + 8.5, { align: 'center' })
      doc.text('SALE', cx(5), yy + 8.5, { align: 'center' })
      doc.text('OBSERVACIONES', cx(6), yy + 7, { align: 'center' })
      const bot = yy + 10.5
      doc.line(ML, yy, MR, yy); doc.line(ML, bot, MR, bot)
      doc.line(B(2), yy + 5, B(6), yy + 5)   // separa "HORARIOS TRABAJADOS" de ENTRA/SALE
      return bot
    }
    let segTop = y            // inicio del tramo de grilla en la página actual
    y = cabGrilla(y)

    // ── Filas de días ──
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
    const cerrarTramo = () => { doc.setDrawColor(0); doc.setLineWidth(0.3); for (const x of bx) doc.line(x, segTop, x, y); doc.line(ML, y, MR, y) }
    for (const f of rows) {
      // Períodos largos (más de un mes): la grilla continúa en la hoja siguiente.
      if (y + rowH > 250) {
        cerrarTramo(); doc.addPage(); y = 14; segTop = y; y = cabGrilla(y)
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
      }
      const base = y + 4.4
      // "LU 01" → "Lu 01" y "SA 06" → "Sá 06" (como el formato anterior)
      const dia = String(f.dia || '')
      const diaFmt = (dia.charAt(0) + dia.slice(1).toLowerCase()).replace(/^Sa\b/, 'Sá')
      doc.text(diaFmt, B(0) + 2, base)
      doc.text(String(f.tiempo || ''), cx(1), base, { align: 'center' })
      doc.text(String(f.entra1 || ''), cx(2), base, { align: 'center' })
      doc.text(String(f.sale1 || ''), cx(3), base, { align: 'center' })
      doc.text(String(f.entra2 || ''), cx(4), base, { align: 'center' })
      doc.text(String(f.sale2 || ''), cx(5), base, { align: 'center' })
      if (f.observa) doc.text(String(f.observa).slice(0, 62), B(6) + 2, base)
      y += rowH
      doc.setDrawColor(150); doc.setLineWidth(0.2); doc.line(ML, y, MR, y)
    }
    cerrarTramo()

    // ── Total del período ──
    if (y + 32 > 285) { doc.addPage(); y = 14 }   // el total y las firmas no se parten
    y += 6
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
    doc.text('TOTAL DE HORAS TRABAJADAS:', B(5), y, { align: 'right' })
    doc.text(String(p.supertotal || ''), MR, y, { align: 'right' })

    // ── Firmas: empleador y empleado ──
    y += 20
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setLineWidth(0.3); doc.setDrawColor(0)
    const anchoF = 72
    const x1 = ML + 6, x2 = MR - anchoF - 6
    doc.line(x1, y, x1 + anchoF, y)
    doc.line(x2, y, x2 + anchoF, y)
    y += 5
    doc.text('Firma del empleador', x1 + anchoF / 2, y, { align: 'center' })
    doc.text('Firma del empleado', x2 + anchoF / 2, y, { align: 'center' })
  })

  cerrarPdf(); pdfNombre.value = 'PLANILLA_SECRETARIA_DE_TRABAJO.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
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
.ht-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; }
.ht-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .ht-btn.blk { background:#1b4332; } .ht-btn:disabled { background:#cbd5e1; cursor:default; }
.ht-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; margin-left:6px; }
.ht-split { display:flex; gap:14px; flex-wrap:wrap; }
.ht-emp { flex:1; min-width:320px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:300px; }
.ht-emp-head { display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:#1b4332; color:#fff; font-size:12.5px; }
.ht-emp-list { overflow:auto; }
.ht-emp-row { display:flex; align-items:center; gap:8px; padding:4px 10px; border-bottom:1px solid #f1f5f9; font-size:12.5px; color:#1e293b; cursor:pointer; }
.ht-emp-row.on { background:#fff7cc; } .ht-emp-row .leg { width:48px; color:#64748b; } .ht-emp-row .nom { flex:1; }
.ht-gen { flex:1; min-width:280px; display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
.ht-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:55vh; }
.ht-tabla { width:100%; border-collapse:collapse; font-size:12px; }
.ht-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 7px; text-align:left; white-space:nowrap; }
.ht-tabla td { padding:4px 7px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .ht-tabla td.c { text-align:center; } .ht-tabla td.b { font-weight:700; }
.ht-tabla tbody tr.obs td { background:#e0f2fe; } .ht-tabla tbody tr.aj td { background:#fef3c7; }
.ht-vacio { color:#94a3b8; padding:14px; } .ht-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fef9c3; color:#854d0e; max-width:760px; }
.ht-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ht-pdf-md { width:min(1000px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ht-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.ht-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ht-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ht-pdf-b.ok { background:#22c55e; color:#fff; } .ht-pdf-b.cancel { background:#ef4444; color:#fff; }
.ht-pdf-frame { flex:1; border:none; width:100%; }
</style>
