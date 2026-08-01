<!-- EntregaHistoricaView.vue — Entregas históricas de EPP por empleado (ropa_entrega_historica.scx). -->
<template>
  <div class="eh-view">
    <div class="eh-cab">
      <div class="eh-ico">📜</div>
      <div class="eh-tx"><h1>Entregas Históricas de E.P.P.</h1><p>Historial de entregas y reimpresión de recibos</p></div>
      <button class="eh-ia" @click="modalIA = true">🤖 IA</button>
      <button class="eh-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/entrega-historica" titulo="Asistente IA — Entregas Históricas"
            subtitulo="Preguntá sobre el historial de entregas"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo reimprimo un recibo?','¿Qué es la Resolución 299/2011?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['eh-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="eh-body">
      <div class="eh-top">
        <label>Empleado</label>
        <div class="eh-busc">
          <input v-model="busqueda" type="text" placeholder="Buscar por código, legajo o nombre…" @input="buscarEmp" @focus="buscarEmp" />
          <ul v-if="resultados.length" class="eh-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionarEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>Cód. {{ r.PER_COD }} · Leg. {{ (r.PER_LEG||'').toString().trim() }}</span></li>
          </ul>
        </div>
      </div>

      <template v-if="codigoEmp">
        <table class="eh-tabla">
          <thead><tr><th class="c" style="width:40px">OK</th><th style="width:60px">Cód.</th><th>Descripción</th><th style="width:140px">Cuil</th><th style="width:100px">Fecha</th><th class="c" style="width:60px">Cant.</th><th>Marca / Talle</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in items" :key="i" :class="{ sel: r.sel }">
              <td class="c"><input type="checkbox" v-model="r.sel" /></td>
              <td class="eh-cod">{{ r.codigo }}</td><td>{{ r.detalle }}</td><td>{{ r.cuil }}</td>
              <td>{{ fmt(r.fecha) }}</td><td class="c">{{ r.cantidad }}</td>
              <td>{{ r.marca }}{{ r.talle ? ' \\ ' + r.talle : '' }}</td>
            </tr>
            <tr v-if="!items.length"><td colspan="7" class="eh-vacio">El empleado no tiene entregas registradas.</td></tr>
          </tbody>
        </table>

        <div class="eh-info">
          <label>Información adicional</label>
          <textarea v-model="motivo" rows="3" placeholder="Texto opcional para el recibo…"></textarea>
        </div>

        <div class="eh-acc">
          <button class="btn reset" @click="reset">↺ Reset</button>
          <span class="eh-spacer"></span>
          <button class="btn ok" :disabled="proc || !haySel" @click="imprimir">{{ proc ? '⟳…' : '🖨 IMPRIMIR ENTREGA' }}</button>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="eh-ov" @click.self="ayuda = false">
        <div class="eh-help-md">
          <h3>❓ Ayuda — Entregas Históricas</h3>
          <ul>
            <li>Buscá un empleado para ver <b>todas sus entregas</b> de ropa/EPP.</li>
            <li>Tildá las que querés incluir y presioná <b>IMPRIMIR ENTREGA</b> para reimprimir el recibo <b>Resolución 299/2011</b>.</li>
            <li>No modifica el stock; es sólo consulta e impresión.</li>
          </ul>
          <div class="eh-acc"><span class="eh-spacer"></span><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="pdfUrl" class="eh-pdf-ov" @click.self="cerrarPdf">
        <div class="eh-pdf-md">
          <div class="eh-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="eh-pdf-acc">
              <button class="eh-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="eh-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="eh-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="eh-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import { generarReciboRopa } from '@/utils/reciboRopa'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'

interface Reg { sel: boolean; codigo: number; detalle: string; cuil: string; fecha: string; cantidad: number; motivo: string; marca: string; talle: string; certifica: boolean }
const codigoEmp = ref(0); const nombreEmp = ref(''); const motivo = ref('')
const items = ref<Reg[]>([]); const proc = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const haySel = computed(() => items.value.some(r => r.sel))
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''

const busqueda = ref(''); const resultados = ref<any[]>([]); let dq: any = null
const buscarEmp = () => {
  clearTimeout(dq); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
async function seleccionarEmp (r: any) {
  resultados.value = []; busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`
  try {
    const { data } = await api.get(`/entrega-historica/empleado/${r.PER_COD}`)
    codigoEmp.value = Number(r.PER_COD); nombreEmp.value = data.nombre
    items.value = (data.items ?? []).map((x: any) => ({ ...x, sel: false }))
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
}

async function imprimir () {
  const sel = items.value.filter(r => r.sel)
  if (!sel.length) { flash('Seleccionar qué E.P.P. imprime.', true); return }
  proc.value = true
  try {
    const { data } = await api.post(`/entrega-historica/recibo/${codigoEmp.value}`, { motivo: motivo.value, items: sel.map(r => ({ codigo: r.codigo, detalle: r.detalle, marca: r.marca, talle: r.talle, cantidad: r.cantidad, certifica: r.certifica, fecha: r.fecha })) })
    generarRecibo(data)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el recibo.', true) }
  finally { proc.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarRecibo (p: any) {
  const blob = generarReciboRopa(p)
  cerrarPdf()
  pdfNombre.value = `Recibo_${(p.empleado.nombre || '').replace(/\s+/g, '_')}.pdf`
  pdfUrl.value = URL.createObjectURL(blob)
}

function reset () { codigoEmp.value = 0; nombreEmp.value = ''; busqueda.value = ''; items.value = []; motivo.value = '' }
</script>

<style scoped>
.eh-view { display:flex; flex-direction:column; min-height:100%; }
.eh-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.eh-ico { font-size:28px; } .eh-tx h1 { margin:0; font-size:19px; color:#1e293b; } .eh-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.eh-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.eh-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.eh-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .eh-msg.ok { background:#d1fae5; color:#065f46; } .eh-msg.err { background:#fee2e2; color:#991b1b; }
.eh-body { padding:16px 18px; }
.eh-top { display:flex; flex-direction:column; gap:5px; max-width:520px; } .eh-top label { font-size:12px; font-weight:600; color:#374151; }
.eh-busc { position:relative; } .eh-busc input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.eh-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.eh-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; } .eh-result li:hover { background:#f0faf4; } .eh-result li b { font-size:13px; color:#1e293b; } .eh-result li span { font-size:11px; color:#6b7280; }
.eh-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; border:1px solid #e2e8f0; }
.eh-tabla th { background:#1e293b; color:#fff; padding:7px 9px; text-align:left; font-size:11.5px; } .eh-tabla th.c { text-align:center; }
.eh-tabla td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .eh-tabla td.c { text-align:center; }
.eh-tabla tr.sel td { background:#fffbe6; } .eh-cod { color:#2d6a9f; font-weight:700; }
.eh-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.eh-vacio { text-align:center; color:#94a3b8; padding:16px; }
.eh-info { margin-top:14px; max-width:760px; } .eh-info label { font-size:12px; font-weight:600; color:#374151; }
.eh-info textarea { width:100%; border:1px solid #d1d5db; border-radius:7px; padding:9px 11px; font-size:12.5px; margin-top:5px; box-sizing:border-box; resize:vertical; }
.eh-acc { display:flex; gap:8px; align-items:center; margin-top:14px; } .eh-spacer { flex:1; }
.btn { border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#1b4332; color:#fff; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.eh-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:60px 18px; }
.eh-help-md { background:#fff; border-radius:14px; padding:22px; width:min(480px,94vw); } .eh-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .eh-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.eh-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.eh-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.eh-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .eh-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.eh-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .eh-pdf-b.ok { background:#22c55e; color:#fff; } .eh-pdf-b.cancel { background:#ef4444; color:#fff; }
.eh-pdf-frame { flex:1; border:none; width:100%; }
</style>
