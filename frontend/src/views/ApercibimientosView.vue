<!-- ApercibimientosView.vue — Apercibimientos: ABM unificado (grilla + alta + imprimir + borrar).
     Reemplaza los módulos separados Crear / Consultar.
     Preparado para abrirse desde la ficha del empleado (prop :empleado) — Propuesta "módulo madre". -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">⚡</div>
      <div class="ab-tx"><h1>Apercibimientos</h1><p>Alta, consulta, impresión y baja de apercibimientos</p></div>
      <button class="ab-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/apercibimiento-consultar" titulo="Asistente IA — Apercibimientos"
            subtitulo="Preguntá sobre el módulo de apercibimientos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo cargo uno?','¿Puedo cargar fecha futura?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-if="!empBloqueado" v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por empleado u observación…" />
      <span class="ab-count">{{ filtradas.length }} apercibimiento(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo apercibimiento</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:110px">Fecha</th>
            <th style="width:260px">Empleado</th>
            <th>Observación</th>
            <th style="width:130px">Usuario</th>
            <th style="width:100px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="5" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="5" class="ab-vacio">No hay apercibimientos para mostrar.</td></tr>
          <tr v-for="(a, i) in filtradas" :key="i">
            <td>{{ fmt(a.fecha) }}</td>
            <td>{{ a.empleado }} — {{ a.empleado_nombre }}</td>
            <td class="ab-obs">{{ a.observacion }}</td>
            <td>{{ a.usuario }}</td>
            <td class="c ab-acc">
              <button class="ab-b imp" title="Imprimir comprobante" @click="imprimir(a)">🖨️</button>
              <button class="ab-b del" title="Borrar" @click="borrar(a)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ─────────── Modal alta ─────────── -->
    <Teleport to="body">
      <div v-if="modalAlta" class="ab-ov" @click.self="cerrarAlta">
        <div class="ab-md">
          <div class="ab-md-head"><span>＋ Nuevo apercibimiento</span><button class="ab-x" @click="cerrarAlta">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-emp">
              <div class="ab-emp-datos">
                <label>Nro. Personal</label>
                <EmpleadoInput :codigo="datos ? datos.codigo : 0" :nombre="datos ? datos.nombre : ''"
                               :disabled="empBloqueado" @select="onLupaEmp" />
              </div>
              <div class="ab-foto"><img v-if="empFoto" :src="empFoto" /><div v-else class="ab-foto-ph">👤</div></div>
            </div>

            <template v-if="datos">
              <div class="ab-campo"><label>Fecha Apercibido</label><input v-model="fecha" type="date" :max="hoyStr" /></div>
              <label class="ab-lbl">Razón del Apercibimiento</label>
              <textarea v-model="razon" rows="7" placeholder="Detalle de la razón…"></textarea>
            </template>
            <div v-else class="ab-elija">Elegí el empleado para cargar el apercibimiento.</div>
          </div>
          <div class="ab-md-foot">
            <button v-if="datos" class="ab-imp2" :disabled="!razon.trim()" @click="imprimirActual">🖨️ Imprimir</button>
            <span style="flex:1"></span>
            <button class="ab-cancel" @click="cerrarAlta">Cancelar</button>
            <button class="ab-confirm" :disabled="grabando || !datos" @click="confirmar">{{ grabando ? '⟳ Grabando…' : '✔ Confirmar' }}</button>
          </div>
        </div>
      </div>

      <!-- Ayuda -->
      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — Apercibimientos</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista todos los apercibimientos. Buscá por empleado u observación.</li>
            <li><b>＋ Nuevo</b>: elegí el empleado, la fecha (no futura) y la razón. Al confirmar se guarda y se ofrece el comprobante.</li>
            <li>En cada fila: <b>🖨️ Imprimir</b> el comprobante y <b>🗑️ Borrar</b>.</li>
          </ul>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-confirm" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>

      <!-- PDF -->
      <div v-if="pdfUrl" class="ab-pdf-ov" @click.self="cerrarPdf">
        <div class="ab-pdf-md">
          <div class="ab-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ab-pdf-acc">
              <button class="ab-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ab-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ab-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ab-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { comprobanteApercibimiento } from '@/utils/apercibimiento'
import ChatIA from '@/components/ChatIA.vue'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const hoy = new Date(); const hoyStr = hoy.toISOString().slice(0, 10)
const rows = ref<any[]>([]); const cargando = ref(false); const filtroTxt = ref('')
const modalAlta = ref(false); const ayuda = ref(false); const modalIA = ref(false)
const datos = ref<any>(null); const empFoto = ref(''); const fecha = ref(hoyStr); const razon = ref('')
const grabando = ref(false)
const msg = ref(''); const msgErr = ref(false)

const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

const filtradas = computed(() => {
  const q = filtroTxt.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter(a => String(a.empleado).includes(q) || (a.empleado_nombre || '').toLowerCase().includes(q) || (a.observacion || '').toLowerCase().includes(q))
})

async function cargarGrilla () {
  cargando.value = true
  try {
    const params: any = {}
    if (props.empleado) params.emp = props.empleado
    rows.value = (await api.get('/apercibimientos/grilla', { params })).data ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

// ── Alta ──
function abrirNuevo () {
  datos.value = null; empFoto.value = ''; razon.value = ''; fecha.value = hoyStr
  modalAlta.value = true
  if (props.empleado) cargarEmpleado(props.empleado)
}
function cerrarAlta () { modalAlta.value = false }

const onLupaEmp = (r: any) => cargarEmpleado(Number(r.cod ?? r.PER_COD))
async function cargarEmpleado (cod: number) {
  datos.value = null; empFoto.value = ''
  try {
    const { data } = await api.get(`/apercibimientos/empleado/${cod}`)
    datos.value = data
    try { const f = await api.get(`/empleados/${cod}/foto`); empFoto.value = f.data?.foto || '' } catch { /* */ }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'Código de empleado inexistente.', true) }
}

async function confirmar () {
  if (!datos.value) { flash('Ingrese el empleado.', true); return }
  if (fecha.value > hoyStr) { flash('No puede ingresar un apercibimiento a futuro.', true); return }
  if (!razon.value.trim()) { flash('Debe ingresar la razón del apercibimiento.', true); return }
  if (!confirm('¿Acepta el apercibimiento?')) return
  grabando.value = true
  try {
    await api.post('/apercibimientos', { empleado: datos.value.codigo, fecha: fecha.value, razon: razon.value })
    generarPdf(datos.value, fecha.value, razon.value)
    modalAlta.value = false
    await cargarGrilla()
    flash('Apercibimiento guardado.')
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar el apercibimiento.', true) }
  finally { grabando.value = false }
}

// ── Borrar ──
async function borrar (a: any) {
  if (!confirm(`¿Borrar el apercibimiento de ${a.empleado_nombre} del ${fmt(a.fecha)}?`)) return
  try { await api.delete('/apercibimientos', { data: { empleado: a.empleado, fecha: a.fecha } }); flash('Apercibimiento borrado.'); await cargarGrilla() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
}

// ── Imprimir ──
const imprimirActual = () => generarPdf(datos.value, fecha.value, razon.value)
async function imprimir (a: any) {
  try {
    const { data } = await api.get(`/apercibimientos/empleado/${a.empleado}`)
    generarPdf(data, a.fecha, a.observacion)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el comprobante.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPdf (dat: any, fec: string, obs: string) {
  if (!dat) return
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  comprobanteApercibimiento(doc, dat, fec, obs)
  cerrarPdf(); pdfNombre.value = `Apercibimiento_${dat.codigo}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

cargarGrilla()
</script>

<style scoped>
.ab-view { display:flex; flex-direction:column; min-height:100%; }
.ab-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ab-ico { font-size:28px; } .ab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ab-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ab-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ab-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ab-msg.ok { background:#d1fae5; color:#065f46; } .ab-msg.err { background:#fee2e2; color:#991b1b; }
.ab-tools { display:flex; align-items:center; gap:10px; padding:14px 18px 8px; flex-wrap:wrap; }
.ab-search { flex:1; min-width:240px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#1b4332; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#0a7d32; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-obs { white-space:pre-wrap; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; }
.ab-b.del:hover { background:#fee2e2; } .ab-b.imp:hover { background:#e0eefc; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(720px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-emp { display:flex; gap:16px; align-items:flex-start; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fafdff; }
.ab-emp-datos { flex:1; } .ab-emp-datos label { font-size:12px; font-weight:700; color:#374151; }
.ab-emp-row { display:flex; gap:10px; margin-top:5px; }
.ab-emp-row input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:15px; color:#1e293b; }
.ab-emp-row input[type=text]:first-child { width:110px; font-weight:800; } .ab-nom { flex:1; background:#f1f5f9; }
.ab-lupa { background:#394959; color:#fff; border:none; padding:9px 13px; border-radius:7px; cursor:pointer; font-size:14px; }
.ab-foto { width:96px; height:78px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#eef2f7; display:flex; align-items:center; justify-content:center; }
.ab-foto img { width:100%; height:100%; object-fit:cover; } .ab-foto-ph { font-size:32px; color:#94a3b8; }
.ab-campo { margin-top:14px; } .ab-campo label { font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px; }
.ab-campo input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; color:#1e293b; }
.ab-lbl { display:block; font-size:12px; font-weight:700; color:#374151; margin:14px 0 4px; }
.ab-md-body textarea { width:100%; box-sizing:border-box; border:1px solid #c8d8ea; border-radius:8px; padding:10px 12px; font-size:14px; color:#1e293b; font-family:inherit; resize:vertical; }
.ab-elija { text-align:center; color:#94a3b8; padding:22px; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-imp2 { background:#f1f5f9; color:#334155; border:none; border-radius:8px; padding:10px 16px; cursor:pointer; font-weight:700; font-size:13px; } .ab-imp2:disabled { opacity:.5; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#1b4332; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(520px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ab-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ab-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ab-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ab-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ab-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ab-pdf-b.ok { background:#22c55e; color:#fff; } .ab-pdf-b.cancel { background:#ef4444; color:#fff; }
.ab-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
