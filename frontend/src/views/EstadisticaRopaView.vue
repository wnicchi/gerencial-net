<!-- EstadisticaRopaView.vue — Estadística de entregas de ropa/EPP (ropa_estadistica.scx). -->
<template>
  <div class="es-view">
    <div class="es-cab">
      <div class="es-ico">📊</div>
      <div class="es-tx"><h1>Estadística de Entregas</h1><p>Totales entregados de uniforme y EPP</p></div>
      <button class="es-ia" @click="modalIA = true">🤖 IA</button>
      <button class="es-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/estadistica-ropa" titulo="Asistente IA — Estadística de Entregas"
            subtitulo="Preguntá sobre las estadísticas de entregas"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo filtro por fechas?','¿Cómo se agrupan los totales?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['es-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="es-body">
      <div class="es-filtros">
        <div class="fila">
          <span class="lbl">Período</span>
          <label class="rad"><input type="radio" value="hist" v-model="periodo" /> Histórico</label>
          <label class="rad"><input type="radio" value="rango" v-model="periodo" /> Rango</label>
          <template v-if="periodo === 'rango'">
            <input v-model="desde" type="date" /> <span>a</span> <input v-model="hasta" type="date" />
          </template>
        </div>
        <div class="fila">
          <span class="lbl">Depósito</span>
          <label class="rad"><input type="radio" :value="false" v-model="fDepo.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fDepo.ind" /> Individual</label>
          <select v-if="fDepo.ind" v-model.number="fDepo.cod"><option :value="0">— —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        </div>
        <div class="fila">
          <span class="lbl">EPP</span>
          <label class="rad"><input type="radio" :value="false" v-model="fRopa.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fRopa.ind" /> Individual</label>
          <template v-if="fRopa.ind">
            <input v-model.number="fRopa.cod" type="number" class="inp-cod" placeholder="Código" />
            <button type="button" class="btn-busca" @click="abrirBuscador">🔍</button>
            <span class="es-ropa-des">{{ fRopa.des }}</span>
          </template>
        </div>
        <div class="fila">
          <span class="lbl">Marca</span>
          <label class="rad"><input type="radio" :value="false" v-model="fMarca.ind" /> Todas</label>
          <label class="rad"><input type="radio" :value="true" v-model="fMarca.ind" /> Una marca</label>
          <select v-if="fMarca.ind" v-model.number="fMarca.cod"><option :value="0">— —</option><option v-for="m in marcas" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select>
        </div>
        <div class="fila">
          <span class="lbl">Talles</span>
          <label class="rad"><input type="radio" :value="false" v-model="fTalle.ind" /> Todos</label>
          <label class="rad"><input type="radio" :value="true" v-model="fTalle.ind" /> Un talle</label>
          <select v-if="fTalle.ind" v-model.number="fTalle.cod"><option :value="0">— —</option><option v-for="t in talles" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
        </div>
        <div class="fila">
          <button class="btn ok" :disabled="cargando" @click="consultar">{{ cargando ? '⟳ Consultando…' : '🔎 CONSULTAR' }}</button>
          <button v-if="cargado && items.length" class="btn pdf" @click="generarPdf">🖨 Imprimir</button>
        </div>
      </div>

      <div v-if="cargado" class="es-result">
        <div class="es-resumen">{{ items.length }} líneas · TOTAL ENTREGADO: <b>{{ total }}</b> piezas</div>
        <div v-for="g in grupos" :key="g.dep" class="es-grupo">
          <div class="es-dep">📍 {{ g.dep }} <span>({{ g.subtotal }})</span></div>
          <table class="es-tabla">
            <thead><tr><th style="width:70px">Código</th><th>Prenda / EPP</th><th>Rubro</th><th>Marca</th><th style="width:90px">Talle</th><th class="c" style="width:90px">Total</th></tr></thead>
            <tbody>
              <tr v-for="(r, i) in g.filas" :key="i">
                <td class="es-cod">{{ r.codigo }}</td><td>{{ r.nombre }}</td><td>{{ r.indumentaria }}</td>
                <td>{{ r.marca }}</td><td>{{ r.talle }}</td><td class="c">{{ r.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!items.length" class="es-vacio">No hay entregas para los filtros seleccionados.</div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="buscador.abierto" class="es-ov" @click.self="buscador.abierto = false">
        <div class="es-busca-md">
          <h3>🔍 Buscar prenda / EPP</h3>
          <input ref="inputBusca" v-model="buscador.q" placeholder="Código o descripción…" @input="buscarRopa" />
          <ul class="es-busca-list">
            <li v-for="r in buscador.res" :key="r.cod" @click="elegirRopa(r)"><b>{{ r.cod }}</b> — {{ r.descripcion }}</li>
            <li v-if="!buscador.res.length" class="es-busca-vacio">{{ buscador.q.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados' }}</li>
          </ul>
          <div class="fila end"><button class="btn reset" @click="buscador.abierto = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="es-ov" @click.self="ayuda = false">
        <div class="es-busca-md">
          <h3>❓ Ayuda — Estadística de Entregas</h3>
          <ul class="es-help">
            <li>Muestra cuántas piezas se entregaron, agrupadas por <b>depósito</b>, prenda, marca y talle.</li>
            <li><b>Período</b>: histórico (todo) o un <b>rango</b> de fechas.</li>
            <li>Filtrá además por depósito, prenda, marca o talle.</li>
          </ul>
          <div class="fila end"><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="es-pdf-ov" @click.self="cerrarPdf">
        <div class="es-pdf-md">
          <div class="es-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="es-pdf-acc">
              <button class="es-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="es-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="es-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="es-pdf-frame"></iframe>
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
interface Linea { codigo: number; nombre: string; marca: string; talle: string; deposito: string; indumentaria: string; total: number }

const depositos = ref<Combo[]>([]); const marcas = ref<Combo[]>([]); const talles = ref<Combo[]>([])
const periodo = ref<'hist' | 'rango'>('hist'); const desde = ref(''); const hasta = ref('')
const fDepo = ref({ ind: false, cod: 0 }); const fRopa = ref({ ind: false, cod: 0, des: '' })
const fMarca = ref({ ind: false, cod: 0 }); const fTalle = ref({ ind: false, cod: 0 })
const items = ref<Linea[]>([]); const total = ref(0); const cargado = ref(false); const cargando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

onMounted(async () => {
  try { const { data } = await api.get('/estadistica-ropa/init'); depositos.value = data.depositos ?? []; marcas.value = data.marcas ?? []; talles.value = data.talles ?? [] }
  catch { flash('No se pudieron cargar los filtros.', true) }
})

const grupos = computed(() => {
  const m = new Map<string, { dep: string; filas: Linea[]; subtotal: number }>()
  for (const r of items.value) {
    const dep = r.deposito || '(sin depósito)'
    let g = m.get(dep)
    if (!g) { g = { dep, filas: [], subtotal: 0 }; m.set(dep, g) }
    g.filas.push(r); g.subtotal += r.total
  }
  return [...m.values()]
})

async function consultar () {
  if (periodo.value === 'rango' && (!desde.value || !hasta.value)) { flash('Indique el rango de fechas.', true); return }
  if (fDepo.value.ind && fDepo.value.cod <= 0) { flash('Seleccione el depósito.', true); return }
  if (fRopa.value.ind && fRopa.value.cod <= 0) { flash('Seleccione la prenda/EPP.', true); return }
  if (fMarca.value.ind && fMarca.value.cod <= 0) { flash('Seleccione la marca.', true); return }
  if (fTalle.value.ind && fTalle.value.cod <= 0) { flash('Seleccione el talle.', true); return }
  cargando.value = true
  try {
    const params: any = { periodo: periodo.value }
    if (periodo.value === 'rango') { params.desde = desde.value; params.hasta = hasta.value }
    if (fDepo.value.ind) params.deposito = fDepo.value.cod
    if (fRopa.value.ind) params.ropa = fRopa.value.cod
    if (fMarca.value.ind) params.marca = fMarca.value.cod
    if (fTalle.value.ind) params.talle = fTalle.value.cod
    const { data } = await api.get('/estadistica-ropa', { params })
    items.value = data.items ?? []; total.value = data.total ?? 0; cargado.value = true
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar.', true) }
  finally { cargando.value = false }
}

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

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPdf () {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const ML = 14, MR = 196, PW = 210, PH = 297; let y = 16
  let titulo = 'ESTADÍSTICA ENTREGAS'
  if (periodo.value === 'rango') titulo += ` - ENTRE ${fmt(desde.value)} HASTA ${fmt(hasta.value)}`
  if (fRopa.value.ind) titulo += ` - (${fRopa.value.cod}) ${fRopa.value.des}`
  doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(20, 50, 90)
  doc.text(titulo, PW / 2, y, { align: 'center', maxWidth: MR - ML }); doc.setTextColor(0, 0, 0); y += 9
  for (const g of grupos.value) {
    if (y > PH - 24) { doc.addPage(); y = 18 }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(27, 67, 50)
    doc.text(`DEPÓSITO: ${g.dep}`, ML, y); doc.setTextColor(0, 0, 0); y += 5
    doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.rect(ML, y - 4, MR - ML, 6, 'F')
    const cx = { c: ML + 2, n: ML + 20, r: ML + 86, m: ML + 122, t: ML + 158, q: MR - 4 }
    doc.text('Código', cx.c, y); doc.text('Prenda/EPP', cx.n, y); doc.text('Rubro', cx.r, y); doc.text('Marca', cx.m, y); doc.text('Talle', cx.t, y); doc.text('Total', cx.q, y, { align: 'right' })
    doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 5.5
    for (const r of g.filas) {
      if (y > PH - 16) { doc.addPage(); y = 18 }
      doc.setFontSize(8)
      doc.text(String(r.codigo), cx.c, y); doc.text((doc.splitTextToSize(r.nombre, 62)[0] || ''), cx.n, y)
      doc.text((r.indumentaria || '').slice(0, 18), cx.r, y); doc.text((r.marca || '').slice(0, 16), cx.m, y)
      doc.text((r.talle || '').slice(0, 8), cx.t, y); doc.text(String(r.total), cx.q, y, { align: 'right' }); y += 4.5
    }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5)
    doc.text(`SUBTOTAL ${g.dep}`, cx.n, y + 1); doc.text(String(g.subtotal), cx.q, y + 1, { align: 'right' })
    doc.setFont('helvetica', 'normal'); y += 7
  }
  if (y > PH - 16) { doc.addPage(); y = 18 }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(10)
  doc.text('TOTAL DE PIEZAS EN GENERAL', ML, y); doc.text(String(total.value), MR - 4, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = 'Estadistica_entregas.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''
</script>

<style scoped>
.es-view { display:flex; flex-direction:column; min-height:100%; }
.es-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.es-ico { font-size:28px; } .es-tx h1 { margin:0; font-size:19px; color:#1e293b; } .es-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.es-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.es-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.es-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .es-msg.ok { background:#d1fae5; color:#065f46; } .es-msg.err { background:#fee2e2; color:#991b1b; }
.es-body { padding:16px 18px; }
.es-filtros { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; max-width:780px; display:flex; flex-direction:column; gap:10px; }
.fila { display:flex; gap:12px; align-items:center; flex-wrap:wrap; } .fila.end { justify-content:flex-end; }
.lbl { width:78px; font-size:13px; font-weight:700; color:#2a4a6a; }
.rad { display:flex; align-items:center; gap:5px; font-size:13px; color:#374151; cursor:pointer; }
.fila select, .inp-cod, .fila input[type=date] { border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:13px; } .inp-cod { width:90px; text-align:right; }
.btn-busca { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:5px 8px; font-size:13px; } .es-ropa-des { font-size:12px; color:#2d6a9f; font-weight:600; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#1b4332; color:#fff; } .btn.pdf { background:#e0eefc; color:#2d6a9f; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.es-result { margin-top:16px; }
.es-resumen { font-size:13px; color:#1e293b; margin-bottom:10px; } .es-resumen b { font-size:16px; color:#1b4332; }
.es-grupo { margin-bottom:14px; }
.es-dep { font-weight:700; color:#1b4332; font-size:13px; margin-bottom:4px; } .es-dep span { color:#6b7280; font-weight:500; }
.es-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.es-tabla th { background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11.5px; } .es-tabla th.c { text-align:center; }
.es-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .es-tabla td.c { text-align:center; font-weight:700; } .es-cod { color:#2d6a9f; font-weight:700; }
.es-vacio { text-align:center; color:#94a3b8; padding:16px; }
.es-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.es-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .es-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.es-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; }
.es-busca-list { list-style:none; margin:10px 0; padding:0; max-height:340px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.es-busca-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .es-busca-list li:hover { background:#f0faf4; } .es-busca-list li b { color:#2d6a9f; }
.es-busca-vacio { color:#94a3b8; cursor:default !important; } .es-busca-vacio:hover { background:none !important; }
.es-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.es-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.es-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.es-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .es-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.es-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .es-pdf-b.ok { background:#22c55e; color:#fff; } .es-pdf-b.cancel { background:#ef4444; color:#fff; }
.es-pdf-frame { flex:1; border:none; width:100%; }
</style>
