<!-- TransferenciaRopaView.vue — Transferencia de ropa entre depósitos (ropa_transferencias.scx). -->
<template>
  <div class="tr-view">
    <div class="tr-cab">
      <div class="tr-ico">🔁</div>
      <div class="tr-tx"><h1>Transferencia de Ropa</h1><p>Movimiento de uniforme y EPP entre depósitos</p></div>
      <button class="tr-ia" @click="modalIA = true">🤖 IA</button>
      <button class="tr-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/transferencia-ropa" titulo="Asistente IA — Transferencia de Ropa"
            subtitulo="Preguntá sobre la transferencia entre depósitos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Pueden ser el mismo depósito?','¿Qué pasa con el stock?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['tr-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="tr-body">
      <div class="tr-top">
        <div class="campo"><label>Depósito origen</label>
          <select v-model.number="origen"><option :value="0">— Seleccione —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        </div>
        <div class="tr-arrow">➡️</div>
        <div class="campo"><label>Depósito destino</label>
          <select v-model.number="destino"><option :value="0">— Seleccione —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        </div>
      </div>

      <table class="tr-tabla">
        <thead><tr>
          <th style="width:110px">Código</th><th>Detalle de lo entregado</th><th style="width:180px">Marca</th>
          <th style="width:130px">Talle</th><th class="c" style="width:80px">Cant.</th><th style="width:140px">Fecha</th><th style="width:30px"></th>
        </tr></thead>
        <tbody>
          <tr v-for="(it, i) in items" :key="i">
            <td class="cod-cell"><input v-model.number="it.rcod" type="number" class="inp-cod" @blur="lookup(it)" @keyup.enter="lookup(it)" /><button type="button" class="btn-busca" @click="abrirBuscador(i)">🔍</button></td>
            <td><input v-model="it.rdes" maxlength="50" class="inp-full" /></td>
            <td><select v-model.number="it.mcod" @change="onMarca(it)"><option :value="0">— —</option><option v-for="m in marcas" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select></td>
            <td><select v-model.number="it.tcod" @change="onTalle(it)"><option :value="0">— —</option><option v-for="t in talles" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select></td>
            <td><input v-model.number="it.cantidad" type="number" min="0" /></td>
            <td><input v-model="it.fecha" type="date" /></td>
            <td class="c"><button class="tr-x" @click="quitarFila(i)">✕</button></td>
          </tr>
        </tbody>
      </table>
      <div class="tr-acc1">
        <button class="btn add" @click="agregarFila">＋ Agregar ítem</button>
        <span class="tr-spacer"></span>
        <span class="tr-total">TOTAL DE PIEZAS A TRANSFERIR: <b>{{ totalPiezas }}</b></span>
      </div>

      <div class="tr-acc2">
        <button class="btn reset" @click="reset">↺ Reset</button>
        <span class="tr-spacer"></span>
        <button class="btn ok" :disabled="proc" @click="transferir">{{ proc ? '⟳ Procesando…' : '✔ TRANSFERIR' }}</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="buscador.abierto" class="tr-ov" @click.self="cerrarBuscador">
        <div class="tr-busca-md">
          <h3>🔍 Buscar prenda / EPP</h3>
          <input ref="inputBusca" v-model="buscador.q" placeholder="Código o descripción…" @input="buscarRopa" />
          <ul class="tr-busca-list">
            <li v-for="r in buscador.res" :key="r.cod" @click="elegirRopa(r)"><b>{{ r.cod }}</b> — {{ r.descripcion }}</li>
            <li v-if="!buscador.res.length" class="tr-busca-vacio">{{ buscador.q.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados' }}</li>
          </ul>
          <div class="tr-acc1"><span class="tr-spacer"></span><button class="btn reset" @click="cerrarBuscador">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="tr-ov" @click.self="ayuda = false">
        <div class="tr-busca-md">
          <h3>❓ Ayuda — Transferencia de Ropa</h3>
          <ul class="tr-help">
            <li>Elegí el <b>depósito origen</b> y el <b>depósito destino</b> (no pueden ser iguales).</li>
            <li>Cargá los ítems a mover (código con 🔍, marca, talle, cantidad).</li>
            <li><b>TRANSFERIR</b> descuenta del origen, suma al destino y genera el remito.</li>
          </ul>
          <div class="tr-acc1"><span class="tr-spacer"></span><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="tr-pdf-ov" @click.self="cerrarPdf">
        <div class="tr-pdf-md">
          <div class="tr-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="tr-pdf-acc">
              <button class="tr-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="tr-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="tr-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="tr-pdf-frame"></iframe>
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
interface Fila { rcod: number | null; rdes: string; mcod: number; mdes: string; tcod: number; tdes: string; cantidad: number | null; fecha: string }
const depositos = ref<Combo[]>([]); const marcas = ref<Combo[]>([]); const talles = ref<Combo[]>([])
const origen = ref(0); const destino = ref(0); const items = ref<Fila[]>([])
const proc = ref(false); const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const hoy = () => new Date().toISOString().slice(0, 10)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4500) }
const totalPiezas = computed(() => items.value.reduce((s, i) => s + (Number(i.cantidad) || 0), 0))

