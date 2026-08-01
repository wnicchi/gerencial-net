<!-- SueldosBorrarView.vue — Liquidaciones Borrar (sueldos_borrar). -->
<template>
  <div class="sb-view">
    <div class="sb-cab">
      <div class="sb-cab-ico">🗑️</div>
      <div class="sb-cab-tx"><h1>Liquidaciones — Borrar</h1><p>Eliminar una liquidación de sueldo</p></div>
      <button class="sb-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="sb-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <LiquidacionesConsultarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/liquidaciones" titulo="Asistente IA — Liquidaciones"
            subtitulo="Preguntá sobre el borrado de liquidaciones"
            :sugerencias="['¿Cómo busco la liquidación?','¿Qué se borra exactamente?','¿Se puede deshacer?']"
            @close="modalIA = false" />

    <div class="sb-filtros" v-enter-next>
      <div class="sb-busc-wrap">
        <span class="sb-lbl">Nro. Personal</span>
        <input v-model="buscar" type="text" class="sb-busc" placeholder="Código o nombre…" @input="buscarEmp" @focus="buscarEmp" />
        <ul v-if="sugerencias.length" class="sb-sug"><li v-for="e in sugerencias" :key="e.cod" @click="elegir(e)">{{ e.cod }} · {{ e.nombre }}</li></ul>
      </div>
      <span v-if="codigo" class="sb-elegido">{{ codigo }} — {{ nombre }}</span>
      <span class="sb-lbl">Tipo</span>
      <select v-model.number="tipo" class="sb-sel"><option :value="0">— Elegir —</option><option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select>
      <span class="sb-lbl">Mes</span>
      <select v-model.number="mes" class="sb-sel"><option v-for="m in 12" :key="m" :value="m">{{ m }}</option></select>
      <span class="sb-lbl">Año</span>
      <input v-model.number="anio" type="number" class="sb-anio" />
      <button class="sb-btn-ok" :disabled="cargando || !codigo || !tipo" @click="consultar">{{ cargando ? '⟳…' : 'BUSCAR' }}</button>
    </div>

    <p v-if="aviso" class="sb-aviso">⚠️ {{ aviso }}</p>

    <div v-if="liq" class="sb-cuerpo">
      <div class="sb-info">
        <span><b>Liquidación N°:</b> {{ liq.nro }}</span><span><b>Empleado:</b> {{ liq.nombre }}</span>
        <span><b>Tipo:</b> {{ liq.tipo }}</span><span><b>Período:</b> {{ liq.mes }}/{{ liq.anio }}</span>
      </div>
      <div class="sb-wrap">
        <table class="sb-grid">
          <thead><tr><th>Cód</th><th>Concepto</th><th class="r">Haber</th><th class="r">Deducción</th></tr></thead>
          <tbody>
            <tr v-for="(it, i) in items" :key="i"><td>{{ it.codigo }}</td><td>{{ it.detalle }}</td><td class="r">{{ it.haber ? money(it.haber) : '' }}</td><td class="r">{{ it.deduccion ? money(it.deduccion) : '' }}</td></tr>
          </tbody>
          <tfoot><tr class="sb-total"><td colspan="2"></td><td class="r">TOTAL</td><td class="r">{{ money(total) }}</td></tr></tfoot>
        </table>
      </div>
      <div class="sb-alerta">⚠️ Esta acción elimina la liquidación completa (todos sus ítems). No se puede deshacer.</div>
      <button class="sb-btn-del" :disabled="borrando" @click="borrar">🗑️ {{ borrando ? 'Borrando…' : 'BORRAR LIQUIDACIÓN' }}</button>
    </div>
    <p v-if="msg" :class="['sb-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import LiquidacionesConsultarAyuda from '@/components/LiquidacionesConsultarAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Item { codigo: number; detalle: string; cantidad: number; haber: number; deduccion: number }
const hoy = new Date()
const buscar = ref(''); const sugerencias = ref<{ cod: number; nombre: string }[]>([])
const codigo = ref(0); const nombre = ref('')
const tipos = ref<{ cod: number; desc: string }[]>([])
const tipo = ref(0); const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const liq = ref<any>(null); const items = ref<Item[]>([]); const total = ref(0)
const cargando = ref(false); const borrando = ref(false); const aviso = ref('')
const msg = ref(''); const msgError = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 4000) }

