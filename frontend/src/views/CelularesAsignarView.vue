<!-- CelularesAsignarView.vue — Telefonía Celular: Asignar Teléfonos / Empleados (celulares_asignar.scx). -->
<template>
  <div class="ca-view">
    <div class="ca-cab">
      <div class="ca-ico">📱</div>
      <div class="ca-tx"><h1>Asignar Teléfonos / Empleados</h1><p>Entrega de un equipo celular a un empleado</p></div>
      <button class="ca-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ca-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="ca-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/celulares-asignar" titulo="Asistente IA — Asignar Teléfonos"
            subtitulo="Preguntá sobre la asignación de celulares"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo asigno un celular?','¿Qué pasa si el equipo ya está entregado?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ca-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ca-body">
      <!-- Empleado -->
      <div class="ca-emp">
        <div class="ca-emp-datos">
          <label>Empleado</label>
          <div class="ca-emp-row">
            <EmpleadoInput v-if="!empBloqueado" :codigo="emp || 0" :nombre="empNombre" @select="onLupaEmp" />
            <input v-else :value="`${emp} — ${empNombre}`" class="ca-nom" readonly />
          </div>
        </div>
        <div class="ca-foto"><img v-if="empFoto" :src="empFoto" /><div v-else class="ca-foto-ph">👤</div></div>
      </div>

      <template v-if="empNombre">
        <!-- Equipo celular -->
        <div class="ca-equipo">
          <div class="ca-eq-cod">
            <label>Equipo Celular</label>
            <div class="ca-eq-row">
              <input v-model.number="eqCod" type="number" min="1" @keyup.enter="cargarEquipo(eqCod)" placeholder="Cód…" />
              <button class="ca-lupa" @click="lupa = true">🔍 Buscar</button>
            </div>
            <div class="ca-phone">📱</div>
          </div>
          <div class="ca-eq-datos">
            <div class="ca-f"><span>IMEI</span><input :value="eq.imei" readonly /></div>
            <div class="ca-f"><span>Marca</span><input :value="eq.marca" readonly /></div>
            <div class="ca-f"><span>Modelo</span><input :value="eq.modelo" readonly /></div>
            <div class="ca-f"><span>Color</span><input :value="eq.color" readonly /></div>
            <div class="ca-f"><span>Pantalla</span><input :value="eq.pantalla || ''" readonly /></div>
            <div class="ca-f"><span>Sistema Operativo</span><input :value="eq.sistema" readonly /></div>
            <div class="ca-acc">
              <span>Accesorios</span>
              <label><input type="checkbox" :checked="eq.cargador" disabled /> Cargador</label>
              <label><input type="checkbox" :checked="eq.auricular" disabled /> Auricular</label>
              <label><input type="checkbox" :checked="eq.cableusb" disabled /> Cable USB</label>
            </div>
          </div>
        </div>
        <CelularBuscar v-if="lupa" @select="onLupa" @close="lupa = false" />

        <!-- Datos de entrega -->
        <label class="ca-lbl">Observaciones del estado en que se entrega el teléfono</label>
        <input v-model="observacion" v-enter-next maxlength="100" class="ca-obs" />
        <div class="ca-entrega">
          <div><label>Fecha de Entrega</label><input v-model="fecha" v-enter-next type="date" /></div>
          <div><label>Número de teléfono</label><input v-model="nroTelefono" v-enter-next maxlength="15" @keyup.enter="agregar" /></div>
        </div>

        <button class="ca-agregar" :disabled="guardando" @click="agregar">
          {{ guardando ? '⟳…' : 'AGREGAR TELÉFONO CELULAR' }}
        </button>

        <!-- Grilla de asignados -->
        <div class="ca-grilla">
          <table class="ca-tabla">
            <thead><tr>
              <th style="width:36px"><input type="checkbox" :checked="todosSel" @change="toggleTodos" /></th>
              <th style="width:60px">Cod.</th><th>IMEI</th><th>Marca</th><th>Modelo</th><th>Color</th><th style="width:56px">Pulg.</th><th>Sistema Operativo</th>
            </tr></thead>
            <tbody>
              <tr v-for="a in asignados" :key="a.id" :class="{ activo: a.activo }">
                <td class="c"><input v-model="seleccion" type="checkbox" :value="a.id" /></td>
                <td class="c">{{ a.cod }}</td><td>{{ a.imei }}</td><td>{{ a.marca }}</td>
                <td>{{ a.modelo }}</td><td>{{ a.color }}</td><td class="c">{{ a.pantalla || '' }}</td><td>{{ a.sistema }}</td>
              </tr>
              <tr v-if="!asignados.length"><td colspan="8" class="vacio">El empleado no tiene celulares asignados.</td></tr>
            </tbody>
          </table>
          <div class="ca-ley"><span class="ca-chip activo"></span> Equipo actualmente en poder del empleado (sin devolver)</div>
        </div>

        <div class="ca-acc-bar">
          <button class="ca-imprimir" :disabled="!seleccion.length || generando" @click="imprimir">
            {{ generando ? '⟳ Generando…' : '🖨 IMPRIMIR ORDEN DE ENTREGA' }}
          </button>
        </div>
      </template>
      <div v-else-if="!cargando" class="ca-elija">Ingrese el código de un empleado para comenzar.</div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ca-ov" @click.self="ayuda = false">
        <div class="ca-help-md">
          <h3>❓ Ayuda — Asignar Teléfonos</h3>
          <ul>
            <li>Ingresá el <b>código del empleado</b> para ver sus datos y los celulares que tiene o tuvo asignados.</li>
            <li>Ingresá o buscá con la 🔍 el <b>equipo celular</b> a entregar; se muestran sus datos y accesorios.</li>
            <li>Completá <b>fecha de entrega</b>, <b>número de línea</b> y observaciones, y presioná <b>AGREGAR TELÉFONO CELULAR</b>.</li>
            <li>No se puede asignar un equipo que ya está entregado a otro empleado y sin devolver.</li>
            <li>Marcá los equipos en la grilla y presioná <b>IMPRIMIR ORDEN DE ENTREGA</b> para generar el comprobante.</li>
          </ul>
          <div class="ca-acc-bar"><span style="flex:1"></span><button class="ca-agregar chico" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="pdfUrl" class="ca-pdf-ov" @click.self="cerrarPdf">
        <div class="ca-pdf-md">
          <div class="ca-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ca-pdf-acc">
              <button class="ca-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ca-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ca-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ca-pdf-frame"></iframe>
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
import ChatIA from '@/components/ChatIA.vue'
import CelularBuscar from '@/components/CelularBuscar.vue'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const auth = useAuthStore()
const empresaNombre = computed(() => auth.empresa === 'logist'
  ? 'Silcar Logística y Representaciones S.A.'
  : 'Autoelevadores Silcar S.R.L.')

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const emp = ref<number | null>(null); const empNombre = ref(''); const empFoto = ref('')
const eqCod = ref<number | null>(null)
const eq = reactive({ cod: 0, imei: '', marca: '', modelo: '', color: '', pantalla: 0, sistema: '', cargador: false, auricular: false, cableusb: false })
const observacion = ref(''); const fecha = ref(''); const nroTelefono = ref('')
const asignados = ref<any[]>([]); const seleccion = ref<number[]>([])
const lupa = ref(false); const cargando = ref(false); const guardando = ref(false); const generando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

