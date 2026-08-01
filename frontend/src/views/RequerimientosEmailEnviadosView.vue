<!-- RequerimientosEmailEnviadosView.vue — Requerimientos Emails Enviados (requerimientos_email_enviados.scx). -->
<template>
  <div class="re-view">
    <div class="re-cab">
      <div class="re-ico">📨</div>
      <div class="re-tx"><h1>Requerimientos — Emails Enviados</h1><p>Historial de correos de requerimientos enviados a clientes</p></div>
      <button class="re-ia" @click="modalIA = true">🤖 IA</button>
      <button class="re-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/requerimientos-enviados" titulo="Asistente IA — Emails Enviados"
            subtitulo="Preguntá sobre el historial de envíos y el reenvío"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo reenvío un correo?','¿Qué adjuntos tiene un envío?','¿Se actualiza con los datos actuales?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['re-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="re-body">
      <div class="re-toolbar">
        <input v-model="filtro" class="re-search" placeholder="🔍 Filtrar por cliente…" />
        <button class="re-mini" @click="marcar(true)">Todos</button>
        <button class="re-mini g" @click="marcar(false)">Nada</button>
        <span style="flex:1"></span>
        <button class="re-send" :disabled="reenviando || !haySel" @click="reenviarSeleccionados">{{ reenviando ? `⟳ ${progreso}` : '✉ Re-enviar Email' }}</button>
      </div>

      <div class="re-grid-wrap">
        <table class="re-grid">
          <thead><tr>
            <th style="width:38px;text-align:center">OK</th>
            <th style="width:64px">Cliente</th>
            <th>Razón Social</th>
            <th style="width:150px">Enviado el</th>
            <th>Emails a los que se envió</th>
          </tr></thead>
          <tbody>
            <tr v-for="(e, i) in enviadosFiltrados" :key="i" :class="{ sel: sel === e.unico }" @click="seleccionar(e)">
              <td style="text-align:center" @click.stop><input type="checkbox" v-model="e.elegir" /></td>
              <td class="re-cod">{{ e.cliente }}</td><td>{{ e.nombre }}</td>
              <td>{{ fmtFecha(e.enviado) }}</td><td class="re-emails">{{ e.emails }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="re-prev">
        <div class="re-prev-tit">{{ selNombre ? `Archivos adjuntos enviados a ${selNombre}` : 'Seleccione un envío para ver sus adjuntos' }}</div>
        <div v-if="cargandoPrev" class="re-prev-info">⟳ Cargando…</div>
        <div v-else-if="selNombre" class="re-prev-row">
          <span v-if="!adjuntos.length" class="re-no">Sin adjuntos registrados</span>
          <span v-for="(a, i) in adjuntos" :key="i" class="re-tag">📄 {{ a }}</span>
        </div>
      </div>
      <p class="re-nota">ℹ️ El re-envío regenera el correo con los <b>emails y documentos actuales</b> del cliente, y se abre en su programa de correo listo para enviar.</p>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="re-ov" @click.self="ayuda = false">
        <div class="re-help-md">
          <h3>❓ Ayuda — Emails Enviados</h3>
          <ul>
            <li>Muestra el <b>historial</b> de correos de requerimientos enviados a cada cliente.</li>
            <li>Al hacer clic en una fila se ven los <b>archivos adjuntos</b> de ese envío.</li>
            <li><b>Re-enviar Email</b> genera de nuevo el <code>.eml</code> (con los datos actuales del cliente) y lo abre en su programa de correo.</li>
          </ul>
          <div class="re-acc"><span style="flex:1"></span><button class="re-send" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

const enviados = ref<any[]>([]); const filtro = ref('')
const sel = ref(0); const selNombre = ref(''); const adjuntos = ref<string[]>([]); const cargandoPrev = ref(false)
const reenviando = ref(false); const progreso = ref('')
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const haySel = computed(() => enviados.value.some(e => e.elegir))
const enviadosFiltrados = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? enviados.value.filter(e => e.nombre.toLowerCase().includes(q)) : enviados.value
})
const fmtFecha = (s: string) => {
  if (!s) return ''
  const [f, h] = s.split(/[ T]/); const [y, m, d] = (f || '').split('-')
  return d ? `${d}/${m}/${y}${h ? ' ' + h.slice(0, 5) : ''}` : s
}
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }
const marcar = (v: boolean) => enviadosFiltrados.value.forEach(e => e.elegir = v)

