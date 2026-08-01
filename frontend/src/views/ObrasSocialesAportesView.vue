<!-- ObrasSocialesAportesView.vue — Planilla Contribuciones y Aportes (obras_sociales_aportes). -->
<template>
  <div class="ap-view">
    <div class="ap-cab">
      <div class="ap-cab-ico">📋</div>
      <div class="ap-cab-tx"><h1>Planilla Contribuciones y Aportes</h1><p>Edición de aportes de obras sociales por empresa y período</p></div>
      <button class="ap-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ap-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ObrasSocialesAportesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/obras-sociales-aportes" titulo="Asistente IA — Planilla de Aportes"
            subtitulo="Preguntá sobre la planilla de aportes"
            :sugerencias="['¿Qué puedo editar?','¿Cómo se calcula la diferencia?','¿Por qué una fila está en amarillo?','¿Qué hace Confirmar?']"
            @close="modalIA = false" />

    <div class="ap-filtros">
      <span class="ap-lbl">Período</span>
      <select v-model.number="mes" class="ap-sel"><option v-for="m in 12" :key="m" :value="m">{{ meses[m - 1] }}</option></select>
      <input v-model.number="anio" type="number" class="ap-anio" />
      <span class="ap-lbl">Empresa</span>
      <select v-model.number="empresa" class="ap-sel ap-emp"><option :value="0">— Elegir —</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
      <button class="ap-btn-ok" :disabled="cargando || !empresa" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
    </div>

    <div v-if="consultado" class="ap-wrap">
      <table class="ap-grid">
        <thead><tr>
          <th>CUIL</th><th>Apellido y Nombre</th><th>Obra Social</th>
          <th class="r">Total Contrib. OS</th><th class="r">Total Aportes OS</th><th class="r">TOTAL</th>
          <th class="r ed">Aportes Reconocidos</th><th class="r ed">FC medife</th><th class="r">Dif.Medife</th><th class="r ed">FC medicus</th><th class="r">Dif.Medicus</th>
        </tr></thead>
        <tbody>
          <tr v-for="(r, i) in planilla" :key="i" :class="{ mod: filaModificada(r) }">
            <td>{{ r.cuil }}</td><td>{{ r.nombre }}</td><td>{{ r.obra_social }}</td>
            <td class="r">{{ money(r.tcon) }}</td><td class="r">{{ money(r.tapo) }}</td><td class="r">{{ money(r.total) }}</td>
            <td class="r"><input v-model.number="r.recon" type="number" step="0.01" class="ap-in" @input="recalc" /></td>
            <td class="r"><input v-model.number="r.medife" type="number" step="0.01" class="ap-in" @input="recalc" /></td>
            <td class="r">{{ money(r.dmedife) }}</td>
            <td class="r"><input v-model.number="r.medicus" type="number" step="0.01" class="ap-in" @input="recalc" /></td>
            <td class="r">{{ money(r.dmedicus) }}</td>
          </tr>
          <tr v-if="!planilla.length"><td colspan="11" class="ap-vacio">No hay empleados activos para esta empresa.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="consultado && planilla.length" class="ap-pie">
      <div class="ap-tot">TOTALES &nbsp; Dif. MEDIFE: <b>{{ money(tmedife) }}</b> &nbsp;·&nbsp; MEDICUS: <b>{{ money(tmedicus) }}</b></div>
      <button class="ap-btn-pdf" @click="imprimir">🖨 Imprimir informe</button>
      <button class="ap-btn-conf" :disabled="guardando" @click="confirmar">💾 {{ guardando ? 'Guardando…' : 'CONFIRMAR CAMBIOS' }}</button>
    </div>
    <p v-if="msg" :class="['ap-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ap-pdf-ov" @click.self="cerrarPdf">
        <div class="ap-pdf-md">
          <div class="ap-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ap-pdf-acc">
              <button class="ap-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ap-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ap-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ap-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ObrasSocialesAportesAyuda from '@/components/ObrasSocialesAportesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
interface Row { cuil: string; nombre: string; obra_social: number; tcon: number; tapo: number; total: number; recon: number; medife: number; dmedife: number; medicus: number; dmedicus: number; _o?: { recon: number; medife: number; medicus: number } }
const hoy = new Date()
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear()); const empresa = ref(0)
const empresas = ref<{ cod: number; nombre: string }[]>([])
const planilla = ref<Row[]>([]); const tmedife = ref(0); const tmedicus = ref(0)
const cargando = ref(false); const consultado = ref(false); const guardando = ref(false)
const msg = ref(''); const msgError = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 4000) }

const filaModificada = (r: Row) => !!r._o && (r.recon !== r._o.recon || r.medife !== r._o.medife || r.medicus !== r._o.medicus)

const recalc = () => {
  let tf = 0, tc = 0
  for (const r of planilla.value) {
    const recon = Number(r.recon || 0), medife = Number(r.medife || 0), medicus = Number(r.medicus || 0)
    r.dmedife = medife > 0 ? Math.round((medife - recon) * 100) / 100 : 0
    r.dmedicus = medicus > 0 ? Math.round((medicus - recon) * 100) / 100 : 0
    tf += r.dmedife; tc += r.dmedicus
  }
  tmedife.value = Math.round(tf * 100) / 100; tmedicus.value = Math.round(tc * 100) / 100
}

