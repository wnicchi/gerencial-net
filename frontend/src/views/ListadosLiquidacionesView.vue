<!-- ListadosLiquidacionesView.vue — Listados de Liquidaciones (sueldos_listados). -->
<template>
  <div class="ll-view">
    <div class="ll-cab">
      <div class="ll-cab-ico">📊</div>
      <div class="ll-cab-tx"><h1>Listados de Liquidaciones</h1><p>Informes de liquidaciones de sueldo</p></div>
      <button class="ll-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ll-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ListadosLiquidacionesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/sueldos-listados" titulo="Asistente IA — Listados de Liquidaciones"
            subtitulo="Preguntá sobre los informes" :sugerencias="['¿Qué diferencia Completo y Resumido?','¿Qué es Sueldos Bruto?','¿Cómo filtro por período?']" @close="modalIA = false" />

    <div class="ll-filtros">
      <div class="ll-f"><span class="ll-lbl">Liquidación</span><select v-model.number="f.tipo" class="ll-sel"><option :value="0">Todas</option><option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Concepto</span><select v-model.number="f.concepto" class="ll-sel"><option :value="0">Todos</option><option v-for="t in conceptos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Empresa</span><select v-model.number="f.empresa" class="ll-sel"><option :value="0">Todas</option><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Contratista</span><select v-model.number="f.contratista" class="ll-sel"><option :value="0">Todas</option><option v-for="o in cat.contratistas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Lugar</span><select v-model.number="f.lugar" class="ll-sel"><option :value="0">Todos</option><option v-for="o in cat.lugares" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Convenio</span><select v-model.number="f.convenio" class="ll-sel"><option :value="0">Todos</option><option v-for="o in cat.convenios" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Categoría</span><select v-model.number="f.categoria" class="ll-sel"><option :value="0">Todas</option><option v-for="o in cat.categorias" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Banco</span><select v-model.number="f.banco" class="ll-sel"><option :value="0">Todos</option><option v-for="o in cat.bancos" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="ll-f"><span class="ll-lbl">Período</span><label class="ll-chk"><input v-model="f.periodo" type="checkbox" /> Rango</label><template v-if="f.periodo"><input v-model="f.fecha1" type="date" class="ll-fin" /> al <input v-model="f.fecha2" type="date" class="ll-fin" /></template></div>
      <div class="ll-f"><span class="ll-lbl">Pagado</span><label class="ll-chk"><input v-model="f.pagado" type="checkbox" /> Rango</label><template v-if="f.pagado"><input v-model="f.pag1" type="date" class="ll-fin" /> al <input v-model="f.pag2" type="date" class="ll-fin" /></template></div>
      <div class="ll-f"><span class="ll-lbl">Detalle</span><div class="ll-radios"><label><input v-model="detalle" type="radio" value="completo" /> Completo</label><label><input v-model="detalle" type="radio" value="resumido" /> Resumido</label></div></div>
    </div>

    <div class="ll-acc">
      <button class="ll-btn-ok" :disabled="cargando" @click="generar(detalle)">{{ cargando ? '⟳…' : 'LIQUIDACIONES' }}</button>
      <button class="ll-btn-bruto" :disabled="cargando" @click="generar('bruto')">SUELDOS BRUTO</button>
      <span v-if="info" class="ll-info">{{ info }}</span>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ll-pdf-ov" @click.self="cerrarPdf">
        <div class="ll-pdf-md">
          <div class="ll-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ll-pdf-acc">
              <button class="ll-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ll-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ll-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ll-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ListadosLiquidacionesAyuda from '@/components/ListadosLiquidacionesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const tipos = ref<any[]>([]); const conceptos = ref<any[]>([])
const cat = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], bancos: [] })
const _hoy = new Date()
// "Un mes hacia atrás" = el MES ANTERIOR COMPLETO (del 1 al último día), para traer todas las
// liquidaciones de ese período (que suelen estar fechadas a principio de mes).
const _ini = new Date(_hoy.getFullYear(), _hoy.getMonth() - 1, 1)
const _fin = new Date(_hoy.getFullYear(), _hoy.getMonth(), 0) // último día del mes anterior
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
// Por defecto: período del mes anterior completo activado; empresa se setea a AUTOELEVADORES al cargar catálogos.
const f = reactive({ tipo: 0, concepto: 0, empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, banco: 0, periodo: true, fecha1: _iso(_ini), fecha2: _iso(_fin), pagado: false, pag1: '', pag2: '' })
const detalle = ref<'completo' | 'resumido'>('completo')
const cargando = ref(false); const info = ref('')
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const generar = async (modo: string) => {
  cargando.value = true; info.value = ''
  try {
    const params: any = { modo, tipo: f.tipo, concepto: f.concepto, empresa: f.empresa, contratista: f.contratista, lugar: f.lugar, convenio: f.convenio, categoria: f.categoria, banco: f.banco, periodo: f.periodo ? 1 : 0, pagado: f.pagado ? 1 : 0 }
    if (f.periodo) { params.fecha1 = f.fecha1; params.fecha2 = f.fecha2 }
    if (f.pagado) { params.pag1 = f.pag1; params.pag2 = f.pag2 }
    const { data } = await api.get('/sueldos-listados', { params })
    if (!data.liquidaciones.length) { info.value = 'No hay liquidaciones para los filtros.'; return }
    construirPdf(data.liquidaciones, data.total_general, modo)
  } catch (e) { console.error(e); info.value = 'No se pudo generar el listado.' } finally { cargando.value = false }
}

