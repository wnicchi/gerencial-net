<!-- CapacitacionDocView.vue — Documentación asociada a la capacitación (capacitacion_documentacion.scx). -->
<template>
  <div class="cd-view">
    <div class="cd-cab">
      <div class="cd-ico">📎</div>
      <div class="cd-tx"><h1>Documentación de la Capacitación</h1><p>Adjuntar documentos y asociarlos a los empleados</p></div>
      <button class="cd-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cd-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/cap-doc" titulo="Asistente IA — Documentación de Capacitación"
            subtitulo="Preguntá sobre la documentación de la jornada"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo asocio un documento?','¿A quiénes se asocia?','¿Qué archivos no se aceptan?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['cd-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="cd-body">
      <div class="cd-sel">
        <label>Jornada de capacitación</label>
        <div class="cd-sel-row">
          <input :value="cab ? `${cab.cod} — ${cab.capacitacion}` : ''" readonly placeholder="Elegí una jornada con la lupa →" class="cd-sel-input" @click="abrirModalCap" />
          <button class="cd-lupa" @click="abrirModalCap">🔍 Buscar jornada</button>
        </div>
      </div>

      <template v-if="cab">
        <div class="cd-jornada">
          <div class="jr-cod">Capacitación #{{ cab.cod }}</div>
          <div class="jr-nom">{{ cab.capacitacion }}</div>
          <div class="jr-meta"><span v-if="cab.fecha">📅 {{ fmt(cab.fecha) }}</span><span v-if="cab.tematica">🏷 {{ cab.tematica }}</span></div>
        </div>

        <div class="cd-cols">
          <!-- Carga de documento -->
          <div class="cd-left">
            <div class="cd-panel">
              <h3>Adjuntar documento</h3>
              <div class="cc"><label>Tipo de documento</label>
                <select v-model="tipo"><option value="">— Seleccione —</option><option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
              </div>
              <div class="cc"><label>Fecha del documento</label><input v-model="fecha" type="date" /></div>
              <div class="cc"><label>Observación</label><input v-model="observacion" maxlength="60" placeholder="Opcional…" /></div>
              <div class="cc"><label>Archivo</label><input ref="fileInput" type="file" @change="onFile" /></div>
              <p class="cd-nota">No se aceptan exe, bat, dll, zip, rar, cmd ni cab. Máximo 50 MB.</p>
              <button class="btn ok" :disabled="proc" @click="subir">{{ proc ? '⟳ Subiendo…' : '⬆ Asociar documento' }}</button>
            </div>
          </div>

          <!-- Cursantes -->
          <div class="cd-right">
            <div class="cd-rh">Marcar empleados a asociar
              <span class="cd-mini-acc"><button class="btn mini" @click="marcarTodos(true)">Todos</button><button class="btn mini g" @click="marcarTodos(false)">Nada</button></span>
            </div>
            <table class="cd-tabla">
              <thead><tr><th class="c" style="width:36px">OK</th><th style="width:64px">Código</th><th>Nombre</th><th style="width:130px">Cuil</th></tr></thead>
              <tbody>
                <tr v-for="(a, i) in alumnos" :key="i" :class="{ sel: a.sel, inactivo: !a.activo }">
                  <td class="c"><input type="checkbox" v-model="a.sel" /></td>
                  <td class="cd-cod">{{ a.codigo }}</td><td>{{ a.nombre }}</td><td>{{ a.cuil }}</td>
                </tr>
                <tr v-if="!alumnos.length"><td colspan="4" class="cd-vacio">La capacitación no tiene empleados asignados.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Documentos asociados -->
        <div class="cd-docs">
          <h3>Documentos asociados ({{ documentos.length }})</h3>
          <table v-if="documentos.length" class="cd-tabla">
            <thead><tr><th>Tipo</th><th>Archivo</th><th style="width:100px">Fecha</th><th>Observación</th><th class="c" style="width:80px">Empleados</th><th style="width:120px;text-align:center">Acciones</th></tr></thead>
            <tbody>
              <tr v-for="d in documentos" :key="d.unico">
                <td>{{ d.detalle_tipo || d.tipo }}</td><td>{{ d.nombre }}.{{ d.ext.toLowerCase() }}</td>
                <td>{{ fmt(d.fecha) }}</td><td class="cd-obs">{{ d.observaciones }}</td>
                <td class="c">{{ d.empleados }}</td>
                <td style="text-align:center"><button class="cd-i" title="Ver" @click="ver(d)">👁️</button><button class="cd-i del" title="Eliminar" @click="eliminar(d)">🗑️</button></td>
              </tr>
            </tbody>
          </table>
          <div v-else class="cd-vacio">Esta capacitación todavía no tiene documentos.</div>
        </div>
      </template>
    </div>

    <!-- Modal búsqueda jornada -->
    <Teleport to="body">
      <div v-if="modalCap" class="cd-ov" @click.self="modalCap = false">
        <div class="cd-busca-md">
          <h3>🔍 Buscar jornada de capacitación</h3>
          <input ref="inputCap" v-model="busqCap" placeholder="Código o nombre de la capacitación…" @input="buscarCap" />
          <ul class="cd-busca-list">
            <li v-for="c in resCap" :key="c.cod" @click="elegirCap(c)"><div class="bl-top"><b>{{ c.cod }}</b> — {{ c.capacitacion }}</div><span v-if="c.tematica">{{ c.tematica }}</span></li>
            <li v-if="!resCap.length" class="cd-busca-vacio">{{ busqCap.trim().length < 1 ? 'Escribí para buscar…' : 'Sin resultados' }}</li>
          </ul>
          <div class="cd-rh end"><button class="btn mini" @click="modalCap = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="ayuda" class="cd-ov" @click.self="ayuda = false">
        <div class="cd-busca-md">
          <h3>❓ Ayuda — Documentación de la Capacitación</h3>
          <ul class="cd-help">
            <li>Elegí la jornada con la lupa.</li>
            <li>Tildá los empleados a los que aplica el documento.</li>
            <li>Elegí tipo, fecha, una observación opcional y el archivo; luego <b>Asociar documento</b>.</li>
            <li>Los documentos cargados se listan abajo: podés <b>verlos</b> 👁️ o <b>eliminarlos</b> 🗑️.</li>
          </ul>
          <div class="cd-rh end"><button class="btn ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="docVisor" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

interface Alumno { sel: boolean; codigo: number; nombre: string; cuil: string; activo: boolean }
const tipos = ref<{ cod: string; nombre: string }[]>([])
const cab = ref<any>(null); const alumnos = ref<Alumno[]>([]); const documentos = ref<any[]>([])
const tipo = ref(''); const fecha = ref(new Date().toISOString().slice(0, 10)); const observacion = ref('')
const fileInput = ref<HTMLInputElement | null>(null); let archivo: File | null = null
const proc = ref(false); const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const fmt = (f: string) => f ? f.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4500) }

