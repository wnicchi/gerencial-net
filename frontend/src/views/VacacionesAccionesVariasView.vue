<!-- VacacionesAccionesVariasView.vue — Vacaciones / Acciones Varias (vacaciones_acciones_varias). -->
<template>
  <div class="va-view">
    <div class="va-cab">
      <div class="va-cab-ico">⚙️</div>
      <div class="va-cab-tx"><h1>Vacaciones - Acciones Varias</h1><p>Modificar, eliminar e imprimir períodos de vacaciones de un empleado</p></div>
      <button class="va-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="va-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesAccionesVariasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-acciones" titulo="Asistente IA — Acciones Varias"
            subtitulo="Preguntá sobre modificar o eliminar vacaciones"
            :sugerencias="['¿Cómo modifico un período?','¿Cómo elimino una vacación?','¿Qué es el acuerdo?']"
            @close="modalIA = false" />

    <div class="va-card" v-enter-next>
      <div class="va-top">
        <div class="va-f bus">
          <span class="va-lbl">Nro. Personal</span>
          <input v-model="busqueda" type="text" class="va-inp" placeholder="Código o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="va-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
          </ul>
        </div>
        <div class="va-f"><span class="va-lbl">F. Ingreso</span><input :value="emp.ingreso" class="va-inp ro" readonly /></div>
        <div class="va-dias"><div><b>{{ emp.corresponden }}</b><span>Corresponden</span></div></div>
      </div>
      <div class="va-f"><span class="va-lbl">Nombre</span><input :value="emp.nombre" class="va-inp ro ancho" readonly /></div>

      <!-- Grilla de períodos -->
      <div class="va-grid-wrap" v-if="emp.cod">
        <table class="va-tabla">
          <thead><tr>
            <th>OK</th><th>Nro.</th><th>Fecha</th><th>Año</th><th>F. Desde</th><th>F. Hasta</th>
            <th>Días</th><th>Presenta</th><th>Liquidada</th><th>Cant.Liq.</th><th>Gozada</th>
          </tr></thead>
          <tbody>
            <tr v-for="v in lista" :key="v.nro" :class="{ sel: v.nro === selNro }" @click="elegir(v)">
              <td class="c"><input type="checkbox" v-model="marcados" :value="v.nro" @click.stop /></td>
              <td>{{ v.nro }}</td><td>{{ fmt(v.fechaPago) }}</td><td>{{ v.anio }}</td>
              <td>{{ fmt(v.fechaDesde) }}</td><td>{{ fmt(v.fechaHasta) }}</td><td class="c">{{ v.dias }}</td>
              <td>{{ fmt(v.presenta) }}</td><td class="c">{{ v.liquidada ? '✔' : '' }}</td>
              <td class="c">{{ v.cantLiquidada }}</td><td class="c">{{ v.gozada ? '✔' : '' }}</td>
            </tr>
            <tr v-if="!lista.length"><td colspan="11" class="vacio">El empleado no tiene vacaciones cargadas.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Panel de edición -->
      <div v-if="selNro" class="va-edit">
        <div class="va-edit-tit">Seleccionar las vacaciones a modificar</div>
        <div class="va-grid">
          <div class="va-f"><span class="va-lbl">Corresponde Año</span><input v-model.number="form.anio" type="number" class="va-inp chico" /></div>
          <label class="va-chk"><input type="checkbox" v-model="form.liquidada" /> Liquidada</label>
          <div class="va-f"><span class="va-lbl">Cantidad Liquidada</span><input v-model.number="form.cantLiquidada" type="number" class="va-inp chico" /></div>
          <div class="va-f"><span class="va-lbl">Cantidad Tomada (días)</span><input v-model.number="form.dias" type="number" class="va-inp chico" /></div>
          <div class="va-f"><span class="va-lbl">Se Presenta</span><input v-model="form.presenta" type="date" class="va-inp" /></div>
          <div class="va-f"><span class="va-lbl">Fecha de Pago</span><input v-model="form.fechaPago" type="date" class="va-inp" /></div>
          <label class="va-chk"><input type="checkbox" v-model="form.gozada" /> Gozada</label>
          <div class="va-f"><span class="va-lbl">Desde Fecha</span><input v-model="form.fechaDesde" type="date" class="va-inp" @change="autoFin" /></div>
          <div class="va-f"><span class="va-lbl">Hasta Fecha</span><input v-model="form.fechaHasta" type="date" class="va-inp" /></div>
          <label class="va-chk"><input type="checkbox" v-model="form.vt" /> Vacaciones trabajadas (VT)</label>
        </div>
        <div class="va-f"><span class="va-lbl">Observaciones</span><textarea v-model="form.observaciones" class="va-obs" rows="2"></textarea></div>
        <div class="va-pie">
          <button class="va-btn-ok" :disabled="procesando" @click="confirmar">{{ procesando ? '⟳…' : 'CONFIRMAR CAMBIOS' }}</button>
          <button class="va-btn-sec" @click="abrirPdf">🖨 Imprimir Acuerdo</button>
        </div>
      </div>

      <div class="va-pie">
        <button class="va-btn-sec" :disabled="!lista.length" @click="abrirConsulta">📄 Consulta Completa</button>
        <button class="va-btn-del" :disabled="!marcados.length || procesando" @click="eliminar">🗑 Eliminar Seleccionadas</button>
      </div>
      <p v-if="msg" :class="['va-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="va-pdf-ov" @click.self="cerrarPdf">
        <div class="va-pdf-md">
          <div class="va-pdf-head"><span>{{ pdfNombre }}</span>
            <div v-if="pdfModo === 'acuerdo'" class="va-pdf-fmt">
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
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import VacacionesAccionesVariasAyuda from '@/components/VacacionesAccionesVariasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

