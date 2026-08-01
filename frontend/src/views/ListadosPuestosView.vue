<!-- ListadosPuestosView.vue — Listados de Puestos (informe puesto_detalle, todos o individual) -->
<template>
  <div class="lp-view">
    <div class="lp-cab">
      <div class="lp-ico">📊</div>
      <div class="lp-tx"><h1>Informes de Puestos de Trabajo</h1><p>Descripción completa de los puestos</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['lp-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="lp-opciones">
      <div class="lp-fila">
        <label class="lp-lbl">Alcance</label>
        <div class="lp-radios">
          <label><input v-model.number="o.modo" type="radio" :value="1" /> Todos</label>
          <label><input v-model.number="o.modo" type="radio" :value="2" /> Individual</label>
        </div>
      </div>
      <div class="lp-fila" v-if="o.modo === 2">
        <label class="lp-lbl">Puesto</label>
        <div class="lp-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar puesto…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="lp-result">
            <li v-for="r in resultados" :key="r.codigo" @click="elegir(r)"><b>{{ r.descripcion }}</b><span>{{ r.codigo }} · {{ r.depto }}</span></li>
          </ul>
        </div>
        <span v-if="puestoCod" class="lp-sel">✔ {{ puestoCod }} — {{ puestoDes }}</span>
      </div>
      <div class="lp-fila">
        <label class="lp-lbl">Revisiones</label>
        <div class="lp-radios">
          <label><input v-model.number="o.revisiones" type="radio" :value="1" /> Con revisiones</label>
          <label><input v-model.number="o.revisiones" type="radio" :value="2" /> Sin revisiones</label>
        </div>
      </div>
      <div class="lp-acc">
        <button class="btn ok" :disabled="gen || (o.modo === 2 && !puestoCod)" @click="generar(false)">{{ gen ? '⟳ …' : '🔍 Consultar' }}</button>
        <button class="btn pdf" :disabled="gen || (o.modo === 2 && !puestoCod)" @click="generar(true)">📄 PDF (con empleados)</button>
      </div>
      <p class="lp-nota">“Consultar” lista todos los puestos del alcance. “PDF” incluye solo los puestos con empleados asignados.</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="lp-pdf-ov" @click.self="cerrarPdf">
        <div class="lp-pdf-md">
          <div class="lp-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="lp-pdf-acc">
              <button class="lp-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="lp-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="lp-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="lp-pdf-frame"></iframe>
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

const o = ref({ modo: 1, revisiones: 1 })
const gen = ref(false); const msg = ref(''); const msgErr = ref(false)
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

async function generar (soloConEmpleados: boolean) {
  gen.value = true
  try {
    const params: any = { modo: o.value.modo, revisiones: o.value.revisiones, solo_con_empleados: soloConEmpleados ? 1 : 0 }
    if (o.value.modo === 2) params.puesto = puestoCod.value
    const { data } = await api.get('/puestos/informe', { params })
    if (!data.puestos.length) { flash('No hay puestos para los filtros seleccionados.', true); return }
    imprimir(data.puestos)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el informe.', true) }
  finally { gen.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const fmtFecha = (f: string) => f ? f.split('-').reverse().join('/') : ''

function imprimir (puestos: any[]) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 16, PW = 210, PH = 297
  puestos.forEach((p, idx) => {
    if (idx > 0) doc.addPage()
    let y = 18
    const salto = (need = 8) => { if (y + need > PH - 14) { doc.addPage(); y = 18 } }
    const titulo = (t: string) => { salto(); doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50); doc.text(t, ML, y); doc.setTextColor(0, 0, 0); y += 6 }
    const linea = (l: string, v: string) => { salto(); doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(l, ML, y); doc.setFont('helvetica', 'normal'); const ls = doc.splitTextToSize(v || '—', 150); doc.text(ls, ML + 38, y); y += Math.max(1, ls.length) * 5 + 1 }

    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(27, 67, 50)
    doc.text('Descripción del Puesto', PW / 2, y, { align: 'center' }); doc.setTextColor(0, 0, 0); y += 10
    linea('Código:', p.codigo); linea('Puesto:', p.descripcion); linea('Departamento:', p.depto_nombre)
    linea('Reporta:', p.reporta); linea('Fecha:', fmtFecha(p.fecha)); linea('Objetivos:', p.objetivo); y += 2

    if (p.tareas.length) { titulo('Tareas'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const t of p.tareas) { salto(); doc.text(`• ${t.des}`, ML + 2, y); doc.text(t.fre_des || '', ML + 110, y); doc.text(t.min ? `${t.min} min` : '', ML + 150, y); y += 5 } y += 2 }
    if (p.competencias.length) { titulo('Competencias'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const c of p.competencias) { salto(); doc.text(`• ${c}`, ML + 2, y); y += 5 } y += 2 }
    if (p.educacion.length) { titulo('Requisitos Educacionales'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const e of p.educacion) { salto(); doc.text(`• ${e.des}${e.aca ? ' (Académica)' : ''}${e.com ? ' (Complementaria)' : ''}`, ML + 2, y); y += 5 } y += 2 }
    if (p.cualidades.length) { titulo('Cualidades Necesarias'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const c of p.cualidades) { salto(); doc.text(`• ${c}`, ML + 2, y); y += 5 } y += 2 }
    if (p.revisiones.length) { titulo('Revisiones realizadas'); doc.setFont('helvetica', 'normal'); doc.setFontSize(9); for (const r of p.revisiones) { salto(); doc.setFont('helvetica', 'bold'); doc.text(`${fmtFecha(r.fre)} — ${r.res}`, ML + 2, y); y += 5; doc.setFont('helvetica', 'normal'); const obs = [r.ob1, r.ob2, r.ob3, r.ob4].filter(Boolean).join(' · '); if (obs) { const ls = doc.splitTextToSize(obs, 170); doc.text(ls, ML + 4, y); y += ls.length * 5 } } }
  })
  cerrarPdf(); pdfNombre.value = 'Puestos_de_Trabajo.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.lp-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.lp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.lp-ico { font-size:28px; } .lp-tx h1 { margin:0; font-size:19px; color:#1e293b; } .lp-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.lp-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .lp-msg.ok { background:#d1fae5; color:#065f46; } .lp-msg.err { background:#fee2e2; color:#991b1b; }
.lp-opciones { margin:18px; max-width:640px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:16px; }
.lp-fila { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.lp-lbl { width:90px; font-size:13px; font-weight:600; color:#374151; flex-shrink:0; }
.lp-radios { display:flex; gap:18px; } .lp-radios label { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
.lp-busc { position:relative; flex:1; min-width:240px; }
.lp-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.lp-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.lp-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.lp-result li:hover { background:#f0faf4; } .lp-result li b { font-size:13px; color:#1e293b; } .lp-result li span { font-size:11px; color:#6b7280; }
.lp-sel { font-size:13px; color:#166534; font-weight:600; }
.lp-acc { display:flex; gap:10px; margin-top:4px; }
.btn { border:none; padding:11px 22px; border-radius:7px; cursor:pointer; font-size:14px; font-weight:700; }
.btn.ok { background:#2563eb; color:#fff; } .btn.pdf { background:#15803d; color:#fff; } .btn:disabled { opacity:.5; cursor:default; }
.lp-nota { font-size:12px; color:#64748b; margin:0; }
.lp-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.lp-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.lp-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.lp-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.lp-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.lp-pdf-b.ok { background:#22c55e; color:#fff; } .lp-pdf-b.cancel { background:#ef4444; color:#fff; }
.lp-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
