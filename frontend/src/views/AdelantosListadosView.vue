<!-- AdelantosListadosView.vue — Adelantos al Personal · Listados -->
<template>
  <div class="ad-view">
    <div class="ad-cab">
      <div class="ad-cab-ico">📊</div>
      <div class="ad-cab-tx"><h1>Adelantos al Personal — Listados</h1><p>Informe de adelantos otorgados</p></div>
      <button class="ad-btn-ayuda" title="Ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <div class="ad-opciones">
      <div class="ad-fila">
        <label class="ad-lbl">Empleados</label>
        <div class="ad-radios">
          <label><input v-model.number="o.empleado" type="radio" :value="1" /> Todos</label>
          <label><input v-model.number="o.empleado" type="radio" :value="2" /> Un empleado</label>
        </div>
      </div>

      <div class="ad-fila" v-if="o.empleado === 2">
        <label class="ad-lbl">Empleado</label>
        <div class="ad-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar por código, legajo o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="ad-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">
              <b>{{ (r.PER_NOM || '').trim() }}</b><span>Cód. {{ r.PER_COD }} · Leg. {{ (r.PER_LEG || '').toString().trim() }}</span>
            </li>
          </ul>
        </div>
        <span v-if="empCod" class="ad-sel">✔ {{ empCod }} — {{ empNom }}</span>
      </div>

      <div class="ad-fila">
        <label class="ad-lbl">Período</label>
        <div class="ad-radios">
          <label><input v-model.number="o.periodo" type="radio" :value="1" /> Histórico</label>
          <label><input v-model.number="o.periodo" type="radio" :value="2" /> Rango</label>
        </div>
      </div>

      <template v-if="o.periodo === 2">
        <div class="ad-fila">
          <label class="ad-lbl">Tipo de fecha</label>
          <div class="ad-radios">
            <label><input v-model.number="o.tipo_periodo" type="radio" :value="1" /> Por emisión</label>
            <label><input v-model.number="o.tipo_periodo" type="radio" :value="2" /> Por imputación</label>
          </div>
        </div>
        <div class="ad-fila">
          <label class="ad-lbl">Desde / Hasta</label>
          <input v-model="o.fecha1" type="date" class="ad-fecha" />
          <input v-model="o.fecha2" type="date" class="ad-fecha" />
        </div>
      </template>

      <div class="ad-acc">
        <button class="ad-btn-conf" :disabled="generando || (o.empleado === 2 && !empCod)" @click="aceptar">
          {{ generando ? '⟳ Generando…' : '📄 Aceptar' }}
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ad-ov" @click.self="ayuda = false">
        <div class="ad-modal">
          <h3>❓ Ayuda — Listados de Adelantos</h3>
          <ul>
            <li><b>Empleados:</b> todos o uno en particular.</li>
            <li><b>Histórico</b> trae todo; <b>Rango</b> filtra por fecha de <b>emisión</b> o por <b>imputación</b> (mes/año).</li>
            <li>Aceptar genera el informe en PDF (A4) con subtotales por empleado y total general.</li>
          </ul>
          <button class="ad-cerrar" @click="ayuda = false">Cerrar</button>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ad-pdf-ov" @click.self="cerrarPdf">
        <div class="ad-pdf-md">
          <div class="ad-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ad-pdf-acc">
              <button class="ad-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ad-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ad-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="ad-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const ayuda = ref(false)
const generando = ref(false)
const o = ref({ empleado: 1, periodo: 1, tipo_periodo: 1, fecha1: new Date().toISOString().slice(0, 10), fecha2: new Date().toISOString().slice(0, 10) })

const empCod = ref(0); const empNom = ref('')
const busqueda = ref(''); const resultados = ref<any[]>([])
let debounce: any = null
const buscar = () => {
  clearTimeout(debounce)
  const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  debounce = setTimeout(async () => {
    try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] }
    catch (e) { resultados.value = [] }
  }, 250)
}
const seleccionar = (r: any) => {
  resultados.value = []
  empCod.value = Number(r.PER_COD); empNom.value = (r.PER_NOM || '').trim()
  busqueda.value = `${r.PER_COD} — ${empNom.value}`
}

const nf = (n: number) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)

