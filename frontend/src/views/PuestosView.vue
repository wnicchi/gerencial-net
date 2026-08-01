<!-- PuestosView.vue — Puestos de Trabajo (ABM 7 solapas) -->
<template>
  <div class="pu-view">
    <div class="pu-cab">
      <div class="pu-ico">💼</div>
      <div class="pu-tx"><h1>Puestos de Trabajo</h1><p>Descripción de puestos · 7 solapas</p></div>
      <button class="pu-nuevo" @click="nuevo">＋ Nuevo puesto</button>
    </div>

    <transition name="fade"><div v-if="msg" :class="['pu-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="pu-layout">
      <!-- Lista -->
      <div class="pu-list">
        <input v-model="busqueda" class="pu-search" placeholder="🔍 Buscar puesto…" @input="cargarLista" />
        <div class="pu-list-body">
          <button v-for="p in lista" :key="p.codigo" class="pu-item" :class="{ on: sel === p.codigo }" @click="elegir(p.codigo)">
            <span class="pu-cod">{{ p.codigo }}</span>
            <span class="pu-des">{{ p.descripcion }}</span>
            <span class="pu-dep">{{ p.depto }}</span>
          </button>
          <p v-if="!lista.length" class="pu-vacio">Sin puestos.</p>
        </div>
      </div>

      <!-- Detalle -->
      <div class="pu-detalle" v-if="sel || modoNuevo">
        <div class="pu-tabs">
          <button v-for="t in tabs" :key="t.id" class="pu-tab" :class="{ on: tab === t.id }" :disabled="modoNuevo && t.id !== 'detalles'" @click="tab = t.id">{{ t.icono }} {{ t.label }}</button>
        </div>

        <!-- DETALLES -->
        <div v-show="tab === 'detalles'" class="pu-pane">
          <div class="pu-grid">
            <div class="campo"><label>Código *</label><input v-model="d.codigo" :disabled="!modoNuevo" maxlength="15" @input="d.codigo = d.codigo.toUpperCase()" /></div>
            <div class="campo"><label>Fecha de Inicio</label><input v-model="d.fecha" type="date" :disabled="!editable" /></div>
            <div class="campo col2">
              <label>Descripción *</label><input v-model="d.descripcion" :disabled="!editable" :class="{ exc: excede('descripcion') }" />
              <span v-if="editable" class="cont" :class="{ over: excede('descripcion') }">{{ (d.descripcion || '').length }} / {{ LIMS.descripcion }}</span>
            </div>
            <div class="campo"><label>Departamento</label>
              <select v-model.number="d.departamento" :disabled="!editable"><option :value="0">— —</option><option v-for="x in cat.departamentos" :key="x.cod" :value="x.cod">{{ x.des }}</option></select>
            </div>
            <div class="campo">
              <label>Reporta a</label><input v-model="d.reporta" :disabled="!editable" :class="{ exc: excede('reporta') }" />
              <span v-if="editable" class="cont" :class="{ over: excede('reporta') }">{{ (d.reporta || '').length }} / {{ LIMS.reporta }}</span>
            </div>
            <div class="campo col2">
              <label>Objetivo</label><textarea v-model="d.objetivo" rows="3" :disabled="!editable" :class="{ exc: excede('objetivo') }"></textarea>
              <span v-if="editable" class="cont" :class="{ over: excede('objetivo') }">{{ (d.objetivo || '').length }} / {{ LIMS.objetivo }}</span>
            </div>
            <div class="campo col2">
              <label>Modificaciones</label><input v-model="d.modificaciones" :disabled="!editable" :class="{ exc: excede('modificaciones') }" />
              <span v-if="editable" class="cont" :class="{ over: excede('modificaciones') }">{{ (d.modificaciones || '').length }} / {{ LIMS.modificaciones }}</span>
            </div>
            <div class="campo col2">
              <label>Requisitos Legales</label><input v-model="d.requisitos" :disabled="!editable" :class="{ exc: excede('requisitos') }" />
              <span v-if="editable" class="cont" :class="{ over: excede('requisitos') }">{{ (d.requisitos || '').length }} / {{ LIMS.requisitos }}</span>
            </div>
          </div>
          <div class="pu-acc">
            <button v-if="modoNuevo" class="btn ok" :disabled="proc || algunoExcede" @click="guardarDetalle">💾 Crear</button>
            <button v-else-if="!editando" class="btn ok" @click="editando = true">✏️ Editar</button>
            <button v-else class="btn ok" :disabled="proc || algunoExcede" @click="guardarDetalle">💾 Guardar</button>
            <span v-if="editable && algunoExcede" class="pu-exc-msg">⚠ Hay campos que superan el máximo permitido.</span>
            <button v-if="!modoNuevo && !editando" class="btn del" :disabled="proc" @click="eliminar">🗑 Eliminar</button>
            <template v-if="!modoNuevo">
              <button class="btn inf" @click="pdfInforme">📄 Informe Completo</button>
              <button class="btn hoja" @click="pdfHoja">📋 Hoja Descripción</button>
            </template>
          </div>
        </div>

        <!-- TAREAS -->
        <div v-show="tab === 'tareas'" class="pu-pane">
          <table class="pu-tabla">
            <thead><tr><th>Tarea</th><th>Frecuencia</th><th>Tiempo (min)</th><th></th></tr></thead>
            <tbody>
              <tr v-for="(t, i) in tareas" :key="i">
                <td><input v-model="t.des" maxlength="100" /></td>
                <td><select v-model.number="t.fre"><option :value="0">— —</option><option v-for="f in cat.frecuencias" :key="f.cod" :value="f.cod">{{ f.des }}</option></select></td>
                <td><input v-model.number="t.min" type="number" min="0" class="inp-min" /></td>
                <td><button class="x" @click="tareas.splice(i,1)">✕</button></td>
              </tr>
              <tr v-if="!tareas.length"><td colspan="4" class="pu-tvacio">Sin tareas.</td></tr>
            </tbody>
          </table>
          <div class="pu-acc"><button class="btn add" @click="tareas.push({des:'',fre:0,min:0})">＋ Agregar tarea</button><button class="btn ok" :disabled="proc" @click="guardarSub('tareas', tareas)">💾 Guardar tareas</button></div>
        </div>

        <!-- REQUERIMIENTO EDUCACIONAL -->
        <div v-show="tab === 'educacion'" class="pu-pane">
          <table class="pu-tabla">
            <thead><tr><th>Descripción</th><th>Académica</th><th>Complementaria</th><th></th></tr></thead>
            <tbody>
              <tr v-for="(e, i) in educacion" :key="i">
                <td><input v-model="e.des" maxlength="50" /></td>
                <td class="c"><input type="checkbox" v-model="e.aca" /></td>
                <td class="c"><input type="checkbox" v-model="e.com" /></td>
                <td><button class="x" @click="educacion.splice(i,1)">✕</button></td>
              </tr>
              <tr v-if="!educacion.length"><td colspan="4" class="pu-tvacio">Sin requisitos educacionales.</td></tr>
            </tbody>
          </table>
          <div class="pu-acc"><button class="btn add" @click="educacion.push({des:'',aca:true,com:false})">＋ Agregar</button><button class="btn ok" :disabled="proc" @click="guardarSub('educacion', educacion)">💾 Guardar</button></div>
        </div>

        <!-- CUALIDADES -->
        <div v-show="tab === 'cualidades'" class="pu-pane">
          <ul class="pu-simple">
            <li v-for="(s, i) in cualidades" :key="i"><input v-model="cualidades[i]" maxlength="100" /><button class="x" @click="cualidades.splice(i,1)">✕</button></li>
            <li v-if="!cualidades.length" class="pu-tvacio2">Sin ítems.</li>
          </ul>
          <div class="pu-acc"><button class="btn add" @click="cualidades.push('')">＋ Agregar</button><button class="btn ok" :disabled="proc" @click="guardarSub('cualidades', cualidades)">💾 Guardar</button></div>
        </div>

        <!-- COMPETENCIAS -->
        <div v-show="tab === 'competencias'" class="pu-pane">
          <ul class="pu-simple">
            <li v-for="(s, i) in competencias" :key="i"><input v-model="competencias[i]" maxlength="100" /><button class="x" @click="competencias.splice(i,1)">✕</button></li>
            <li v-if="!competencias.length" class="pu-tvacio2">Sin ítems.</li>
          </ul>
          <div class="pu-acc"><button class="btn add" @click="competencias.push('')">＋ Agregar</button><button class="btn ok" :disabled="proc" @click="guardarSub('competencias', competencias)">💾 Guardar</button></div>
        </div>

        <!-- ELEMENTOS -->
        <div v-show="tab === 'elementos'" class="pu-pane">
          <ul class="pu-simple">
            <li v-for="(s, i) in elementos" :key="i"><input v-model="elementos[i]" maxlength="100" /><button class="x" @click="elementos.splice(i,1)">✕</button></li>
            <li v-if="!elementos.length" class="pu-tvacio2">Sin ítems.</li>
          </ul>
          <div class="pu-acc"><button class="btn add" @click="elementos.push('')">＋ Agregar</button><button class="btn ok" :disabled="proc" @click="guardarSub('elementos', elementos)">💾 Guardar</button></div>
        </div>

        <!-- REVISIONES -->
        <div v-show="tab === 'revisiones'" class="pu-pane">
          <div class="pu-rev-form">
            <input v-model="revNueva.fre" type="date" />
            <input v-model="revNueva.res" placeholder="Responsable" maxlength="100" />
            <input v-model="revNueva.ob1" placeholder="Observación 1" maxlength="100" />
            <input v-model="revNueva.ob2" placeholder="Observación 2" maxlength="100" />
            <button class="btn add" @click="agregarRev">＋ Agregar revisión</button>
          </div>
          <table class="pu-tabla">
            <thead><tr><th>Fecha</th><th>Responsable</th><th>Observaciones</th><th></th></tr></thead>
            <tbody>
              <tr v-for="(r, i) in revisiones" :key="i">
                <td>{{ r.fre }}</td><td>{{ r.res }}</td>
                <td>{{ [r.ob1, r.ob2, r.ob3, r.ob4].filter(Boolean).join(' · ') }}</td>
                <td><button class="x" @click="revisiones.splice(i,1)">✕</button></td>
              </tr>
              <tr v-if="!revisiones.length"><td colspan="4" class="pu-tvacio">Sin revisiones.</td></tr>
            </tbody>
          </table>
          <div class="pu-acc"><button class="btn ok" :disabled="proc" @click="guardarSub('revisiones', revisiones)">💾 Guardar revisiones</button></div>
        </div>
      </div>
      <div class="pu-detalle pu-vacia" v-else><div class="pu-sinsel">👈 Elegí un puesto o creá uno nuevo</div></div>
    </div>

    <!-- PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pu-pdf-ov" @click.self="cerrarPdf">
        <div class="pu-pdf-md">
          <div class="pu-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="pu-pdf-acc">
              <button class="pu-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="pu-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="pu-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="pu-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const logoDataUrl = ref('')
