<!-- HorasTrabajadasView.vue — Reloj / Consulta de Horas Trabajadas (reloj_horarios_trabajados). -->
<template>
  <div class="ht-view">
    <div class="ht-cab">
      <div class="ht-cab-ico">🕘</div>
      <div class="ht-cab-tx"><h1>Consulta de Horas Trabajadas</h1><p>Horas normales, extras 50/100, nocturnas y faltas por empleado y día</p></div>
      <button class="ht-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ht-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <HorasTrabajadasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/horas-trabajadas" titulo="Asistente IA — Horas Trabajadas"
            subtitulo="Preguntá sobre el cálculo de horas y turnos"
            :sugerencias="['¿Qué diferencia hay entre Estándar y Logística?','¿Cómo se calculan las horas al 50%?','¿Qué es el adicional nocturno?']"
            @close="modalIA = false" />

    <div class="ht-card">
      <!-- Filtros y traer empleados -->
      <div class="ht-toolbar">
        <div class="ht-f"><span class="ht-lbl">Empresa</span>
          <select v-model.number="filtros.empresa" class="ht-inp"><option :value="0">Todas</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
        </div>
        <div class="ht-f"><span class="ht-lbl">Contratista</span>
          <select v-model.number="filtros.contratista" class="ht-inp"><option :value="0">Todos</option><option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option></select>
        </div>
        <div class="ht-f"><span class="ht-lbl">Convenio</span>
          <select v-model.number="filtros.convenio" class="ht-inp"><option :value="0">Todos</option><option v-for="c in convenios" :key="c.cod" :value="c.cod">{{ c.nombre }}</option></select>
        </div>
        <div class="ht-f"><span class="ht-lbl">Orden</span>
          <select v-model.number="filtros.orden" class="ht-inp"><option :value="1">Alfabeto</option><option :value="2">Legajo</option><option :value="3">F. Ingreso</option><option :value="4">F. Nacimiento</option></select>
        </div>
        <button class="ht-btn ok" :disabled="cargandoEmp" @click="traerEmpleados">{{ cargandoEmp ? '⟳…' : 'Traer Empleados' }}</button>
        <input v-model="buscar" type="text" class="ht-inp" placeholder="Buscar en la lista…" />
      </div>

      <div class="ht-split">
        <!-- Lista de empleados -->
        <div class="ht-emp">
          <div class="ht-emp-head">
            <span>Empleados ({{ seleccionados }} / {{ empleadosFiltrados.length }}<template v-if="buscar.trim()"> filtrados</template>)</span>
            <div><button class="ht-btn-sm" @click="marcarTodos(true)">Todos</button><button class="ht-btn-sm" @click="marcarTodos(false)">Nada</button></div>
          </div>
          <div class="ht-emp-list">
            <label v-for="e in empleadosFiltrados" :key="e.cod" class="ht-emp-row" :class="{ on: e.sel }">
              <input type="checkbox" v-model="e.sel" />
              <span class="leg">{{ e.legajo }}</span><span class="nom">{{ e.nombre }}</span>
            </label>
            <p v-if="!empleados.length" class="ht-vacio">Usá “Traer Empleados” para listar.</p>
          </div>
        </div>

        <!-- Generación -->
        <div class="ht-gen">
          <div class="ht-f"><span class="ht-lbl">Desde</span><input v-model="fecha1" type="date" class="ht-inp" /></div>
          <div class="ht-f"><span class="ht-lbl">al</span><input v-model="fecha2" type="date" class="ht-inp" /></div>
          <label class="ht-chk"><input type="checkbox" v-model="resumen" /> Resumen - solo totales</label>
          <button v-if="!esLogistica" class="ht-btn ok blk" :disabled="!seleccionados || !!cargandoGen" @click="generar('estandar')">{{ cargandoGen === 'estandar' ? '⟳…' : 'Generar Turnos Estándar' }}</button>
          <button class="ht-btn ok blk" :disabled="!seleccionados || !!cargandoGen" @click="generar('logistica')">{{ cargandoGen === 'logistica' ? '⟳…' : 'Generar Turnos Logística' }}</button>
          <p class="ht-hint">Al generar se arma el PDF para descargar o imprimir; desde el visor también podés bajar el Excel.</p>
        </div>
      </div>

      <!-- Resultados -->
      <div v-if="resumen && resumenRows.length" class="ht-grid-wrap">
        <table class="ht-tabla">
          <thead><tr><th>Legajo</th><th>Empleado</th><th>Normal</th><th>50</th><th>100</th><th>Adic. Noct.</th><th>Extra Noct.</th><th>Total</th><th>Días</th></tr></thead>
          <tbody><tr v-for="r in resumenRows" :key="r.cod">
            <td>{{ r.legajo }}</td><td>{{ r.nombre }}</td><td class="c">{{ n(r.trab) }}</td><td class="c">{{ n(r.h50) }}</td><td class="c">{{ n(r.h100) }}</td>
            <td class="c">{{ n(r.adicNoc) }}</td><td class="c">{{ n(r.extraNoc) }}</td><td class="c b">{{ n(r.total) }}</td><td class="c">{{ r.dias }}</td>
          </tr></tbody>
        </table>
      </div>
      <div v-else-if="!resumen && detalle.length" class="ht-grid-wrap">
        <table class="ht-tabla">
          <thead><tr><th>Legajo</th><th>Empleado</th><th>Fecha</th><th>Día</th><th>Entra</th><th>Sale</th><th>C.Ent</th><th>C.Sal</th><th>Normal</th><th>50</th><th>100</th><th>A.Noc</th><th>E.Noc</th><th>Total</th></tr></thead>
          <tbody><tr v-for="(r, i) in detalle" :key="i" :class="{ falta: r.esFalta, feriado: r.feriado, finde: esFinde(r.fecha) }">
            <td>{{ r.legajo }}</td><td>{{ r.nombre }}</td><td>{{ fmt(r.fecha) }}</td><td>{{ diaCorto(r.dia) }} [T{{ r.turno }}/S{{ r.semana }}]</td>
            <td class="c">{{ hm(r.entra1) }}</td><td class="c">{{ hm(r.sale1) }}</td>
            <td v-if="r.esFalta" colspan="8" class="falta-tx">{{ r.queFalta }}</td>
            <template v-else>
              <td class="c">{{ hm(r.entraCal) }}</td><td class="c">{{ hm(r.saleCal) }}</td>
              <td class="c">{{ n(r.trab) }}</td><td class="c">{{ n(r.h50) }}</td><td class="c">{{ n(r.h100) }}</td><td class="c">{{ n(r.adicNoc) }}</td><td class="c">{{ n(r.extraNoc) }}</td><td class="c b">{{ n(r.total) }}</td>
            </template>
          </tr></tbody>
        </table>
      </div>
      <p v-if="msg" class="ht-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ht-pdf-ov" @click.self="cerrarPdf">
        <div class="ht-pdf-md">
          <div class="ht-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ht-pdf-acc">
              <button class="ht-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar PDF</button>
              <button class="ht-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ht-pdf-b excel" @click="exportarExcel">📊 Excel</button>
              <button class="ht-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ht-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import HorasTrabajadasAyuda from '@/components/HorasTrabajadasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
