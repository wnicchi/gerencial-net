<!-- IngresoRopaView.vue — Ingreso de Ropa/EPP a stock (ropa_ingreso.scx). -->
<template>
  <div class="ir-view">
    <div class="ir-cab">
      <div class="ir-ico">📥</div>
      <div class="ir-tx"><h1>Ingreso de Ropa / EPP</h1><p>Suma stock de uniforme y elementos de protección a un depósito</p></div>
      <button class="ir-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ir-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/ingreso-ropa" titulo="Asistente IA — Ingreso de Ropa/EPP"
            subtitulo="Preguntá sobre el ingreso a stock"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué campos son obligatorios?','¿Qué es Origen y Condición?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ir-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ir-body">
      <div class="ir-top">
        <label>en Depósito</label>
        <select v-model.number="deposito"><option :value="0">— Seleccione —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
      </div>

      <table class="ir-tabla">
        <thead><tr>
          <th style="width:110px">Código</th><th>Detalle de lo recibido</th><th style="width:180px">Marca</th>
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
            <td class="c"><button class="ir-x" @click="quitarFila(i)">✕</button></td>
          </tr>
        </tbody>
      </table>
      <div class="ir-acc1">
        <button class="btn add" @click="agregarFila">＋ Agregar ítem</button>
        <span class="ir-spacer"></span>
        <span class="ir-total">TOTAL DE PIEZAS A INGRESAR: <b>{{ totalPiezas }}</b></span>
      </div>

      <div class="ir-info">
        <label>Origen y condición</label>
        <input v-model="motivo" maxlength="100" placeholder="Ej. FÁBRICA, COMPRA, DONACIÓN…" />
      </div>

      <div class="ir-acc2">
        <button class="btn reset" @click="reset">↺ Reset</button>
        <span class="ir-spacer"></span>
        <button class="btn ok" :disabled="proc" @click="ingresar">{{ proc ? '⟳ Procesando…' : '✔ INGRESAR ROPA' }}</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="buscador.abierto" class="ir-ov" @click.self="cerrarBuscador">
        <div class="ir-busca-md">
          <h3>🔍 Buscar prenda / EPP</h3>
          <input ref="inputBusca" v-model="buscador.q" placeholder="Código o descripción…" @input="buscarRopa" />
          <ul class="ir-busca-list">
            <li v-for="r in buscador.res" :key="r.cod" @click="elegirRopa(r)"><b>{{ r.cod }}</b> — {{ r.descripcion }}</li>
            <li v-if="!buscador.res.length" class="ir-busca-vacio">{{ buscador.q.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados' }}</li>
          </ul>
          <div class="ir-acc1"><span class="ir-spacer"></span><button class="btn reset" @click="cerrarBuscador">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="ir-ov" @click.self="ayuda = false">
        <div class="ir-busca-md">
          <h3>❓ Ayuda — Ingreso de Ropa/EPP</h3>
          <ul class="ir-help">
            <li>Elegí el <b>depósito</b> donde ingresan los elementos.</li>
            <li>Cargá cada ítem: código (con 🔍), marca, talle y cantidad (todos obligatorios).</li>
            <li><b>Origen y condición</b>: de dónde provienen (fábrica, compra, etc.).</li>
            <li><b>INGRESAR ROPA</b> suma las cantidades al stock del depósito.</li>
          </ul>
          <div class="ir-acc1"><span class="ir-spacer"></span><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

interface Combo { cod: number; nombre: string }
interface Fila { rcod: number | null; rdes: string; mcod: number; mdes: string; tcod: number; tdes: string; cantidad: number | null; fecha: string }
const depositos = ref<Combo[]>([]); const marcas = ref<Combo[]>([]); const talles = ref<Combo[]>([])
const deposito = ref(0); const motivo = ref(''); const items = ref<Fila[]>([])
const proc = ref(false); const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const hoy = () => new Date().toISOString().slice(0, 10)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4500) }
const totalPiezas = computed(() => items.value.reduce((s, i) => s + (Number(i.cantidad) || 0), 0))

onMounted(async () => {
  try { const { data } = await api.get('/ingreso-ropa/init'); depositos.value = data.depositos ?? []; marcas.value = data.marcas ?? []; talles.value = data.talles ?? [] }
  catch { flash('No se pudieron cargar los datos iniciales.', true) }
  agregarFila()
})

