<!-- StockRopaView.vue — Consulta de stock de ropa/EPP (ropa_stock.scx). -->
<template>
  <div class="sk-view">
    <div class="sk-cab">
      <div class="sk-ico">📦</div>
      <div class="sk-tx"><h1>Consulta Stock de Ropa/EPP</h1><p>Existencias de uniforme y elementos de protección</p></div>
      <button class="sk-ia" @click="modalIA = true">🤖 IA</button>
      <button class="sk-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/consulta-stock" titulo="Asistente IA — Consulta de Stock"
            subtitulo="Preguntá sobre las existencias de ropa y EPP"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué diferencia hay entre Detallado y Resumido?','¿Cómo veo sólo un depósito?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['sk-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="sk-body">
      <div class="sk-filtros">
        <div class="fila">
          <span class="lbl">Depósito</span>
          <label class="rad"><input type="radio" :value="false" v-model="fDepo.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fDepo.ind" /> Individual</label>
          <select v-if="fDepo.ind" v-model.number="fDepo.cod"><option :value="0">— Seleccione —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        </div>
        <div class="fila">
          <span class="lbl">EPP</span>
          <label class="rad"><input type="radio" :value="false" v-model="fRopa.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fRopa.ind" /> Individual</label>
          <template v-if="fRopa.ind">
            <input v-model.number="fRopa.cod" type="number" class="inp-cod" placeholder="Código" />
            <button type="button" class="btn-busca" @click="abrirBuscador">🔍</button>
            <span class="sk-ropa-des">{{ fRopa.des }}</span>
          </template>
        </div>
        <div class="fila">
          <span class="lbl">Marca</span>
          <label class="rad"><input type="radio" :value="false" v-model="fMarca.ind" /> Todas</label>
          <label class="rad"><input type="radio" :value="true" v-model="fMarca.ind" /> Una marca</label>
          <select v-if="fMarca.ind" v-model.number="fMarca.cod"><option :value="0">— Seleccione —</option><option v-for="m in marcas" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select>
        </div>
        <div class="fila">
          <span class="lbl">Talles</span>
          <label class="rad"><input type="radio" :value="false" v-model="fTalle.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fTalle.ind" /> Un talle</label>
          <select v-if="fTalle.ind" v-model.number="fTalle.cod"><option :value="0">— Seleccione —</option><option v-for="t in talles" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
        </div>
        <div class="fila">
          <span class="lbl">Carácter</span>
          <label class="rad"><input type="radio" value="det" v-model="caracter" /> Detallado</label>
          <label class="rad"><input type="radio" value="res" v-model="caracter" /> Resumido</label>
          <label class="rad chk"><input type="checkbox" v-model="soloActivo" /> Mostrar EPP activo</label>
        </div>
        <div class="fila">
          <button class="btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳ Consultando…' : '🔎 CONSULTAR' }}</button>
          <button v-if="cargado && items.length" class="btn pdf" @click="generarPdf">🖨 Imprimir</button>
        </div>
      </div>

      <div v-if="cargado" class="sk-result">
        <div class="sk-resumen">{{ items.length }} líneas · TOTAL DE PIEZAS: <b>{{ total }}</b></div>
        <div v-for="g in grupos" :key="g.rubro" class="sk-grupo">
          <div class="sk-rubro">{{ g.rubro }} <span>({{ g.subtotal }})</span></div>
          <table class="sk-tabla">
            <thead><tr><th style="width:70px">Código</th><th>Detalle del EPP</th><th v-if="caracter==='det'">Marca</th><th v-if="caracter==='det'" style="width:90px">Talle</th><th v-if="caracter==='det'">Depósito</th><th class="c" style="width:90px">Cantidad</th></tr></thead>
            <tbody>
              <tr v-for="(r, i) in g.filas" :key="i">
                <td class="sk-cod">{{ r.codigo }}</td><td>{{ r.detalle }}</td>
                <td v-if="caracter==='det'">{{ r.marca }}</td><td v-if="caracter==='det'">{{ r.talle }}</td>
                <td v-if="caracter==='det'">{{ r.deposito }}</td><td class="c">{{ r.stock }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!items.length" class="sk-vacio">No hay existencias para los filtros seleccionados.</div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="buscador.abierto" class="sk-ov" @click.self="buscador.abierto = false">
        <div class="sk-busca-md">
          <h3>🔍 Buscar prenda / EPP</h3>
          <input ref="inputBusca" v-model="buscador.q" placeholder="Código o descripción…" @input="buscarRopa" />
          <ul class="sk-busca-list">
            <li v-for="r in buscador.res" :key="r.cod" @click="elegirRopa(r)"><b>{{ r.cod }}</b> — {{ r.descripcion }}</li>
            <li v-if="!buscador.res.length" class="sk-busca-vacio">{{ buscador.q.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados' }}</li>
          </ul>
          <div class="fila end"><button class="btn reset" @click="buscador.abierto = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="sk-ov" @click.self="ayuda = false">
        <div class="sk-busca-md">
          <h3>❓ Ayuda — Consulta de Stock</h3>
          <ul class="sk-help">
            <li>Filtrá por <b>depósito</b>, <b>prenda/EPP</b>, <b>marca</b> y <b>talle</b> (o dejá "Todos").</li>
            <li><b>Mostrar EPP activo</b>: excluye las prendas dadas de baja.</li>
            <li><b>Detallado</b>: cada combinación de marca y talle. <b>Resumido</b>: subtotales por rubro y prenda.</li>
            <li>El informe se agrupa por rubro y muestra el total general de piezas.</li>
          </ul>
          <div class="fila end"><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="sk-pdf-ov" @click.self="cerrarPdf">
        <div class="sk-pdf-md">
          <div class="sk-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="sk-pdf-acc">
              <button class="sk-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="sk-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="sk-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="sk-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

interface Combo { cod: number; nombre: string }
interface Linea { codigo: number; detalle: string; mcod: number; marca: string; tcod: number; talle: string; deposito: string; stock: number; rubro: string }

const depositos = ref<Combo[]>([]); const marcas = ref<Combo[]>([]); const talles = ref<Combo[]>([])
const fDepo = ref({ ind: false, cod: 0 }); const fRopa = ref({ ind: false, cod: 0, des: '' })
const fMarca = ref({ ind: false, cod: 0 }); const fTalle = ref({ ind: false, cod: 0 })
const caracter = ref<'det' | 'res'>('det'); const soloActivo = ref(true)
const items = ref<Linea[]>([]); const total = ref(0); const cargado = ref(false); const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

onMounted(async () => {
  try { const { data } = await api.get('/consulta-stock/init'); depositos.value = data.depositos ?? []; marcas.value = data.marcas ?? []; talles.value = data.talles ?? [] }
  catch { flash('No se pudieron cargar los filtros.', true) }
})

// Para el modo Resumido, agrupa por rubro + detalle sumando cantidades.
const itemsMostrar = computed<Linea[]>(() => {
  if (caracter.value === 'det') return items.value
  const map = new Map<string, Linea>()
  for (const r of items.value) {
    const k = `${r.rubro}||${r.detalle}`
    const e = map.get(k)
    if (e) e.stock += r.stock
    else map.set(k, { ...r, marca: '', talle: '', deposito: '' })
  }
  return [...map.values()]
})
const grupos = computed(() => {
  const m = new Map<string, { rubro: string; filas: Linea[]; subtotal: number }>()
  for (const r of itemsMostrar.value) {
    let g = m.get(r.rubro)
    if (!g) { g = { rubro: r.rubro, filas: [], subtotal: 0 }; m.set(r.rubro, g) }
    g.filas.push(r); g.subtotal += r.stock
  }
  return [...m.values()]
})

async function consultar () {
  if (fDepo.value.ind && fDepo.value.cod <= 0) { flash('Seleccione el depósito.', true); return }
  if (fRopa.value.ind && fRopa.value.cod <= 0) { flash('Seleccione la prenda/EPP.', true); return }
  if (fMarca.value.ind && fMarca.value.cod <= 0) { flash('Seleccione la marca.', true); return }
  if (fTalle.value.ind && fTalle.value.cod <= 0) { flash('Seleccione el talle.', true); return }
  cargando.value = true
  try {
    const params: any = { solo_activo: soloActivo.value }
    if (fDepo.value.ind) params.deposito = fDepo.value.cod
    if (fRopa.value.ind) params.ropa = fRopa.value.cod
    if (fMarca.value.ind) params.marca = fMarca.value.cod
    if (fTalle.value.ind) params.talle = fTalle.value.cod
    const { data } = await api.get('/consulta-stock', { params })
    items.value = data.items ?? []; total.value = data.total ?? 0; cargado.value = true
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar el stock.', true) }
  finally { cargando.value = false }
}

// ── Buscador de prenda ──
const inputBusca = ref<HTMLInputElement | null>(null)
const buscador = ref<{ abierto: boolean; q: string; res: any[] }>({ abierto: false, q: '', res: [] })
let dr: any = null
async function abrirBuscador () { buscador.value = { abierto: true, q: '', res: [] }; await nextTick(); inputBusca.value?.focus() }
const buscarRopa = () => {
  clearTimeout(dr); const q = buscador.value.q.trim()
  if (q.length < 2) { buscador.value.res = []; return }
  dr = setTimeout(async () => { try { buscador.value.res = (await api.get('/ropa-epp', { params: { buscar: q } })).data ?? [] } catch { buscador.value.res = [] } }, 250)
}
function elegirRopa (r: any) { fRopa.value.cod = r.cod; fRopa.value.des = r.descripcion; buscador.value.abierto = false }

// ── PDF ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPdf () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, MR = 196, PW = 210, PH = 297; let y = 16
  const det = caracter.value === 'det'
  let titulo = 'STOCK DE EPP Y ELEMENTOS DE SEGURIDAD'
  if (fRopa.value.ind) titulo += ` - (${fRopa.value.cod}) ${fRopa.value.des}`
  doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(20, 50, 90)
  doc.text(titulo, PW / 2, y, { align: 'center', maxWidth: MR - ML }); doc.setTextColor(0, 0, 0); y += 9
  for (const g of grupos.value) {
    if (y > PH - 24) { doc.addPage(); y = 18 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
    doc.text(g.rubro, ML, y); doc.setTextColor(0, 0, 0); y += 5
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.rect(ML, y - 4, MR - ML, 6, 'F')
    const cx = det ? { c: ML + 2, d: ML + 22, m: ML + 96, t: ML + 132, p: ML + 152, q: MR - 4 } : { c: ML + 2, d: ML + 22, m: 0, t: 0, p: 0, q: MR - 4 }
    doc.text('Código', cx.c, y); doc.text('Detalle del EPP', cx.d, y)
    if (det) { doc.text('Marca', cx.m, y); doc.text('Talle', cx.t, y); doc.text('Depósito', cx.p, y) }
    doc.text('Cant.', cx.q, y, { align: 'right' })
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 5.5
    for (const r of g.filas) {
      if (y > PH - 16) { doc.addPage(); y = 18 }
      doc.setFontSize(8)
      doc.text(String(r.codigo), cx.c, y); doc.text((doc.splitTextToSize(r.detalle, det ? 70 : 150)[0] || ''), cx.d, y)
      if (det) { doc.text((r.marca || '').slice(0, 18), cx.m, y); doc.text((r.talle || '').slice(0, 8), cx.t, y); doc.text((r.deposito || '').slice(0, 14), cx.p, y) }
      doc.text(String(r.stock), cx.q, y, { align: 'right' }); y += 4.5
    }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5)
    doc.text(`SUBTOTAL ${g.rubro}`, cx.d, y + 1); doc.text(String(g.subtotal), cx.q, y + 1, { align: 'right' })
    doc.setFont('helvetica', 'normal'); y += 7
  }
  if (y > PH - 16) { doc.addPage(); y = 18 }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
  doc.text('TOTAL DE PIEZAS EN GENERAL', ML, y); doc.text(String(total.value), MR - 4, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = `Stock_ropa_${det ? 'detallado' : 'resumido'}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.sk-view { display:flex; flex-direction:column; min-height:100%; }
.sk-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sk-ico { font-size:28px; } .sk-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sk-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sk-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sk-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sk-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .sk-msg.ok { background:#d1fae5; color:#065f46; } .sk-msg.err { background:#fee2e2; color:#991b1b; }
.sk-body { padding:16px 18px; }
.sk-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; max-width:760px; display:flex; flex-direction:column; gap:10px; }
.fila { display:flex; gap:12px; align-items:center; flex-wrap:wrap; } .fila.end { justify-content:flex-end; }
.lbl { width:78px; font-size:13px; font-weight:700; color:#2a4a6a; }
.rad { display:flex; align-items:center; gap:5px; font-size:13px; color:#374151; cursor:pointer; } .rad.chk { margin-left:8px; font-weight:600; color:#1b4332; }
.fila select, .inp-cod { border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:13px; } .inp-cod { width:90px; text-align:right; }
.btn-busca { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:5px 8px; font-size:13px; } .sk-ropa-des { font-size:12px; color:#2d6a9f; font-weight:600; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#1b4332; color:#fff; } .btn.pdf { background:#e0eefc; color:#2d6a9f; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.sk-result { margin-top:16px; }
.sk-resumen { font-size:13px; color:#1e293b; margin-bottom:10px; } .sk-resumen b { font-size:16px; color:#1b4332; }
.sk-grupo { margin-bottom:14px; }
.sk-rubro { font-weight:700; color:#1b4332; font-size:13px; margin-bottom:4px; } .sk-rubro span { color:#6b7280; font-weight:500; }
.sk-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.sk-tabla th { background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11.5px; } .sk-tabla th.c { text-align:center; }
.sk-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .sk-tabla td.c { text-align:center; } .sk-cod { color:#2d6a9f; font-weight:700; }
.sk-vacio { text-align:center; color:#94a3b8; padding:16px; }
.sk-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.sk-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .sk-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.sk-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; }
.sk-busca-list { list-style:none; margin:10px 0; padding:0; max-height:340px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.sk-busca-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .sk-busca-list li:hover { background:#f0faf4; } .sk-busca-list li b { color:#2d6a9f; }
.sk-busca-vacio { color:#94a3b8; cursor:default !important; } .sk-busca-vacio:hover { background:none !important; }
.sk-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.sk-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.sk-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.sk-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .sk-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.sk-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .sk-pdf-b.ok { background:#22c55e; color:#fff; } .sk-pdf-b.cancel { background:#ef4444; color:#fff; }
.sk-pdf-frame { flex:1; border:none; width:100%; }
</style>
