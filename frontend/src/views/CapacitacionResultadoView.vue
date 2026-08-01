<!-- CapacitacionResultadoView.vue — Resultados de la capacitación (capacitacion_resultados.scx). -->
<template>
  <div class="cr-view">
    <div class="cr-cab">
      <div class="cr-ico">🎯</div>
      <div class="cr-tx"><h1>Resultados de la Capacitación</h1><p>Aplicar resultado, eficacia y planilla de asistencia</p></div>
      <button class="cr-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cr-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/cap-resultado" titulo="Asistente IA — Resultados de Capacitación"
            subtitulo="Preguntá sobre los resultados de la jornada"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué es la confirmación parcial?','¿Qué es la eficacia?','¿Cómo doy por finalizada la jornada?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['cr-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="cr-body">
      <div class="cr-sel">
        <label>Jornada de capacitación</label>
        <div class="cr-sel-row">
          <input :value="cab ? `${cab.cod} — ${cab.capacitacion}` : ''" readonly placeholder="Elegí una jornada con la lupa →" class="cr-sel-input" @click="abrirModalCap" />
          <button class="cr-lupa" @click="abrirModalCap">🔍 Buscar jornada</button>
        </div>
      </div>

      <template v-if="cab">
        <div class="cr-jornada">
          <div class="jr-cod">Capacitación #{{ cab.cod }} <span v-if="cab.cerrada" class="jr-cerr">FINALIZADA</span></div>
          <div class="jr-nom">{{ cab.capacitacion }}</div>
          <div class="jr-meta"><span v-if="cab.fecha">📅 {{ fmt(cab.fecha) }}</span><span v-if="cab.tematica">🏷 {{ cab.tematica }}</span></div>
        </div>

        <!-- Controles de resultado -->
        <div class="cr-controles">
          <div class="cc"><label>Disertante</label>
            <select v-model.number="rDisertante"><option :value="0">— —</option><option v-for="d in disertantes" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
          </div>
          <div class="cc"><label>Duración</label><input v-model="rDuracion" list="cr-dur" maxlength="40" /><datalist id="cr-dur"><option v-for="d in duraciones" :key="d" :value="d" /></datalist></div>
          <div class="cc"><label>Modalidad</label><input v-model="rModalidad" list="cr-mod" maxlength="40" /><datalist id="cr-mod"><option v-for="m in modalidades" :key="m" :value="m" /></datalist></div>
          <div class="cc"><label>Eficacia</label>
            <select v-model.number="rEficacia"><option :value="0">— —</option><option v-for="e in eficacias" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
          </div>
        </div>

        <div class="cr-tbar">
          <input v-model="filtro" class="cr-filtro" placeholder="🔍 Filtrar por nombre o cuil…" />
          <span class="cr-hint">Seleccioná los empleados a los que aplicar el resultado</span>
        </div>

        <table class="cr-tabla">
          <thead><tr>
            <th class="c" style="width:36px">OK</th><th style="width:60px">Código</th><th>Nombre</th><th style="width:130px">Cuil</th>
            <th style="width:120px">Eficacia</th><th class="c" style="width:90px">No participó</th><th>Disertante</th>
          </tr></thead>
          <tbody>
            <tr v-for="(r, i) in alumnosFiltrados" :key="i" :class="{ sel: r.sel, inactivo: !r.activo }">
              <td class="c"><input type="checkbox" v-model="r.sel" /></td>
              <td class="cr-cod">{{ r.codigo }}</td><td>{{ r.nombre }}</td><td>{{ r.cuil }}</td>
              <td>{{ r.eficacia }}</td>
              <td class="c"><input type="checkbox" v-model="r.no_participo" /></td>
              <td>{{ r.disertante }}</td>
            </tr>
            <tr v-if="!alumnos.length"><td colspan="7" class="cr-vacio">Esta capacitación no tiene empleados asignados.</td></tr>
          </tbody>
        </table>

        <div class="cr-acc">
          <button class="btn mini" @click="marcarTodos(true)">Todos</button>
          <button class="btn mini g" @click="marcarTodos(false)">Nada</button>
          <button class="btn warn" :disabled="proc || !haySel" @click="resetear">↺ Resetear estado de seleccionados</button>
          <span class="cr-spacer"></span>
          <button class="btn pdf" :disabled="!haySel" @click="generarPlanilla">📄 Generar planilla de asistencia</button>
        </div>
        <div class="cr-acc2">
          <button class="btn reset" @click="reset">Reset</button>
          <button class="btn ok" :disabled="proc || !haySel" @click="confirmar">✔ Confirmación parcial de empleados</button>
          <span class="cr-spacer"></span>
          <button class="btn fin" :disabled="proc || cab.cerrada" @click="finalizar">🏁 Dar por finalizada esta capacitación</button>
        </div>
      </template>
    </div>

    <!-- Modal búsqueda jornada -->
    <Teleport to="body">
      <div v-if="modalCap" class="cr-ov" @click.self="modalCap = false">
        <div class="cr-busca-md">
          <h3>🔍 Buscar jornada de capacitación</h3>
          <input ref="inputCap" v-model="busqCap" placeholder="Código o nombre de la capacitación…" @input="buscarCap" />
          <ul class="cr-busca-list">
            <li v-for="c in resCap" :key="c.cod" @click="elegirCap(c)"><div class="bl-top"><b>{{ c.cod }}</b> — {{ c.capacitacion }}</div><span v-if="c.tematica">{{ c.tematica }}</span></li>
            <li v-if="!resCap.length" class="cr-busca-vacio">{{ busqCap.trim().length < 1 ? 'Escribí para buscar…' : 'Sin resultados' }}</li>
          </ul>
          <div class="cr-acc"><span class="cr-spacer"></span><button class="btn mini" @click="modalCap = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="cr-ov" @click.self="ayuda = false">
        <div class="cr-busca-md">
          <h3>❓ Ayuda — Resultados de la Capacitación</h3>
          <ul class="cr-help">
            <li>Elegí la jornada con la lupa. Se listan los empleados asignados.</li>
            <li>Cargá arriba <b>disertante, duración, modalidad y eficacia</b>, tildá empleados y aplicá con <b>Confirmación parcial</b>.</li>
            <li><b>No participó</b> se marca por empleado en la grilla.</li>
            <li><b>Resetear</b> borra el resultado de los seleccionados. <b>Planilla</b> genera la hoja de firmas.</li>
            <li><b>Dar por finalizada</b> cierra la jornada. Empleados inactivos se muestran en rojo.</li>
          </ul>
          <div class="cr-acc"><span class="cr-spacer"></span><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <!-- PDF planilla -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="cr-pdf-ov" @click.self="cerrarPdf">
        <div class="cr-pdf-md">
          <div class="cr-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cr-pdf-acc">
              <button class="cr-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cr-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cr-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="cr-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

interface Combo { cod: number; nombre: string }
interface Alumno { sel: boolean; codigo: number; nombre: string; cuil: string; telefono: string; activo: boolean; efi_cod: number; eficacia: string; no_participo: boolean; disertante: string }

const eficacias = ref<Combo[]>([]); const disertantes = ref<Combo[]>([]); const modalidades = ref<string[]>([]); const duraciones = ref<string[]>([])
const cab = ref<any>(null); const empresa = ref<any>({ razon: '', cuit: '' }); const alumnos = ref<Alumno[]>([])
const rDisertante = ref(0); const rDuracion = ref(''); const rModalidad = ref(''); const rEficacia = ref(0)
const filtro = ref(''); const proc = ref(false); const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const haySel = computed(() => alumnos.value.some(a => a.sel))
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4500) }

