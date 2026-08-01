<!-- EmpleadosPorPuestoView.vue — Empleados por Puesto de Trabajo (consulta/informe) -->
<template>
  <div class="ep-view">
    <div class="ep-cab">
      <div class="ep-ico">👥</div>
      <div class="ep-tx"><h1>Empleados por Puesto de Trabajo</h1><p>Listado de empleados agrupados por su puesto</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ep-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ep-opciones">
      <div class="ep-fila">
        <label class="ep-lbl">Empresa</label>
        <div class="ep-radios">
          <label><input v-model.number="o.empresa" type="radio" :value="1" /> Todas</label>
          <label><input v-model.number="o.empresa" type="radio" :value="2" /> Una</label>
        </div>
        <select v-if="o.empresa === 2" v-model.number="o.empresa_id" class="ep-sel">
          <option :value="0">— Seleccioná —</option>
          <option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option>
        </select>
      </div>

      <div class="ep-fila">
        <label class="ep-lbl">Puestos</label>
        <div class="ep-radios">
          <label><input v-model.number="o.modo" type="radio" :value="1" /> Todos los puestos</label>
          <label><input v-model.number="o.modo" type="radio" :value="2" /> Un puesto</label>
        </div>
      </div>
      <div class="ep-fila" v-if="o.modo === 2">
        <label class="ep-lbl">Puesto</label>
        <div class="ep-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar puesto…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="ep-result">
            <li v-for="r in resultados" :key="r.codigo" @click="elegir(r)"><b>{{ r.descripcion }}</b><span>{{ r.codigo }} · {{ r.depto }}</span></li>
          </ul>
        </div>
        <span v-if="puestoCod" class="ep-selp">✔ {{ puestoCod }} — {{ puestoDes }}</span>
      </div>

      <div class="ep-fila">
        <label class="ep-lbl">Estado</label>
        <div class="ep-radios">
          <label><input v-model.number="o.estado" type="radio" :value="1" /> Todos</label>
          <label><input v-model.number="o.estado" type="radio" :value="2" /> Activos</label>
          <label><input v-model.number="o.estado" type="radio" :value="3" /> Pasivos</label>
        </div>
      </div>

      <div class="ep-acc">
        <button class="btn ok" :disabled="gen || (o.empresa === 2 && !o.empresa_id) || (o.modo === 2 && !puestoCod)" @click="consultar">{{ gen ? '⟳ …' : '🔍 Consultar' }}</button>
        <button v-if="data" class="btn pdf" @click="imprimir">📄 PDF</button>
        <button v-if="data" class="btn exp" @click="exportar">⬇ Excel</button>
      </div>
    </div>

    <div class="ep-grid" v-if="data">
      <div class="ep-grid-head"><span>{{ data.titulo }}</span><span class="ep-tot">{{ data.total }} empleado(s)</span></div>
      <div v-for="g in data.grupos" :key="g.puesto" class="ep-grupo">
        <div class="ep-grupo-tit">{{ g.puesto_des }} <em>({{ g.puesto }})</em> · {{ g.empleados.length }}</div>
        <table>
          <thead><tr><th>Legajo</th><th>Código</th><th>Nombre</th><th>CUIT</th><th>Estado</th></tr></thead>
          <tbody>
            <tr v-for="e in g.empleados" :key="e.codigo">
              <td>{{ e.legajo }}</td><td>{{ e.codigo }}</td><td>{{ e.nombre }}</td><td>{{ e.cuit }}</td><td>{{ e.estado }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="!data.grupos.length" class="ep-vacio">Sin empleados para los filtros seleccionados.</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="ep-pdf-ov" @click.self="cerrarPdf">
        <div class="ep-pdf-md">
          <div class="ep-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ep-pdf-acc">
              <button class="ep-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ep-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ep-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ep-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl, guardarComo } from '@/utils/descargas'

const o = ref({ empresa: 1, empresa_id: 0, modo: 1, estado: 2 })
const empresas = ref<{ cod: number; nombre: string }[]>([])
const gen = ref(false); const data = ref<any>(null); const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const puestoCod = ref(''); const puestoDes = ref('')
const busqueda = ref(''); const resultados = ref<any[]>([])
let dq: any = null
const buscar = () => {
  clearTimeout(dq)
  const q = busqueda.value.trim()
  if (q.length < 1) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/puestos', { params: { buscar: q } })).data ?? [] } catch { resultados.value = [] } }, 250)
}
const elegir = (r: any) => { resultados.value = []; puestoCod.value = r.codigo; puestoDes.value = r.descripcion; busqueda.value = `${r.codigo} — ${r.descripcion}` }

onMounted(async () => { try { empresas.value = (await api.get('/empresas')).data ?? [] } catch { /* */ } })

async function consultar () {
  gen.value = true; data.value = null
  try {
    const params: any = { empresa: o.value.empresa, modo: o.value.modo, estado: o.value.estado }
    if (o.value.empresa === 2) params.empresa_id = o.value.empresa_id
    if (o.value.modo === 2) params.puesto = puestoCod.value
    data.value = (await api.get('/puestos/empleados-por-puesto', { params })).data
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar.', true) }
  finally { gen.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function imprimir () {
  if (!data.value) return
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 16, PW = 210, PH = 297; let y = 18
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(27, 67, 50)
  doc.text('INFORME DE EMPLEADOS POR PUESTO', PW / 2, y, { align: 'center' }); y += 6
  doc.setFontSize(9); doc.setTextColor(80, 80, 80); doc.text(data.value.titulo.replace('INFORME DE EMPLEADOS POR PUESTO — ', ''), PW / 2, y, { align: 'center' })
  doc.setTextColor(0, 0, 0); y += 8
  for (const g of data.value.grupos) {
    if (y > PH - 26) { doc.addPage(); y = 18 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50)
    doc.text(`${g.puesto_des}  ( ${g.puesto} )`, ML, y); doc.setTextColor(0, 0, 0); y += 5
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFont('helvetica', 'bold'); doc.setFontSize(8)
    doc.rect(ML, y - 4, PW - ML * 2, 6, 'F')
    doc.text('Legajo', ML + 1, y); doc.text('Nombre', ML + 26, y); doc.text('CUIT', ML + 120, y); doc.text('Estado', PW - ML - 2, y, { align: 'right' })
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 6; doc.setFontSize(8.5)
    for (const e of g.empleados) {
      if (y > PH - 14) { doc.addPage(); y = 18 }
      doc.text(e.legajo || String(e.codigo), ML + 1, y)
      doc.text(doc.splitTextToSize(e.nombre, 92)[0] || '', ML + 26, y)
      doc.text(e.cuit, ML + 120, y)
      doc.text(e.estado, PW - ML - 2, y, { align: 'right' }); y += 4.8
    }
    y += 5
  }
  cerrarPdf(); pdfNombre.value = 'Empleados_por_puesto.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

function exportar () {
  if (!data.value) return
  const f: string[] = ['Puesto;CodPuesto;Legajo;Codigo;Nombre;CUIT;Estado']
  for (const g of data.value.grupos) for (const e of g.empleados) {
    f.push([g.puesto_des, g.puesto, e.legajo, e.codigo, (e.nombre || '').replace(/;/g, ','), e.cuit, e.estado].join(';'))
  }
  guardarComo(new Blob(['﻿' + f.join('\n')], { type: 'text/csv;charset=utf-8;' }), 'Empleados_por_puesto.csv')
}
</script>

<style scoped>
.ep-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ep-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ep-ico { font-size:28px; } .ep-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ep-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ep-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ep-msg.ok { background:#d1fae5; color:#065f46; } .ep-msg.err { background:#fee2e2; color:#991b1b; }
.ep-opciones { margin:18px 18px 8px; max-width:720px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:14px; }
.ep-fila { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.ep-lbl { width:90px; font-size:13px; font-weight:600; color:#374151; flex-shrink:0; }
.ep-radios { display:flex; gap:16px; } .ep-radios label { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
.ep-sel { border:1px solid #c8d8ea; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; min-width:240px; }
.ep-busc { position:relative; flex:1; min-width:240px; }
.ep-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.ep-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.ep-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.ep-result li:hover { background:#f0faf4; } .ep-result li b { font-size:13px; color:#1e293b; } .ep-result li span { font-size:11px; color:#6b7280; }
.ep-selp { font-size:13px; color:#166534; font-weight:600; }
.ep-acc { display:flex; gap:10px; margin-top:4px; }
.btn { border:none; padding:10px 20px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2563eb; color:#fff; } .btn.pdf { background:#15803d; color:#fff; } .btn.exp { background:#0e7490; color:#fff; } .btn:disabled { opacity:.5; cursor:default; }
.ep-grid { margin:8px 18px 18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.ep-grid-head { display:flex; justify-content:space-between; padding:10px 14px; background:#f4f7fc; border-bottom:1px solid #e2e8f0; font-size:13px; color:#1a3a5c; font-weight:700; }
.ep-grupo { border-bottom:1px solid #eef2f7; }
.ep-grupo-tit { padding:8px 14px; background:#eef6ef; font-weight:700; color:#1b4332; font-size:13px; } .ep-grupo-tit em { color:#64748b; font-style:normal; }
table { width:100%; border-collapse:collapse; font-size:13px; }
th { background:#2d6a9f; color:#fff; padding:7px 10px; text-align:left; font-size:12px; }
td { padding:6px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ep-vacio { text-align:center; color:#94a3b8; padding:18px; }
.ep-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ep-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ep-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.ep-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ep-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ep-pdf-b.ok { background:#22c55e; color:#fff; } .ep-pdf-b.cancel { background:#ef4444; color:#fff; }
.ep-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
