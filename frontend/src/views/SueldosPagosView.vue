<!-- SueldosPagosView.vue — Comparar con Pagos Bancarios (sueldos_pagos). -->
<template>
  <div class="sp-view">
    <div class="sp-cab">
      <div class="sp-cab-ico">🏦</div>
      <div class="sp-cab-tx"><h1>Comparar con Pagos Bancarios</h1><p>Lo liquidado contra lo realmente pagado por banco</p></div>
      <button class="sp-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="sp-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <SueldosPagosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/sueldos-pagos" titulo="Asistente IA — Comparar con Pagos Bancarios"
            subtitulo="Preguntá sobre la comparación con pagos bancarios"
            :sugerencias="['¿Qué significa la diferencia?','¿Qué es Pagado desde?','¿Cómo interpreto los Totales Bancarios?']"
            @close="modalIA = false" />

    <div class="sp-filtros" v-enter-next>
      <div class="sp-f"><span class="sp-lbl">Empresa</span><select v-model.number="f.empresa" class="sp-sel"><option :value="0">Todas</option><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="sp-f"><span class="sp-lbl">Banco Depósito</span><select v-model.number="f.banco" class="sp-sel"><option :value="0">Todos</option><option v-for="o in cat.bancos" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="sp-f"><span class="sp-lbl">Liquidación</span><select v-model.number="f.tipo" class="sp-sel"><option :value="0">Todas</option><option v-for="t in cat.tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select></div>
      <div class="sp-f"><span class="sp-lbl">Período Liquidación</span><div class="sp-rango"><input v-model="f.liqFec1" type="date" class="sp-date" /><span>al</span><input v-model="f.liqFec2" type="date" class="sp-date" /></div></div>
      <div class="sp-f"><span class="sp-lbl">Pagado desde</span><div class="sp-rango"><input v-model="f.pagFec1" type="date" class="sp-date" /><span>al</span><input v-model="f.pagFec2" type="date" class="sp-date" /></div></div>
      <div class="sp-f"><span class="sp-lbl">Vista</span>
        <select v-model="f.modo" class="sp-sel">
          <option value="detallado">Detallado por Liquidación</option>
          <option value="totalizada">Totalizada por Liquidación</option>
          <option value="bancarios">Totales Bancarios</option>
        </select>
      </div>
      <button class="sp-btn-ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'CONSULTAR' }}</button>
    </div>

    <!-- Resultados -->
    <div v-if="cargado && cantidad" class="sp-wrap">
      <!-- Detallado -->
      <table v-if="f.modo === 'detallado'" class="sp-grid">
        <thead><tr><th>Banco</th><th>Liq.</th><th>Cód.</th><th>Empleado</th><th>Tipo</th><th>Per.</th><th>Fecha</th><th>Pago</th><th class="r">Haberes</th><th class="r">Deducción</th><th class="r">Neto</th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in liquidaciones" :key="i">
            <td>{{ l.bancoDesc }}</td><td>{{ l.nro }}</td><td>{{ l.codigo }}</td><td>{{ l.nombre }}</td><td>{{ l.tipoDesc }}</td>
            <td>{{ l.mes }}/{{ l.anio }}</td><td>{{ l.fecha }}</td><td>{{ l.pago }}</td>
            <td class="r">{{ money(l.haberes) }}</td><td class="r">{{ money(l.deducciones) }}</td><td class="r">{{ money(l.neto) }}</td>
          </tr>
        </tbody>
        <tfoot><tr class="sp-tfoot"><td colspan="10">TOTAL GENERAL</td><td class="r">{{ money(totalGeneral) }}</td></tr></tfoot>
      </table>

      <!-- Totalizada por banco + tipo -->
      <table v-else-if="f.modo === 'totalizada'" class="sp-grid">
        <thead><tr><th>Banco</th><th>Tipo de Liquidación</th><th class="r">Liquidaciones</th><th class="r">Total Neto</th></tr></thead>
        <tbody>
          <tr v-for="(g, i) in totalizada" :key="i"><td>{{ g.bancoDesc }}</td><td>{{ g.tipoDesc }}</td><td class="r">{{ g.cant }}</td><td class="r">{{ money(g.neto) }}</td></tr>
        </tbody>
        <tfoot><tr class="sp-tfoot"><td colspan="3">TOTAL GENERAL</td><td class="r">{{ money(totalGeneral) }}</td></tr></tfoot>
      </table>

      <!-- Totales Bancarios -->
      <table v-else class="sp-grid">
        <thead><tr><th>Banco</th><th class="r">Liquidado</th><th class="r">Pagado por Banco</th><th class="r">Diferencia</th></tr></thead>
        <tbody>
          <tr v-for="(b, i) in bancos" :key="i" :class="{ dif: Math.abs(b.diferencia) >= 0.01 }">
            <td>{{ b.bancoDesc }}</td><td class="r">{{ money(b.liquidado) }}</td><td class="r">{{ money(b.pagadoBanco) }}</td>
            <td class="r" :class="b.diferencia < 0 ? 'neg' : (b.diferencia > 0 ? 'pos' : '')">{{ money(b.diferencia) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="sp-tfoot"><td>TOTAL GENERAL</td><td class="r">{{ money(totBan.liq) }}</td><td class="r">{{ money(totBan.pag) }}</td><td class="r">{{ money(totBan.dif) }}</td></tr>
        </tfoot>
      </table>
    </div>
    <div v-else-if="cargado" class="sp-vacio">No hay liquidaciones pagadas para los filtros y períodos seleccionados.</div>

    <!-- Total destacado + PDF -->
    <div v-if="cargado && cantidad" class="sp-pie">
      <div class="sp-total-box">
        <span class="sp-total-lbl">TOTAL GENERAL LIQUIDADO</span>
        <span class="sp-total-val">$ {{ money(totalGeneral) }}</span>
        <span class="sp-total-cant">{{ cantidad }} liquidaciones</span>
      </div>
      <button class="sp-btn-pdf" @click="imprimir">📄 Visualizar / Imprimir PDF</button>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="sp-pdf-ov" @click.self="cerrarPdf">
        <div class="sp-pdf-md">
          <div class="sp-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="sp-pdf-acc">
              <button class="sp-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="sp-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="sp-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="sp-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import SueldosPagosAyuda from '@/components/SueldosPagosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Liq { nro: number; codigo: number; nombre: string; empCod: number; bancoCod: number; bancoDesc: string; tipo: number; tipoDesc: string; mes: number; anio: number; fecha: string; pago: string; haberes: number; deducciones: number; neto: number }
interface Banco { empCod: number; bancoCod: number; bancoDesc: string; liquidado: number; pagadoBanco: number; diferencia: number }

const hoy = new Date()
// Por defecto ambos rangos = mes anterior completo (igual que los demás informes de liquidaciones).
const _ini = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)
const _fin = new Date(hoy.getFullYear(), hoy.getMonth(), 0)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const f = reactive({ empresa: 0, banco: 0, tipo: 0, modo: 'detallado', liqFec1: _iso(_ini), liqFec2: _iso(_fin), pagFec1: _iso(_ini), pagFec2: _iso(_fin) })
const cat = reactive<{ empresas: any[]; bancos: any[]; tipos: any[] }>({ empresas: [], bancos: [], tipos: [] })

const liquidaciones = ref<Liq[]>([]); const bancos = ref<Banco[]>([]); const totalGeneral = ref(0); const cantidad = ref(0)
const cargando = ref(false); const cargado = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))