interface Vac { nro: number; cod: number; nombre: string; anio: number; fechaPago: string; fechaDesde: string; fechaHasta: string; dias: number; presenta: string; liquidada: boolean; gozada: boolean; cantLiquidada: number; observaciones: string; vt: boolean }
const emp = reactive({ cod: 0, nombre: '', legajo: 0, ingreso: '', corresponden: 0, cuil: '', empresa: { nombre: '', domicilio: '', cuit: '' } })
const lista = ref<Vac[]>([]); const marcados = ref<number[]>([]); const selNro = ref<number | null>(null)
const form = reactive({ anio: 0, liquidada: false, cantLiquidada: 0, dias: 0, presenta: '', fechaPago: '', gozada: false, fechaDesde: '', fechaHasta: '', vt: false, observaciones: '' })

const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 7000) }
const fmt = (iso: string) => { if (!iso) return ''; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }
const cuilFmt = (c: string) => { const x = (c || '').replace(/\D/g, '').padStart(11, '0').slice(0, 11); return `${x.slice(0, 2)}-${x.slice(2, 10)}-${x.slice(10)}` }

const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionar = async (r: any) => { busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = []; await cargar(r.PER_COD) }

const cargar = async (cod: number) => {
  try {
    const [hdr, acc] = await Promise.all([
      api.get('/vacaciones/empleado', { params: { cod } }),
      api.get('/vacaciones/acciones', { params: { cod } }),
    ])
    Object.assign(emp, { cod, nombre: acc.data.nombre, legajo: acc.data.legajo, ingreso: acc.data.ingreso, corresponden: acc.data.corresponden, cuil: hdr.data.cuil, empresa: hdr.data.empresa })
    lista.value = acc.data.vacaciones; marcados.value = []; selNro.value = null
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
}

const elegir = (v: Vac) => {
  selNro.value = v.nro
  Object.assign(form, { anio: v.anio, liquidada: v.liquidada, cantLiquidada: v.cantLiquidada, dias: v.dias, presenta: v.presenta, fechaPago: v.fechaPago, gozada: v.gozada, fechaDesde: v.fechaDesde, fechaHasta: v.fechaHasta, vt: v.vt, observaciones: '' })
}
const autoFin = () => {
  if (!form.fechaDesde || !form.dias) return
  const d = new Date(form.fechaDesde + 'T00:00:00'); const fin = new Date(d); fin.setDate(fin.getDate() + form.dias - 1)
  form.fechaHasta = _iso(fin); const pre = new Date(fin); pre.setDate(pre.getDate() + 1); form.presenta = _iso(pre)
}

const confirmar = async () => {
  if (!selNro.value) return
  procesando.value = true
  try {
    await api.post('/vacaciones/modificar', { nro: selNro.value, anio: form.anio, fechaPago: form.fechaPago || null, fechaDesde: form.fechaDesde, fechaHasta: form.fechaHasta, dias: form.dias, presenta: form.presenta || null, liquidada: form.liquidada, gozada: form.gozada, cantLiquidada: form.cantLiquidada || 0, observaciones: form.observaciones, vt: form.vt })
    flash('Modificaciones guardadas correctamente.')
    await cargar(emp.cod)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo modificar.', true) }
  finally { procesando.value = false }
}

const eliminar = async () => {
  if (!marcados.value.length) return
  if (!confirm(`¿Borra ${marcados.value.length} período(s) de vacaciones seleccionado(s)?`)) return
  procesando.value = true
  try {
    const { data } = await api.post('/vacaciones/eliminar', { nros: marcados.value })
    flash(data.message); await cargar(emp.cod)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}

// ── PDF ──
const pdfUrl = ref(''); const pdfNombre = ref(''); const pdfModo = ref<'acuerdo' | 'consulta'>('acuerdo'); const formato = ref<'totales' | 'separadas'>('totales')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const abrirPdf = () => { pdfModo.value = 'acuerdo'; formato.value = 'totales'; construirPdf() }

const construirPdf = () => {
  const v = lista.value.find(x => x.nro === selNro.value); if (!v) return
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  doc.setFont('courier', 'normal'); doc.setFontSize(11)
  const mL = 22, mR = 188; let y = 24
  const fde = fmt(v.fechaDesde), fha = fmt(v.fechaHasta), pre = fmt(v.presenta)

  if (formato.value === 'separadas') {
    const _d = v.fechaDesde ? new Date(v.fechaDesde + 'T00:00:00') : new Date(); _d.setDate(_d.getDate() - 15)
    doc.text(`Rosario, ${_d.getDate()} de ${MESES[_d.getMonth()]} de ${_d.getFullYear()}`, mR, y, { align: 'right' }); y += 12
    doc.text(emp.empresa.nombre, mL, y); y += 6; doc.text('Señores', mL, y); y += 6; doc.text('Presente', mL, y); y += 12
    doc.text(doc.splitTextToSize(`Solicito por medio de la presente hacer uso de ${v.dias} días de vacaciones de mi período vacacional ${v.anio} desde el día ${fde} al ${fha} inclusive.`, mR - mL), mL, y); y += 16
    doc.text(doc.splitTextToSize('Desde ya agradezco vuestra atención y aprovecho esta oportunidad para saludarlos atte.', mR - mL), mL, y); y += 24
    doc.text('..............................', mL, y); y += 5; doc.text('Firma del trabajador', mL, y); y += 12
    doc.text('..............................', mL, y); y += 5; doc.text('Aclaración', mL, y); y += 14
    doc.setLineWidth(0.3); doc.line(mL, y, mR, y); y += 10
  }
  doc.text(`EMPRESA: ${emp.empresa.nombre}`, mL, y); y += 5
  if (emp.empresa.domicilio) { doc.text(emp.empresa.domicilio, mL, y); y += 5 }
  if (emp.empresa.cuit) { doc.text(`CUIT: ${emp.empresa.cuit}`, mL, y); y += 7 }
  doc.text(`PERSONAL: ${emp.nombre}`, mL, y); doc.text(`LEGAJO: ${emp.legajo}`, mR, y, { align: 'right' }); y += 5
  doc.text(`DNI / CUIL: ${cuilFmt(emp.cuil)}`, mL, y); y += 5
  doc.text(`INGRESO: ${emp.ingreso || '—'}`, mL, y); doc.text(`FECHA: ${fmt(v.fechaPago)}`, mR, y, { align: 'right' }); y += 10
  if (formato.value === 'separadas') {
    doc.text(doc.splitTextToSize(`Con motivo del otorgamiento del descanso anual y en cumplimiento de lo establecido por el art. 154 de la LCT, le comunicamos a Usted que de acuerdo a su antigüedad gozará de ${emp.corresponden} días de vacaciones correspondientes al año ${v.anio}, tomándose ${v.dias} días desde el ${fde} hasta el día ${fha} inclusive, debiendo reincorporarse a sus tareas el día ${pre}.`, mR - mL), mL, y)
  } else {
    doc.text(doc.splitTextToSize(`Le comunicamos que de acuerdo con las disposiciones legales vigentes gozará de vacaciones correspondientes al año ${v.anio} por el término de ${v.dias} días. Dichas vacaciones comenzarán a regir el día ${fde} hasta el día ${fha}, inclusive, debiendo reintegrarse a sus tareas el día ${pre}.`, mR - mL), mL, y)
  }
  y += 26
  doc.setFont('courier', 'bold'); doc.text('VACACIONES', mL, y); doc.setFont('courier', 'normal'); y += 14
  doc.text('..............................    Firma del empleador', mL, y); y += 16
  doc.text('Quedo debidamente notificado de la comunicación precedente.', mL, y); y += 14
  doc.text('..............................    Firma del trabajador', mL, y)
  cerrarPdf(); pdfNombre.value = `ACUERDO_VACACIONES_${emp.cod}_${v.nro}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const abrirConsulta = () => {
  pdfModo.value = 'consulta'
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text(`Vacaciones — ${emp.nombre} (Legajo ${emp.legajo})`, 14, 16)
  doc.setFontSize(9); doc.text(`Días que corresponden por antigüedad: ${emp.corresponden}`, 14, 22)
  const cols = ['Nro.', 'Fecha', 'Año', 'Desde', 'Hasta', 'Días', 'Presenta', 'Liq.', 'Cant.Liq.', 'Goz.']
  const xs = [14, 30, 55, 70, 95, 120, 135, 165, 180, 205]; let y = 32
  doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 2
  doc.setLineWidth(0.2); doc.line(14, y, 283, y); y += 5
  for (const v of lista.value) {
    if (y > 195) { doc.addPage(); y = 20 }
    const row = [String(v.nro), fmt(v.fechaPago), String(v.anio), fmt(v.fechaDesde), fmt(v.fechaHasta), String(v.dias), fmt(v.presenta), v.liquidada ? 'Sí' : 'No', String(v.cantLiquidada), v.gozada ? 'Sí' : 'No']
    row.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
  }
  cerrarPdf(); pdfNombre.value = `VACACIONES_CONSULTA_${emp.cod}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.va-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.va-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.va-cab-ico { font-size:28px; } .va-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .va-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.va-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.va-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.va-card { margin:16px 18px; max-width:1080px; display:flex; flex-direction:column; gap:12px; }
.va-top { display:flex; align-items:flex-end; gap:16px; flex-wrap:wrap; }
.va-f { display:flex; flex-direction:column; gap:4px; position:relative; } .va-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.va-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; min-width:150px; } .va-inp.ro { background:#f1f5f9; } .va-inp.ancho { min-width:520px; } .va-inp.chico { min-width:90px; width:90px; }
.va-bus .va-inp { min-width:200px; }
.va-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.va-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .va-result li:hover { background:#f0faf4; }
.va-dias { display:flex; gap:18px; margin-left:auto; }
.va-dias div { display:flex; flex-direction:column; align-items:center; background:#ecfdf5; border:1px solid #d1fae5; border-radius:8px; padding:6px 16px; }
.va-dias b { font-size:20px; color:#14532d; } .va-dias span { font-size:11px; color:#475569; }
.va-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:340px; }
.va-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.va-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.va-tabla td { padding:5px 8px; border-bottom:1px solid #f1f5f9; white-space:nowrap; color:#1e293b; } .va-tabla td.c { text-align:center; }
.va-tabla tbody tr { cursor:pointer; } .va-tabla tbody tr:hover { background:#f0faf4; } .va-tabla tbody tr.sel { background:#fde68a; }
.va-tabla .vacio { text-align:center; color:#94a3b8; padding:16px; }
.va-edit { border:1px solid #c3e6cb; border-radius:8px; padding:12px 14px; background:#f8fafc; }
.va-edit-tit { font-weight:800; color:#1b4332; font-size:13px; margin-bottom:8px; }
.va-grid { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px; padding:6px 0; }
.va-chk { display:flex; align-items:center; gap:6px; font-size:13px; color:#1e293b; }
.va-obs { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; resize:vertical; max-width:600px; }
.va-pie { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
.va-btn-ok { background:#16a34a; color:#fff; border:none; padding:10px 26px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:800; } .va-btn-ok:disabled { background:#cbd5e1; }
.va-btn-sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; } .va-btn-sec:disabled { color:#cbd5e1; border-color:#e5e7eb; cursor:default; }
.va-btn-del { background:#ef4444; color:#fff; border:none; padding:9px 18px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; } .va-btn-del:disabled { background:#fca5a5; cursor:default; }
.va-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:700px; } .va-msg.ok { background:#dcfce7; color:#166534; } .va-msg.err { background:#fee2e2; color:#b91c1c; }
.va-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.va-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.va-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.va-pdf-fmt { display:flex; gap:12px; font-size:12px; } .va-pdf-fmt label { display:flex; align-items:center; gap:4px; cursor:pointer; }
.va-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.va-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .va-pdf-b.ok { background:#22c55e; color:#fff; } .va-pdf-b.cancel { background:#ef4444; color:#fff; }
.va-pdf-frame { flex:1; border:none; width:100%; }
</style>
