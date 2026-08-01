<!-- BorrarRopaView.vue — Borrar Ropa entregada (ropa_borrar.scx). -->
<template>
  <div class="br-view">
    <div class="br-cab">
      <div class="br-ico">🗑️</div>
      <div class="br-tx"><h1>Borrar Ropa entregada</h1><p>Anula entregas y devuelve los elementos al stock</p></div>
      <button class="br-ia" @click="modalIA = true">🤖 IA</button>
      <button class="br-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/borrar-ropa" titulo="Asistente IA — Borrar Ropa entregada"
            subtitulo="Preguntá sobre la anulación de entregas"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué pasa con el stock al borrar?','¿Cómo filtro por empleado?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['br-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="br-body">
      <div class="br-top">
        <label class="rad"><input type="radio" :value="0" v-model.number="seleccion" /> Todos los empleados</label>
        <label class="rad"><input type="radio" :value="1" v-model.number="seleccion" /> Un empleado</label>
        <div v-if="seleccion === 1" class="br-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar empleado…" @input="buscarEmp" @focus="buscarEmp" />
          <ul v-if="resultados.length" class="br-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionarEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>Cód. {{ r.PER_COD }}</span></li>
          </ul>
        </div>
        <button class="btn ver" @click="visualizar">🔍 VISUALIZAR</button>
      </div>

      <table v-if="cargado" class="br-tabla">
        <thead><tr><th class="c" style="width:40px">OK</th><th style="width:70px">Cód.</th><th>Descripción</th><th style="width:140px">Cuil</th><th style="width:110px">Fecha</th><th class="c" style="width:70px">Cant.</th><th>Motivo</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in lista" :key="i" :class="{ sel: r.sel }">
            <td class="c"><input type="checkbox" v-model="r.sel" /></td>
            <td class="br-cod">{{ r.codigo }}</td><td>{{ r.objeto }}</td><td>{{ r.cuil }}</td>
            <td>{{ fmtFecha(r.fecha) }}</td><td class="c">{{ r.cantidad }}</td><td class="br-mot">{{ r.motivo }}</td>
          </tr>
          <tr v-if="!lista.length"><td colspan="7" class="br-vacio">No hay entregas para mostrar.</td></tr>
        </tbody>
      </table>

      <div v-if="cargado && lista.length" class="br-acc">
        <button class="btn reset" @click="reset">↺ Reset</button>
        <span class="br-spacer"></span>
        <button class="btn del" :disabled="proc || !haySel" @click="devolver">{{ proc ? '⟳…' : '↩ DEVUELVE E.E.P.' }}</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="br-ov" @click.self="ayuda = false">
        <div class="br-help-md">
          <h3>❓ Ayuda — Borrar Ropa entregada</h3>
          <ul>
            <li>Elegí <b>Todos los empleados</b> o <b>Un empleado</b> y presioná <b>VISUALIZAR</b>.</li>
            <li>Tildá las entregas a anular y presioná <b>DEVUELVE E.E.P.</b></li>
            <li>Al borrar, la cantidad vuelve al <b>stock</b> del depósito de origen (si la entrega había descontado stock).</li>
          </ul>
          <div class="br-acc"><span class="br-spacer"></span><button class="btn ver" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

interface Reg { sel: boolean; codigo: number; objeto: string; cuil: string; fecha: string; cantidad: number; motivo: string; mac: number; tal: number; dep: number }
const seleccion = ref(0); const empleadoCod = ref(0)
const lista = ref<Reg[]>([]); const cargado = ref(false); const proc = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const haySel = computed(() => lista.value.some(r => r.sel))
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }
const fmtFecha = (f: string) => f ? f.split('-').reverse().join('/') : ''

const busqueda = ref(''); const resultados = ref<any[]>([]); let dq: any = null
const buscarEmp = () => {
  clearTimeout(dq); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionarEmp = (r: any) => { empleadoCod.value = Number(r.PER_COD); busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = [] }

async function visualizar () {
  if (seleccion.value === 1 && empleadoCod.value <= 0) { flash('Seleccione un empleado.', true); return }
  try {
    const params = seleccion.value === 1 ? { empleado: empleadoCod.value } : {}
    const { data } = await api.get('/borrar-ropa', { params })
    lista.value = (data ?? []).map((r: any) => ({ ...r, sel: false }))
    cargado.value = true
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar.', true) }
}

async function devolver () {
  const sel = lista.value.filter(r => r.sel)
  if (!sel.length) { flash('Debe seleccionar el registro que desea eliminar.', true); return }
  if (!confirm('¿Confirma borrar la ropa seleccionada?')) return
  proc.value = true
  try {
    const { data } = await api.post('/borrar-ropa/devolver', { items: sel.map(r => ({ codigo: r.codigo, cuil: r.cuil, fecha: r.fecha, cantidad: r.cantidad, mac: r.mac, tal: r.tal, dep: r.dep })) })
    flash(`Se anularon ${data.borradas} entrega(s) y se devolvió el stock.`)
    await visualizar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
  finally { proc.value = false }
}

function reset () { seleccion.value = 0; empleadoCod.value = 0; busqueda.value = ''; lista.value = []; cargado.value = false }
</script>

<style scoped>
.br-view { display:flex; flex-direction:column; min-height:100%; }
.br-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.br-ico { font-size:28px; } .br-tx h1 { margin:0; font-size:19px; color:#1e293b; } .br-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.br-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.br-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.br-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .br-msg.ok { background:#d1fae5; color:#065f46; } .br-msg.err { background:#fee2e2; color:#991b1b; }
.br-body { padding:16px 18px; }
.br-top { display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
.rad { display:flex; align-items:center; gap:6px; font-size:14px; font-weight:600; color:#374151; cursor:pointer; }
.br-busc { position:relative; } .br-busc input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; min-width:260px; }
.br-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.br-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; } .br-result li:hover { background:#f0faf4; } .br-result li b { font-size:13px; } .br-result li span { font-size:11px; color:#6b7280; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ver { background:#2d6a9f; color:#fff; } .btn.del { background:#fee2e2; color:#991b1b; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.br-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; border:1px solid #e2e8f0; }
.br-tabla th { background:#1e293b; color:#fff; padding:7px 9px; text-align:left; font-size:11.5px; } .br-tabla th.c { text-align:center; }
.br-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .br-tabla td.c { text-align:center; }
.br-tabla tr.sel td { background:#fffbe6; } .br-cod { color:#2d6a9f; font-weight:700; } .br-mot { color:#6b7280; font-size:12px; }
.br-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.br-vacio { text-align:center; color:#94a3b8; padding:16px; }
.br-acc { display:flex; gap:8px; align-items:center; margin-top:14px; } .br-spacer { flex:1; }
.br-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.br-help-md { background:#fff; border-radius:14px; padding:22px; width:min(480px,94vw); } .br-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .br-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