const totalizada = computed(() => {
  const m: Record<string, { bancoDesc: string; tipoDesc: string; cant: number; neto: number }> = {}
  for (const l of liquidaciones.value) {
    const k = l.bancoDesc + '|' + l.tipoDesc
    if (!m[k]) m[k] = { bancoDesc: l.bancoDesc, tipoDesc: l.tipoDesc, cant: 0, neto: 0 }
    m[k].cant++; m[k].neto += l.neto
  }
  return Object.values(m).sort((a, b) => a.bancoDesc.localeCompare(b.bancoDesc) || a.tipoDesc.localeCompare(b.tipoDesc))
})
const totBan = computed(() => bancos.value.reduce((a, b) => ({ liq: a.liq + b.liquidado, pag: a.pag + b.pagadoBanco, dif: a.dif + b.diferencia }), { liq: 0, pag: 0, dif: 0 }))

const consultar = async () => {
  cargando.value = true; cargado.value = true
  try {
    const { data } = await api.get('/sueldos-pagos', { params: { ...f } })
    liquidaciones.value = data.liquidaciones || []; bancos.value = data.bancos || []
    totalGeneral.value = data.totalGeneral || 0; cantidad.value = data.cantidad || 0
  } catch (e) { console.error(e); liquidaciones.value = []; bancos.value = []; totalGeneral.value = 0; cantidad.value = 0 }
  finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: f.modo === 'detallado' ? 'landscape' : 'portrait', unit: 'mm', format: 'a4' })
  const mR = f.modo === 'detallado' ? 283 : 196, mL = 14; let y = 14
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('LIQUIDACIONES PAGADAS', mL, y); y += 5
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
  doc.text(`Período de liquidación ${fmt(f.liqFec1)} al ${fmt(f.liqFec2)}  ·  Pagado desde ${fmt(f.pagFec1)} al ${fmt(f.pagFec2)}`, mL, y); y += 5
  doc.setLineWidth(0.4); doc.line(mL, y, mR, y); y += 5

  if (f.modo === 'bancarios') {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
    doc.text('Banco', mL, y); doc.text('Liquidado', mL + 80, y, { align: 'right' }); doc.text('Pagado Banco', mL + 130, y, { align: 'right' }); doc.text('Diferencia', mR, y, { align: 'right' }); y += 1
    doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'normal')
    for (const b of bancos.value) {
      doc.text(b.bancoDesc.slice(0, 36), mL, y); doc.text(money(b.liquidado), mL + 80, y, { align: 'right' }); doc.text(money(b.pagadoBanco), mL + 130, y, { align: 'right' })
      if (Math.abs(b.diferencia) >= 0.01) doc.setTextColor(185, 28, 28)
      doc.text(money(b.diferencia), mR, y, { align: 'right' }); doc.setTextColor(0, 0, 0); y += 5
    }
    doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold')
    doc.text('TOTAL GENERAL', mL, y); doc.text(money(totBan.value.liq), mL + 80, y, { align: 'right' }); doc.text(money(totBan.value.pag), mL + 130, y, { align: 'right' }); doc.text(money(totBan.value.dif), mR, y, { align: 'right' })
  } else if (f.modo === 'totalizada') {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
    doc.text('Banco', mL, y); doc.text('Tipo', mL + 70, y); doc.text('Cant.', mL + 130, y, { align: 'right' }); doc.text('Total Neto', mR, y, { align: 'right' }); y += 1
    doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
    for (const g of totalizada.value) {
      if (y > 280) { doc.addPage(); y = 20 }
      doc.text(g.bancoDesc.slice(0, 32), mL, y); doc.text(g.tipoDesc.slice(0, 28), mL + 70, y); doc.text(String(g.cant), mL + 130, y, { align: 'right' }); doc.text(money(g.neto), mR, y, { align: 'right' }); y += 4.6
    }
    y += 1; doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold')
    doc.text('TOTAL GENERAL', mL, y); doc.text(money(totalGeneral.value), mR, y, { align: 'right' })
  } else {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    const X = { banco: mL, liq: mL + 42, cod: mL + 66, nom: mL + 80, tipo: mL + 138, per: mL + 168, hab: mL + 215, ded: mL + 245, neto: mR }
    doc.text('Banco', X.banco, y); doc.text('Liq.', X.liq, y); doc.text('Cód', X.cod, y); doc.text('Empleado', X.nom, y); doc.text('Tipo', X.tipo, y); doc.text('Per.', X.per, y)
    doc.text('Haberes', X.hab, y, { align: 'right' }); doc.text('Deducc.', X.ded, y, { align: 'right' }); doc.text('Neto', X.neto, y, { align: 'right' }); y += 1
    doc.line(mL, y, mR, y); y += 4; doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5)
    for (const l of liquidaciones.value) {
      if (y > 200) { doc.addPage(); y = 18 }
      doc.text(l.bancoDesc.slice(0, 22), X.banco, y); doc.text(String(l.nro), X.liq, y); doc.text(String(l.codigo), X.cod, y); doc.text(l.nombre.slice(0, 30), X.nom, y)
      doc.text(l.tipoDesc.slice(0, 16), X.tipo, y); doc.text(`${l.mes}/${l.anio}`, X.per, y)
      doc.text(money(l.haberes), X.hab, y, { align: 'right' }); doc.text(money(l.deducciones), X.ded, y, { align: 'right' }); doc.text(money(l.neto), X.neto, y, { align: 'right' }); y += 3.9
    }
    y += 1; doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold'); doc.setFontSize(9)
    doc.text('TOTAL GENERAL', mL, y); doc.text(money(totalGeneral.value), X.neto, y, { align: 'right' })
  }
  cerrarPdf(); pdfNombre.value = 'Liquidaciones_Pagadas.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