async function cargar () {
  try { enviados.value = ((await api.get('/requerimientos-enviados')).data ?? []).map((e: any) => ({ ...e, elegir: false })) }
  catch { flash('No se pudo cargar el historial.', true) }
}
async function seleccionar (e: any) {
  sel.value = e.unico; selNombre.value = e.nombre; cargandoPrev.value = true; adjuntos.value = []
  try { adjuntos.value = (await api.get(`/requerimientos-enviados/${e.unico}/adjuntos`)).data.adjuntos ?? [] }
  catch { /* */ } finally { cargandoPrev.value = false }
}

async function reenviarSeleccionados () {
  const elegidos = enviados.value.filter(e => e.elegir)
  if (!elegidos.length) { flash('Marque al menos un envío.', true); return }
  if (!confirm(`Se regenerará el correo (.eml) de ${elegidos.length} envío(s) con los datos actuales del cliente. Cada uno se abrirá en su programa de correo. ¿Continuar?`)) return
  reenviando.value = true; let ok = 0; const errores: string[] = []
  for (let i = 0; i < elegidos.length; i++) {
    const e = elegidos[i]; progreso.value = `${i + 1}/${elegidos.length}`
    try {
      const resp = await api.post(`/requerimientos-enviados/${e.unico}/reenviar`, {}, { responseType: 'blob' })
      const url = URL.createObjectURL(resp.data as Blob)
      const a = document.createElement('a'); a.href = url
      a.download = `Requerimientos_${e.nombre.replace(/[^A-Za-z0-9]+/g, '_')}.eml`
      document.body.appendChild(a); a.click(); a.remove()
      setTimeout(() => URL.revokeObjectURL(url), 4000)
      ok++; await new Promise(r => setTimeout(r, 600))
    } catch (err: any) {
      let m = 'error'
      try { m = JSON.parse(await (err?.response?.data as Blob).text())?.message ?? 'error' } catch { /* */ }
      errores.push(`${e.nombre}: ${m}`)
    }
  }
  reenviando.value = false; progreso.value = ''
  await cargar()
  if (errores.length) flash(`Regenerados ${ok}. Con problemas: ${errores.join(' · ')}`, true)
  else flash(`Se regeneraron ${ok} correo(s). Revíselos en su programa de correo y presione Enviar.`)
}

cargar()
</script>

<style scoped>
.re-view { display:flex; flex-direction:column; min-height:100%; }
.re-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.re-ico { font-size:28px; } .re-tx h1 { margin:0; font-size:19px; color:#1e293b; } .re-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.re-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.re-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.re-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .re-msg.ok { background:#d1fae5; color:#065f46; } .re-msg.err { background:#fee2e2; color:#991b1b; }
.re-body { padding:16px 18px; }
.re-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.re-search { border:1px solid #d1d5db; border-radius:7px; padding:8px 12px; font-size:14px; min-width:240px; }
.re-mini { background:#eef2f7; color:#334155; border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .re-mini.g { background:#e2e8f0; }
.re-send { background:#1b4332; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:800; } .re-send:disabled { opacity:.5; cursor:default; }
.re-grid-wrap { max-height:380px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.re-grid { width:100%; border-collapse:collapse; font-size:12.5px; }
.re-grid th { position:sticky; top:0; background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11px; }
.re-grid td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; cursor:pointer; }
.re-grid tr:hover td { background:#f0faf4; } .re-grid tr.sel td { background:#fef9c3; }
.re-cod { color:#2d6a9f; font-weight:700; }
.re-emails { color:#475569; font-size:11.5px; max-width:340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.re-grid input[type=checkbox] { width:15px; height:15px; accent-color:#1b4332; }
.re-prev { margin-top:12px; border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fbff; }
.re-prev-tit { font-size:13px; font-weight:800; color:#14532d; margin-bottom:8px; }
.re-prev-info { color:#64748b; font-size:13px; }
.re-prev-row { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.re-tag { background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px; padding:2px 8px; font-size:12px; }
.re-no { color:#94a3b8; font-size:12px; }
.re-nota { font-size:12px; color:#64748b; margin:10px 2px 0; }
.re-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.re-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.re-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .re-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .re-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; } .re-help-md code { background:#eef2f7; padding:1px 5px; border-radius:4px; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