// En Logística no se muestra el botón "Estándar" (igual que Fox: solo Autoelevadores).
const esLogistica = computed(() => auth.empresa === 'logist')

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
// Período por defecto: del 20 del mes anterior al 19 del mes actual (como Fox).
const _h = new Date()
const _desde = new Date(_h.getFullYear(), _h.getMonth() - 1, 20)  // mes-1 se normaliza solo (enero -> diciembre año anterior)
const _hasta = new Date(_h.getFullYear(), _h.getMonth(), 19)
const fecha1 = ref(_iso(_desde)); const fecha2 = ref(_iso(_hasta)); const resumen = ref(false)

const empresas = ref<{ cod: number; nombre: string }[]>([])
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const convenios = ref<{ cod: number; nombre: string }[]>([])
const filtros = reactive({ empresa: 0, contratista: 0, convenio: 0, orden: 1 })
interface Emp { cod: number; legajo: number; nombre: string; sel: boolean }
const empleados = ref<Emp[]>([]); const buscar = ref('')
const cargandoEmp = ref(false); const cargandoGen = ref<'' | 'estandar' | 'logistica'>(''); const msg = ref('')
const detalle = ref<any[]>([]); const resumenRows = ref<any[]>([]); const cabeceras = ref<Record<string, any>>({})

