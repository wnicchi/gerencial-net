<!-- EntregaRopaView.vue — Entrega de Ropa/EPP a un empleado (ropa_entrega.scx). -->
<template>
  <div class="er-view">
    <div class="er-cab">
      <div class="er-ico">📦</div>
      <div class="er-tx"><h1>Entrega de Ropa / EPP</h1><p>Entrega de uniforme y elementos de protección al empleado</p></div>
      <button class="er-ia" @click="modalIA = true">🤖 IA</button>
      <button class="er-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/entrega-ropa" titulo="Asistente IA — Entrega de Ropa/EPP"
            subtitulo="Preguntá sobre la entrega de uniforme y EPP"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué hace NO DESCONTAR DE STOCK?','¿Cómo se imprime el recibo?','¿Qué es la Resolución 299/2011?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['er-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="er-body">
      <div class="er-top">
        <div class="campo"><label>Empresa</label>
          <select v-model.number="empresa"><option :value="0">— Seleccione —</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
        </div>
        <div class="campo"><label>Depósito</label>
          <select v-model.number="deposito"><option :value="0">— Seleccione —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        </div>
        <div class="campo grow"><label>Empleado</label>
          <EmpleadoInput :codigo="codigoEmp" :nombre="nombreEmp" :disabled="empBloqueado" @select="onSelEmp" />
        </div>
        <div class="er-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl=''" /><div v-else class="er-foto-ph">Sin foto</div></div>
      </div>

      <template v-if="codigoEmp">
        <table class="er-tabla">
          <thead><tr>
            <th style="width:110px">Cód.</th><th>Detalle de lo recibido</th><th style="width:170px">Marca</th>
            <th style="width:130px">Talle</th><th class="c" style="width:70px">Certif.</th>
            <th class="c" style="width:80px">Cant.</th><th style="width:140px">Fecha</th><th style="width:30px"></th>
          </tr></thead>
          <tbody>
            <tr v-for="(it, i) in items" :key="i">
              <td class="cod-cell">
                <input v-model.number="it.rcod" type="number" class="inp-cod" @blur="lookup(it)" @keyup.enter="lookup(it)" />
                <button type="button" class="btn-busca" title="Buscar prenda/EPP" @click="abrirBuscador(i)">🔍</button>
              </td>
              <td><input v-model="it.rdes" maxlength="50" class="inp-full" /></td>
              <td><select v-model.number="it.mcod" @change="onMarca(it)"><option :value="0">— —</option><option v-for="m in marcas" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select></td>
              <td><select v-model.number="it.tcod" @change="onTalle(it)"><option :value="0">— —</option><option v-for="t in talles" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select></td>
              <td class="c"><input type="checkbox" v-model="it.certifica" /></td>
              <td><input v-model.number="it.cantidad" type="number" min="0" class="inp-cant" /></td>
              <td><input v-model="it.fecha" type="date" class="inp-fecha" /></td>
              <td class="c"><button class="er-x" title="Quitar fila" @click="quitarFila(i)">✕</button></td>
            </tr>
          </tbody>
        </table>
        <div class="er-acc1">
          <button class="btn add" @click="agregarFila">＋ Agregar ítem</button>
          <span class="er-spacer"></span>
          <span class="er-total">TOTAL DE PIEZAS A ENTREGAR: <b>{{ totalPiezas }}</b></span>
        </div>

        <div class="er-info">
          <label>Información adicional</label>
          <textarea v-model="motivo" rows="5"></textarea>
        </div>

        <div class="er-acc2">
          <button class="btn reset" @click="reset">↺ Reset</button>
          <label class="chk"><input type="checkbox" v-model="noStock" /> NO DESCONTAR DE STOCK</label>
          <span class="er-spacer"></span>
          <button class="btn ok" :disabled="proc" @click="entregar">{{ proc ? '⟳ Procesando…' : '✔ ENTREGAR ROPA' }}</button>
        </div>
      </template>
    </div>

    <!-- Buscador de prenda/EPP -->
    <Teleport to="body">
      <div v-if="buscador.abierto" class="er-ov" @click.self="cerrarBuscador">
        <div class="er-busca-md">
          <h3>🔍 Buscar prenda / EPP</h3>
          <input ref="inputBusca" v-model="buscador.q" placeholder="Código o descripción…" @input="buscarRopa" />
          <ul class="er-busca-list">
            <li v-for="r in buscador.res" :key="r.cod" @click="elegirRopa(r)"><b>{{ r.cod }}</b> — {{ r.descripcion }}<span v-if="r.inactivo" class="er-inac">inactivo</span></li>
            <li v-if="!buscador.res.length" class="er-busca-vacio">{{ buscador.q.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados' }}</li>
          </ul>
          <div class="er-acc1"><span class="er-spacer"></span><button class="btn reset" @click="cerrarBuscador">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <!-- Ayuda -->
    <Teleport to="body">
      <div v-if="ayuda" class="er-ov" @click.self="ayuda = false">
        <div class="er-busca-md">
          <h3>❓ Ayuda — Entrega de Ropa/EPP</h3>
          <ul class="er-help">
            <li>Elegí <b>Empresa</b>, <b>Depósito</b> (de dónde egresan los elementos) y el <b>empleado</b>.</li>
            <li>Cargá los ítems: código de prenda (con 🔍), marca, talle, si certifica, cantidad y fecha.</li>
            <li><b>NO DESCONTAR DE STOCK</b>: registra la entrega sin afectar el inventario.</li>
            <li>Al confirmar se descuenta el stock y se puede imprimir el recibo <b>Resolución 299/2011</b>.</li>
          </ul>
          <div class="er-acc1"><span class="er-spacer"></span><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <!-- PDF recibo -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="er-pdf-ov" @click.self="cerrarPdf">
        <div class="er-pdf-md">
          <div class="er-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="er-pdf-acc">
              <button class="er-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="er-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="er-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="er-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import { generarReciboRopa } from '@/utils/reciboRopa'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

interface Combo { cod: number; nombre: string }
interface Fila { rcod: number | null; rdes: string; mcod: number; mdes: string; tcod: number; tdes: string; certifica: boolean; cantidad: number | null; fecha: string }

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const empresas = ref<Combo[]>([]); const depositos = ref<Combo[]>([]); const marcas = ref<Combo[]>([]); const talles = ref<Combo[]>([])
const empresa = ref(0); const deposito = ref(0); const noStock = ref(false)
const codigoEmp = ref(0); const nombreEmp = ref(''); const fotoUrl = ref('')
const items = ref<Fila[]>([]); const motivo = ref('')
const proc = ref(false); const msg = ref(''); const msgErr = ref(false)
const modalIA = ref(false); const ayuda = ref(false)
const hoy = () => new Date().toISOString().slice(0, 10)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4500) }
const totalPiezas = computed(() => items.value.reduce((s, i) => s + (Number(i.cantidad) || 0), 0))

onMounted(async () => {
  try {
    const { data } = await api.get('/entrega-ropa/init')
    empresas.value = data.empresas ?? []; depositos.value = data.depositos ?? []
    marcas.value = data.marcas ?? []; talles.value = data.talles ?? []
    motivo.value = data.compromiso ?? ''
    if (empresas.value.length === 1) empresa.value = empresas.value[0]!.cod
  } catch { flash('No se pudieron cargar los datos iniciales.', true) }
  if (props.empleado) seleccionarEmp({ PER_COD: props.empleado })   // precarga desde la ficha (Propuesta A)
})

// ── Empleado ──
const onSelEmp = (r: any) => seleccionarEmp(r)
async function seleccionarEmp (r: any) {
  try {
    const { data } = await api.get(`/entrega-ropa/empleado/${r.PER_COD}`)
    codigoEmp.value = Number(r.PER_COD); nombreEmp.value = data.nombre
    if (!items.value.length) agregarFila()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true); return }
  fotoUrl.value = ''
  try { const { data } = await api.get(`/empleados/${r.PER_COD}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* sin foto */ }
}

const agregarFila = () => items.value.push({ rcod: null, rdes: '', mcod: 0, mdes: '', tcod: 0, tdes: '', certifica: false, cantidad: 1, fecha: hoy() })
const quitarFila = (i: number) => { items.value.splice(i, 1) }
const onMarca = (it: Fila) => { const m = marcas.value.find(x => x.cod === it.mcod); it.mdes = m ? m.nombre : '' }
const onTalle = (it: Fila) => { const t = talles.value.find(x => x.cod === it.tcod); it.tdes = t ? t.nombre : '' }

async function lookup (it: Fila) {
  if (!it.rcod || it.rcod <= 0) { it.rdes = ''; return }
  try { const { data } = await api.get(`/entrega-ropa/ropa/${it.rcod}`); it.rdes = data.des }
  catch { it.rdes = ''; flash('Código de prenda/EPP inexistente.', true) }
}

// ── Buscador de prenda ──
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

// ── Entregar ──
async function entregar () {
  if (deposito.value <= 0) { flash('No ha seleccionado el depósito donde egresarán los elementos.', true); return }
  if (empresa.value <= 0) { flash('Seleccione la empresa.', true); return }
  const validos = items.value.filter(i => i.rcod && i.rcod > 0 && i.rdes.trim() && (i.cantidad ?? 0) > 0)
  if (!validos.length) { flash('No ha ingresado ningún ítem válido, verifique códigos y cantidades por favor.', true); return }
  if (!confirm('¿Confirma la entrega de ropa al empleado?')) return
  proc.value = true
  try {
    const body = {
      empresa: empresa.value, deposito: deposito.value, empleado: codigoEmp.value,
      motivo: motivo.value, no_stock: noStock.value,
      items: validos.map(i => ({ rcod: i.rcod, rdes: i.rdes, mcod: i.mcod, mdes: i.mdes, tcod: i.tcod, tdes: i.tdes, certifica: i.certifica, cantidad: i.cantidad, fecha: i.fecha })),
    }
    const { data } = await api.post('/entrega-ropa', body)
    flash('Entrega registrada correctamente.')
    if (confirm('¿Imprime recibo de entrega (Resolución 299/2011)?')) generarRecibo(data)
    reset()
  } catch (e: any) {
    flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo registrar la entrega.', true)
  } finally { proc.value = false }
}

function reset () {
  codigoEmp.value = 0; nombreEmp.value = ''; fotoUrl.value = ''
  items.value = []; deposito.value = 0; noStock.value = false
}

// ── Recibo Resolución 299/2011 (A4) ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarRecibo (p: any) {
  const blob = generarReciboRopa(p)
  cerrarPdf()
  pdfNombre.value = `Entrega_${(p.empleado.nombre || '').replace(/\s+/g, '_')}.pdf`
  pdfUrl.value = URL.createObjectURL(blob)
}
</script>

<style scoped>
.er-view { display:flex; flex-direction:column; min-height:100%; }
.er-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.er-ico { font-size:28px; } .er-tx h1 { margin:0; font-size:19px; color:#1e293b; } .er-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.er-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.er-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.er-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .er-msg.ok { background:#d1fae5; color:#065f46; } .er-msg.err { background:#fee2e2; color:#991b1b; }
.er-body { padding:16px 18px; max-width:1080px; }
.er-top { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo.grow { flex:1; min-width:240px; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.campo select, .er-busc input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; min-width:200px; box-sizing:border-box; }
.er-busc { position:relative; } .er-busc input { width:100%; }
.er-busc-lupa { display:flex; gap:8px; } .er-busc-lupa input { flex:1; }
.er-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.er-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.er-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; } .er-result li:hover { background:#f0faf4; } .er-result li b { font-size:13px; color:#1e293b; } .er-result li span { font-size:11px; color:#6b7280; }
.er-foto { width:120px; height:80px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.er-foto img { width:100%; height:100%; object-fit:cover; } .er-foto-ph { font-size:11px; color:#9ca3af; }
.er-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; border:1px solid #e2e8f0; }
.er-tabla th { background:#1e293b; color:#fff; padding:7px 8px; text-align:left; font-size:11.5px; } .er-tabla th.c { text-align:center; }
.er-tabla td { padding:4px 6px; border-bottom:1px solid #f0f4f9; } .er-tabla td.c { text-align:center; }
.er-tabla input, .er-tabla select { width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:12.5px; box-sizing:border-box; }
.er-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.cod-cell { display:flex; gap:3px; align-items:center; } .inp-cod { width:66px; min-width:66px; text-align:right; } .btn-busca { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:4px 5px; font-size:12px; flex-shrink:0; }
.er-x { background:none; border:none; color:#b91c1c; cursor:pointer; font-size:14px; }
.er-acc1 { display:flex; gap:8px; align-items:center; margin-top:10px; } .er-spacer { flex:1; }
.er-total { font-size:13px; color:#1e293b; } .er-total b { font-size:16px; color:#1b4332; }
.er-info { margin-top:16px; } .er-info label { font-size:12px; font-weight:600; color:#374151; }
.er-info textarea { width:100%; border:1px solid #d1d5db; border-radius:7px; padding:9px 11px; font-size:12.5px; margin-top:5px; box-sizing:border-box; resize:vertical; }
.er-acc2 { display:flex; gap:14px; align-items:center; margin-top:14px; }
.er-acc2 .chk { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#92400e; } .er-acc2 .chk input { width:15px; height:15px; accent-color:#d97706; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#1b4332; color:#fff; } .btn.add { background:#d1fae5; color:#065f46; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.er-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.er-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); }
.er-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.er-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; }
.er-busca-list { list-style:none; margin:10px 0; padding:0; max-height:340px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.er-busca-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .er-busca-list li:hover { background:#f0faf4; } .er-busca-list li b { color:#2d6a9f; }
.er-busca-vacio { color:#94a3b8; cursor:default !important; } .er-busca-vacio:hover { background:none !important; }
.er-inac { margin-left:8px; font-size:11px; color:#b91c1c; background:#fee2e2; padding:1px 6px; border-radius:10px; }
.er-help { margin:0 0 4px; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.er-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.er-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.er-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .er-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.er-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .er-pdf-b.ok { background:#22c55e; color:#fff; } .er-pdf-b.cancel { background:#ef4444; color:#fff; }
.er-pdf-frame { flex:1; border:none; width:100%; }
</style>
