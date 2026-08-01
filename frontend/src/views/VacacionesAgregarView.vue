<!-- VacacionesAgregarView.vue — Vacaciones / Agregar (vacaciones_agregar). -->
<template>
  <div class="va-view">
    <div class="va-cab">
      <div class="va-cab-ico">🏖️</div>
      <div class="va-cab-tx"><h1>Vacaciones - Agregar</h1><p>Alta de un período de vacaciones con notificación (art. 154 LCT)</p></div>
      <button class="va-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="va-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesAgregarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-agregar" titulo="Asistente IA — Vacaciones Agregar"
            subtitulo="Preguntá sobre los días que corresponden y la carga"
            :sugerencias="['¿Cuántos días corresponden por antigüedad?','¿Qué es Tomados y Liquidadas?','¿Qué formato de PDF elijo?']"
            @close="modalIA = false" />

    <div class="va-card" v-enter-next>
      <div class="va-top">
        <div v-if="!empBloqueado" class="va-f bus">
          <span class="va-lbl">Nro. Personal</span>
          <input v-model="busqueda" type="text" class="va-inp" placeholder="Código o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="va-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
          </ul>
        </div>
        <div v-else class="va-f"><span class="va-lbl">Empleado</span><input :value="`${emp.cod} — ${emp.nombre}`" class="va-inp ro" readonly /></div>
        <div class="va-f"><span class="va-lbl">F. Ingreso</span><input :value="emp.ingreso" class="va-inp ro" readonly /></div>
        <div class="va-dias">
          <div><b>{{ emp.corresponden }}</b><span>Corresponden</span></div>
          <div><b>{{ emp.tomados }}</b><span>Tomados</span></div>
          <div><b>{{ emp.liquidadas }}</b><span>Liquidadas</span></div>
        </div>
      </div>

      <div class="va-f"><span class="va-lbl">Nombre</span><input :value="emp.nombre" class="va-inp ro ancho" readonly /></div>

      <!-- Botón que se prende si el empleado tiene permisos pendientes -->
      <div v-if="permisosPendientes.length" class="va-f">
        <button class="va-btn-perm" @click="modalPermisos = true">
          🪪 Tiene {{ permisosPendientes.length }} permiso{{ permisosPendientes.length === 1 ? '' : 's' }} pendiente{{ permisosPendientes.length === 1 ? '' : 's' }} — Ver / usar
        </button>
      </div>
      <div v-if="permisoCod" class="va-detalle-perm">✓ Cargado desde un permiso solicitado.</div>

      <div class="va-grid">
        <div class="va-f"><span class="va-lbl">Corresponde Año</span><input v-model.number="form.anio" type="number" class="va-inp chico" @change="recargar" /></div>
        <label class="va-chk"><input type="checkbox" v-model="form.liquidada" /> Liquidada</label>
        <div class="va-f"><span class="va-lbl">Cantidad Liquidada</span><input v-model.number="form.cantLiquidada" type="number" class="va-inp chico" /></div>
        <div class="va-f"><span class="va-lbl">Fecha de Pago</span><input v-model="form.fechaPago" type="date" class="va-inp" /></div>
        <label class="va-chk"><input type="checkbox" v-model="form.gozada" /> Gozada</label>
        <div class="va-f"><span class="va-lbl">Desde Fecha</span><input v-model="form.fechaDesde" type="date" class="va-inp" @change="autoFin" /></div>
        <div class="va-f"><span class="va-lbl">Hasta Fecha</span><input v-model="form.fechaHasta" type="date" class="va-inp" /></div>
        <div class="va-f"><span class="va-lbl">Cantidad Tomada (días)</span><input v-model.number="form.dias" type="number" class="va-inp chico" /></div>
        <div class="va-f"><span class="va-lbl">Se Presenta</span><input v-model="form.presenta" type="date" class="va-inp" /></div>
        <label class="va-chk"><input type="checkbox" v-model="form.vt" /> Vacaciones trabajadas (VT)</label>
      </div>

      <div class="va-f"><span class="va-lbl">Observaciones</span><textarea v-model="form.observaciones" class="va-obs" rows="2"></textarea></div>

      <div v-if="emp.periodos.length" class="va-analisis">
        <div class="va-analisis-tit">⚠ ANALIZAR ANTES DE PROCEDER CON LA CARGA DE ESTAS VACACIONES</div>
        <div class="va-analisis-bo">
          FECHAS DEL AÑO {{ emp.anio }}: {{ emp.periodos.map(p => '(' + p + ')').join(' ') }}<br>
          <b>&gt; {{ emp.meses.join(', ') }} &lt;</b>
        </div>
      </div>

      <div class="va-pie">
        <button class="va-btn-ok" :disabled="!emp.cod || procesando" @click="confirmar()">{{ procesando ? '⟳…' : 'CONFIRMAR' }}</button>
      </div>
      <p v-if="msg" :class="['va-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="va-pdf-ov" @click.self="cerrarPdf">
        <div class="va-pdf-md">
          <div class="va-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="va-pdf-fmt">
              <label><input type="radio" value="totales" v-model="formato" @change="construirPdf" /> Totales</label>
              <label><input type="radio" value="separadas" v-model="formato" @change="construirPdf" /> Separadas</label>
            </div>
            <div class="va-pdf-acc">
              <button class="va-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="va-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="va-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="va-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>

    <PermisosPendientesModal
      v-if="modalPermisos"
      :permisos="permisosPendientes"
      :nombre="emp.nombre"
      @elegir="usarPermiso"
      @close="modalPermisos = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import VacacionesAgregarAyuda from '@/components/VacacionesAgregarAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import PermisosPendientesModal from '@/components/PermisosPendientesModal.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date()
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const anioDefault = hoy.getMonth() + 1 > 9 ? hoy.getFullYear() : hoy.getFullYear() - 1
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