const todosSel = computed(() => asignados.value.length > 0 && seleccion.value.length === asignados.value.length)
const toggleTodos = () => { seleccion.value = todosSel.value ? [] : asignados.value.map(a => a.id) }

function limpiarEquipo () {
  eqCod.value = null; Object.assign(eq, { cod: 0, imei: '', marca: '', modelo: '', color: '', pantalla: 0, sistema: '', cargador: false, auricular: false, cableusb: false })
  observacion.value = ''; nroTelefono.value = ''
}

async function cargarEmpleado () {
  if (!emp.value || emp.value <= 0) { flash('Debe ingresar el empleado.', true); return }
  cargando.value = true; empNombre.value = ''; empFoto.value = ''; asignados.value = []; seleccion.value = []; limpiarEquipo()
  try {
    const { data } = await api.get(`/celulares/empleado/${emp.value}`)
    empNombre.value = data.empleado.nombre; asignados.value = data.asignados ?? []
    try { const f = await api.get(`/empleados/${emp.value}/foto`); empFoto.value = f.data?.foto || '' } catch { /* */ }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
  finally { cargando.value = false }
}

async function cargarEquipo (cod: number | null) {
  if (!cod || cod <= 0) return
  try {
    const { data } = await api.get(`/celulares/equipos/${cod}`)
    Object.assign(eq, data); eqCod.value = data.cod
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No existe el equipo celular.', true); limpiarEquipo() }
}
const onLupa = (cod: number) => { lupa.value = false; eqCod.value = cod; cargarEquipo(cod) }
const onLupaEmp = (r: any) => { emp.value = r.cod; cargarEmpleado() }

// Precarga desde la ficha del empleado (Propuesta A)
onMounted(() => { if (props.empleado) { emp.value = props.empleado; cargarEmpleado() } })

async function agregar () {
  if (!emp.value || emp.value <= 0) { flash('Debe ingresar el empleado.', true); return }
  if (!eqCod.value || eqCod.value <= 0) { flash('Debe ingresar el celular a entregar.', true); return }
  guardando.value = true
  try {
    await api.post('/celulares/asignar', {
      empleado: emp.value, equipo: eqCod.value,
      fecha: fecha.value || null, observacion: observacion.value, nro_celular: nroTelefono.value,
    })
    flash('Teléfono celular asignado.')
    limpiarEquipo()
    await cargarEmpleado()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo asignar el celular.', true) }
  finally { guardando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function imprimir () {
  const elegidos = asignados.value.filter(a => seleccion.value.includes(a.id))
  if (!elegidos.length) return
  generando.value = true
  try {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const ML = 22, MR = 22, PW = 210; const AW = PW - ML - MR; let primera = true

    for (const a of elegidos) {
      if (!primera) doc.addPage()
      primera = false
      let y = 28
      const p = (txt: string, opts: { bold?: boolean, size?: number, gap?: number, center?: boolean } = {}) => {
        doc.setFont('helvetica', opts.bold ? 'bold' : 'normal'); doc.setFontSize(opts.size ?? 11)
        for (const ln of doc.splitTextToSize(txt, AW)) { doc.text(ln, opts.center ? PW / 2 : ML, y, opts.center ? { align: 'center' } : {}); y += (opts.size ?? 11) * 0.52 }
        y += opts.gap ?? 4
      }
      const fe = a.entrega ? new Date(a.entrega + 'T00:00:00') : new Date()
      const fechaEntrega = `Rosario, ${fe.getDate()} de ${MESES[fe.getMonth()]} de ${fe.getFullYear()}`

      p(fechaEntrega, { gap: 8 })
      p('Sres.', { bold: true, gap: 1 }); p(empresaNombre.value, { bold: true, gap: 1 }); p('Presente', { gap: 8 })
      p('Por medio del presente dejo constancia de haber recibido en el día de la fecha un celular ' +
        `${a.marca} ${a.modelo} ${a.color}  IMEI: ${a.imei}, con número de línea habilitada y en funcionamiento nro. ${a.nro_celular || '...................'}`, { gap: 8 })
      p(`Quedo debidamente notificado que la Empresa ${empresaNombre.value} no se hará cargo ante roturas por mal uso. Como tampoco se hará cargo por la reposición y reemplazos del vidrio templado, cargador del equipo, ni por la carcasa de protección del equipo, entregados por única vez con la entrega del equipo celular.`, { gap: 6 })
      p('Y me comprometo a cuidar del aparato, asumiendo total responsabilidad por la pérdida y/o extravío del mismo, salvo casos de robo ó hurto debidamente acreditados mediante denuncia policial.', { gap: 6 })
      p('Asumo además el compromiso de utilizar la línea y el uso de datos (internet) solamente para mi actividad laboral.', { gap: 14 })
      p('Recibí en fecha ...../....../..........', { gap: 10 })
      p('Firma: ................................', { gap: 6 })
      p('Aclaración: ...........................', { gap: 6 })
    }

    cerrarPdf(); pdfNombre.value = `Orden_Entrega_${emp.value}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } finally { generando.value = false }
}

function reset () {
  emp.value = null; empNombre.value = ''; empFoto.value = ''
  asignados.value = []; seleccion.value = []; limpiarEquipo(); fecha.value = ''
}
</script>

<style scoped>
.ca-view { display:flex; flex-direction:column; min-height:100%; }
.ca-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ca-ico { font-size:28px; } .ca-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ca-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ca-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ca-msg.ok { background:#d1fae5; color:#065f46; } .ca-msg.err { background:#fee2e2; color:#991b1b; }
.ca-body { padding:16px 18px; max-width:920px; }
.ca-emp { display:flex; gap:16px; align-items:flex-start; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fafdff; }
.ca-emp-datos { flex:1; } .ca-emp-datos label { font-size:12px; font-weight:700; color:#374151; }
.ca-emp-row { display:flex; gap:10px; margin-top:5px; }
.ca-emp-row input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:15px; color:#1e293b; }
.ca-emp-row input[type=number] { width:110px; font-weight:800; } .ca-nom { flex:1; background:#f1f5f9; }
.ca-foto { width:96px; height:78px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#eef2f7; display:flex; align-items:center; justify-content:center; }
.ca-foto img { width:100%; height:100%; object-fit:cover; } .ca-foto-ph { font-size:32px; color:#94a3b8; }
.ca-equipo { display:flex; gap:16px; margin-top:16px; border:1px solid #e2e8f0; border-radius:10px; padding:14px; }
.ca-eq-cod { width:150px; text-align:center; } .ca-eq-cod label { font-size:12px; font-weight:700; color:#374151; display:block; }
.ca-eq-row { display:flex; flex-direction:column; gap:6px; margin-top:5px; }
.ca-eq-row input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:16px; font-weight:800; color:#1e293b; text-align:center; }
.ca-lupa { background:#394959; color:#fff; border:none; padding:7px 10px; border-radius:7px; cursor:pointer; font-weight:700; font-size:12px; }
.ca-phone { font-size:54px; margin-top:10px; }
.ca-eq-datos { flex:1; display:grid; grid-template-columns:1fr 1fr; gap:8px 14px; }
.ca-f { display:flex; flex-direction:column; } .ca-f span { font-size:11px; color:#6b7280; } .ca-f input { border:1px solid #e2e8f0; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; background:#f8fafc; margin-top:2px; }
.ca-acc { grid-column:1 / -1; display:flex; align-items:center; gap:16px; margin-top:4px; } .ca-acc > span { font-size:12px; font-weight:700; color:#374151; }
.ca-acc label { display:flex; align-items:center; gap:5px; font-size:13px; color:#1e293b; }
.ca-lbl { display:block; font-size:12px; font-weight:700; color:#374151; margin:16px 0 4px; }
.ca-obs { width:100%; box-sizing:border-box; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; color:#1e293b; }
.ca-entrega { display:flex; gap:18px; margin-top:12px; }
.ca-entrega label { font-size:12px; font-weight:700; color:#374151; display:block; }
.ca-entrega input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; color:#1e293b; margin-top:4px; }
.ca-agregar { margin-top:16px; width:100%; background:#22c55e; color:#0f3d22; border:none; border-radius:8px; padding:12px; cursor:pointer; font-weight:800; font-size:14px; } .ca-agregar:disabled { opacity:.5; } .ca-agregar.chico { width:auto; padding:9px 20px; }
.ca-grilla { margin-top:20px; }
.ca-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.ca-tabla th { background:#6a957b; color:#fff; text-align:left; padding:7px 9px; font-size:11.5px; white-space:nowrap; }
.ca-tabla td { border-bottom:1px solid #eef2f7; padding:6px 9px; color:#1e293b; } .ca-tabla td.c { text-align:center; }
.ca-tabla tr.activo td { background:#fee2e2; }
.ca-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; }
.ca-ley { display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b; margin-top:8px; } .ca-chip { width:13px; height:13px; border-radius:3px; display:inline-block; } .ca-chip.activo { background:#fca5a5; }
.ca-acc-bar { display:flex; align-items:center; gap:8px; margin-top:16px; }
.ca-imprimir { background:#7f1d1d; color:#fff; border:none; border-radius:8px; padding:11px 24px; cursor:pointer; font-weight:800; font-size:13px; } .ca-imprimir:disabled { opacity:.5; }
.ca-elija { text-align:center; color:#94a3b8; padding:30px; }
.ca-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ca-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .ca-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ca-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ca-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ca-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ca-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ca-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ca-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ca-pdf-b.ok { background:#22c55e; color:#fff; } .ca-pdf-b.cancel { background:#ef4444; color:#fff; }
.ca-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