const alumnosFiltrados = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  if (!q) return alumnos.value
  return alumnos.value.filter(a => `${a.nombre} ${a.cuil}`.toLowerCase().includes(q))
})

onMounted(async () => {
  try { const { data } = await api.get('/cap-resultado/init'); eficacias.value = data.eficacias ?? []; disertantes.value = data.disertantes ?? []; modalidades.value = data.modalidades ?? []; duraciones.value = data.duraciones ?? [] }
  catch { flash('No se pudieron cargar los datos iniciales.', true) }
})

// Modal jornada
const modalCap = ref(false); const inputCap = ref<HTMLInputElement | null>(null)
const busqCap = ref(''); const resCap = ref<any[]>([]); let dc: any = null
async function abrirModalCap () { modalCap.value = true; await nextTick(); inputCap.value?.focus() }
const buscarCap = () => {
  clearTimeout(dc); const q = busqCap.value.trim()
  if (q.length < 1) { resCap.value = []; return }
  dc = setTimeout(async () => { try { resCap.value = ((await api.get('/capacitaciones', { params: { buscar: q } })).data ?? []).slice(0, 20) } catch { resCap.value = [] } }, 250)
}
async function elegirCap (c: any) { modalCap.value = false; resCap.value = []; busqCap.value = ''; await cargar(c.cod) }
async function cargar (cod: number) {
  try {
    const { data } = await api.get(`/cap-resultado/capacitacion/${cod}`)
    cab.value = data.cabecera; empresa.value = data.empresa ?? { razon: '', cuit: '' }
    alumnos.value = (data.alumnos ?? []).map((x: any) => ({ ...x, sel: false }))
    rDisertante.value = cab.value.disertante_cod || 0; rDuracion.value = cab.value.duracion || ''; rModalidad.value = cab.value.modalidad || ''; rEficacia.value = 0
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la capacitación.', true) }
}

const marcarTodos = (v: boolean) => alumnosFiltrados.value.forEach(a => a.sel = v)

async function resetear () {
  const sel = alumnos.value.filter(a => a.sel)
  if (!sel.length) return
  if (!confirm('¿Resetear el estado de capacitación de los seleccionados?')) return
  proc.value = true
  try { const { data } = await api.post(`/cap-resultado/${cab.value.cod}/resetear`, { empleados: sel.map(a => a.codigo) }); alumnos.value = (data.alumnos ?? []).map((x: any) => ({ ...x, sel: false })); flash('Estado reseteado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo resetear.', true) }
  finally { proc.value = false }
}

async function confirmar () {
  const sel = alumnos.value.filter(a => a.sel)
  if (!sel.length) return
  proc.value = true
  try {
    const { data } = await api.post(`/cap-resultado/${cab.value.cod}/confirmar`, {
      disertante_cod: rDisertante.value, duracion: rDuracion.value, modalidad: rModalidad.value, efi_cod: rEficacia.value,
      empleados: sel.map(a => ({ cod: a.codigo, no_participo: a.no_participo })),
    })
    alumnos.value = (data.alumnos ?? []).map((x: any) => ({ ...x, sel: false }))
    flash(`Resultado aplicado a ${sel.length} empleado(s).`)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo confirmar.', true) }
  finally { proc.value = false }
}

async function finalizar () {
  if (!confirm('¿Dar por finalizada esta capacitación? Quedará cerrada.')) return
  proc.value = true
  try { await api.post(`/cap-resultado/${cab.value.cod}/finalizar`); cab.value.cerrada = true; flash('Capacitación finalizada.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo finalizar.', true) }
  finally { proc.value = false }
}

// Planilla de asistencia PDF
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPlanilla () {
  const sel = alumnos.value.filter(a => a.sel)
  if (!sel.length) { flash('Seleccioná empleados para la planilla.', true); return }
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, MR = 196, PW = 210, PH = 297; let y = 16
  const resp = disertantes.value.find(d => d.cod === rDisertante.value)?.nombre || cab.value.disertante || ''
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.text('FG-6.02-05', MR, y - 6, { align: 'right' })
  doc.setFontSize(13); doc.setTextColor(20, 50, 90)
  doc.text('PLANILLA DE ASISTENCIA A CAPACITACIONES', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 7
  doc.setFontSize(9); doc.text(`${empresa.value.razon}${empresa.value.cuit ? ' - CUIT ' + empresa.value.cuit : ''}`, PW / 2, y, { align: 'center' }); y += 8
  const linea = (lbl: string, val: string) => { doc.setFont('helvetica', 'bold'); doc.text(lbl, ML, y); doc.setFont('helvetica', 'normal'); doc.text(val || '', ML + 42, y); y += 5.5 }
  linea('Capacitación / Temario:', (cab.value.capacitacion || '').toUpperCase())
  linea('Responsable:', (resp || '').toUpperCase())
  linea('Duración:', (rDuracion.value || '').toUpperCase()); y -= 5.5
  doc.setFont('helvetica', 'bold'); doc.text('Modalidad:', ML + 100, y); doc.setFont('helvetica', 'normal'); doc.text((rModalidad.value || '').toUpperCase(), ML + 124, y); y += 5.5
  linea('Eficacia:', eficacias.value.find(e => e.cod === rEficacia.value)?.nombre || ''); y += 2
  // Tabla
  doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
  doc.rect(ML, y - 4, MR - ML, 7, 'F')
  const cx = { n: ML + 2, nom: ML + 8, cu: ML + 86, pu: ML + 124, fi: ML + 156 }
  doc.text('#', cx.n, y); doc.text('PARTICIPANTES CONVOCADOS', cx.nom, y); doc.text('CUIL/DNI/Leg.', cx.cu, y); doc.text('PUESTO', cx.pu, y); doc.text('FIRMA', cx.fi, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 7
  const filas = [...sel] as any[]
  for (let k = filas.length; k < 25; k++) filas.push(null)
  filas.forEach((a, idx) => {
    if (y > PH - 20) { doc.addPage(); y = 18 }
    doc.setFontSize(8); doc.text(String(idx + 1), cx.n, y)
    if (a) { doc.text((a.nombre || '').slice(0, 44), cx.nom, y); doc.text(a.cuil || '', cx.cu, y) }
    doc.setDrawColor(190); doc.line(ML, y + 2, MR, y + 2); y += 7
  })
  cerrarPdf(); pdfNombre.value = 'Planilla_asistencia.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

function reset () { cab.value = null; alumnos.value = []; rDisertante.value = 0; rDuracion.value = ''; rModalidad.value = ''; rEficacia.value = 0; filtro.value = '' }
</script>

<style scoped>
.cr-view { display:flex; flex-direction:column; min-height:100%; }
.cr-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cr-ico { font-size:28px; } .cr-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cr-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cr-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cr-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cr-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cr-msg.ok { background:#d1fae5; color:#065f46; } .cr-msg.err { background:#fef3c7; color:#92400e; }
.cr-body { padding:16px 18px; }
.cr-sel { display:flex; flex-direction:column; gap:5px; max-width:680px; } .cr-sel label { font-size:12px; font-weight:600; color:#374151; }
.cr-sel-row { display:flex; gap:8px; } .cr-sel-input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; background:#fff; color:#1e293b; cursor:pointer; }
.cr-lupa { border:none; background:#2d6a9f; color:#fff; border-radius:6px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.cr-jornada { background:#eef6ef; border:1px solid #c3e6cb; border-radius:10px; padding:10px 14px; margin-top:14px; }
.jr-cod { font-size:11px; color:#1b7a4b; font-weight:700; } .jr-cerr { background:#fee2e2; color:#991b1b; padding:1px 7px; border-radius:10px; margin-left:6px; }
.jr-nom { font-size:15px; font-weight:700; color:#14532d; margin:2px 0 4px; } .jr-meta { display:flex; gap:12px; font-size:12px; color:#475569; }
.cr-controles { display:flex; gap:12px; flex-wrap:wrap; margin-top:14px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; }
.cc { display:flex; flex-direction:column; gap:4px; } .cc label { font-size:11.5px; font-weight:600; color:#374151; }
.cc select, .cc input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; min-width:180px; color:#1e293b; }
.cr-tbar { display:flex; align-items:center; gap:14px; margin:14px 0 6px; }
.cr-filtro { border:1px solid #d1d5db; border-radius:7px; padding:8px 11px; font-size:13px; min-width:260px; color:#1e293b; }
.cr-hint { font-size:12.5px; color:#6b7280; font-style:italic; }
.cr-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.cr-tabla th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11.5px; } .cr-tabla th.c { text-align:center; }
.cr-tabla td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .cr-tabla td.c { text-align:center; }
.cr-tabla tr.sel td { background:#fffbe6; } .cr-tabla tr.inactivo td { background:#fee2e2; color:#991b1b; } .cr-cod { color:#2d6a9f; font-weight:700; }
.cr-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.cr-vacio { text-align:center; color:#94a3b8; padding:16px; }
.cr-acc, .cr-acc2 { display:flex; gap:8px; align-items:center; margin-top:10px; flex-wrap:wrap; } .cr-spacer { flex:1; }
.btn { border:none; padding:8px 14px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.mini { background:#eef2f7; color:#334155; padding:6px 12px; font-size:12px; } .btn.mini.g { background:#e2e8f0; }
.btn.warn { background:#fef3c7; color:#92400e; } .btn.pdf { background:#dcfce7; color:#166534; } .btn.ok { background:#1b4332; color:#fff; } .btn.reset { background:#eef2f7; color:#475569; } .btn.fin { background:#fde68a; color:#854d0e; }
.btn:disabled { opacity:.5; cursor:default; }
.cr-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.cr-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(560px,94vw); } .cr-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.cr-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; }
.cr-busca-list { list-style:none; margin:12px 0; padding:0; max-height:380px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.cr-busca-list li { padding:9px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; color:#1e293b; } .cr-busca-list li:hover { background:#f0faf4; }
.cr-busca-list .bl-top { font-size:13px; } .cr-busca-list li b { color:#2d6a9f; } .cr-busca-list li span { display:block; font-size:11px; color:#6b7280; margin-top:1px; }
.cr-busca-vacio { color:#94a3b8; cursor:default !important; } .cr-busca-vacio:hover { background:none !important; }
.cr-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.cr-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cr-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.cr-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .cr-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cr-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cr-pdf-b.ok { background:#22c55e; color:#fff; } .cr-pdf-b.cancel { background:#ef4444; color:#fff; }
.cr-pdf-frame { flex:1; border:none; width:100%; }
</style>
