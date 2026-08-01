<!-- ConsultaCalificacionesView.vue — Informes de Calificaciones (calificacion_consulta) -->
<template>
  <div class="cc-view">
    <div class="cc-cab">
      <div class="cc-ico">🔍</div>
      <div class="cc-tx"><h1>Informes de Calificaciones</h1><p>Consulta de calificaciones registradas</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['cc-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="cc-opciones">
      <div class="cc-fila">
        <label class="cc-lbl">Empleados</label>
        <div class="cc-radios"><label><input v-model.number="o.empleado" type="radio" :value="1" /> Todos</label><label><input v-model.number="o.empleado" type="radio" :value="2" /> Individual</label></div>
        <div v-if="o.empleado === 2" class="cc-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar empleado…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="cc-result"><li v-for="r in resultados" :key="r.PER_COD" @click="elegirEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>{{ r.PER_COD }}</span></li></ul>
        </div>
        <span v-if="o.empleado === 2 && empCod" class="cc-sel">✔ {{ empCod }} — {{ empNom }}</span>
      </div>

      <FiltroCombo label="Empresa" v-model:modo="o.empresa" v-model:id="o.empresa_id" :items="cat.empresas" />
      <FiltroCombo label="Contratista" v-model:modo="o.contratista" v-model:id="o.contratista_id" :items="cat.contratistas" />
      <FiltroCombo label="Lugar" v-model:modo="o.lugar" v-model:id="o.lugar_id" :items="cat.lugares" />
      <FiltroCombo label="Convenio" v-model:modo="o.convenio" v-model:id="o.convenio_id" :items="cat.convenios" />
      <FiltroCombo label="Categoría" v-model:modo="o.categoria" v-model:id="o.categoria_id" :items="cat.categorias" />

      <div class="cc-fila">
        <label class="cc-lbl">Período</label>
        <div class="cc-radios"><label><input v-model.number="o.periodo" type="radio" :value="1" /> Histórico</label><label><input v-model.number="o.periodo" type="radio" :value="2" /> Rango</label></div>
        <template v-if="o.periodo === 2"><input v-model="o.fecha1" type="date" class="cc-fecha" /><input v-model="o.fecha2" type="date" class="cc-fecha" /></template>
      </div>

      <div class="cc-acc">
        <button class="btn ok" :disabled="gen || (o.empleado === 2 && !empCod)" @click="consultar">{{ gen ? '⟳ …' : '🔍 Consultar' }}</button>
        <button v-if="data" class="btn pdf" @click="imprimir">📄 PDF</button>
      </div>
    </div>

    <div class="cc-grid" v-if="data">
      <div class="cc-grid-head"><span>Calificaciones</span><span class="cc-tot">{{ data.total }} registro(s)</span></div>
      <div v-for="g in data.grupos" :key="g.codigo" class="cc-grupo">
        <div class="cc-grupo-tit">({{ g.codigo }}) {{ g.nombre }} <em>· {{ g.cuit }}</em></div>
        <table>
          <thead><tr><th>Fecha</th><th>Puesto</th><th>Resultado</th><th>Positivas</th><th>Aspectos a Mejorar</th><th>Cuil Resp.</th></tr></thead>
          <tbody>
            <tr v-for="(it, i) in g.items" :key="i">
              <td>{{ it.fecha }}</td><td>{{ it.puesto_des || it.puesto }}</td><td>{{ it.resultado }}</td>
              <td>{{ it.positivas }}</td><td>{{ it.negativas }}</td><td>{{ it.cuil_resp }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!data.grupos.length" class="cc-vacio">Sin calificaciones para los filtros seleccionados.</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="cc-pdf-ov" @click.self="cerrarPdf">
        <div class="cc-pdf-md">
          <div class="cc-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cc-pdf-acc">
              <button class="cc-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cc-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cc-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="cc-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, h, defineComponent } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'

// Sub-componente reutilizable: radio Todas/Un + combo
const FiltroCombo = defineComponent({
  props: { label: String, modo: Number, id: Number, items: Array as () => any[] },
  emits: ['update:modo', 'update:id'],
  setup (p, { emit }) {
    return () => h('div', { class: 'cc-fila' }, [
      h('label', { class: 'cc-lbl' }, p.label),
      h('div', { class: 'cc-radios' }, [
        h('label', [h('input', { type: 'radio', checked: p.modo === 1, onChange: () => emit('update:modo', 1) }), ' Todas']),
        h('label', [h('input', { type: 'radio', checked: p.modo === 2, onChange: () => emit('update:modo', 2) }), ' Una']),
      ]),
      p.modo === 2 ? h('select', { class: 'cc-sel-combo', value: p.id, onChange: (e: any) => emit('update:id', Number(e.target.value)) }, [
        h('option', { value: 0 }, '— Seleccioná —'),
        ...(p.items || []).map((x: any) => h('option', { value: x.id, key: x.id }, x.label)),
      ]) : null,
    ])
  },
})

const o = ref({ empleado: 1, empresa: 1, empresa_id: 0, contratista: 1, contratista_id: 0, lugar: 1, lugar_id: 0, convenio: 1, convenio_id: 0, categoria: 1, categoria_id: 0, periodo: 1, fecha1: new Date().toISOString().slice(0, 10), fecha2: new Date().toISOString().slice(0, 10) })
const cat = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [] })
const gen = ref(false); const data = ref<any>(null); const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const norm = (x: any) => ({ id: Number(x.cod ?? x.codigo ?? x.id ?? 0), label: (x.nombre ?? x.descripcion ?? x.des ?? x.detalle ?? x.cont_det ?? '').toString().trim() })