const fmt = (iso: string) => { if (!iso) return ''; const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }

const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))
onMounted(async () => {
  const fetch = async (url: string) => { try { return (await api.get(url)).data } catch { return [] } }
  cat.empresas = mapCat(await fetch('/empresas'))
  cat.bancos = mapCat(await fetch('/ctas-bancarias'))
  const ts = await fetch('/liquidaciones/tipos')
  cat.tipos = (ts || []).map((t: any) => ({ cod: t.cod ?? t.COD, desc: (t.desc ?? t.descripcion ?? '').toString().trim() }))
  const autoelev = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (autoelev) f.empresa = autoelev.cod
})
</script>

<style scoped>
.sp-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.sp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sp-cab-ico { font-size:28px; } .sp-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sp-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sp-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sp-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sp-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.sp-f { display:flex; flex-direction:column; gap:4px; } .sp-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.sp-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:170px; color:#1e293b; }
.sp-rango { display:flex; align-items:center; gap:6px; } .sp-rango span { font-size:12px; color:#64748b; }
.sp-date { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; color:#1e293b; }
.sp-btn-ok { background:#16a34a; color:#fff; border:none; padding:8px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .sp-btn-ok:disabled { background:#cbd5e1; }
.sp-wrap { margin:12px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:58vh; }
.sp-grid { width:100%; border-collapse:collapse; font-size:13px; }
.sp-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .sp-grid th.r { text-align:right; }
.sp-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .sp-grid td.r { text-align:right; }
.sp-grid tr:nth-child(even) td { background:#f8fafc; } .sp-grid tr.dif td { background:#fef9c3; }
.sp-grid td.neg { color:#b91c1c; font-weight:700; } .sp-grid td.pos { color:#166534; font-weight:700; }
.sp-tfoot td { background:#14532d !important; color:#fff !important; font-weight:800; font-size:13px; padding:9px 10px; border-top:2px solid #14532d; }
.sp-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.sp-pie { display:flex; align-items:center; gap:16px; padding:4px 18px 18px; }
.sp-total-box { display:flex; align-items:center; gap:14px; background:linear-gradient(120deg,#14532d,#16a34a); color:#fff; padding:12px 22px; border-radius:10px; box-shadow:0 6px 18px rgba(20,83,45,.35); }
.sp-total-lbl { font-size:12px; font-weight:700; letter-spacing:.4px; opacity:.9; }
.sp-total-val { font-size:24px; font-weight:800; } .sp-total-cant { font-size:12px; opacity:.85; }
.sp-btn-pdf { margin-left:auto; background:#2563eb; color:#fff; border:none; padding:11px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.sp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.sp-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.sp-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.sp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.sp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .sp-pdf-b.ok { background:#22c55e; color:#fff; } .sp-pdf-b.cancel { background:#ef4444; color:#fff; }
.sp-pdf-frame { flex:1; border:none; width:100%; }
</style>
