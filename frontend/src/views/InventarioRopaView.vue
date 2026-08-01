<!-- InventarioRopaView.vue — Carga de inventario de ropa/EPP (ropa_inventario.scx). -->
<template>
  <div class="iv-view">
    <div class="iv-cab">
      <div class="iv-ico">📋</div>
      <div class="iv-tx"><h1>Carga de Inventario</h1><p>Ajuste del stock real de ropa y EPP por depósito</p></div>
      <button class="iv-ia" @click="modalIA = true">🤖 IA</button>
      <button class="iv-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/inventario-ropa" titulo="Asistente IA — Carga de Inventario"
            subtitulo="Preguntá sobre el ajuste de inventario"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué diferencia hay con Ingreso?','¿Qué significan las filas amarillas?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['iv-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="iv-body">
      <div class="iv-top">
        <label>Seleccionar el depósito a inventariar</label>
        <select v-model.number="deposito"><option :value="0">— Seleccione —</option><option v-for="d in depositos" :key="d.cod" :value="d.cod">{{ d.nombre }}</option></select>
        <button class="btn ver" :disabled="deposito <= 0 || cargando" @click="traer">{{ cargando ? '⟳…' : '⬇ TRAER TODO EL STOCK' }}</button>
        <span v-if="cargado" class="iv-conteo">{{ lista.length }} líneas · {{ cambiadas }} con cambios</span>
      </div>

      <table v-if="cargado" class="iv-tabla">
        <thead><tr><th style="width:70px">Código</th><th>Detalle del EPP</th><th>Marca</th><th style="width:120px">Talle</th><th class="c" style="width:90px">Cant. Actual</th><th class="c" style="width:100px">Cant. Real</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in lista" :key="i" :class="{ dif: r.actual !== r.real }">
            <td class="iv-cod">{{ r.codigo }}</td><td>{{ r.detalle }}</td>
            <td>{{ r.mcod }} {{ r.marca }}</td><td>{{ r.talle }}</td>
            <td class="c">{{ r.actual }}</td>
            <td class="c"><input type="number" v-model.number="r.real" class="iv-real" /></td>
          </tr>
          <tr v-if="!lista.length"><td colspan="6" class="iv-vacio">El depósito no tiene stock cargado.</td></tr>
        </tbody>
      </table>

      <div v-if="cargado && lista.length" class="iv-acc">
        <button class="btn reset" @click="reset">↺ Reset</button>
        <span class="iv-spacer"></span>
        <button class="btn ok" :disabled="proc" @click="confirmar">{{ proc ? '⟳ Guardando…' : '✔ CONFIRMAR CAMBIOS INVENTARIO' }}</button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="iv-ov" @click.self="ayuda = false">
        <div class="iv-help-md">
          <h3>❓ Ayuda — Carga de Inventario</h3>
          <ul>
            <li>Elegí un depósito y presioná <b>TRAER TODO EL STOCK</b>.</li>
            <li>Corregí la <b>Cantidad Real</b> de cada línea según el conteo físico. Las filas modificadas se resaltan en amarillo.</li>
            <li><b>CONFIRMAR</b> fija el stock a la cantidad real cargada (no suma ni resta, asigna el valor).</li>
          </ul>
          <div class="iv-acc"><span class="iv-spacer"></span><button class="btn ver" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

interface Linea { codigo: number; detalle: string; mcod: number; marca: string; tcod: number; talle: string; actual: number; real: number }
const depositos = ref<{ cod: number; nombre: string }[]>([]); const deposito = ref(0)
const lista = ref<Linea[]>([]); const cargado = ref(false); const cargando = ref(false); const proc = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const cambiadas = computed(() => lista.value.filter(r => r.actual !== r.real).length)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

onMounted(async () => { try { depositos.value = (await api.get('/inventario-ropa/depositos')).data ?? [] } catch { flash('No se pudieron cargar los depósitos.', true) } })

async function traer () {
  if (deposito.value <= 0) return
  cargando.value = true
  try { lista.value = ((await api.get(`/inventario-ropa/stock/${deposito.value}`)).data ?? []).map((r: any) => ({ ...r })); cargado.value = true }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo leer el stock.', true) }
  finally { cargando.value = false }
}

async function confirmar () {
  if (!confirm('¿Confirma los cambios de inventario? El stock quedará en la cantidad real cargada.')) return
  proc.value = true
  try {
    const { data } = await api.post(`/inventario-ropa/${deposito.value}`, { items: lista.value.map(r => ({ codigo: r.codigo, detalle: r.detalle, mcod: r.mcod, marca: r.marca, tcod: r.tcod, talle: r.talle, real: r.real })) })
    flash(`Inventario actualizado: ${data.actualizados} líneas.`)
    await traer()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo confirmar.', true) }
  finally { proc.value = false }
}

function reset () { deposito.value = 0; lista.value = []; cargado.value = false }
</script>

<style scoped>
.iv-view { display:flex; flex-direction:column; min-height:100%; }
.iv-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.iv-ico { font-size:28px; } .iv-tx h1 { margin:0; font-size:19px; color:#1e293b; } .iv-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.iv-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.iv-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.iv-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .iv-msg.ok { background:#d1fae5; color:#065f46; } .iv-msg.err { background:#fee2e2; color:#991b1b; }
.iv-body { padding:16px 18px; }
.iv-top { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.iv-top label { font-size:13px; font-weight:600; color:#374151; }
.iv-top select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; min-width:220px; }
.iv-conteo { font-size:12px; color:#6b7280; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ver { background:#2d6a9f; color:#fff; } .btn.ok { background:#1b4332; color:#fff; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.iv-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; border:1px solid #e2e8f0; }
.iv-tabla th { background:#1e293b; color:#fff; padding:7px 9px; text-align:left; font-size:11.5px; } .iv-tabla th.c { text-align:center; }
.iv-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .iv-tabla td.c { text-align:center; } .iv-cod { color:#2d6a9f; font-weight:700; }
.iv-tabla tr.dif td { background:#fef9c3; }
.iv-real { width:70px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:13px; text-align:right; }
.iv-vacio { text-align:center; color:#94a3b8; padding:16px; }
.iv-acc { display:flex; gap:8px; align-items:center; margin-top:14px; } .iv-spacer { flex:1; }
.iv-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.iv-help-md { background:#fff; border-radius:14px; padding:22px; width:min(480px,94vw); } .iv-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .iv-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