const aceptar = async () => {
  generando.value = true
  try {
    const params: any = { empleado: o.value.empleado === 2 ? empCod.value : 0, periodo: o.value.periodo }
    if (o.value.periodo === 2) { params.tipo_periodo = o.value.tipo_periodo; params.fecha1 = o.value.fecha1; params.fecha2 = o.value.fecha2 }
    const { data } = await api.get('/adelantos/listado', { params })
    imprimir(data)
  } catch (e: any) { alert('⚠️ ' + (e?.response?.data?.message ?? 'No se pudo generar el listado.')) }
  finally { generando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const imprimir = (data: any) => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, PW = 210, PH = 297
  let y = 18
  const usuario = (auth.usuario?.NOMBRE || auth.usuario?.DATO1 || '').toString().trim()

  const encabezado = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(27, 67, 50)
    doc.text(data.titulo, PW / 2, y, { align: 'center' }); y += 6
    if (data.subtitulo) { doc.setFontSize(9); doc.setTextColor(80, 80, 80); doc.text(data.subtitulo, PW / 2, y, { align: 'center' }); y += 5 }
    doc.setTextColor(0, 0, 0); y += 2
    cabeceraTabla()
  }
  const cabeceraTabla = () => {
    doc.setFillColor(27, 67, 50); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    doc.rect(ML, y - 4, PW - ML * 2, 6, 'F')
    doc.text('Anticipo', ML + 2, y)
    doc.text('Fecha', ML + 26, y)
    doc.text('Mes', ML + 48, y)
    doc.text('Año', ML + 60, y)
    doc.text('Detalle', ML + 74, y)
    doc.text('LIQ.', ML + 138, y)
    doc.text('Importe', PW - ML - 2, y, { align: 'right' })
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  }
  const saltoSiHaceFalta = () => {
    if (y > PH - 24) { pie(); doc.addPage(); y = 18; encabezado() }
  }
  let pagina = 1
  const pie = () => {
    doc.setFontSize(7.5); doc.setTextColor(120, 120, 120)
    doc.text(new Date().toLocaleString('es-AR') + '  -  ' + usuario, ML, PH - 8)
    doc.text('Página ' + pagina, PW - ML, PH - 8, { align: 'right' }); pagina++
    doc.setTextColor(0, 0, 0)
  }

  encabezado()
  if (!data.grupos.length) {
    doc.setFont('helvetica', 'italic'); doc.setFontSize(10); doc.text('Sin adelantos para los filtros seleccionados.', ML, y + 4)
  }
  for (const g of data.grupos) {
    saltoSiHaceFalta()
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(27, 67, 50)
    doc.text(`(${g.codigo}) ${g.nombre}`, ML, y); doc.setTextColor(0, 0, 0); y += 5
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
    for (const r of g.rows) {
      saltoSiHaceFalta()
      doc.text(String(r.numero), ML + 2, y)
      doc.text(r.fecha, ML + 26, y)
      doc.text(String(r.mes), ML + 48, y)
      doc.text(String(r.anio), ML + 60, y)
      doc.text(doc.splitTextToSize(r.detalle || '', 60)[0] || '', ML + 74, y)
      doc.text(String(r.liq), ML + 138, y)
      doc.text('$ ' + nf(r.importe), PW - ML - 2, y, { align: 'right' }); y += 5
    }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    doc.text('Total ' + g.nombre, ML + 74, y); doc.text('$ ' + nf(g.subtotal), PW - ML - 2, y, { align: 'right' })
    doc.setFont('helvetica', 'normal'); y += 4
    doc.setDrawColor(200); doc.line(ML, y, PW - ML, y); y += 5
  }
  if (data.grupos.length) {
    saltoSiHaceFalta()
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
    doc.text('TOTAL GENERAL', ML + 60, y); doc.text('$ ' + nf(data.total_general), PW - ML - 2, y, { align: 'right' })
  }
  pie()
  cerrarPdf(); pdfNombre.value = 'Adelantos_otorgados.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ad-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ad-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ad-cab-ico { font-size:28px; } .ad-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ad-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ad-btn-ayuda { margin-left:auto; background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ad-opciones { margin:18px; max-width:680px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:16px; }
.ad-fila { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.ad-lbl { width:110px; font-size:13px; font-weight:600; color:#374151; flex-shrink:0; }
.ad-radios { display:flex; gap:18px; }
.ad-radios label { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
.ad-fecha { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; }
.ad-busc { position:relative; flex:1; min-width:240px; }
.ad-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.ad-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.ad-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.ad-result li:hover { background:#f0faf4; } .ad-result li b { font-size:13px; color:#1e293b; } .ad-result li span { font-size:11px; color:#6b7280; }
.ad-sel { font-size:13px; color:#166534; font-weight:600; }
.ad-acc { margin-top:6px; }
.ad-btn-conf { background:#2563eb; color:#fff; border:none; padding:11px 28px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.ad-btn-conf:hover:not(:disabled){ background:#1d4ed8; } .ad-btn-conf:disabled { background:#cbd5e1; }
.ad-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; }
.ad-modal { background:#fff; border-radius:14px; padding:22px; max-width:520px; width:90%; }
.ad-modal h3 { margin:0 0 12px; color:#1a3a5c; } .ad-modal ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ad-cerrar { margin-top:16px; background:#2d6a9f; color:#fff; border:none; border-radius:8px; padding:9px 18px; cursor:pointer; font-weight:700; }
.ad-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ad-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.ad-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ad-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ad-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ad-pdf-b.ok { background:#22c55e; color:#fff; } .ad-pdf-b.cancel { background:#ef4444; color:#fff; }
.ad-pdf-frame { flex:1; border:none; width:100%; }
</style>