const empleadosFiltrados = computed(() => { const q = buscar.value.trim().toLowerCase(); return q ? empleados.value.filter(e => e.nombre.toLowerCase().includes(q) || String(e.legajo).includes(q)) : empleados.value })
// Se trabaja siempre sobre lo que se ve en la lista: si hay un texto en "Buscar",
// generar/Todos/Nada aplican solo a los empleados visibles y seleccionados.
const aGenerar = computed(() => empleadosFiltrados.value.filter(e => e.sel))
const seleccionados = computed(() => aGenerar.value.length)

const n = (v: number) => v ? v.toFixed(1) : ''
const fmt = (iso: string) => { if (!iso) return ''; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }
const hm = (v: number) => v ? `${String(Math.floor(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}` : ''
const diaCorto = (d: string) => (d || '').slice(0, 3)
const esFinde = (iso: string) => { if (!iso) return false; const d = new Date(iso + 'T00:00:00').getDay(); return d === 0 || d === 6 }

onMounted(async () => {
  try { const data = (await api.get('/empresas')).data; const arr = Array.isArray(data) ? data : (data.data ?? []); empresas.value = arr.map((e: any) => ({ cod: Number(e.EMP_COD ?? e.cod), nombre: (e.EMP_NOM ?? e.nombre ?? '').toString().trim() })) } catch { /* */ }
  // Catálogos para los filtros de Contratista y Convenio (mismo endpoint que usa Crear Planilla).
  try {
    const { data } = await api.get('/empleados/opciones')
    contratistas.value = (data.contratistas ?? []).map((c: any) => ({ cod: Number(c.CONT_COD), nombre: (c.CONT_DET ?? '').toString().trim() }))
    convenios.value = (data.convenios ?? []).map((c: any) => ({ cod: Number(c.CON_COD), nombre: (c.CON_DES ?? '').toString().trim() }))
  } catch { /* */ }
})

