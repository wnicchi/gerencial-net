<!-- ExamenesView.vue — Control de Salud · Exámenes: ABM unificado (grilla + alta + ver/editar + documentos + eliminar).
     Reemplaza Exámenes Agregar / Modificar / Eliminar / Consultar Empleados.
     La variante "Exámenes Médicos Agregar" (CIE10 + multi-doc) queda como acceso aparte.
     Preparado para abrirse desde la ficha del empleado (prop :empleado) — Propuesta "módulo madre". -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">🏥</div>
      <div class="ab-tx"><h1>Control de Salud — Exámenes</h1><p>Alta, consulta, edición y baja de exámenes médicos y su documentación</p></div>
      <button class="ab-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/examenes" titulo="Asistente IA — Exámenes Médicos"
            subtitulo="Preguntá sobre el módulo de exámenes"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cuándo es obligatorio el próximo examen?','¿Cómo adjunto un documento?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-if="!empBloqueado" v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por empleado, tipo de examen o médico…" />
      <select v-model="filtroCoe" class="ab-estado">
        <option value="">Exámenes y certificados</option>
        <option value="E">Solo exámenes</option>
        <option value="C">Solo certificados</option>
      </select>
      <span class="ab-count">{{ filtradas.length }} registro(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo examen</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:230px">Empleado</th>
            <th>Tipo de examen</th>
            <th style="width:100px">Fecha</th>
            <th style="width:100px">Próximo</th>
            <th style="width:160px">Médico</th>
            <th style="width:60px" class="c">E/C</th>
            <th style="width:120px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="7" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="7" class="ab-vacio">No hay exámenes para mostrar.</td></tr>
          <tr v-for="e in filtradas" :key="e.unico" @dblclick="abrirEditar(e)">
            <td>{{ e.empleado }} — {{ e.empleado_nombre }}</td>
            <td>{{ e.tipo_det }}</td>
            <td>{{ fmt(e.fecha) }}</td>
            <td :class="{ 'ab-venc': vencido(e.proximo) }">{{ fmt(e.proximo) }}</td>
            <td>{{ e.medico }}</td>
            <td class="c"><span :class="['ab-badge', e.coe === 'C' ? 'c' : 'e']">{{ e.coe === 'C' ? 'CERT' : 'EXAM' }}</span></td>
            <td class="c ab-acc">
              <button class="ab-b ver" title="Ver"      @click="abrirVer(e)">👁️</button>
              <button class="ab-b edi" title="Editar"   @click="abrirEditar(e)">✏️</button>
              <button class="ab-b del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ─────────── Modal detalle (nuevo / ver / editar) ─────────── -->
    <Teleport to="body">
      <div v-if="modo" class="ab-ov" @click.self="cerrar">
        <div class="ab-md">
          <div class="ab-md-head">
            <span>{{ modo === 'nuevo' ? '＋ Nuevo examen' : modo === 'ver' ? '👁️ Examen' : '✏️ Editar examen' }}</span>
            <button class="ab-x" @click="cerrar">✕</button>
          </div>

          <div class="ab-md-body">
            <!-- Empleado -->
            <div class="ab-top">
              <div class="campo" style="flex:1">
                <label>Empleado</label>
                <EmpleadoInput v-if="modo === 'nuevo' && !empBloqueado" :codigo="empCod" :nombre="empNombre" @select="onSelEmp" />
                <input v-else :value="`${empCod} — ${empNombre}`" type="text" readonly />
              </div>
              <div class="ab-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl=''" /><div v-else class="ab-foto-ph">Sin foto</div></div>
            </div>

            <div class="ab-form">
              <div class="campo span2" v-if="modo === 'nuevo'"><label>Médico responsable *</label>
                <select v-model.number="f.medico_cod"><option :value="0">— Seleccione —</option><option v-for="m in medicos" :key="m.cod" :value="m.cod">{{ m.nombre }}</option></select>
              </div>
              <div class="campo span2" v-else><label>Médico responsable</label><input :value="f.medico" type="text" readonly /></div>

              <div class="campo span2"><label>Tipo de documento</label>
                <div class="ab-radios">
                  <label class="rad"><input type="radio" value="E" v-model="f.coe" :disabled="ro" /> Examen</label>
                  <label class="rad"><input type="radio" value="C" v-model="f.coe" :disabled="ro" /> Certificado</label>
                </div>
              </div>
              <div class="campo"><label>Fecha del examen *</label><input v-model="f.fecha" type="date" :disabled="ro" /></div>
              <div class="campo"><label>Próximo examen <span v-if="f.coe==='E'" class="oblig">*</span></label><input v-model="f.proximo" type="date" :disabled="ro || f.coe!=='E'" /></div>
              <div class="campo span2"><label>Tipo de examen *</label>
                <select v-model.number="f.tipo_cod" :disabled="ro"><option :value="0">— Seleccione —</option><option v-for="t in tiposExamen" :key="t.cod" :value="t.cod">{{ t.nombre }}</option></select>
              </div>
              <div class="campo span2"><label>Notas médicas</label><textarea v-model="f.notas" rows="3" maxlength="2000" :disabled="ro"></textarea></div>
            </div>

            <!-- Documento opcional en el alta -->
            <div v-if="modo === 'nuevo'" class="ab-doc">
              <div class="ab-doc-h"><b>Agregar documento</b> <span>(opcional)</span></div>
              <div class="ab-doc-grid">
                <div class="campo"><label>Tipo de documento</label>
                  <select v-model="docTipo"><option value="">— Sin documento —</option><option v-for="t in tiposDoc" :key="t.cod" :value="t.cod">{{ t.cod }} — {{ t.nombre }}</option></select>
                </div>
                <div class="campo"><label>Fecha del documento</label><input v-model="docFecha" type="date" :disabled="!docTipo" /></div>
                <div class="campo span2"><label>Archivo</label>
                  <div class="ab-file"><input ref="fileInput" type="file" :disabled="!docTipo" @change="onFile" /><button v-if="archNom" type="button" class="ab-ver" @click="verArchivoLocal">👁️ Ver</button></div>
                </div>
                <div class="campo span2"><label>Observación</label><input v-model="docObs" maxlength="60" :disabled="!docTipo" placeholder="Opcional…" /></div>
              </div>
              <p v-if="docTipo" class="ab-nota">No se aceptan exe, bat, dll, zip, rar, cmd ni cab. Máximo 50 MB.</p>
            </div>

            <!-- Documentos del examen (ver/editar) -->
            <div v-if="modo !== 'nuevo'" class="ab-doc">
              <div class="ab-doc-h"><b>Documentos digitales</b> <span>({{ documentos.length }})</span></div>
              <table v-if="documentos.length" class="ab-dt">
                <thead><tr><th style="width:50px">Cód.</th><th>Tipo</th><th>Nombre</th><th style="width:55px">Ext.</th><th style="width:95px">Fecha</th><th>Obs.</th><th style="width:74px">Acc.</th></tr></thead>
                <tbody>
                  <tr v-for="d in documentos" :key="d.id">
                    <td class="ab-cod">{{ d.tipo }}</td><td>{{ d.detalle }}</td><td>{{ d.nombre }}</td><td>{{ d.ext }}</td><td>{{ fmt(d.fecha) }}</td><td class="ab-obs">{{ d.observaciones }}</td>
                    <td><button class="ab-i" title="Ver" @click="verDoc(d)">👁️</button><button v-if="!ro" class="ab-i" title="Eliminar" @click="eliminarDoc(d)">🗑️</button></td>
                  </tr>
                </tbody>
              </table>
              <div v-else class="ab-vacio2">Este examen no tiene documentos.</div>

              <div v-if="!ro" class="ab-doc-grid" style="margin-top:10px">
                <div class="campo"><label>Agregar — Tipo</label>
                  <select v-model="docTipo"><option value="">— —</option><option v-for="t in tiposDoc" :key="t.cod" :value="t.cod">{{ t.cod }} — {{ t.nombre }}</option></select>
                </div>
                <div class="campo"><label>Fecha del documento</label><input v-model="docFecha" type="date" :disabled="!docTipo" /></div>
                <div class="campo span2"><label>Archivo</label><div class="ab-file"><input ref="fileInput" type="file" :disabled="!docTipo" @change="onFile" /><button v-if="archNom" type="button" class="ab-ver" @click="verArchivoLocal">👁️ Ver</button></div></div>
                <div class="campo span2"><label>Observación</label><input v-model="docObs" maxlength="60" :disabled="!docTipo" placeholder="Opcional…" /></div>
                <div class="campo span2"><button class="ab-adddoc" :disabled="docProc" @click="agregarDocExistente">➕ {{ docProc ? 'Subiendo…' : 'Agregar documento' }}</button></div>
              </div>
            </div>

            <p v-if="formError" class="ab-md-err">{{ formError }}</p>
          </div>

          <div class="ab-md-foot">
            <span style="flex:1"></span>
            <button class="ab-cancel" @click="cerrar">{{ ro ? 'Cerrar' : 'Cancelar' }}</button>
            <button v-if="!ro" class="ab-confirm" :disabled="proc" @click="guardar">{{ proc ? '⟳ Guardando…' : (modo === 'nuevo' ? '✔ Confirmar' : '✔ Confirmar cambios') }}</button>
          </div>
        </div>
      </div>

      <!-- Ayuda -->
      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — Control de Salud · Exámenes</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista todos los exámenes. Buscá por empleado, tipo o médico; filtrá exámenes/certificados.</li>
            <li><b>＋ Nuevo examen</b>: elegí empleado, médico, tipo, fechas; opcionalmente adjuntá un documento.</li>
            <li>En cada fila: <b>👁️ Ver</b>, <b>✏️ Editar</b> (datos + documentos) y <b>🗑️ Eliminar</b>.</li>
            <li>El <b>próximo examen</b> es obligatorio cuando el tipo de documento es <b>Examen</b>.</li>
          </ul>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-confirm" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="visor" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const medicos = ref<{ cod: number; nombre: string }[]>([])
const tiposExamen = ref<{ cod: number; nombre: string }[]>([])
const tiposDoc = ref<{ cod: string; nombre: string }[]>([])

const rows = ref<any[]>([]); const cargando = ref(false)
const filtroTxt = ref(''); const filtroCoe = ref('')
const modo = ref<'' | 'nuevo' | 'ver' | 'editar'>('')
const ro = computed(() => modo.value === 'ver')

const empCod = ref(0); const empNombre = ref(''); const fotoUrl = ref('')
const f = reactive({ unico: 0, medico_cod: 0, medico: '', coe: 'E' as 'E' | 'C', fecha: '', proximo: '', tipo_cod: 0, notas: '' })
const documentos = ref<any[]>([])
const proc = ref(false); const docProc = ref(false); const formError = ref('')
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const visor = ref<InstanceType<typeof DocViewer> | null>(null)

// Documento a adjuntar
const docTipo = ref(''); const docFecha = ref(''); const docObs = ref(''); const fileInput = ref<HTMLInputElement | null>(null)
let archivo: File | null = null; const archNom = ref('')

const hoyStr = new Date().toISOString().slice(0, 10)
const fmt = (s: string) => s ? s.split('-').reverse().join('/') : ''
const vencido = (s: string) => !!s && s < hoyStr
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const filtradas = computed(() => {
  const q = filtroTxt.value.trim().toLowerCase()
  return rows.value.filter(e => {
    if (filtroCoe.value && e.coe !== filtroCoe.value) return false
    if (!q) return true
    return String(e.empleado).includes(q) || (e.empleado_nombre || '').toLowerCase().includes(q)
      || (e.tipo_det || '').toLowerCase().includes(q) || (e.medico || '').toLowerCase().includes(q)
  })
})

onMounted(async () => {
  try { const { data } = await api.get('/examenes/init'); medicos.value = data.medicos ?? []; tiposExamen.value = data.tipos_examen ?? []; tiposDoc.value = data.tipos_doc ?? [] }
  catch { flash('No se pudieron cargar los datos iniciales.', true) }
  cargarGrilla()
})

async function cargarGrilla () {
  cargando.value = true
  try {
    const params: any = {}
    if (props.empleado) params.emp = props.empleado
    rows.value = (await api.get('/examenes/grilla', { params })).data ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

function limpiarDoc () { docTipo.value = ''; docFecha.value = ''; docObs.value = ''; archivo = null; archNom.value = ''; if (fileInput.value) fileInput.value.value = '' }
function resetForm () { f.unico = 0; f.medico_cod = 0; f.medico = ''; f.coe = 'E'; f.fecha = hoyStr; f.proximo = ''; f.tipo_cod = 0; f.notas = ''; documentos.value = []; formError.value = ''; limpiarDoc() }

// ── Abrir ──
async function abrirNuevo () {
  resetForm(); empCod.value = 0; empNombre.value = ''; fotoUrl.value = ''
  if (props.empleado) { empCod.value = props.empleado; empNombre.value = props.empleadoNombre; cargarFoto(props.empleado) }
  modo.value = 'nuevo'
}
async function abrirDetalle (e: any, m: 'ver' | 'editar') {
  resetForm()
  empCod.value = e.empleado; empNombre.value = e.empleado_nombre; fotoUrl.value = ''
  f.unico = e.unico; f.medico_cod = e.medico_cod; f.medico = e.medico
  f.coe = e.coe === 'C' ? 'C' : 'E'; f.fecha = e.fecha; f.proximo = e.proximo; f.tipo_cod = e.tipo_cod; f.notas = e.notas
  modo.value = m
  cargarFoto(e.empleado)
  try { documentos.value = (await api.get(`/examenes/${e.unico}/documentos`)).data ?? [] } catch { documentos.value = [] }
}
const abrirVer = (e: any) => abrirDetalle(e, 'ver')
const abrirEditar = (e: any) => abrirDetalle(e, 'editar')
function cerrar () { modo.value = '' }

async function cargarFoto (cod: number) {
  try { const { data } = await api.get(`/empleados/${cod}/foto`); fotoUrl.value = data?.foto || '' } catch { fotoUrl.value = '' }
}

const onSelEmp = (r: any) => { empCod.value = Number(r.cod ?? r.PER_COD); empNombre.value = (r.nombre ?? r.PER_NOM ?? '').trim(); cargarFoto(empCod.value) }
const onFile = (e: Event) => { archivo = (e.target as HTMLInputElement).files?.[0] ?? null; archNom.value = archivo?.name ?? '' }
const verArchivoLocal = () => { if (archivo) visor.value?.open(archivo, archivo.name) }

// ── Guardar (alta / edición) ──
async function guardar () {
  formError.value = ''
  if (modo.value === 'nuevo') {
    if (!empCod.value) { formError.value = 'Seleccioná el empleado.'; return }
    if (f.medico_cod <= 0) { formError.value = 'Debe identificar el médico responsable.'; return }
  }
  if (!f.fecha) { formError.value = 'Fecha del examen vacía.'; return }
  if (f.coe === 'E' && !f.proximo) { formError.value = 'Ingrese la fecha del próximo examen médico.'; return }
  if (f.tipo_cod <= 0) { formError.value = 'Debe identificar el tipo de examen médico.'; return }
  if (modo.value === 'nuevo' && docTipo.value && !archivo) { formError.value = 'Eligió un tipo de documento: seleccione el archivo o quite el tipo.'; return }
  if (modo.value === 'nuevo' && docTipo.value && archivo && !docFecha.value) { formError.value = 'Debe ingresar la fecha del documento.'; return }
  if (!confirm('¿Acepta el examen médico?')) return
  proc.value = true
  try {
    if (modo.value === 'nuevo') {
      const fd = new FormData()
      fd.append('empleado', String(empCod.value)); fd.append('tipo_documento', f.coe)
      fd.append('medico_cod', String(f.medico_cod)); fd.append('tipo_examen_cod', String(f.tipo_cod))
      fd.append('fecha_examen', f.fecha); if (f.proximo) fd.append('fecha_proximo', f.proximo); fd.append('notas', f.notas)
      if (docTipo.value && archivo) { fd.append('doc_tipo', docTipo.value); fd.append('doc_fecha', docFecha.value); fd.append('doc_obs', docObs.value); fd.append('archivo', archivo) }
      const { data } = await api.post('/examenes', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      flash(data.doc_error || 'Examen registrado correctamente.', !!data.doc_error)
    } else {
      await api.put(`/examenes/${f.unico}`, { tipo_documento: f.coe, tipo_examen_cod: f.tipo_cod, fecha_examen: f.fecha, fecha_proximo: f.proximo || null, notas: f.notas })
      flash('Examen actualizado correctamente.')
    }
    await cargarGrilla()
    cerrar()
  } catch (e: any) { formError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { proc.value = false }
}

// ── Eliminar examen ──
async function eliminar (e: any) {
  if (!confirm(`¿Eliminar el examen "${e.tipo_det}" de ${e.empleado_nombre} del ${fmt(e.fecha)}?`)) return
  try { await api.post('/examenes/eliminar', { unicos: [e.unico] }); flash('Examen eliminado.'); await cargarGrilla() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

// ── Documentos (en edición) ──
async function agregarDocExistente () {
  if (!docTipo.value) { flash('Elegí el tipo de documento.', true); return }
  if (!archivo) { flash('Seleccioná el archivo.', true); return }
  if (!docFecha.value) { flash('Indicá la fecha del documento.', true); return }
  docProc.value = true
  try {
    const fd = new FormData()
    fd.append('doc_tipo', docTipo.value); fd.append('doc_fecha', docFecha.value); fd.append('doc_obs', docObs.value); fd.append('archivo', archivo)
    const { data } = await api.post(`/examenes/${f.unico}/documento`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    documentos.value = data.documentos ?? []
    flash('Documento agregado.'); limpiarDoc()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar el documento.', true) }
  finally { docProc.value = false }
}
async function verDoc (d: any) {
  try { const resp = await api.get(`/examenes/documento/${d.id}/ver`, { responseType: 'blob' }); visor.value?.open(resp.data as Blob, `${d.nombre}.${(d.ext || '').toLowerCase()}`) }
  catch { flash('No se pudo abrir el documento.', true) }
}
async function eliminarDoc (d: any) {
  if (!confirm(`¿Eliminar el documento "${d.nombre}.${(d.ext || '').toLowerCase()}"?`)) return
  try { const { data } = await api.delete(`/examenes/documento/${d.id}`); documentos.value = data.documentos ?? []; flash('Documento eliminado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
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
.ab-search { flex:1; min-width:240px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-estado { border:1px solid #c8d8ea; border-radius:8px; padding:9px 10px; font-size:13px; color:#1e293b; background:#fff; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#1b4332; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-venc { color:#dc2626; font-weight:700; }
.ab-badge { font-size:10px; padding:1px 7px; border-radius:10px; font-weight:700; } .ab-badge.e { background:#dbeafe; color:#1e40af; } .ab-badge.c { background:#fce7f3; color:#9d174d; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; }
.ab-b.del:hover { background:#fee2e2; } .ab-b.edi:hover { background:#e0eefc; } .ab-b.ver:hover { background:#e0f2fe; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(880px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-top { display:flex; gap:14px; align-items:flex-end; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo.span2 { grid-column:1 / -1; }
.campo label { font-size:12px; font-weight:600; color:#374151; } .oblig { color:#dc2626; }
.ab-lupa-row { display:flex; gap:8px; } .ab-lupa-row input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.ab-top .campo input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.ab-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ab-foto { width:120px; height:90px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ab-foto img { width:100%; height:100%; object-fit:cover; } .ab-foto-ph { font-size:11px; color:#9ca3af; }
.ab-form { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; }
.ab-form select, .ab-form input, .ab-form textarea { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; box-sizing:border-box; width:100%; font-family:inherit; }
.ab-form input:disabled, .ab-form select:disabled, .ab-form textarea:disabled { background:#f1f5f9; color:#64748b; }
.ab-radios { display:flex; gap:18px; padding-top:4px; } .rad { display:flex; align-items:center; gap:6px; font-size:14px; color:#374151; cursor:pointer; }
.ab-doc { margin-top:14px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:14px; }
.ab-doc-h { font-size:13px; color:#2a4a6a; margin-bottom:10px; } .ab-doc-h span { color:#94a3b8; font-weight:400; }
.ab-doc-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.ab-doc-grid select, .ab-doc-grid input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; box-sizing:border-box; width:100%; }
.ab-doc-grid input:disabled, .ab-doc-grid select:disabled { background:#eef2f7; color:#94a3b8; }
.ab-nota { font-size:11px; color:#94a3b8; margin:8px 0 0; }
.ab-file { display:flex; gap:8px; align-items:center; } .ab-file input { flex:1; }
.ab-ver { border:none; background:#e0eefc; color:#2d6a9f; border-radius:6px; padding:7px 12px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; }
.ab-adddoc { background:#d1fae5; color:#065f46; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:700; font-size:13px; } .ab-adddoc:disabled { opacity:.5; }
.ab-dt { width:100%; border-collapse:collapse; font-size:12px; border:1px solid #e2e8f0; }
.ab-dt th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11px; }
.ab-dt td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-cod { color:#2d6a9f; font-weight:700; } .ab-obs { color:#64748b; }
.ab-i { background:none; border:none; cursor:pointer; font-size:14px; padding:2px 4px; }
.ab-vacio2 { color:#94a3b8; font-size:13px; border:1px dashed #e2e8f0; border-radius:8px; padding:12px; text-align:center; }
.ab-md-err { color:#991b1b; font-size:13px; margin:12px 0 0; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#1b4332; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
