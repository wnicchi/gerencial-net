<!-- PermisosLaboralesView.vue — Permisos Laborales (permisos_laborales.scx). Datos de la base de gestión. -->
<template>
  <div class="pl-view">
    <div class="pl-cab">
      <div class="pl-ico">🪪</div>
      <div class="pl-tx"><h1>Permisos Laborales</h1><p>Permisos de empleados — pendientes de procesar e históricos</p></div>
      <button class="pl-ia" @click="modalIA = true">🤖 IA</button>
      <button class="pl-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="pl-reset" @click="cargar">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/permisos-laborales" titulo="Asistente IA — Permisos Laborales"
            subtitulo="Preguntá sobre el procesado de permisos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo confirmo un permiso?','¿Qué significan los colores?','¿De dónde salen los permisos?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['pl-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="pl-tabs">
      <button :class="['pl-tab', tab === 'pend' && 'on']" @click="tab = 'pend'">Pendientes de procesar <span class="pl-badge">{{ pendientes.length }}</span></button>
      <button :class="['pl-tab', tab === 'hist' && 'on']" @click="tab = 'hist'">Históricos <span class="pl-badge g">{{ historico.length }}</span></button>
    </div>

    <div class="pl-body">
      <div v-if="cargando" class="pl-info">⟳ Cargando permisos…</div>

      <!-- Pendientes -->
      <template v-else-if="tab === 'pend'">
        <div class="pl-toolbar">
          <button class="pl-mini" @click="marcar(true)">Todos</button>
          <button class="pl-mini g" @click="marcar(false)">Nada</button>
          <span style="flex:1"></span>
          <button class="pl-confirm" :disabled="confirmando || !haySel" @click="confirmar">{{ confirmando ? '⟳…' : '✔ CONFIRMAR PROCESADO' }}</button>
        </div>
        <div v-if="!pendientes.length" class="pl-vacio">No hay permisos pendientes de procesar.</div>
        <div v-else class="pl-grid-wrap">
          <table class="pl-grid">
            <thead><tr>
              <th style="width:36px;text-align:center">OK</th>
              <th style="width:54px">Nro.</th>
              <th>Fecha Desde</th><th>Fecha Hasta</th><th>H. Inicial</th><th>H. Final</th>
              <th>Empleado</th><th>Sector</th><th>Fecha Carga</th><th>Falta</th><th>Observaciones</th><th>Responsable</th>
            </tr></thead>
            <tbody>
              <tr v-for="p in pendientes" :key="p.cod" :class="{ sel: p.sel }">
                <td style="text-align:center"><input type="checkbox" v-model="p.sel" /></td>
                <td class="pl-cod">{{ p.cod }}</td>
                <td>{{ fmt(p.fecha_desde) }}</td><td>{{ fmt(p.fecha_hasta) }}</td>
                <td>{{ p.hora_inicio }}</td><td>{{ p.hora_fin }}</td>
                <td>{{ p.empleado }}</td><td>{{ p.sector }}</td><td>{{ fmt(p.fecha_carga) }}</td>
                <td>{{ p.falta }}</td><td class="pl-obs">{{ p.observaciones }}</td><td>{{ p.responsable }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Históricos -->
      <template v-else>
        <p class="pl-leyenda"><span class="pl-chip ok"></span> Procesado &nbsp;&nbsp; <span class="pl-chip pend"></span> Pendiente</p>
        <div class="pl-grid-wrap">
          <table class="pl-grid">
            <thead><tr>
              <th style="width:54px">Nro.</th>
              <th>Fecha Desde</th><th>Fecha Hasta</th><th>H. Inicial</th><th>H. Final</th>
              <th>Empleado</th><th>Sector</th><th>Fecha Carga</th><th>Falta</th><th>Observaciones</th><th>Responsable</th>
              <th>Fecha Confirm.</th><th>Resp. Confirm.</th>
            </tr></thead>
            <tbody>
              <tr v-for="p in historico" :key="p.cod" :class="p.procesado ? 'hok' : 'hpend'">
                <td class="pl-cod">{{ p.cod }}</td>
                <td>{{ fmt(p.fecha_desde) }}</td><td>{{ fmt(p.fecha_hasta) }}</td>
                <td>{{ p.hora_inicio }}</td><td>{{ p.hora_fin }}</td>
                <td>{{ p.empleado }}</td><td>{{ p.sector }}</td><td>{{ fmt(p.fecha_carga) }}</td>
                <td>{{ p.falta }}</td><td class="pl-obs">{{ p.observaciones }}</td><td>{{ p.responsable }}</td>
                <td>{{ fmtHora(p.fecha_confirma) }}</td><td>{{ p.resp_confirma }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="pl-ov" @click.self="ayuda = false">
        <div class="pl-help-md">
          <h3>❓ Ayuda — Permisos Laborales</h3>
          <ul>
            <li>Los permisos provienen del <b>sistema de gestión</b>.</li>
            <li>En <b>Pendientes de procesar</b> se tildan los permisos y se presiona <b>Confirmar procesado</b>.</li>
            <li>En <b>Históricos</b>: verde = procesado, amarillo = pendiente.</li>
          </ul>
          <div class="pl-acc"><span style="flex:1"></span><button class="pl-confirm" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

const tab = ref<'pend' | 'hist'>('pend')
const pendientes = ref<any[]>([]); const historico = ref<any[]>([])
const cargando = ref(false); const confirmando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const haySel = computed(() => pendientes.value.some(p => p.sel))
const fmt = (s: string) => s ? s.split('-').reverse().join('/') : ''
const fmtHora = (s: string) => { if (!s) return ''; const [f, h] = s.split(/[ T]/); const [y, m, d] = (f || '').split('-'); return d ? `${d}/${m}/${y}${h ? ' ' + h : ''}` : s }
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }
const marcar = (v: boolean) => pendientes.value.forEach(p => p.sel = v)

async function cargar () {
  cargando.value = true
  try {
    const { data } = await api.get('/permisos-laborales')
    pendientes.value = (data.pendientes ?? []).map((p: any) => ({ ...p, sel: false }))
    historico.value = data.historico ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo acceder al sistema de gestión.', true) }
  finally { cargando.value = false }
}

async function confirmar () {
  const sel = pendientes.value.filter(p => p.sel)
  if (!sel.length) { flash('Debe seleccionar el permiso a confirmar.', true); return }
  if (!confirm(`¿Confirma ${sel.length} permiso(s) procesado(s)?`)) return
  confirmando.value = true
  try {
    const { data } = await api.post('/permisos-laborales/confirmar', { codigos: sel.map(p => p.cod) })
    flash(`Se confirmaron ${data.confirmados} permiso(s).`)
    await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo confirmar.', true) }
  finally { confirmando.value = false }
}

cargar()
</script>

<style scoped>
.pl-view { display:flex; flex-direction:column; min-height:100%; }
.pl-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.pl-ico { font-size:28px; } .pl-tx h1 { margin:0; font-size:19px; color:#1e293b; } .pl-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.pl-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.pl-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.pl-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.pl-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .pl-msg.ok { background:#d1fae5; color:#065f46; } .pl-msg.err { background:#fee2e2; color:#991b1b; }
.pl-tabs { display:flex; gap:4px; padding:10px 18px 0; border-bottom:2px solid #e2e8f0; }
.pl-tab { background:none; border:none; padding:9px 16px; cursor:pointer; font-size:13px; font-weight:700; color:#64748b; border-bottom:3px solid transparent; margin-bottom:-2px; display:flex; align-items:center; gap:6px; }
.pl-tab.on { color:#1b4332; border-bottom-color:#40916c; }
.pl-badge { background:#1e293b; color:#fff; border-radius:10px; padding:1px 8px; font-size:11px; } .pl-badge.g { background:#16a34a; }
.pl-body { padding:14px 18px; }
.pl-info, .pl-vacio { text-align:center; color:#94a3b8; padding:24px; }
.pl-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.pl-mini { background:#eef2f7; color:#334155; border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .pl-mini.g { background:#e2e8f0; }
.pl-confirm { background:#dc2626; color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:800; } .pl-confirm:disabled { opacity:.5; cursor:default; }
.pl-grid-wrap { max-height:60vh; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.pl-grid { width:100%; border-collapse:collapse; font-size:12px; white-space:nowrap; }
.pl-grid th { position:sticky; top:0; background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11px; }
.pl-grid td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.pl-grid tr.sel td { background:#fef9c3; } .pl-cod { color:#2d6a9f; font-weight:700; }
.pl-grid tr.hok td { background:#dcfce7; } .pl-grid tr.hpend td { background:#fef9c3; }
.pl-obs { max-width:200px; overflow:hidden; text-overflow:ellipsis; }
.pl-grid input[type=checkbox] { width:15px; height:15px; accent-color:#dc2626; }
.pl-leyenda { font-size:12px; color:#475569; margin:0 2px 8px; display:flex; align-items:center; gap:4px; }
.pl-chip { width:13px; height:13px; border-radius:3px; display:inline-block; } .pl-chip.ok { background:#dcfce7; border:1px solid #86efac; } .pl-chip.pend { background:#fef9c3; border:1px solid #fde047; }
.pl-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.pl-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.pl-help-md { background:#fff; border-radius:14px; padding:22px; width:min(520px,94vw); } .pl-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .pl-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