interface Emp { cod: number; nombre: string; ingreso: string; ndoc: string; legajo: number; cuil: string; empresa: { nombre: string; domicilio: string; cuit: string }; anio: number; corresponden: number; tomados: number; liquidadas: number; periodos: string[]; meses: string[] }
const emp = reactive<Emp>({ cod: 0, nombre: '', ingreso: '', ndoc: '', legajo: 0, cuil: '', empresa: { nombre: '', domicilio: '', cuit: '' }, anio: anioDefault, corresponden: 0, tomados: 0, liquidadas: 0, periodos: [], meses: [] })
const form = reactive({ anio: anioDefault, liquidada: false, cantLiquidada: 0, fechaPago: '', gozada: false, fechaDesde: '', fechaHasta: '', dias: 0, presenta: '', observaciones: '', vt: false })

const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 7000) }

const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8, activo: 1 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionar = async (r: any) => { busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = []; await cargar(r.PER_COD) }

// Precarga desde la ficha del empleado (Propuesta A)
onMounted(() => { if (props.empleado) cargar(props.empleado) })

const cargar = async (cod: number) => {
  try {
    const { data } = await api.get('/vacaciones/empleado', { params: { cod, anio: form.anio } })
    Object.assign(emp, data)
    form.anio = data.anio
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
  cargarPendientes(cod)
}

// ── Permisos pendientes del empleado (integración con permisos del encargado) ──
const permisosPendientes = ref<any[]>([])
const modalPermisos = ref(false)
const permisoCod = ref(0)
const permisoFinalizar = ref<boolean | null>(null)

const cargarPendientes = async (cod: number) => {
  permisoCod.value = 0; permisoFinalizar.value = null
  try { permisosPendientes.value = (await api.get(`/permisos-laborales/pendientes/${cod}`)).data.permisos ?? [] }
  catch { permisosPendientes.value = [] }
}

// Al elegir un permiso del modal: precargar fechas y observación (Vacaciones no usa licencia).
const usarPermiso = (p: any) => {
  form.fechaDesde = p.fecha_desde || form.fechaDesde
  form.fechaHasta = p.fecha_hasta || p.fecha_desde || form.fechaHasta
  form.observaciones = p.observaciones || ''
  permisoCod.value = p.cod
  permisoFinalizar.value = null
  modalPermisos.value = false
}
const recargar = () => { if (emp.cod) cargar(emp.cod) }

// Auto-calcula Hasta y Se Presenta a partir de Desde + días (corridos).
const autoFin = () => {
  if (!form.fechaDesde || !form.dias) return
  const d = new Date(form.fechaDesde + 'T00:00:00')
  const fin = new Date(d); fin.setDate(fin.getDate() + form.dias - 1)
  form.fechaHasta = _iso(fin)
  const pre = new Date(fin); pre.setDate(pre.getDate() + 1)
  form.presenta = _iso(pre)
}

const confirmar = async (forzar = false) => {
  if (!emp.cod) return flash('Seleccioná un empleado.', true)
  if (!form.fechaDesde || !form.fechaHasta) return flash('Indicá las fechas Desde y Hasta.', true)
  // Si se cargó desde un permiso, preguntar (una vez) si darlo por finalizado.
  if (permisoCod.value && permisoFinalizar.value === null) {
    permisoFinalizar.value = confirm('¿Da por finalizado el permiso solicitado? (queda marcado como usado)')
  }
  procesando.value = true
  try {
    const payload = { cod: emp.cod, anio: form.anio, fechaDesde: form.fechaDesde, fechaHasta: form.fechaHasta, dias: form.dias,
      fechaPago: form.fechaPago || null, presenta: form.presenta || null, liquidada: form.liquidada, gozada: form.gozada,
      cantLiquidada: form.cantLiquidada || 0, observaciones: form.observaciones, vt: form.vt, forzar,
      permiso_cod: (permisoCod.value && permisoFinalizar.value) ? permisoCod.value : 0 }
    const { data } = await api.post('/vacaciones', payload)
    flash(`Vacaciones agregadas (Nº ${data.vac_nro}). Generá la notificación.`)
    abrirPdf()
    await cargar(emp.cod)
  } catch (e: any) {
    if (e?.response?.status === 409 && e.response.data?.requiere_confirmacion) {
      const lista = (e.response.data.solapados || []).join('\n')
      if (confirm(`${e.response.data.message}\n\n${lista}\n\n¿Continúa igual?`)) { procesando.value = false; return confirmar(true) }
    } else flash(e?.response?.data?.message ?? 'No se pudo agregar.', true)
  } finally { procesando.value = false }
}

// ── PDF de notificación / solicitud ──
const pdfUrl = ref(''); const pdfNombre = ref(''); const formato = ref<'totales' | 'separadas'>('totales')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const abrirPdf = () => { formato.value = 'totales'; construirPdf() }

const fmt = (iso: string) => { if (!iso) return '__/__/____'; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }
const cuilFmt = (c: string) => { const x = (c || '').replace(/\D/g, '').padStart(11, '0').slice(0, 11); return `${x.slice(0, 2)}-${x.slice(2, 10)}-${x.slice(10)}` }

const construirPdf = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  doc.setFont('courier', 'normal'); doc.setFontSize(11)
  const mL = 22, mR = 188; let y = 24
  const fde = fmt(form.fechaDesde), fha = fmt(form.fechaHasta), pre = fmt(form.presenta)

  if (formato.value === 'separadas') {
    // Solicitud del trabajador
    const _d = form.fechaDesde ? new Date(form.fechaDesde + 'T00:00:00') : new Date()
    _d.setDate(_d.getDate() - 15)
    const emis = `${_d.getDate()} de ${MESES[_d.getMonth()]} de ${_d.getFullYear()}`
    doc.text(`Rosario, ${emis}`, mR, y, { align: 'right' }); y += 12
    doc.text(emp.empresa.nombre, mL, y); y += 6
    doc.text('Señores', mL, y); y += 6
    doc.text('Presente', mL, y); y += 12
    doc.text(doc.splitTextToSize(`Solicito por medio de la presente hacer uso de ${form.dias} días de vacaciones de mi período vacacional ${form.anio} desde el día ${fde} al ${fha} inclusive.`, mR - mL), mL, y); y += 16
    doc.text(doc.splitTextToSize('Desde ya agradezco vuestra atención y aprovecho esta oportunidad para saludarlos atte.', mR - mL), mL, y); y += 24
    doc.text('..............................', mL, y); y += 5; doc.text('Firma del trabajador', mL, y); y += 12
    doc.text('..............................', mL, y); y += 5; doc.text('Aclaración', mL, y); y += 14
    doc.setLineWidth(0.3); doc.line(mL, y, mR, y); y += 10
  }

  // Notificación de la empresa (art. 154 LCT)
  doc.text(`EMPRESA: ${emp.empresa.nombre}`, mL, y); y += 5
  if (emp.empresa.domicilio) { doc.text(emp.empresa.domicilio, mL, y); y += 5 }
  if (emp.empresa.cuit) { doc.text(`CUIT: ${emp.empresa.cuit}`, mL, y); y += 7 }
  doc.text(`PERSONAL: ${emp.nombre}`, mL, y); doc.text(`LEGAJO: ${emp.legajo}`, mR, y, { align: 'right' }); y += 5
  doc.text(`DNI / CUIL: ${cuilFmt(emp.cuil)}`, mL, y); y += 5
  doc.text(`INGRESO: ${emp.ingreso || '—'}`, mL, y); doc.text(`FECHA: ${fmt(form.fechaPago)}`, mR, y, { align: 'right' }); y += 10

  if (formato.value === 'separadas') {
    doc.text(doc.splitTextToSize(`Con motivo del otorgamiento del descanso anual y en cumplimiento de lo establecido por el art. 154 de la LCT, le comunicamos a Usted que de acuerdo a su antigüedad gozará de ${emp.corresponden} días de vacaciones correspondientes al año ${form.anio}, tomándose ${form.dias} días desde el ${fde} hasta el día ${fha} inclusive, debiendo reincorporarse a sus tareas el día ${pre}.`, mR - mL), mL, y)
  } else {
    doc.text(doc.splitTextToSize(`Le comunicamos que de acuerdo con las disposiciones legales vigentes gozará de vacaciones correspondientes al año ${form.anio} por el término de ${form.dias} días. Dichas vacaciones comenzarán a regir el día ${fde} hasta el día ${fha}, inclusive, debiendo reintegrarse a sus tareas el día ${pre}.`, mR - mL), mL, y)
  }
  y += 26
  doc.setFont('courier', 'bold'); doc.text('VACACIONES', mL, y); doc.setFont('courier', 'normal'); y += 14
  doc.text('..............................    Firma del empleador', mL, y); y += 16
  doc.text('Quedo debidamente notificado de la comunicación precedente.', mL, y); y += 14
  doc.text('..............................    Firma del trabajador', mL, y)

  cerrarPdf(); pdfNombre.value = `VACACIONES_${emp.cod}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.va-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.va-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.va-cab-ico { font-size:28px; } .va-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .va-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.va-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.va-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.va-card { margin:16px 18px; max-width:900px; display:flex; flex-direction:column; gap:12px; }
.va-top { display:flex; align-items:flex-end; gap:16px; flex-wrap:wrap; }
.va-f { display:flex; flex-direction:column; gap:4px; position:relative; } .va-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.va-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; min-width:150px; } .va-inp.ro { background:#f1f5f9; } .va-inp.ancho { min-width:520px; } .va-inp.chico { min-width:90px; width:90px; }
.va-bus .va-inp { min-width:200px; }
.va-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.va-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .va-result li:hover { background:#f0faf4; }
.va-dias { display:flex; gap:18px; margin-left:auto; }
.va-dias div { display:flex; flex-direction:column; align-items:center; background:#ecfdf5; border:1px solid #d1fae5; border-radius:8px; padding:6px 16px; }
.va-dias b { font-size:20px; color:#14532d; } .va-dias span { font-size:11px; color:#475569; }
.va-grid { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px; padding:12px 0; border-top:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; }
.va-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; }
.va-obs { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; resize:vertical; max-width:600px; }
.va-btn-perm { background:#fef3c7; color:#92600b; border:1.5px solid #fde68a; border-radius:8px; padding:10px 14px; cursor:pointer; font-size:13px; font-weight:700; text-align:left; max-width:560px; }
.va-btn-perm:hover { background:#fde68a; }
.va-detalle-perm { background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; border-radius:6px; padding:7px 12px; font-size:13px; font-weight:600; max-width:560px; }
.va-analisis { border:2px solid #dc2626; border-radius:8px; overflow:hidden; max-width:700px; }
.va-analisis-tit { background:#fee2e2; color:#991b1b; font-weight:800; font-size:12.5px; padding:7px 12px; }
.va-analisis-bo { padding:10px 12px; font-size:13px; color:#7f1d1d; line-height:1.5; }
.va-pie { display:flex; }
.va-btn-ok { background:#16a34a; color:#fff; border:none; padding:11px 40px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:800; } .va-btn-ok:disabled { background:#cbd5e1; }
.va-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:700px; } .va-msg.ok { background:#dcfce7; color:#166534; } .va-msg.err { background:#fee2e2; color:#b91c1c; }
.va-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.va-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.va-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.va-pdf-fmt { display:flex; gap:12px; font-size:12px; } .va-pdf-fmt label { display:flex; align-items:center; gap:4px; cursor:pointer; }
.va-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.va-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .va-pdf-b.ok { background:#22c55e; color:#fff; } .va-pdf-b.cancel { background:#ef4444; color:#fff; }
.va-pdf-frame { flex:1; border:none; width:100%; }
</style>