const agregarFila = () => items.value.push({ rcod: null, rdes: '', mcod: 0, mdes: '', tcod: 0, tdes: '', cantidad: 1, fecha: hoy() })
const quitarFila = (i: number) => { items.value.splice(i, 1) }
const onMarca = (it: Fila) => { const m = marcas.value.find(x => x.cod === it.mcod); it.mdes = m ? m.nombre : '' }
const onTalle = (it: Fila) => { const t = talles.value.find(x => x.cod === it.tcod); it.tdes = t ? t.nombre : '' }
async function lookup (it: Fila) {
  if (!it.rcod || it.rcod <= 0) { it.rdes = ''; return }
  try { const { data } = await api.get(`/ingreso-ropa/ropa/${it.rcod}`); it.rdes = data.des }
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

async function ingresar () {
  if (deposito.value <= 0) { flash('No ha seleccionado el depósito donde ingresarán los elementos.', true); return }
  const validos = items.value.filter(i => i.rcod && i.rcod > 0 && i.rdes.trim() && i.mcod > 0 && i.tcod > 0 && (i.cantidad ?? 0) > 0)
  if (!validos.length) { flash('No ha ingresado ningún ítem válido, verifique códigos y cantidades por favor.', true); return }
  if (!confirm('¿Confirma el ingreso de ropa / elementos de protección?')) return
  proc.value = true
  try {
    const { data } = await api.post('/ingreso-ropa', { deposito: deposito.value, motivo: motivo.value, items: validos.map(i => ({ rcod: i.rcod, rdes: i.rdes, mcod: i.mcod, mdes: i.mdes, tcod: i.tcod, tdes: i.tdes, cantidad: i.cantidad, fecha: i.fecha })) })
    flash(`Ingreso registrado: ${data.ingresados} ítem(s) sumados al stock.`)
    reset()
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo registrar el ingreso.', true) }
  finally { proc.value = false }
}

function reset () { items.value = []; deposito.value = 0; motivo.value = ''; agregarFila() }
</script>

<style scoped>
.ir-view { display:flex; flex-direction:column; min-height:100%; }
.ir-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ir-ico { font-size:28px; } .ir-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ir-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ir-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ir-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ir-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ir-msg.ok { background:#d1fae5; color:#065f46; } .ir-msg.err { background:#fee2e2; color:#991b1b; }
.ir-body { padding:16px 18px; max-width:1000px; }
.ir-top { display:flex; gap:12px; align-items:center; } .ir-top label { font-size:13px; font-weight:600; color:#374151; }
.ir-top select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; min-width:220px; }
.ir-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; border:1px solid #e2e8f0; }
.ir-tabla th { background:#1e293b; color:#fff; padding:7px 8px; text-align:left; font-size:11.5px; } .ir-tabla th.c { text-align:center; }
.ir-tabla td { padding:4px 6px; border-bottom:1px solid #f0f4f9; } .ir-tabla td.c { text-align:center; }
.ir-tabla input, .ir-tabla select { width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:12.5px; box-sizing:border-box; }
.cod-cell { display:flex; gap:3px; align-items:center; } .inp-cod { width:66px; min-width:66px; text-align:right; } .btn-busca { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:4px 5px; font-size:12px; flex-shrink:0; }
.ir-x { background:none; border:none; color:#b91c1c; cursor:pointer; font-size:14px; }
.ir-acc1 { display:flex; gap:8px; align-items:center; margin-top:10px; } .ir-spacer { flex:1; }
.ir-total { font-size:13px; color:#1e293b; } .ir-total b { font-size:16px; color:#1b4332; }
.ir-info { margin-top:16px; } .ir-info label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:5px; }
.ir-info input { width:100%; max-width:520px; border:1px solid #d1d5db; border-radius:7px; padding:9px 11px; font-size:13px; box-sizing:border-box; }
.ir-acc2 { display:flex; gap:14px; align-items:center; margin-top:14px; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#1b4332; color:#fff; } .btn.add { background:#d1fae5; color:#065f46; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.ir-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.ir-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .ir-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.ir-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; }
.ir-busca-list { list-style:none; margin:10px 0; padding:0; max-height:340px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.ir-busca-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .ir-busca-list li:hover { background:#f0faf4; } .ir-busca-list li b { color:#2d6a9f; }
.ir-busca-vacio { color:#94a3b8; cursor:default !important; } .ir-busca-vacio:hover { background:none !important; }
.ir-help { margin:0 0 4px; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