async function cargarLogo () {
  try {
    const resp = await fetch(auth.logoEmpresa)
    const blob = await resp.blob()
    logoDataUrl.value = await new Promise<string>((res) => { const fr = new FileReader(); fr.onload = () => res(fr.result as string); fr.readAsDataURL(blob) })
  } catch { /* sin logo */ }
}

const tabs = [
  { id: 'detalles', label: 'Detalles', icono: '📝' },
  { id: 'tareas', label: 'Tareas', icono: '📋' },
  { id: 'educacion', label: 'Req. Educacional', icono: '🎓' },
  { id: 'cualidades', label: 'Cualidades', icono: '⭐' },
  { id: 'competencias', label: 'Competencias', icono: '🏅' },
  { id: 'elementos', label: 'Elementos', icono: '🧰' },
  { id: 'revisiones', label: 'Revisiones', icono: '🔄' },
] as const
type TabId = typeof tabs[number]['id']
const tab = ref<TabId>('detalles')

const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

interface Item { codigo: string; descripcion: string; depto: string }
const lista = ref<Item[]>([]); const busqueda = ref(''); const sel = ref(''); const modoNuevo = ref(false); const proc = ref(false)
const editando = ref(false)   // Detalles: al seleccionar queda en solo lectura; "Editar" habilita
const editable = computed(() => modoNuevo.value || editando.value)
// Límites de cada campo (tamaño real de las columnas) para avisar en vivo si se excede.
const LIMS: Record<string, number> = { descripcion: 50, reporta: 50, objetivo: 2000, modificaciones: 100, requisitos: 100 }
const excede = (campo: string) => String((d as any)[campo] ?? '').length > LIMS[campo]
const algunoExcede = computed(() => Object.keys(LIMS).some(c => excede(c)))
const cat = reactive<{ departamentos: any[]; frecuencias: any[] }>({ departamentos: [], frecuencias: [] })