onMounted(async () => {
  try { const { data } = await api.get('/transferencia-ropa/init'); depositos.value = data.depositos ?? []; marcas.value = data.marcas ?? []; talles.value = data.talles ?? [] }
  catch { flash('No se pudieron cargar los datos iniciales.', true) }
  agregarFila()
})

const agregarFila = () => items.value.push({ rcod: null, rdes: '', mcod: 0, mdes: '', tcod: 0, tdes: '', cantidad: 1, fecha: hoy() })
const quitarFila = (i: number) => { items.value.splice(i, 1) }
const onMarca = (it: Fila) => { const m = marcas.value.find(x => x.cod === it.mcod); it.mdes = m ? m.nombre : '' }
const onTalle = (it: Fila) => { const t = talles.value.find(x => x.cod === it.tcod); it.tdes = t ? t.nombre : '' }
async function lookup (it: Fila) {
  if (!it.rcod || it.rcod <= 0) { it.rdes = ''; return }
  try { const { data } = await api.get(`/transferencia-ropa/ropa/${it.rcod}`); it.rdes = data.des }
  catch { it.rdes = ''; flash('Código de prenda/EPP inexistente.', true) }
}

const inputBusca = ref<HTMLInputElement | null>(null)
const buscador = ref<{ abierto: boolean; fila: number; q: string; res: any[] }>({ abierto: false, fila: -1, q: '', res: [] })
let dr: any = null
async function abrirBuscador (i: number) { buscador.value = { abierto: true, fila: i, q: '', res: [] }; await nextTick(); inputBusca.value?.focus() }
const cerrarBuscador = () => { buscador.value.abierto = false }
const buscarRopa = () => {
  clearTimeout(dr); const q = buscador.value.q.trim()
  if (q.length < 2) { buscador.value.res = []; return }
  dr = setTimeout(async () => { try { buscador.value.res = (await api.get('/ropa-epp', { params: { buscar: q } })).data ?? [] } catch { buscador.value.res = [] } }, 250)
}
function elegirRopa (r: any) { const it = items.value[buscador.value.fila]; if (it) { it.rcod = r.cod; it.rdes = r.descripcion }; cerrarBuscador() }