const empCod = ref(0); const empNom = ref('')
const busqueda = ref(''); const resultados = ref<any[]>([])
let dq: any = null
const buscar = () => { clearTimeout(dq); const q = busqueda.value.trim(); if (q.length < 2) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250) }
const elegirEmp = (r: any) => { resultados.value = []; empCod.value = Number(r.PER_COD); empNom.value = (r.PER_NOM || '').trim(); busqueda.value = `${r.PER_COD} — ${empNom.value}` }

onMounted(async () => {
  try {
    const [e, c, l, cv, ct] = await Promise.all([api.get('/empresas'), api.get('/contratistas'), api.get('/lugares'), api.get('/convenios'), api.get('/categorias')])
    cat.empresas = (e.data ?? []).map(norm); cat.contratistas = (c.data ?? []).map(norm); cat.lugares = (l.data ?? []).map(norm); cat.convenios = (cv.data ?? []).map(norm); cat.categorias = (ct.data ?? []).map(norm)
  } catch { /* */ }
})

async function consultar () {
  gen.value = true; data.value = null
  try {
    const p: any = { empleado: o.value.empleado, empresa: o.value.empresa, contratista: o.value.contratista, lugar: o.value.lugar, convenio: o.value.convenio, categoria: o.value.categoria, periodo: o.value.periodo }
    if (o.value.empleado === 2) p.codigo = empCod.value
    if (o.value.empresa === 2) p.empresa_id = o.value.empresa_id
    if (o.value.contratista === 2) p.contratista_id = o.value.contratista_id
    if (o.value.lugar === 2) p.lugar_id = o.value.lugar_id
    if (o.value.convenio === 2) p.convenio_id = o.value.convenio_id
    if (o.value.categoria === 2) p.categoria_id = o.value.categoria_id
    if (o.value.periodo === 2) { p.fecha1 = o.value.fecha1; p.fecha2 = o.value.fecha2 }
    data.value = (await api.get('/calificaciones/consulta', { params: p })).data
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar.', true) }
  finally { gen.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function imprimir () {
  if (!data.value) return
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 12, PW = 297, PH = 210; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(27, 67, 50)
  doc.text('CALIFICACIONES', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 8
  const cab = () => {
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.rect(ML, y - 4, PW - ML * 2, 6, 'F')
    doc.text('Fecha', ML + 1, y); doc.text('Puesto', ML + 24, y); doc.text('Resultado', ML + 80, y); doc.text('Positivas', ML + 140, y); doc.text('Aspectos a Mejorar', ML + 195, y); doc.text('Cuil Resp.', PW - ML - 24, y)
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6
  }
  for (const g of data.value.grupos) {
    if (y > PH - 24) { doc.addPage(); y = 16 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(0, 0, 0); doc.text(`(${g.codigo}) ${g.nombre}`, ML, y); y += 5; cab(); doc.setFontSize(8)
    for (const it of g.items) {
      if (y > PH - 12) { doc.addPage(); y = 16; cab() }
      doc.text(it.fecha, ML + 1, y)
      doc.text(doc.splitTextToSize(it.puesto_des || it.puesto, 52)[0] || '', ML + 24, y)
      doc.text(doc.splitTextToSize(it.resultado, 56)[0] || '', ML + 80, y)
      doc.text(doc.splitTextToSize(it.positivas, 52)[0] || '', ML + 140, y)
      doc.text(doc.splitTextToSize(it.negativas, 48)[0] || '', ML + 195, y)
      doc.text(it.cuil_resp, PW - ML - 24, y); y += 4.6
    }
    y += 4
  }
  cerrarPdf(); pdfNombre.value = 'Calificaciones.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.cc-view { display:flex; flex-direction:column; min-height:100%; }
.cc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cc-ico { font-size:28px; } .cc-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cc-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cc-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cc-msg.ok { background:#d1fae5; color:#065f46; } .cc-msg.err { background:#fee2e2; color:#991b1b; }
.cc-opciones { margin:18px 18px 8px; max-width:760px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:12px; }
:deep(.cc-fila) { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
:deep(.cc-lbl) { width:95px; font-size:13px; font-weight:600; color:#374151; flex-shrink:0; }
:deep(.cc-radios) { display:flex; gap:16px; } :deep(.cc-radios label) { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
:deep(.cc-sel-combo) { border:1px solid #c8d8ea; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; min-width:240px; }
.cc-fecha { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; }
.cc-busc { position:relative; flex:1; min-width:220px; } .cc-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.cc-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:220px; overflow:auto; }
.cc-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; } .cc-result li:hover { background:#f0faf4; } .cc-result li b { font-size:13px; color:#1e293b; } .cc-result li span { font-size:11px; color:#6b7280; }
.cc-sel { font-size:13px; color:#166534; font-weight:600; }
.cc-acc { display:flex; gap:10px; margin-top:4px; }
.btn { border:none; padding:10px 20px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; } .btn.ok { background:#2563eb; color:#fff; } .btn.pdf { background:#15803d; color:#fff; } .btn:disabled { opacity:.5; cursor:default; }
.cc-grid { margin:8px 18px 18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.cc-grid-head { display:flex; justify-content:space-between; padding:10px 14px; background:#f4f7fc; border-bottom:1px solid #e2e8f0; font-size:13px; color:#1a3a5c; font-weight:700; }
.cc-grupo-tit { padding:8px 14px; background:#eef6ef; font-weight:700; color:#1b4332; font-size:13px; } .cc-grupo-tit em { color:#64748b; font-style:normal; }
table { width:100%; border-collapse:collapse; font-size:12.5px; } th { background:#2d6a9f; color:#fff; padding:6px 9px; text-align:left; font-size:11.5px; } td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.cc-vacio { text-align:center; color:#94a3b8; padding:18px; }
.cc-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cc-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.cc-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .cc-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cc-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cc-pdf-b.ok { background:#22c55e; color:#fff; } .cc-pdf-b.cancel { background:#ef4444; color:#fff; }
.cc-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