let tB: any
const buscarEmp = () => {
  clearTimeout(tB)
  tB = setTimeout(async () => {
    const q = buscar.value.trim(); if (!q) { sugerencias.value = []; return }
    try { sugerencias.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data.map((e: any) => ({ cod: e.PER_COD ?? e.cod, nombre: (e.PER_NOM ?? e.nombre ?? '').trim() })) }
    catch (e) { console.error(e) }
  }, 250)
}
const elegir = (e: { cod: number; nombre: string }) => { codigo.value = e.cod; nombre.value = e.nombre; buscar.value = ''; sugerencias.value = [] }

const consultar = async () => {
  cargando.value = true; aviso.value = ''; liq.value = null; flash('')
  try {
    const { data } = await api.get('/liquidaciones/consultar', { params: { codigo: codigo.value, tipo: tipo.value, mes: mes.value, anio: anio.value } })
    liq.value = data.liquidacion; items.value = data.items; total.value = data.total
  } catch (e: any) {
    if (e?.response?.status === 404) aviso.value = e.response.data.message
    else { console.error(e); aviso.value = 'No se pudo buscar.' }
  } finally { cargando.value = false }
}

const borrar = async () => {
  if (!confirm(`¿Borrar la liquidación N° ${liq.value.nro} de ${liq.value.nombre}? Esta acción no se puede deshacer.`)) return
  borrando.value = true
  try {
    await api.delete('/liquidaciones', { params: { codigo: codigo.value, tipo: tipo.value, mes: mes.value, anio: anio.value } })
    flash(`Liquidación N° ${liq.value.nro} borrada.`); liq.value = null; items.value = []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
  finally { borrando.value = false }
}

onMounted(async () => { try { tipos.value = (await api.get('/liquidaciones/tipos')).data } catch (e) { console.error(e) } })
</script>

<style scoped>
.sb-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.sb-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sb-cab-ico { font-size:28px; } .sb-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sb-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sb-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sb-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sb-filtros { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.sb-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.sb-busc-wrap { position:relative; }
.sb-busc { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; width:200px; outline:none; }
.sb-sug { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; list-style:none; margin:0; padding:0; max-height:240px; overflow:auto; z-index:20; box-shadow:0 8px 20px rgba(0,0,0,.12); }
.sb-sug li { padding:7px 10px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:#1e293b; } .sb-sug li:hover { background:#eef6ee; color:#1b4332; }
.sb-elegido { font-size:13px; font-weight:600; color:#1b4332; background:#dcfce7; padding:5px 10px; border-radius:6px; }
.sb-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; } .sb-anio { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; width:80px; }
.sb-btn-ok { background:#16a34a; color:#fff; border:none; padding:9px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .sb-btn-ok:disabled { background:#cbd5e1; }
.sb-aviso { margin:12px 18px; background:#fef9c3; border:1px solid #facc15; color:#854d0e; padding:9px 14px; border-radius:6px; font-size:13px; }
.sb-cuerpo { padding:14px 18px; }
.sb-info { display:flex; flex-wrap:wrap; gap:16px; font-size:13px; color:#475569; margin-bottom:10px; } .sb-info b { color:#1e293b; }
.sb-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:50vh; margin-bottom:12px; }
.sb-grid { width:100%; border-collapse:collapse; font-size:13px; }
.sb-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .sb-grid th.r { text-align:right; }
.sb-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .sb-grid td.r { text-align:right; }
.sb-grid tr:nth-child(even) td { background:#f8fafc; } .sb-grid tfoot tr.sb-total td { background:#b91c1c; color:#fff; font-weight:800; font-size:15px; padding:10px; border-top:3px solid #991b1b; position:sticky; bottom:0; }
.sb-alerta { background:#fef2f2; border:1px solid #fca5a5; color:#b91c1c; padding:9px 12px; border-radius:6px; font-size:13px; margin-bottom:12px; }
.sb-btn-del { background:#dc2626; color:#fff; border:none; padding:11px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .sb-btn-del:disabled { background:#cbd5e1; }
.sb-msg { padding:8px 14px; margin:12px 18px; font-size:13px; border-radius:6px; } .sb-msg.ok { background:#dcfce7; color:#166534; } .sb-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