async function transferir () {
  if (origen.value <= 0) { flash('No ha seleccionado el depósito origen.', true); return }
  if (destino.value <= 0) { flash('No ha seleccionado el depósito destino.', true); return }
  if (origen.value === destino.value) { flash('Los depósitos de origen y destino no pueden ser iguales.', true); return }
  const validos = items.value.filter(i => i.rcod && i.rcod > 0 && i.rdes.trim() && (i.cantidad ?? 0) > 0)
  if (!validos.length) { flash('No ha ingresado ningún ítem válido, verifique códigos y cantidades por favor.', true); return }
  if (!confirm('¿Confirma la transferencia de ropa y elementos de seguridad?')) return
  proc.value = true
  try {
    const { data } = await api.post('/transferencia-ropa', { origen: origen.value, destino: destino.value, items: validos.map(i => ({ rcod: i.rcod, rdes: i.rdes, mcod: i.mcod, mdes: i.mdes, tcod: i.tcod, tdes: i.tdes, cantidad: i.cantidad, fecha: i.fecha })) })
    flash('Transferencia registrada correctamente.')
    if (confirm('¿Imprime remito de transferencia?')) generarRemito(data)
    reset()
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo transferir.', true) }
  finally { proc.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarRemito (p: any) {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 14, MR = 283, PW = 297; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(20, 50, 90)
  doc.text('TRANSFERENCIA INTERNA DE ROPA DE TRABAJO Y ELEMENTOS DE PROTECCIÓN PERSONAL', PW / 2, y, { align: 'center' })
  doc.setTextColor(0, 0, 0); y += 9
  doc.setFontSize(10); doc.setFont('helvetica', 'bold')
  doc.text(`DEPÓSITO ORIGEN: ${p.origen}`, ML, y); doc.text(`DEPÓSITO DESTINO: ${p.destino}`, 160, y); y += 8
  doc.setFillColor(45, 106, 159); doc.setTextColor(255, 255, 255); doc.setFontSize(9)
  doc.rect(ML, y - 5, MR - ML, 7, 'F')
  const cx = { cod: ML + 2, det: ML + 22, mar: ML + 120, tal: ML + 175, can: ML + 215, fec: ML + 240 }
  doc.text('Código', cx.cod, y); doc.text('Producto / Detalle', cx.det, y); doc.text('Marca', cx.mar, y); doc.text('Talle', cx.tal, y); doc.text('Cantidad', cx.can, y); doc.text('Fecha', cx.fec, y)
  doc.setTextColor(0, 0, 0); doc.setFont('helvetica', 'normal'); y += 7
  for (const it of p.items as any[]) {
    if (y > 185) { doc.addPage(); y = 18 }
    doc.setFontSize(9)
    doc.text(String(it.codigo), cx.cod, y); doc.text((doc.splitTextToSize(it.detalle, 94)[0] || ''), cx.det, y)
    doc.text((it.marca || '').slice(0, 24), cx.mar, y); doc.text((it.talle || '').slice(0, 10), cx.tal, y)
    doc.text(String(it.cantidad), cx.can + 6, y, { align: 'right' }); doc.text(it.fecha.split('-').reverse().join('/'), cx.fec, y)
    doc.setDrawColor(220); doc.line(ML, y + 1.5, MR, y + 1.5); y += 6
  }
  y += 18
  doc.setDrawColor(0); doc.line(40, y, 110, y); doc.line(190, y, 260, y); y += 4
  doc.setFontSize(9); doc.text('FIRMA RESPONSABLE ORIGEN', 50, y); doc.text('FIRMA RESPONSABLE DESTINO', 200, y)
  cerrarPdf(); pdfNombre.value = 'Remito_transferencia.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

function reset () { items.value = []; origen.value = 0; destino.value = 0; agregarFila() }
</script>

<style scoped>
.tr-view { display:flex; flex-direction:column; min-height:100%; }
.tr-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.tr-ico { font-size:28px; } .tr-tx h1 { margin:0; font-size:19px; color:#1e293b; } .tr-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.tr-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.tr-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.tr-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .tr-msg.ok { background:#d1fae5; color:#065f46; } .tr-msg.err { background:#fee2e2; color:#991b1b; }
.tr-body { padding:16px 18px; max-width:1040px; }
.tr-top { display:flex; gap:16px; align-items:flex-end; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo label { font-size:12px; font-weight:600; color:#374151; }
.campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; min-width:240px; }
.tr-arrow { font-size:22px; padding-bottom:6px; }
.tr-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; border:1px solid #e2e8f0; }
.tr-tabla th { background:#1e293b; color:#fff; padding:7px 8px; text-align:left; font-size:11.5px; } .tr-tabla th.c { text-align:center; }
.tr-tabla td { padding:4px 6px; border-bottom:1px solid #f0f4f9; } .tr-tabla td.c { text-align:center; }
.tr-tabla input, .tr-tabla select { width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:12.5px; box-sizing:border-box; }
.cod-cell { display:flex; gap:3px; align-items:center; } .inp-cod { width:66px; min-width:66px; text-align:right; } .btn-busca { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:4px 5px; font-size:12px; flex-shrink:0; }
.tr-x { background:none; border:none; color:#b91c1c; cursor:pointer; font-size:14px; }
.tr-acc1 { display:flex; gap:8px; align-items:center; margin-top:10px; } .tr-spacer { flex:1; }
.tr-total { font-size:13px; color:#1e293b; } .tr-total b { font-size:16px; color:#1b4332; }
.tr-acc2 { display:flex; gap:14px; align-items:center; margin-top:16px; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#1b4332; color:#fff; } .btn.add { background:#d1fae5; color:#065f46; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.tr-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.tr-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .tr-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.tr-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; }
.tr-busca-list { list-style:none; margin:10px 0; padding:0; max-height:340px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.tr-busca-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .tr-busca-list li:hover { background:#f0faf4; } .tr-busca-list li b { color:#2d6a9f; }
.tr-busca-vacio { color:#94a3b8; cursor:default !important; } .tr-busca-vacio:hover { background:none !important; }
.tr-help { margin:0 0 4px; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.tr-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.tr-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.tr-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .tr-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.tr-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .tr-pdf-b.ok { background:#22c55e; color:#fff; } .tr-pdf-b.cancel { background:#ef4444; color:#fff; }
.tr-pdf-frame { flex:1; border:none; width:100%; }
</style>
