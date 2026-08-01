<!-- ObrasView.vue — Obras de contratistas externos: ABM unificado (grilla + habilitar + editar).
     Reemplaza Obras - Habilitar / Modificar. Listados y Habilitados a Ingresar quedan aparte.
     (El backend no tiene baja de obras, por eso no hay 🗑️.) -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">🏗️</div>
      <div class="ab-tx"><h1>Obras de Contratistas</h1><p>Alta (habilitación) y edición de obras y sus obreros</p></div>
      <button class="ab-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/obras-habilitar" titulo="Asistente IA — Obras"
            subtitulo="Preguntá sobre la habilitación de obras"
            :sugerencias="['¿Qué datos necesito?','¿Qué significan las filas amarillas y rojas?','¿Qué es una obra en ejecución?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por obra o contratista…" />
      <select v-model="filtroEstado" class="ab-estado">
        <option value="">Todas</option>
        <option value="ejecucion">En ejecución</option>
        <option value="finalizada">Finalizadas</option>
      </select>
      <span class="ab-count">{{ filtradas.length }} obra(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nueva obra</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:60px">Nº</th>
            <th>Contratista</th>
            <th>Descripción</th>
            <th style="width:100px">Inicio</th>
            <th style="width:100px">Fin</th>
            <th style="width:60px" class="c">Obreros</th>
            <th style="width:110px" class="c">Estado</th>
            <th style="width:80px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="8" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="8" class="ab-vacio">No hay obras para mostrar.</td></tr>
          <tr v-for="o in filtradas" :key="o.cod" @dblclick="abrirEditar(o)">
            <td class="ab-nro">{{ o.cod }}</td>
            <td>{{ o.contratista }}</td>
            <td>{{ o.descripcion }}</td>
            <td>{{ fmt(o.inicio) }}</td>
            <td>{{ o.final ? fmt(o.final) : '—' }}</td>
            <td class="c">{{ o.empleados ? o.empleados.length : 0 }}</td>
            <td class="c"><span :class="['ab-chip', o.vigente ? 'green' : 'slate']">{{ o.vigente ? 'En ejecución' : 'Finalizada' }}</span></td>
            <td class="c ab-acc"><button class="ab-b edi" title="Editar" @click="abrirEditar(o)">✏️</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ─────────── Modal alta / edición ─────────── -->
    <Teleport to="body">
      <div v-if="modo" class="ab-ov" @click.self="cerrar">
        <div class="ab-md">
          <div class="ab-md-head"><span>{{ modo === 'nuevo' ? '🏗️ Nueva obra' : `✏️ Editar obra N° ${obraCod}` }}</span><button class="ab-x" @click="cerrar">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-fila-2">
              <div><label>Fecha de Inicio *</label><input v-model="fechaInicio" type="date" /></div>
              <div><label>Fecha de Finalización <small>(vacía = en curso)</small></label><input v-model="fechaFinal" type="date" /></div>
            </div>
            <div class="ab-fila"><label>Descripción *</label><input v-model="descripcion" type="text" maxlength="200" placeholder="Descripción de la obra" /></div>
            <div class="ab-fila"><label>Contratista *</label>
              <select v-model.number="contratista" @change="onContratista">
                <option :value="0">— Elegir contratista —</option>
                <option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option>
              </select>
            </div>
            <div class="ab-fila"><label>Detalles y Notas</label><textarea v-model="notas" rows="2"></textarea></div>

            <div v-if="contratista" class="ab-grid-cab">
              <span>Obreros del contratista habilitados para esta obra</span>
              <span class="ab-sel-info">{{ marcados.length }} seleccionado(s) · <i class="ab-lg amar"></i> ocupado · <i class="ab-lg rojo"></i> ART vencida</span>
            </div>
            <div v-if="contratista" class="ab-wrap">
              <table class="ab-og">
                <thead><tr><th style="width:34px"></th><th>Nombre</th><th>DNI</th><th>CUIL</th><th>Venc. ART</th></tr></thead>
                <tbody>
                  <tr v-for="e in empleados" :key="e.unico" :class="filaClase(e)">
                    <td class="c"><input v-model="e._sel" type="checkbox" /></td>
                    <td>{{ e.nombre }}</td><td>{{ e.dni }}</td><td>{{ e.cuil }}</td><td>{{ fmt(e.venc_art) }}</td>
                  </tr>
                  <tr v-if="!empleados.length"><td colspan="5" class="ab-vacio2">Este contratista no tiene empleados cargados.</td></tr>
                </tbody>
              </table>
            </div>
            <p v-if="formError" class="ab-md-err">⚠️ {{ formError }}</p>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span>
            <button class="ab-cancel" @click="cerrar">Cancelar</button>
            <button class="ab-confirm" :disabled="proc" @click="guardar">{{ proc ? '⟳ Guardando…' : (modo === 'nuevo' ? '🏗️ Confirmar obra' : '✔ Confirmar cambios') }}</button>
          </div>
        </div>
      </div>

      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — Obras</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista las obras; filtrá por estado o buscá por obra/contratista.</li>
            <li><b>＋ Nueva obra</b>: cargá fechas, descripción, contratista y marcá los <b>obreros</b> a habilitar.</li>
            <li>Filas <b>amarillas</b> = obrero ocupado en otra obra; <b>rojas</b> = ART vencida.</li>
            <li><b>✏️ Editar</b> permite cambiar datos y los obreros habilitados.</li>
          </ul>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-confirm" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

interface Emp { unico: number; cod: number; nombre: string; dni: string; cuil: string; venc_art: string; art_vencida: boolean; ocupado: boolean; _sel: boolean }

const hoy = new Date().toISOString().slice(0, 10)
const rows = ref<any[]>([]); const cargando = ref(false)
const filtroTxt = ref(''); const filtroEstado = ref('')
const modalIA = ref(false); const ayuda = ref(false)
const msg = ref(''); const msgErr = ref(false)

const modo = ref<'' | 'nuevo' | 'editar'>(''); const obraCod = ref(0)
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const contratista = ref(0)
const descripcion = ref(''); const fechaInicio = ref(hoy); const fechaFinal = ref(''); const notas = ref('')
const empleados = ref<Emp[]>([])
const proc = ref(false); const formError = ref('')

const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const marcados = computed(() => empleados.value.filter(e => e._sel))
const filaClase = (e: Emp) => e.art_vencida ? 'venc' : (e.ocupado ? 'ocu' : '')

const filtradas = computed(() => {
  const q = filtroTxt.value.trim().toLowerCase()
  return rows.value.filter(o => {
    if (filtroEstado.value === 'ejecucion' && !o.vigente) return false
    if (filtroEstado.value === 'finalizada' && o.vigente) return false
    if (!q) return true
    return String(o.cod).includes(q) || (o.descripcion || '').toLowerCase().includes(q) || (o.contratista || '').toLowerCase().includes(q)
  })
})

async function cargarGrilla () {
  cargando.value = true
  try { rows.value = (await api.get('/obras/listado', { params: { cuantas: 1, estado: 1, orden: 1 } })).data.obras ?? [] }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

onMounted(async () => {
  try { contratistas.value = (await api.get('/contratistas-externos')).data.map((c: any) => ({ cod: c.cod, nombre: c.nombre })) } catch { /* */ }
  cargarGrilla()
})

async function cargarEmpleadosContratista (marcadosPrev: Record<number, boolean> = {}) {
  empleados.value = []
  if (!contratista.value) return
  try {
    empleados.value = (await api.get(`/contratistas-externos/${contratista.value}/empleados`)).data
      .map((e: any) => ({ ...e, _sel: !!marcadosPrev[e.unico] }))
  } catch { flash('No se pudieron cargar los empleados.', true) }
}
const onContratista = () => cargarEmpleadosContratista()

function abrirNuevo () {
  modo.value = 'nuevo'; obraCod.value = 0
  descripcion.value = ''; fechaInicio.value = hoy; fechaFinal.value = ''; notas.value = ''; contratista.value = 0; empleados.value = []; formError.value = ''
}
async function abrirEditar (o: any) {
  formError.value = ''
  try {
    const { data } = await api.get(`/obras/${o.cod}`)
    obraCod.value = data.cod; descripcion.value = data.descripcion; fechaInicio.value = data.inicio || hoy; fechaFinal.value = data.final || ''
    notas.value = data.notas; contratista.value = data.contratista
    empleados.value = (data.empleados ?? []).map((e: any) => ({ ...e, _sel: !!e._sel }))
    modo.value = 'editar'
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo abrir la obra.', true) }
}
function cerrar () { modo.value = '' }

async function guardar () {
  formError.value = ''
  if (!descripcion.value.trim()) { formError.value = 'No puede habilitar una obra sin descripción.'; return }
  if (!contratista.value) { formError.value = 'Elegí el contratista.'; return }
  if (!marcados.value.length) { formError.value = 'Al menos un obrero debe trabajar en la obra.'; return }
  if (!confirm(modo.value === 'nuevo' ? '¿Confirmar habilitación de obra?' : '¿Confirmar los cambios de la obra?')) return
  proc.value = true
  const payload = {
    descripcion: descripcion.value, fecha_inicio: fechaInicio.value, fecha_final: fechaFinal.value || null,
    contratista: contratista.value, notas: notas.value, empleados: marcados.value.map(e => e.unico),
  }
  try {
    if (modo.value === 'nuevo') { const { data } = await api.post('/obras', payload); flash(`Obra N° ${data.cod} habilitada con ${marcados.value.length} obrero(s).`) }
    else { await api.put(`/obras/${obraCod.value}`, payload); flash('Obra modificada correctamente.') }
    await cargarGrilla(); cerrar()
  } catch (e: any) { formError.value = e?.response?.data?.message ?? 'No se pudo guardar la obra.' }
  finally { proc.value = false }
}
</script>

<style scoped>
.ab-view { display:flex; flex-direction:column; min-height:100%; }
.ab-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ab-ico { font-size:28px; } .ab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ab-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ab-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ab-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ab-msg.ok { background:#d1fae5; color:#065f46; } .ab-msg.err { background:#fee2e2; color:#991b1b; }
.ab-tools { display:flex; align-items:center; gap:10px; padding:14px 18px 8px; flex-wrap:wrap; }
.ab-search { flex:1; min-width:220px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-estado { border:1px solid #c8d8ea; border-radius:8px; padding:9px 10px; font-size:13px; color:#1e293b; background:#fff; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#16a34a; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-nro { font-weight:800; color:#16a34a; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-chip { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; } .ab-chip.green { background:#d1fae5; color:#065f46; } .ab-chip.slate { background:#e2e8f0; color:#334155; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; } .ab-b.edi:hover { background:#e0eefc; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(760px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-fila { margin-bottom:12px; } .ab-fila-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px; }
.ab-md-body label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; } .ab-md-body label small { color:#94a3b8; font-weight:400; }
.ab-md-body input, .ab-md-body select, .ab-md-body textarea { width:100%; border:1px solid #cbd5e1; border-radius:7px; padding:8px 10px; font-size:14px; color:#1e293b; box-sizing:border-box; font-family:inherit; }
.ab-grid-cab { display:flex; align-items:center; justify-content:space-between; font-size:12.5px; color:#1b4332; font-weight:600; margin:6px 0 8px; flex-wrap:wrap; gap:6px; }
.ab-sel-info { font-weight:400; color:#6b7280; display:flex; align-items:center; gap:5px; }
.ab-lg { display:inline-block; width:12px; height:12px; border-radius:3px; } .ab-lg.amar { background:#fef9c3; border:1px solid #facc15; } .ab-lg.rojo { background:#fecaca; border:1px solid #f87171; }
.ab-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:40vh; margin-bottom:8px; }
.ab-og { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.ab-og th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 10px; font-size:12px; text-align:left; } .ab-og th.c, .ab-og td.c { text-align:center; }
.ab-og td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ab-og tr.venc td { background:#fee2e2; } .ab-og tr.ocu td { background:#fef9c3; }
.ab-vacio2 { padding:20px; text-align:center; color:#9ca3af; }
.ab-md-err { color:#b91c1c; font-size:13px; margin:4px 0 0; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