onMounted(async () => {
  try { tipos.value = (await api.get('/cap-doc/init')).data.tipos ?? [] } catch { flash('No se pudieron cargar los tipos de documento.', true) }
})

// Modal jornada
const modalCap = ref(false); const inputCap = ref<HTMLInputElement | null>(null)
const busqCap = ref(''); const resCap = ref<any[]>([]); let dc: any = null
async function abrirModalCap () { modalCap.value = true; await nextTick(); inputCap.value?.focus() }
const buscarCap = () => {
  clearTimeout(dc); const q = busqCap.value.trim()
  if (q.length < 1) { resCap.value = []; return }
  dc = setTimeout(async () => { try { resCap.value = ((await api.get('/capacitaciones', { params: { buscar: q } })).data ?? []).slice(0, 20) } catch { resCap.value = [] } }, 250)
}
async function elegirCap (c: any) { modalCap.value = false; resCap.value = []; busqCap.value = ''; await cargar(c.cod) }
async function cargar (cod: number) {
  try {
    const { data } = await api.get(`/cap-doc/capacitacion/${cod}`)
    cab.value = data.cabecera; alumnos.value = (data.alumnos ?? []).map((x: any) => ({ ...x, sel: false })); documentos.value = data.documentos ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la capacitación.', true) }
}

const marcarTodos = (v: boolean) => alumnos.value.forEach(a => a.sel = v)
const onFile = (e: Event) => { archivo = (e.target as HTMLInputElement).files?.[0] ?? null }

async function subir () {
  if (!tipo.value) { flash('Seleccione el tipo de documento.', true); return }
  if (!fecha.value) { flash('Indique la fecha del documento.', true); return }
  if (!archivo) { flash('Seleccione un archivo.', true); return }
  const sel = alumnos.value.filter(a => a.sel)
  if (!sel.length) { flash('Seleccione al menos un empleado para asociar el documento.', true); return }
  const fd = new FormData()
  fd.append('tipo', tipo.value); fd.append('fecha', fecha.value); fd.append('observaciones', observacion.value)
  fd.append('archivo', archivo)
  for (const a of sel) fd.append('empleados[]', String(a.codigo))
  proc.value = true
  try {
    const { data } = await api.post(`/cap-doc/${cab.value.cod}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    documentos.value = data.documentos ?? []
    flash(`Documento asociado a ${sel.length} empleado(s).`)
    tipo.value = ''; observacion.value = ''; archivo = null; if (fileInput.value) fileInput.value.value = ''
    marcarTodos(false)
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo subir el documento.', true) }
  finally { proc.value = false }
}

// Ver / eliminar
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)
async function ver (d: any) {
  try {
    const resp = await api.get(`/cap-doc/documento/${d.unico}/ver`, { responseType: 'blob' })
    docVisor.value?.open(resp.data as Blob, `${d.nombre}.${(d.ext || '').toLowerCase()}`)
  } catch { flash('No se pudo abrir el documento.', true) }
}
async function eliminar (d: any) {
  if (!confirm(`¿Eliminar el documento "${d.nombre}.${(d.ext || '').toLowerCase()}"? Se quitará para todos los empleados.`)) return
  try { const { data } = await api.delete(`/cap-doc/documento/${d.unico}`); documentos.value = data.documentos ?? []; flash('Documento eliminado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
</script>

<style scoped>
.cd-view { display:flex; flex-direction:column; min-height:100%; }
.cd-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cd-ico { font-size:28px; } .cd-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cd-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cd-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cd-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cd-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cd-msg.ok { background:#d1fae5; color:#065f46; } .cd-msg.err { background:#fee2e2; color:#991b1b; }
.cd-body { padding:16px 18px; }
.cd-sel { display:flex; flex-direction:column; gap:5px; max-width:680px; } .cd-sel label { font-size:12px; font-weight:600; color:#374151; }
.cd-sel-row { display:flex; gap:8px; } .cd-sel-input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; background:#fff; color:#1e293b; cursor:pointer; }
.cd-lupa { border:none; background:#2d6a9f; color:#fff; border-radius:6px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
.cd-jornada { background:#eef6ef; border:1px solid #c3e6cb; border-radius:10px; padding:10px 14px; margin-top:14px; }
.jr-cod { font-size:11px; color:#1b7a4b; font-weight:700; } .jr-nom { font-size:15px; font-weight:700; color:#14532d; margin:2px 0 4px; } .jr-meta { display:flex; gap:12px; font-size:12px; color:#475569; }
.cd-cols { display:flex; gap:16px; margin-top:14px; align-items:flex-start; flex-wrap:wrap; }
.cd-left { flex:1; min-width:320px; max-width:420px; } .cd-right { flex:1.3; min-width:380px; }
.cd-panel { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; } .cd-panel h3 { margin:0 0 12px; font-size:14px; color:#2a4a6a; }
.cc { display:flex; flex-direction:column; gap:4px; margin-bottom:10px; } .cc label { font-size:12px; font-weight:600; color:#374151; }
.cc select, .cc input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; box-sizing:border-box; }
.cd-nota { font-size:11px; color:#94a3b8; margin:2px 0 10px; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; } .btn.ok { background:#1b4332; color:#fff; width:100%; } .btn.mini { background:#eef2f7; color:#334155; padding:5px 11px; font-size:12px; } .btn.mini.g { background:#e2e8f0; } .btn:disabled { opacity:.5; cursor:default; }
.cd-rh { font-weight:700; color:#2a4a6a; font-size:13px; margin-bottom:8px; display:flex; align-items:center; gap:10px; } .cd-rh.end { justify-content:flex-end; } .cd-mini-acc { display:flex; gap:6px; }
.cd-tabla { width:100%; border-collapse:collapse; font-size:13px; border:1px solid #e2e8f0; }
.cd-tabla th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11.5px; } .cd-tabla th.c { text-align:center; }
.cd-tabla td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .cd-tabla td.c { text-align:center; }
.cd-tabla tr.sel td { background:#fffbe6; } .cd-tabla tr.inactivo td { background:#fee2e2; color:#991b1b; } .cd-cod { color:#2d6a9f; font-weight:700; } .cd-obs { color:#64748b; font-size:12px; }
.cd-tabla input[type=checkbox] { width:15px; height:15px; accent-color:#2d6a9f; }
.cd-vacio { text-align:center; color:#94a3b8; padding:16px; border:1px dashed #e2e8f0; border-radius:8px; }
.cd-docs { margin-top:18px; } .cd-docs h3 { font-size:14px; color:#2a4a6a; margin:0 0 8px; }
.cd-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; } .cd-i.del:hover { filter:saturate(2); }
.cd-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.cd-busca-md { background:#fff; border-radius:14px; padding:20px; width:min(560px,94vw); } .cd-busca-md h3 { margin:0 0 12px; color:#1a3a5c; font-size:16px; }
.cd-busca-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; }
.cd-busca-list { list-style:none; margin:12px 0; padding:0; max-height:380px; overflow:auto; border:1px solid #eef2f7; border-radius:8px; }
.cd-busca-list li { padding:9px 12px; cursor:pointer; border-bottom:1px solid #f4f7fc; color:#1e293b; } .cd-busca-list li:hover { background:#f0faf4; }
.cd-busca-list .bl-top { font-size:13px; } .cd-busca-list li b { color:#2d6a9f; } .cd-busca-list li span { display:block; font-size:11px; color:#6b7280; margin-top:1px; }
.cd-busca-vacio { color:#94a3b8; cursor:default !important; } .cd-busca-vacio:hover { background:none !important; }
.cd-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
.cd-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cd-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.cd-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .cd-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cd-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cd-pdf-b.ok { background:#22c55e; color:#fff; } .cd-pdf-b.cancel { background:#ef4444; color:#fff; }
.cd-pdf-frame { flex:1; border:none; width:100%; }
.cd-doc-scroll { flex:1; overflow:auto; background:#f1f5f9; padding:16px; }
.cd-xls :deep(table) { border-collapse:collapse; background:#fff; font-size:12.5px; margin-bottom:18px; box-shadow:0 1px 4px rgba(0,0,0,.1); }
.cd-xls :deep(td), .cd-xls :deep(th) { border:1px solid #cbd5e1; padding:3px 8px; color:#1e293b; white-space:nowrap; }
.cd-xls :deep(.xls-h) { font-weight:700; color:#1b4332; margin:4px 0 6px; font-size:13px; }
.cd-doc-scroll :deep(.docx-wrapper) { background:#f1f5f9; padding:0; }
.cd-doc-scroll :deep(.docx) { background:#fff; box-shadow:0 1px 8px rgba(0,0,0,.15); margin:0 auto; }
</style>
