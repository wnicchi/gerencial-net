<!-- AsignarEppView.vue — Asignar EPP utilizada por un empleado (per_ropa). -->
<template>
  <div class="ae-view">
    <div class="ae-cab">
      <div class="ae-ico">🦺</div>
      <div class="ae-tx"><h1>Asignar EPP a Empleado</h1><p>EPP / uniforme utilizado por el empleado</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ae-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ae-body">
      <div class="ae-top">
        <div class="campo grow">
          <label>Empleado</label>
          <EmpleadoInput :codigo="codigo" :nombre="nombre" @select="onSelEmp" />
        </div>
        <div class="ae-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl=''" /><div v-else class="ae-foto-ph">Sin foto</div></div>
      </div>

      <template v-if="codigo">
        <div class="ae-sub">EPP asignada</div>
        <table class="ae-tabla">
          <thead><tr><th class="c">OK</th><th style="width:140px">Código</th><th>Detalle de lo recibido</th><th style="width:200px">Talle</th></tr></thead>
          <tbody>
            <tr v-for="(it, i) in items" :key="i">
              <td class="c"><input type="checkbox" v-model="it.sel" /></td>
              <td class="cod-cell">
                <input v-model.number="it.rcod" type="number" class="inp-cod" @blur="lookup(it)" @keyup.enter="lookup(it)" />
                <button type="button" class="btn-busca" title="Buscar prenda/EPP" @click="abrirBuscador(i)">🔍</button>
              </td>
              <td>{{ it.rdes || '—' }}</td>
              <td>
                <select v-model.number="it.rtal" @change="onTalle(it)">
                  <option :value="0">— —</option>
                  <option v-for="t in talles" :key="t.cod" :value="t.cod">{{ t.nombre }}</option>
                </select>
              </td>
            </tr>
            <tr v-if="!items.length"><td colspan="4" class="ae-vacio">Sin EPP asignada. Agregá ítems con el botón.</td></tr>
          </tbody>
        </table>
        <div class="ae-acc">
          <button class="btn add" @click="agregarFila">＋ Agregar ítem</button>
          <button class="btn del" :disabled="!hayElegidos" @click="borrarItems">🗑 Borrar ítem(s)</button>
          <span class="ae-spacer"></span>
          <button class="btn reset" @click="reset">↺ Reset</button>
          <button class="btn ok" :disabled="proc" @click="confirmar">{{ proc ? '⟳ Guardando…' : '✔ CONFIRMAR' }}</button>
        </div>
      </template>
    </div>

    <!-- Buscador de prenda/EPP -->
    <Teleport to="body">
      <div v-if="buscador.abierto" class="ae-ov" @click.self="cerrarBuscador">
        <div class="ae-busca-md">
          <h3>🔍 Buscar prenda / EPP</h3>
          <input ref="inputBusca" v-model="buscador.q" placeholder="Código o descripción…" @input="buscarRopa" />
          <ul class="ae-busca-list">
            <li v-for="r in buscador.res" :key="r.cod" @click="elegirRopa(r)">
              <b>{{ r.cod }}</b> — {{ r.descripcion }}<span v-if="r.inactivo" class="ae-inac">inactivo</span>
            </li>
            <li v-if="!buscador.res.length" class="ae-busca-vacio">{{ buscador.q.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados' }}</li>
          </ul>
          <div class="ae-acc"><span class="ae-spacer"></span><button class="btn reset" @click="cerrarBuscador">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

interface Fila { sel: boolean; rcod: number | null; rdes: string; rtal: number; rtad: string }
const codigo = ref(0); const nombre = ref(''); const fotoUrl = ref('')
const items = ref<Fila[]>([]); const talles = ref<{ cod: number; nombre: string }[]>([])
const proc = ref(false); const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }
const hayElegidos = computed(() => items.value.some(i => i.sel))

const onSelEmp = (r: any) => seleccionar(r)
async function seleccionar (r: any) {
  if (!talles.value.length) { try { talles.value = (await api.get('/talles')).data ?? [] } catch { /* */ } }
  try {
    const { data } = await api.get(`/asignar-epp/empleado/${r.PER_COD}`)
    codigo.value = Number(r.PER_COD); nombre.value = data.nombre
    items.value = (data.items ?? []).map((x: any) => ({ sel: false, rcod: x.rcod, rdes: x.rdes, rtal: x.rtal, rtad: x.rtad }))
    if (!items.value.length) agregarFila()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
  fotoUrl.value = ''
  try { const { data } = await api.get(`/empleados/${r.PER_COD}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* sin foto */ }
}

const agregarFila = () => items.value.push({ sel: false, rcod: null, rdes: '', rtal: 0, rtad: '' })

async function lookup (it: Fila) {
  if (!it.rcod || it.rcod <= 0) { it.rdes = ''; return }
  try { const { data } = await api.get(`/asignar-epp/ropa/${it.rcod}`); it.rdes = data.des }
  catch { it.rdes = ''; flash('Código de prenda/EPP inexistente.', true) }
}
const onTalle = (it: Fila) => { const t = talles.value.find(x => x.cod === it.rtal); it.rtad = t ? t.nombre : '' }
const borrarItems = () => { items.value = items.value.filter(i => !i.sel) }

// ── Buscador de prenda/EPP ──
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
function elegirRopa (r: any) {
  const it = items.value[buscador.value.fila]
  if (it) { it.rcod = r.cod; it.rdes = r.descripcion }
  cerrarBuscador()
}

async function confirmar () {
  const validos = items.value.filter(i => i.rcod && i.rcod > 0)
  if (!confirm(`¿Confirmar EPP asignada a ${nombre.value}?`)) return
  proc.value = true
  try {
    await api.post(`/asignar-epp/${codigo.value}`, { items: validos.map(i => ({ rcod: i.rcod, rdes: i.rdes, rtal: i.rtal, rtad: i.rtad })) })
    flash('EPP asignada guardada correctamente.')
    const { data } = await api.get(`/asignar-epp/empleado/${codigo.value}`)
    items.value = (data.items ?? []).map((x: any) => ({ sel: false, rcod: x.rcod, rdes: x.rdes, rtal: x.rtal, rtad: x.rtad }))
    if (!items.value.length) agregarFila()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}

function reset () {
  codigo.value = 0; nombre.value = ''; items.value = []; fotoUrl.value = ''
}
</script>

<style scoped>
.ae-view { display:flex; flex-direction:column; min-height:100%; }
.ae-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ae-ico { font-size:28px; } .ae-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ae-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ae-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ae-msg.ok { background:#d1fae5; color:#065f46; } .ae-msg.err { background:#fee2e2; color:#991b1b; }
.ae-body { padding:16px 18px; max-width:920px; }
.ae-top { display:flex; gap:14px; align-items:flex-end; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo.grow { flex:1; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.ae-busc { position:relative; } .ae-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.ae-busc-lupa { display:flex; gap:8px; } .ae-busc-lupa input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; }
.ae-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ae-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.ae-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.ae-result li:hover { background:#f0faf4; } .ae-result li b { font-size:13px; color:#1e293b; } .ae-result li span { font-size:11px; color:#6b7280; }
.ae-foto { width:140px; height:90px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ae-foto img { width:100%; height:100%; object-fit:cover; } .ae-foto-ph { font-size:11px; color:#9ca3af; }
.ae-sub { margin:16px 0 6px; font-weight:700; color:#2a4a6a; font-size:13px; }
.ae-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.ae-tabla th { background:#1e293b; color:#fff; padding:8px 10px; text-align:left; font-size:12px; } .ae-tabla th.c { text-align:center; width:50px; }
.ae-tabla td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ae-tabla td.c { text-align:center; }
.ae-tabla input[type=number], .ae-tabla select { width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:13px; } .inp-cod { width:90px; min-width:90px; text-align:right; }
.ae-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.ae-vacio { text-align:center; color:#94a3b8; padding:14px; }
.ae-acc { display:flex; gap:8px; margin-top:14px; align-items:center; } .ae-spacer { flex:1; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.del { background:#fee2e2; color:#991b1b; } .btn.add { background:#d1fae5; color:#065f46; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.cod-cell { display:flex; gap:4px; align-items:center; } .btn-busca { border:none; background:#eef2f7; border-radius:5px; cursor:pointer; padding:4px 7px; font-size:13px; }
.ae-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.ae-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(520px,94vw); }
.ae-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.ae-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; }
.ae-busca-list { list-style:none; margin:10px 0 0; padding:0; max-height:340px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.ae-busca-list li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; font-size:13px; color:#1e293b; } .ae-busca-list li:hover { background:#f0faf4; } .ae-busca-list li b { color:#2d6a9f; }
.ae-busca-vacio { color:#94a3b8; cursor:default !important; } .ae-busca-vacio:hover { background:none !important; }
.ae-inac { margin-left:8px; font-size:11px; color:#b91c1c; background:#fee2e2; padding:1px 6px; border-radius:10px; }
</style>