const d = reactive({ codigo: '', descripcion: '', departamento: 0, reporta: '', objetivo: '', modificaciones: '', requisitos: '', fecha: '' })
const tareas = ref<any[]>([]); const educacion = ref<any[]>([])
const cualidades = ref<string[]>([]); const competencias = ref<string[]>([]); const elementos = ref<string[]>([])
const revisiones = ref<any[]>([])
const revNueva = reactive({ fre: '', res: '', ob1: '', ob2: '', ob3: '', ob4: '' })
const deptoNombre = ref('')

onMounted(async () => { await Promise.all([cargarLista(), cargarCatalogos(), cargarLogo()]) })

let dq: any = null
async function cargarLista () {
  clearTimeout(dq)
  dq = setTimeout(async () => {
    try { lista.value = (await api.get('/puestos', { params: { buscar: busqueda.value } })).data ?? [] } catch { /* */ }
  }, 200)
}
async function cargarCatalogos () { try { const c = (await api.get('/puestos/catalogos')).data; cat.departamentos = c.departamentos; cat.frecuencias = c.frecuencias } catch { /* */ } }

async function elegir (cod: string) {
  try {
    const { data } = await api.get(`/puestos/${encodeURIComponent(cod)}`)
    sel.value = cod; modoNuevo.value = false; editando.value = false; tab.value = 'detalles'
    Object.assign(d, data.puesto, { departamento: data.puesto.departamento })
    deptoNombre.value = data.puesto.depto_nombre
    tareas.value = data.tareas.map((t: any) => ({ ...t }))
    educacion.value = data.educacion.map((e: any) => ({ ...e }))
    cualidades.value = [...data.cualidades]
    competencias.value = [...data.competencias]
    elementos.value = [...data.elementos]
    revisiones.value = data.revisiones.map((r: any) => ({ ...r }))
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el puesto.', true) }
}

function nuevo () {
  modoNuevo.value = true; sel.value = ''; tab.value = 'detalles'
  Object.assign(d, { codigo: '', descripcion: '', departamento: 0, reporta: '', objetivo: '', modificaciones: '', requisitos: '', fecha: '' })
  tareas.value = []; educacion.value = []; cualidades.value = []; competencias.value = []; elementos.value = []; revisiones.value = []
}

async function guardarDetalle () {
  if (!d.descripcion.trim()) return flash('Indicá la descripción.', true)
  if (modoNuevo.value && !d.codigo.trim()) return flash('Indicá el código.', true)
  if (algunoExcede.value) return flash('Hay campos que superan el máximo permitido. Acortalos antes de guardar.', true)
  proc.value = true
  try {
    if (modoNuevo.value) { await api.post('/puestos', { ...d }); await cargarLista(); await elegir(d.codigo); flash('Puesto creado.') }
    else { await api.put(`/puestos/${encodeURIComponent(sel.value)}`, { ...d }); await cargarLista(); editando.value = false; flash('Puesto guardado.') }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}

async function eliminar () {
  if (!confirm(`¿Elimina el puesto "${d.descripcion}" y todas sus colecciones?`)) return
  proc.value = true
  try { await api.delete(`/puestos/${encodeURIComponent(sel.value)}`); sel.value = ''; await cargarLista(); flash('Puesto eliminado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { proc.value = false }
}

async function guardarSub (id: string, items: any) {
  proc.value = true
  try {
    await api.put(`/puestos/${encodeURIComponent(sel.value)}/${id}`, { items })
    flash('Cambios guardados.'); await elegir(sel.value)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}

function agregarRev () {
  if (!revNueva.fre) return flash('Indicá la fecha de la revisión.', true)
  if (!revNueva.res.trim()) return flash('Indicá el responsable.', true)
  revisiones.value.unshift({ ...revNueva })
  Object.assign(revNueva, { fre: '', res: '', ob1: '', ob2: '', ob3: '', ob4: '' })
}

// ── PDFs ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const fmtFecha = (f: string) => f ? f.split('-').reverse().join('/') : ''

function pdfInforme () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 16, PW = 210, PH = 297; let y = 18
  const salto = () => { if (y > PH - 22) { doc.addPage(); y = 18 } }
  const titulo = (t: string) => { salto(); doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50); doc.text(t, ML, y); doc.setTextColor(0, 0, 0); y += 6 }
  const linea = (l: string, v: string) => { salto(); doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(l, ML, y); doc.setFont('helvetica', 'normal'); doc.text(doc.splitTextToSize(v || '', 150), ML + 38, y); y += 6 }

  doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
  doc.text('Descripción del Puesto', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
  linea('Código:', d.codigo); linea('Puesto:', d.descripcion); linea('Departamento:', deptoNombre.value)
  linea('Reporta:', d.reporta); linea('Fecha:', fmtFecha(d.fecha)); linea('Objetivos:', d.objetivo); y += 2

  if (tareas.value.length) { titulo('Tareas'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    for (const t of tareas.value) { salto(); doc.text(`• ${t.des}`, ML + 2, y); doc.text(t.fre_des || '', ML + 110, y); doc.text(t.min ? `${t.min} min` : '', ML + 150, y); y += 5 } y += 2 }
  if (competencias.value.length) { titulo('Competencias'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const c of competencias.value) { salto(); doc.text(`• ${c}`, ML + 2, y); y += 5 } y += 2 }
  if (educacion.value.length) { titulo('Requisitos Educacionales'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const e of educacion.value) { salto(); doc.text(`• ${e.des}${e.aca ? ' (Académica)' : ''}${e.com ? ' (Complementaria)' : ''}`, ML + 2, y); y += 5 } y += 2 }
  if (cualidades.value.length) { titulo('Cualidades Necesarias'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const c of cualidades.value) { salto(); doc.text(`• ${c}`, ML + 2, y); y += 5 } y += 2 }
  if (revisiones.value.length) { titulo('Revisiones realizadas'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    for (const r of revisiones.value) { salto(); doc.setFont('helvetica', 'bold'); doc.text(`${fmtFecha(r.fre)} — ${r.res}`, ML + 2, y); y += 5; doc.setFont('helvetica', 'normal'); const obs = [r.ob1, r.ob2, r.ob3, r.ob4].filter(Boolean).join(' · '); if (obs) { doc.text(doc.splitTextToSize(obs, 170), ML + 4, y); y += 5 } } }
  mostrar(doc, `Puesto_${d.codigo}.pdf`)
}

function pdfHoja () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 15, MR = 15, PW = 210, PH = 297, W = PW - ML - MR
  let y = 14
  const salto = (need = 14) => { if (y + need > PH - 12) { doc.addPage(); y = 14 } }

  // ── Encabezado con recuadro: logo | título | revisión/fecha ──
  const headH = 20
  doc.setDrawColor(60); doc.setLineWidth(0.4); doc.rect(ML, y, W, headH)
  if (logoDataUrl.value) { try { doc.addImage(logoDataUrl.value, 'JPEG', ML + 2, y + 3, 34, headH - 6) } catch { /* */ } }
  doc.line(ML + 40, y, ML + 40, y + headH)
  doc.line(ML + W - 52, y, ML + W - 52, y + headH)
  doc.setFont('helvetica', 'bold'); doc.setFontSize(12.5); doc.setTextColor(20, 20, 20)
  doc.text('Descripción de puestos de trabajo', ML + 40 + (W - 40 - 52) / 2, y + 11, { align: 'center' })
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(40, 40, 40)
  doc.text(`Revisión: ${revisiones.value.length}`, ML + W - 50, y + 8)
  doc.text(`Fecha Últ. Rev.:`, ML + W - 50, y + 13)
  doc.text(revisiones.value[0] ? fmtFecha(revisiones.value[0].fre) : '—', ML + W - 50, y + 17)
  doc.setTextColor(0, 0, 0); y += headH + 4

  const headerBar = (t: string) => {
    salto(16)
    doc.setFillColor(225, 232, 240); doc.setDrawColor(60); doc.setLineWidth(0.3)
    doc.rect(ML, y, W, 7, 'FD')
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(20, 40, 70)
    doc.text(t, PW / 2, y + 5, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 7
  }
  const filaLV = (label: string, value: string) => {
    const h = 8
    doc.setDrawColor(120); doc.setLineWidth(0.2)
    doc.rect(ML, y, 72, h); doc.rect(ML + 72, y, W - 72, h)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(label, ML + 2, y + 5.4)
    doc.setFont('helvetica', 'normal'); doc.text(doc.splitTextToSize(value || '—', W - 76)[0] || '—', ML + 74, y + 5.4)
    y += h
  }
  const boxTexto = (texto: string, minLines = 2) => {
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
    const lines = doc.splitTextToSize(texto || '—', W - 6)
    const h = Math.max(minLines, lines.length) * 5 + 4
    salto(h + 2)
    doc.setDrawColor(120); doc.setLineWidth(0.2); doc.rect(ML, y, W, h)
    doc.text(lines, ML + 3, y + 5)
    y += h
  }
  const subLabel = (t: string) => { doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(t, ML + 1, y + 4); y += 5 }

  headerBar('Identificación del cargo')
  filaLV('Nombre del puesto de trabajo', d.descripcion)
  filaLV('Dependencia', deptoNombre.value)
  filaLV('Cargo del jefe inmediato', d.reporta)
  y += 4

  headerBar('Descripción del cargo')
  boxTexto(d.objetivo, 2)
  y += 4

  headerBar('Funciones del cargo')
  boxTexto(tareas.value.map((t: any) => '•  ' + t.des).join('\n'), 3)
  y += 4

  headerBar('Requisitos mínimos de Formación')
  subLabel('Formación Académica')
  boxTexto(educacion.value.filter((e: any) => e.aca).map((e: any) => '•  ' + e.des).join('\n'), 2)
  y += 3
  subLabel('Formación Complementaria')
  boxTexto(educacion.value.filter((e: any) => e.com).map((e: any) => '•  ' + e.des).join('\n'), 2)

  mostrar(doc, `Hoja_puesto_${d.codigo}.pdf`)
}

function mostrar (doc: jsPDF, nombre: string) { cerrarPdf(); pdfNombre.value = nombre; pdfUrl.value = URL.createObjectURL(doc.output('blob')) }
</script>

<style scoped>
.pu-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.pu-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.pu-ico { font-size:28px; } .pu-tx h1 { margin:0; font-size:19px; color:#1e293b; } .pu-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.pu-nuevo { margin-left:auto; background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; }
.pu-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .pu-msg.ok { background:#d1fae5; color:#065f46; } .pu-msg.err { background:#fee2e2; color:#991b1b; }
.pu-layout { display:flex; gap:14px; flex:1; min-height:0; padding:14px 18px; }
.pu-list { width:280px; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
.pu-search { border:none; border-bottom:1px solid #e2e8f0; padding:10px 12px; font-size:13px; outline:none; }
.pu-list-body { overflow:auto; flex:1; }
.pu-item { display:flex; flex-direction:column; gap:1px; width:100%; padding:9px 12px; border:none; background:transparent; border-bottom:1px solid #f0f4f9; cursor:pointer; text-align:left; }
.pu-item:hover { background:#f0f7ff; } .pu-item.on { background:#e0eefc; border-left:3px solid #2d6a9f; }
.pu-cod { font-size:11px; color:#2d6a9f; font-weight:700; } .pu-des { font-size:13px; color:#1a3a5c; font-weight:600; } .pu-dep { font-size:11px; color:#64748b; }
.pu-vacio { color:#94a3b8; padding:14px; font-size:13px; }
.pu-detalle { flex:1; background:#fff; border:1px solid #dde4ee; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
.pu-vacia { justify-content:center; align-items:center; } .pu-sinsel { color:#8aacca; }
.pu-tabs { display:flex; gap:3px; padding:8px 10px 0; border-bottom:1px solid #e8eef5; background:#f4f7fc; flex-wrap:wrap; }
.pu-tab { border:none; background:#e8eef5; color:#475569; padding:7px 12px; border-radius:7px 7px 0 0; cursor:pointer; font-size:12.5px; font-weight:600; }
.pu-tab.on { background:#2d6a9f; color:#fff; } .pu-tab:disabled { opacity:.5; cursor:default; }
.pu-pane { flex:1; overflow:auto; padding:16px; }
.pu-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; max-width:720px; }
.campo { display:flex; flex-direction:column; gap:4px; } .campo.col2 { grid-column:1 / -1; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.campo input, .campo select, .campo textarea { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; color:#1e293b; }
.campo input:disabled { background:#f1f5f9; }
.campo .cont { align-self:flex-end; font-size:11px; color:#94a3b8; margin-top:2px; }
.campo .cont.over { color:#dc2626; font-weight:700; }
.campo input.exc, .campo textarea.exc { border-color:#dc2626; background:#fef2f2; }
.pu-exc-msg { color:#b91c1c; font-size:12.5px; font-weight:600; align-self:center; }
.pu-acc { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.btn { border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.del { background:#fee2e2; color:#991b1b; } .btn.add { background:#d1fae5; color:#065f46; }
.btn.inf { background:#e0eefc; color:#2d6a9f; } .btn.hoja { background:#dcfce7; color:#166534; } .btn:disabled { opacity:.5; cursor:default; }
.pu-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.pu-tabla th { background:#2d6a9f; color:#fff; padding:8px 10px; text-align:left; font-size:12px; }
.pu-tabla td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .pu-tabla td.c { text-align:center; }
.pu-tabla input, .pu-tabla select { width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:13px; } .inp-min { max-width:90px; }
.pu-tabla input[type=checkbox] { width:auto; }
.x { background:#fee2e2; color:#991b1b; border:none; border-radius:5px; padding:4px 8px; cursor:pointer; }
.pu-tvacio { text-align:center; color:#94a3b8; padding:12px; } .pu-tvacio2 { list-style:none; color:#94a3b8; padding:8px; }
.pu-simple { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:6px; max-width:620px; }
.pu-simple li { display:flex; gap:8px; } .pu-simple input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; }
.pu-rev-form { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.pu-rev-form input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; }
.pu-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.pu-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.pu-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.pu-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.pu-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.pu-pdf-b.ok { background:#22c55e; color:#fff; } .pu-pdf-b.cancel { background:#ef4444; color:#fff; }
.pu-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