const construirPdf = (liqs: any[], totalGral: number, modo: string) => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 12, mR = 198; let y = 0
  const tit = modo === 'bruto' ? 'LIQUIDACIONES BRUTAS' : (modo === 'resumido' ? 'LIQUIDACIONES (RESUMIDO)' : 'LIQUIDACIONES')
  const hoy = new Date().toLocaleDateString('es-AR')
  const cab = () => { doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text(tit, mL, 14); doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.text(`Fecha: ${hoy}`, mR, 14, { align: 'right' }); doc.setLineWidth(0.4); doc.line(mL, 17, mR, 17); y = 23 }
  cab()
  const salto = (h: number) => { if (y + h > 285) { doc.addPage(); cab() } }

  // Columnas estilo FoxPro: Código | Concepto | Cantidad | Haber | Deducción
  const cCod = mL + 4, cDes = mL + 18, cCan = mL + 118, cHab = mL + 152, cDed = mR
  const ncan = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  for (const l of liqs) {
    salto(modo === 'resumido' ? 8 : 16)
    // Encabezado del empleado / liquidación (código, nombre, cuil, ingreso, empresa)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.setTextColor(27, 67, 50)
    doc.text(`Liq. ${l.nro} · ${l.codigo} ${l.nombre} · CUIL ${l.cuil} · Ing. ${l.ingreso} · Emp. ${l.empresa} · ${l.tipo} · ${l.mes}/${l.anio}`, mL, y)
    doc.setTextColor(0, 0, 0); y += 4.5
    if (modo !== 'resumido') {
      // Cabecera de columnas
      doc.setFont('helvetica', 'bold'); doc.setFontSize(6.5)
      doc.text('Cód', cCod, y); doc.text('Concepto', cDes, y)
      if (modo !== 'bruto') doc.text('Cantidad', cCan + 18, y, { align: 'right' })
      doc.text('Haber', cHab + 18, y, { align: 'right' }); if (modo !== 'bruto') doc.text('Deducción', cDed, y, { align: 'right' })
      y += 1; doc.setDrawColor(210); doc.line(mL, y, mR, y); y += 3
      doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5)
      for (const it of l.items) {
        salto(4.5)
        doc.text(String(it.codigo), cCod, y); doc.text((it.detalle || '').slice(0, 44), cDes, y)
        if (modo !== 'bruto' && it.cantidad) doc.text(ncan.format(it.cantidad), cCan + 18, y, { align: 'right' })
        if (it.haber) doc.text(money(it.haber), cHab + 18, y, { align: 'right' })
        if (modo !== 'bruto' && it.deduccion) doc.text(money(it.deduccion), cDed, y, { align: 'right' })
        y += 4.2
      }
    }
    if (modo !== 'bruto') { doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.text(`TOTAL LIQUIDACIÓN: ${money(l.total)}`, mR, y + 1, { align: 'right' }); y += 6 }
    doc.setDrawColor(180); doc.line(mL, y - 1, mR, y - 1); y += 2.5
  }
  salto(8); doc.setLineWidth(0.3); doc.line(mL, y, mR, y); y += 6; doc.setFont('helvetica', 'bold'); doc.setFontSize(11)
  doc.text(`TOTAL GENERAL: ${money(totalGral)}`, mR, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = `${tit.replace(/[^\w]/g, '_')}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))
onMounted(async () => {
  const g = async (u: string) => { try { return (await api.get(u)).data } catch { return [] } }
  tipos.value = await g('/liquidaciones/tipos'); conceptos.value = await g('/liquidaciones/conceptos')
  cat.empresas = mapCat(await g('/empresas')); cat.contratistas = mapCat(await g('/contratistas')); cat.lugares = mapCat(await g('/lugares'))
  cat.convenios = mapCat(await g('/convenios')); cat.categorias = mapCat(await g('/categorias')); cat.bancos = mapCat(await g('/ctas-bancarias'))
  // Por defecto, empresa AUTOELEVADORES (si existe); si no, la primera empresa.
  const autoelev = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (autoelev) f.empresa = autoelev.cod
})
</script>

<style scoped>
.ll-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ll-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ll-cab-ico { font-size:28px; } .ll-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ll-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ll-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ll-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ll-filtros { display:flex; flex-wrap:wrap; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ll-f { display:flex; flex-direction:column; gap:4px; } .ll-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.ll-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:160px; }
.ll-fin { border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:12px; } .ll-chk { font-size:12px; display:flex; align-items:center; gap:5px; cursor:pointer; }
.ll-radios { display:flex; gap:12px; font-size:13px; } .ll-radios label { display:flex; align-items:center; gap:4px; cursor:pointer; }
.ll-acc { display:flex; align-items:center; gap:14px; padding:16px 18px; }
.ll-btn-ok { background:#16a34a; color:#fff; border:none; padding:11px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ll-btn-ok:disabled { background:#cbd5e1; }
.ll-btn-bruto { background:#2563eb; color:#fff; border:none; padding:11px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ll-btn-bruto:disabled { background:#cbd5e1; }
.ll-info { font-size:13px; color:#b91c1c; }
.ll-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ll-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ll-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ll-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ll-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ll-pdf-b.ok { background:#22c55e; color:#fff; } .ll-pdf-b.cancel { background:#ef4444; color:#fff; }
.ll-pdf-frame { flex:1; border:none; width:100%; }
</style>