const consultar = async () => {
  cargando.value = true; consultado.value = true
  try {
    const { data } = await api.get('/obras-sociales/aportes', { params: { anio: anio.value, mes: mes.value, empresa: empresa.value } })
    planilla.value = data.planilla.map((r: Row) => ({ ...r, _o: { recon: r.recon, medife: r.medife, medicus: r.medicus } }))
    tmedife.value = data.tmedife; tmedicus.value = data.tmedicus
  } catch (e) { console.error(e); planilla.value = [] } finally { cargando.value = false }
}

const confirmar = async () => {
  if (!confirm('¿Confirma los cambios?')) return
  guardando.value = true
  try {
    await api.post('/obras-sociales/aportes', { anio: anio.value, mes: mes.value, rows: planilla.value.map(r => ({ cuil: r.cuil, recon: r.recon, medife: r.medife, medicus: r.medicus })) })
    flash('Proceso terminado OK'); await consultar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const mL = 10, mR = 287; let y = 0
  const empNom = empresas.value.find(e => e.cod === empresa.value)?.nombre ?? ''
  const cab = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.text(`APORTES OBRAS SOCIALES — ${meses[mes.value - 1]} DE ${anio.value}`, mL, 13)
    doc.setFontSize(9); doc.setFont('helvetica', 'normal'); doc.text(empNom, mL, 19)
    doc.setLineWidth(0.3); doc.line(mL, 22, mR, 22); y = 27
    const h = ['CUIL', 'Apellido y Nombre', 'O.S.', 'Contrib.', 'Aportes', 'Total', 'Reconoc.', 'FC medife', 'Dif.Medife', 'FC medicus', 'Dif.Medicus']
    const x = [mL, mL + 26, mL + 78, mL + 92, mL + 116, mL + 140, mL + 164, mL + 190, mL + 214, mL + 240, mL + 264]
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7)
    h.forEach((t, i) => doc.text(t, i < 3 ? x[i] : x[i] + 20, y, i < 3 ? {} : { align: 'right' })); y += 1; doc.line(mL, y, mR, y); y += 3.5
    doc.setFont('helvetica', 'normal')
  }
  cab()
  const x = [mL, mL + 26, mL + 78, mL + 92, mL + 116, mL + 140, mL + 164, mL + 190, mL + 214, mL + 240, mL + 264]
  for (const r of planilla.value) {
    if (y > 198) { doc.addPage(); cab() }
    doc.text(r.cuil, x[0], y); doc.text(r.nombre.slice(0, 28), x[1], y); doc.text(String(r.obra_social), x[2] + 20, y, { align: 'right' })
    doc.text(money(r.tcon), x[3] + 20, y, { align: 'right' }); doc.text(money(r.tapo), x[4] + 20, y, { align: 'right' }); doc.text(money(r.total), x[5] + 20, y, { align: 'right' })
    doc.text(money(r.recon), x[6] + 20, y, { align: 'right' }); doc.text(money(r.medife), x[7] + 20, y, { align: 'right' }); doc.text(money(r.dmedife), x[8] + 20, y, { align: 'right' })
    doc.text(money(r.medicus), x[9] + 20, y, { align: 'right' }); doc.text(money(r.dmedicus), x[10] + 20, y, { align: 'right' }); y += 4.2
  }
  doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
  doc.text(`TOTALES — Dif. MEDIFE: ${money(tmedife.value)}    MEDICUS: ${money(tmedicus.value)}`, mR, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = 'Aportes_Obras_Sociales.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(async () => { try { empresas.value = (await api.get('/empresas')).data } catch (e) { console.error(e) } })
</script>

<style scoped>
.ap-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ap-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ap-cab-ico { font-size:28px; } .ap-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ap-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ap-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ap-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ap-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ap-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ap-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; } .ap-sel.ap-emp { min-width:240px; }
.ap-anio { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; width:90px; }
.ap-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ap-btn-ok:disabled { background:#cbd5e1; }
.ap-wrap { margin:12px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:62vh; }
.ap-grid { width:100%; border-collapse:collapse; font-size:12.5px; white-space:nowrap; }
.ap-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 9px; font-size:11px; text-align:left; } .ap-grid th.r { text-align:right; } .ap-grid th.ed { background:#15803d; }
.ap-grid td { padding:3px 9px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .ap-grid td.r { text-align:right; }
.ap-grid tr:nth-child(even) td { background:#f8fafc; } .ap-grid tr.mod td { background:#fff200 !important; }
.ap-in { width:96px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:12.5px; text-align:right; outline:none; } .ap-in:focus { border-color:#40916c; box-shadow:0 0 0 2px rgba(64,145,108,.15); }
.ap-vacio { padding:24px; text-align:center; color:#9ca3af; }
.ap-pie { display:flex; align-items:center; gap:14px; padding:0 18px 16px; flex-wrap:wrap; }
.ap-tot { font-size:13px; color:#374151; } .ap-tot b { color:#1b4332; }
.ap-btn-pdf { margin-left:auto; background:#2563eb; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ap-btn-conf { background:#16a34a; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ap-btn-conf:disabled { background:#cbd5e1; }
.ap-msg { padding:8px 14px; margin:0 18px 14px; font-size:13px; border-radius:6px; } .ap-msg.ok { background:#dcfce7; color:#166534; } .ap-msg.err { background:#fee2e2; color:#b91c1c; }
.ap-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ap-pdf-md { width:min(960px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ap-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ap-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ap-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ap-pdf-b.ok { background:#22c55e; color:#fff; } .ap-pdf-b.cancel { background:#ef4444; color:#fff; }
.ap-pdf-frame { flex:1; border:none; width:100%; }
</style>