const traerEmpleados = async () => {
  cargandoEmp.value = true; msg.value = ''
  try {
    const params: any = { orden: filtros.orden }
    if (filtros.empresa) params.empresa = filtros.empresa
    if (filtros.contratista) params.contratista = filtros.contratista
    if (filtros.convenio) params.convenio = filtros.convenio
    empleados.value = (await api.get('/reloj/horas-trabajadas/empleados', { params })).data.empleados ?? []
    if (!empleados.value.length) msg.value = 'No se encuentran empleados en el esquema consultado.'
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudieron traer los empleados.' }
  finally { cargandoEmp.value = false }
}
const marcarTodos = (v: boolean) => empleadosFiltrados.value.forEach(e => e.sel = v)

const generar = async (modo: 'estandar' | 'logistica') => {
  const codigos = aGenerar.value.map(e => e.cod)
  if (!codigos.length) return
  cargandoGen.value = modo; msg.value = ''; detalle.value = []; resumenRows.value = []
  try {
    const { data } = await api.post('/reloj/horas-trabajadas/generar', { codigos, fecha1: fecha1.value, fecha2: fecha2.value, modo })
    detalle.value = data.detalle ?? []; resumenRows.value = data.resumen ?? []; cabeceras.value = data.cabeceras ?? {}
    const hayDatos = resumen.value ? resumenRows.value.length : detalle.value.length
    if (!hayDatos) { msg.value = 'No se generaron datos para el período.' }
    else { imprimir() }   // arma el PDF directamente (el usuario descarga/imprime a gusto)
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo generar el cálculo.' }
  finally { cargandoGen.value = '' }
}

const imprimir = () => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const PW = 297, ML = 12, MR = PW - 12

  if (resumen.value) {
    // Resumen general: una tabla corrida (no cambia).
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
    doc.text('Horas Trabajadas — Resumen General', ML, 14)
    doc.setFontSize(9); doc.setFont('helvetica', 'normal')
    doc.text(`Desde el ${fmt(fecha1.value)} hasta el ${fmt(fecha2.value)}`, ML, 20)
    let y = 30
    const cols = ['Legajo', 'Empleado', 'Normal', '50', '100', 'A.Noct', 'E.Noct', 'Total', 'Días']
    const xs = [14, 34, 150, 172, 190, 208, 230, 252, 272]
    doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 6
    for (const r of resumenRows.value) {
      if (y > 195) { doc.addPage(); y = 20 }
      const row = [String(r.legajo), (r.nombre || '').slice(0, 38), n(r.trab), n(r.h50), n(r.h100), n(r.adicNoc), n(r.extraNoc), n(r.total), String(r.dias)]
      row.forEach((c, i) => doc.text(String(c), xs[i], y)); y += 5
    }
    cerrarPdf(); pdfNombre.value = 'HORAS_TRABAJADAS_RESUMEN.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
    return
  }

  // ── Detalle: UN EMPLEADO POR PÁGINA, con grilla cuadriculada (como Fox) ──
  const grupos: Record<string, any[]> = {}; const orden: string[] = []
  for (const r of detalle.value) {
    const k = String(r.cod ?? r.legajo)
    if (!grupos[k]) { grupos[k] = []; orden.push(k) }
    grupos[k].push(r)
  }
  // "Página X de Y" se escribe al final (recién ahí se sabe cuántas hojas usó cada empleado).
  const marcasPag: { page: number; emp: string; x: number; y: number }[] = []
  // Bordes verticales de las 15 columnas (x en mm) — de 8 a 289.
  const bx = [8, 30, 64, 78, 92, 106, 120, 138, 156, 172, 189, 205, 219, 233, 261, 289]
  const heads: string[][] = [['Fecha'], ['Día'], ['Entra'], ['Sale'], ['Entra'], ['Sale'], ['Calc.', 'Entra'], ['Calc.', 'Sale'],
    ['TOTAL'], ['Horas', 'No Trab.'], ['Normal'], ['50'], ['100'], ['Adicional', 'Nocturno'], ['Extra', 'Noct.']]
  const cxc = (i: number) => (bx[i] + bx[i + 1]) / 2
  const cC = (t: any, i: number, yy: number) => { if (t !== '' && t != null) doc.text(String(t), cxc(i), yy, { align: 'center' }) }
  const cL = (t: any, i: number, yy: number) => { if (t !== '' && t != null) doc.text(String(t), bx[i] + 1.5, yy) }
  const rowH = 4.0   // compacto: un mes típico (30-31 días) + pie entra en una hoja A4 apaisada
  const drawVerts = (top: number, bottom: number) => { doc.setDrawColor(0); doc.setLineWidth(0.3); for (const x of bx) doc.line(x, top, x, bottom) }

  // Color de fondo del renglón: licencia/falta gris, feriado rosado, sábado/domingo gris claro.
  const colorFila = (r: any): [number, number, number] | null => {
    if (r.esFalta) return [255, 238, 130]   // licencia/vacaciones: amarillo
    if (r.feriado) return [252, 218, 218]
    const dow = new Date(r.fecha + 'T00:00:00').getDay()   // 0=Dom, 6=Sáb
    if (dow === 0 || dow === 6) return [204, 204, 204]      // sábado/domingo: gris
    return null
  }

  const dibujarCab = (cab: any, emp: string) => {
    let y = 12
    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(0, 0, 0)
    doc.text('HORAS TRABAJADAS', PW / 2, y, { align: 'center' })
    marcasPag.push({ page: doc.getNumberOfPages(), emp, x: 289, y })
    doc.setFont('helvetica', 'normal'); y += 3
    // Barra legajo/nombre
    doc.setFillColor(222, 222, 222); doc.setDrawColor(0); doc.setLineWidth(0.3)
    doc.rect(8, y, 281, 6, 'FD')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5)
    doc.text('Legajo', 12, y + 4); doc.text(String(cab.legajo ?? ''), 42, y + 4)
    doc.text((cab.nombre || '').trim(), 62, y + 4)
    doc.setFontSize(8.5); doc.text('Días Feriados', 287, y + 4, { align: 'right' })
    y += 6
    // Caja de datos del empleado
    const boxTop = y
    const campos: [number, string, string][][] = [
      [[12, 'Código', String(cab.codigo ?? '')], [110, 'Ingreso', cab.ingreso || ''], [172, 'Nacimiento', cab.nacimiento || ''], [236, 'Baja', cab.baja || '']],
      [[12, 'Domicilio', cab.domicilio || ''], [110, 'Convenio', cab.convenio || ''], [172, 'Documento', `${cab.tdoc || ''} ${cab.ndoc || ''}`.trim()], [236, 'CUIL', cab.cuil || '']],
      [[12, 'Localidad', cab.localidad || ''], [110, 'Categoría', cab.categoria || ''], [172, 'Teléfonos', cab.telefono || ''], [236, 'Celular', cab.celular || '']],
      [[12, 'Empresa', cab.empresa || ''], [110, 'CBU', cab.cbu || ''], [172, 'Estado Civil', cab.estadoCivil || ''], [236, 'Conyuge', cab.conyuge || '']],
    ]
    const colNext = [110, 172, 236, 289]  // límite derecho de cada columna
    doc.setFontSize(7.5); let yy = y + 3.6
    for (const fila of campos) {
      fila.forEach(([x, lab, val], ci) => {
        doc.setFont('helvetica', 'bold'); doc.text(lab, x, yy)
        const vx = x + doc.getTextWidth(lab) + 2   // valor pegado al título
        doc.setFont('helvetica', 'normal')
        const maxW = Math.max(6, colNext[ci] - vx - 2)
        doc.text(doc.splitTextToSize(String(val), maxW)[0] || '', vx, yy)
      })
      yy += 4
    }
    const boxBottom = yy - 0.5
    doc.setDrawColor(0); doc.setLineWidth(0.3); doc.rect(8, boxTop, 281, boxBottom - boxTop)
    y = boxBottom + 1.5
    // Encabezado de columnas
    const headTop = y
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7)
    heads.forEach((h, i) => h.forEach((ln, li) => doc.text(ln, cxc(i), y + 3 + li * 2.8, { align: 'center' })))
    const headBottom = y + 7
    doc.setLineWidth(0.3); doc.setDrawColor(0)
    doc.line(8, headTop, 289, headTop); doc.line(8, headBottom, 289, headBottom)
    doc.setFont('helvetica', 'normal')
    return { y: headBottom, headTop }
  }

  orden.forEach((k, idx) => {
    if (idx > 0) doc.addPage()
    const rows = grupos[k]; const cab = cabeceras.value[k] || rows[0]
    let { y, headTop } = dibujarCab(cab, k); let curTop = headTop
    let sTrab = 0, s50 = 0, s100 = 0, sANoc = 0, sENoc = 0, sTotal = 0, dias = 0, entTarde = 0, salTarde = 0
    // Filas de licencia/falta: el texto va desde Calc.Entra y se borran las verticales interiores
    // de ese renglón (se reponen DESPUÉS de dibujar la grilla, para que no lo pisen).
    let segFaltas: { top: number; text: string; bg: [number, number, number] }[] = []
    const flushFaltas = () => {
      for (const f of segFaltas) {
        doc.setFillColor(f.bg[0], f.bg[1], f.bg[2])
        doc.rect(bx[6] + 0.2, f.top + 0.15, 289 - bx[6] - 0.4, rowH - 0.3, 'F')  // borra verticales interiores
        doc.setDrawColor(205); doc.setLineWidth(0.2)
        doc.line(bx[6], f.top, 289, f.top); doc.line(bx[6], f.top + rowH, 289, f.top + rowH)
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(0, 0, 0)
        doc.text(f.text, bx[6] + 1.5, f.top + 2.9)
      }
      segFaltas = []
    }
    doc.setFontSize(7.5)
    for (const r of rows) {
      if (y + rowH > 201) {
        drawVerts(curTop, y); flushFaltas()
        doc.addPage()
        const c = dibujarCab(cab, k); y = c.y; curTop = c.headTop; doc.setFontSize(7.5)
      }
      const base = y + 2.9
      const bg = colorFila(r)
      if (bg) { doc.setFillColor(bg[0], bg[1], bg[2]); doc.rect(8, y, 281, rowH, 'F') }
      // Fecha, día y marcaciones reales se muestran SIEMPRE (aunque haya licencia/permiso).
      cL(fmt(r.fecha), 0, base); cL(`${diaCorto(r.dia)} [T${r.turno}/S${r.semana}]`, 1, base)
      cC(hm(r.entra1), 2, base); cC(hm(r.sale1), 3, base); cC(hm(r.entra2), 4, base); cC(hm(r.sale2), 5, base)
      if (r.esFalta) {
        segFaltas.push({ top: y, text: (r.queFalta || '').slice(0, 110), bg: bg ?? [255, 255, 255] })
        if (r.entra1) dias++
      } else {
        cC(hm(r.entraCal), 6, base); cC(hm(r.saleCal), 7, base)
        cC(n(r.total), 8, base); cC(n(r.minutosTarde), 9, base); cC(n(r.trab), 10, base)
        cC(n(r.h50), 11, base); cC(n(r.h100), 12, base); cC(n(r.adicNoc), 13, base); cC(n(r.extraNoc), 14, base)
        sTrab += r.trab || 0; s50 += r.h50 || 0; s100 += r.h100 || 0; sANoc += r.adicNoc || 0; sENoc += r.extraNoc || 0; sTotal += r.total || 0
        if ((r.total || 0) > 0 || r.entra1) dias++
      }
      const me = (r.entra1 || 0) % 100; if (me > 0 && me < 16) entTarde += me
      const ms = (r.sale1 || 0) % 100; if (ms > 0 && ms < 30) salTarde += ms
      y += rowH
      doc.setDrawColor(205); doc.setLineWidth(0.2); doc.line(8, y, 289, y)
    }
    // Fila de totales dentro de la grilla
    doc.setDrawColor(0); doc.setLineWidth(0.3); doc.line(8, y, 289, y)
    const bt = y + 2.9; doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5)
    cL('TOTALES', 0, bt)
    cC(n(sTotal), 8, bt); cC(n(sTrab), 10, bt); cC(n(s50), 11, bt); cC(n(s100), 12, bt); cC(n(sANoc), 13, bt); cC(n(sENoc), 14, bt)
    y += rowH; doc.line(8, y, 289, y); drawVerts(curTop, y); flushFaltas()
    doc.setFont('helvetica', 'normal')
    // Pie: días trabajados, total y minutos de tardanza (como Fox).
    // Si no entra el bloque completo (~30mm), sigue en una página nueva para que
    // la Diferencia (el dato clave) no quede fuera de la hoja.
    if (y + 24 > 205) { doc.addPage(); marcasPag.push({ page: doc.getNumberOfPages(), emp: k, x: 289, y: 12 }); y = 16 }
    y += 6; doc.setFontSize(9)
    doc.setFont('helvetica', 'bold'); doc.text(`Total de días trabajados =  ${dias}`, 12, y)
    doc.setFillColor(222, 222, 222); doc.rect(90, y - 4.5, 78, 6.5, 'F')
    doc.text('TOTAL DE HORAS TRABAJADAS', 93, y); doc.text(`${n(sTotal) || '0.0'}`, 165, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); y += 6
    const lx = 92, vx = 96   // etiquetas alineadas a la derecha en lx, valores en vx (columna)
    doc.text('Suma de minutos entrada tarde (1 a 15) =', lx, y, { align: 'right' }); doc.text(String(entTarde), vx, y); y += 4
    doc.text('Suma de minutos salida tarde (1 a 30) =', lx, y, { align: 'right' }); doc.text(String(salTarde), vx, y); y += 1.8
    doc.setDrawColor(0); doc.setLineWidth(0.3); doc.line(vx - 2, y, vx + 14, y); y += 3.6
    doc.setFont('helvetica', 'bold'); doc.text('Diferencias en minutos =', lx, y, { align: 'right' }); doc.text(String(entTarde - salTarde), vx, y)
    doc.setFont('helvetica', 'normal')
  })

  // "Página X de Y" — Y = hojas reales que ocupó ese empleado.
  const hojasPorEmp: Record<string, number> = {}
  for (const m of marcasPag) hojasPorEmp[m.emp] = (hojasPorEmp[m.emp] ?? 0) + 1
  const vistas: Record<string, number> = {}
  doc.setFont('helvetica', 'italic'); doc.setFontSize(9); doc.setTextColor(0, 0, 0)
  for (const m of marcasPag) {
    vistas[m.emp] = (vistas[m.emp] ?? 0) + 1
    doc.setPage(m.page)
    doc.text(`Página ${vistas[m.emp]} de ${hojasPorEmp[m.emp]}`, m.x, m.y, { align: 'right' })
  }

  cerrarPdf(); pdfNombre.value = 'HORAS_TRABAJADAS.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const exportarExcel = async () => {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  let head: string[], rows: any[][]
  if (resumen.value) {
    head = ['Legajo', 'Empleado', 'Normal', 'Hs 50', 'Hs 100', 'Adic Noct', 'Extra Noct', 'Total', 'Días']
    rows = resumenRows.value.map(r => [r.legajo, r.nombre, n(r.trab), n(r.h50), n(r.h100), n(r.adicNoc), n(r.extraNoc), n(r.total), r.dias])
  } else {
    head = ['Legajo', 'Fecha', 'Ent1', 'Sal1', 'Ent2', 'Sal2', 'Cal Ent', 'Cal Sal', 'Horas', 'Hs 50', 'Hs 100', 'Adic Noct', 'Extra Noct', 'Empresa', 'Código', 'Nombre', 'Tipo Doc', 'Nro Doc', 'CUIL']
    rows = detalle.value.map(r => [r.legajo, fmt(r.fecha), hm(r.entra1), hm(r.sale1), hm(r.entra2), hm(r.sale2), hm(r.entraCal), hm(r.saleCal), n(r.trab), n(r.h50), n(r.h100), n(r.adicNoc), n(r.extraNoc), r.empresa, r.cod, r.nombre, r.tdoc, r.ndoc, r.cuil])
  }
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), 'HORAS_TRABAJADAS.xls')
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
.ht-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; padding-bottom:7px; }
.ht-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .ht-btn.excel { background:#107c41; } .ht-btn.blk { background:#1b4332; } .ht-btn:disabled { background:#cbd5e1; cursor:default; }
.ht-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:4px 10px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; margin-left:6px; }
.ht-split { display:flex; gap:14px; flex-wrap:wrap; }
.ht-emp { flex:1; min-width:320px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:300px; }
.ht-emp-head { display:flex; align-items:center; justify-content:space-between; padding:7px 10px; background:#1b4332; color:#fff; font-size:12.5px; }
.ht-emp-list { overflow:auto; }
.ht-emp-row { display:flex; align-items:center; gap:8px; padding:4px 10px; border-bottom:1px solid #f1f5f9; font-size:12.5px; color:#1e293b; cursor:pointer; }
.ht-emp-row.on { background:#fff7cc; } .ht-emp-row .leg { width:48px; color:#64748b; } .ht-emp-row .nom { flex:1; }
.ht-gen { flex:1; min-width:280px; display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
.ht-genacc { display:flex; gap:8px; }
.ht-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:52vh; }
.ht-tabla { width:100%; border-collapse:collapse; font-size:12px; }
.ht-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 7px; text-align:left; white-space:nowrap; }
.ht-tabla td { padding:4px 7px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .ht-tabla td.c { text-align:center; } .ht-tabla td.b { font-weight:700; }
.ht-tabla tbody tr.finde td { background:#cccccc; }
.ht-tabla tbody tr.falta td { background:#ffee82; color:#334155; } .ht-tabla tbody tr.feriado td { background:#fcdada; } .ht-tabla .falta-tx { font-style:italic; }
.ht-vacio { color:#94a3b8; padding:14px; } .ht-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fef9c3; color:#854d0e; max-width:760px; }
.ht-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ht-pdf-md { width:min(1000px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ht-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.ht-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ht-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ht-pdf-b.ok { background:#22c55e; color:#fff; } .ht-pdf-b.excel { background:#107c41; color:#fff; } .ht-pdf-b.cancel { background:#ef4444; color:#fff; }
.ht-hint { font-size:12px; color:#64748b; margin:2px 0 0; max-width:320px; }
.ht-pdf-frame { flex:1; border:none; width:100%; }
</style>
