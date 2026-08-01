<!-- RelojFaltaModal.vue — Ficha unificada de Falta/Licencia (crear + editar + documentación). -->
<template>
  <div class="fm-ov" @click.self="cerrar">
    <div class="fm-md">
      <div class="fm-head">
        <span>{{ esEditar ? '✏️ Editar falta / licencia' : '➕ Nueva falta / licencia' }}</span>
        <button class="fm-x" @click="cerrar">✕</button>
      </div>

      <div class="fm-body" v-enter-next>
        <!-- Empleado -->
        <div v-if="!esEditar && !empBloqueado" class="fm-f bus">
          <label>Personal</label>
          <input v-model="busqueda" type="text" placeholder="Código o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="fm-result">
            <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
          </ul>
        </div>
        <div class="fm-f"><label>Nombre</label><input :value="emp.nombre" class="ro" readonly /></div>

        <!-- Permisos pendientes (solo al crear) -->
        <div v-if="!esEditar && permisosPendientes.length" class="fm-f">
          <button class="fm-perm" @click="modalPermisos = true">
            🪪 Tiene {{ permisosPendientes.length }} permiso(s) pendiente(s) — Ver / usar
          </button>
        </div>
        <div v-if="permisoCod" class="fm-info">✓ Cargado desde un permiso. Al guardar, ese permiso quedará usado.</div>

        <!-- Fechas: editables solo al crear -->
        <div class="fm-row">
          <div class="fm-f"><label>Fecha desde</label><input v-model="fecha1" type="date" :readonly="esEditar" :class="{ ro: esEditar }" /></div>
          <div class="fm-f"><label>Hasta</label><input v-model="fecha2" type="date" :readonly="esEditar" :class="{ ro: esEditar }" /></div>
        </div>
        <p v-if="esEditar" class="fm-nota">Las fechas no se modifican. Podés cambiar el tipo de licencia, la observación y la documentación.</p>

        <div class="fm-f"><label>Licencia</label>
          <select v-model.number="licencia">
            <option :value="0">— Seleccione —</option>
            <option v-for="l in licencias" :key="l.cod" :value="l.cod">{{ l.cod }} — {{ l.detalle }}</option>
          </select>
        </div>
        <div class="fm-f"><label>Observación</label><input v-model="observacion" type="text" placeholder="Observación…" /></div>

        <!-- ── Documentación ── -->
        <div class="fm-docs">
          <div class="fm-docs-h">📎 Documentación <span class="fm-count">{{ docs.length }}</span></div>
          <table v-if="docs.length" class="fm-tabla">
            <thead><tr><th>Tipo</th><th>Nombre</th><th style="width:60px">Ext.</th><th>Observación</th><th style="width:96px">Fecha</th><th style="width:70px"></th></tr></thead>
            <tbody>
              <tr v-for="(dc, i) in docs" :key="i">
                <td>{{ dc.tipoNombre || dc.detalle || dc.tipo }}</td>
                <td>{{ dc.nombre }}</td><td>{{ dc.ext }}</td><td>{{ dc.observaciones }}</td><td>{{ fmt(dc.fecha) }}</td>
                <td>
                  <button class="fm-i" title="Ver" @click="verDoc(dc)">👁️</button>
                  <button class="fm-i del" title="Quitar" @click="quitarDoc(dc, i)">🗑️</button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="fm-vacio">Sin documentos adjuntos.</p>

          <div class="fm-doc-add">
            <div class="fm-f"><label>Tipo de doc.</label>
              <select v-model="dTipo"><option value="">— —</option><option v-for="t in tiposDoc" :key="t.cod" :value="t.cod">{{ t.cod }} — {{ t.nombre }}</option></select>
            </div>
            <div class="fm-f"><label>Fecha</label><input v-model="dFecha" type="date" /></div>
            <div class="fm-f grow"><label>Archivo</label><input ref="fileInput" type="file" @change="onFile" /></div>
            <div class="fm-f"><label>Observación</label><input v-model="dObs" type="text" maxlength="60" placeholder="Opcional" /></div>
            <button class="fm-add" :disabled="subiendo" @click="agregarDoc">{{ subiendo ? '⟳…' : '＋ Agregar' }}</button>
          </div>
          <p class="fm-mini">No se aceptan exe, bat, dll, zip, rar, cmd ni cab. Máximo 50 MB.</p>
        </div>

        <p v-if="msg" :class="['fm-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
      </div>

      <div class="fm-foot">
        <button class="fm-btn sec" @click="cerrar">Cancelar</button>
        <button class="fm-btn ok" :disabled="guardando" @click="guardar">{{ guardando ? '⟳…' : (esEditar ? 'Guardar cambios' : 'Grabar falta') }}</button>
      </div>
    </div>

    <PermisosPendientesModal v-if="modalPermisos" :permisos="permisosPendientes" :nombre="emp.nombre" @elegir="usarPermiso" @close="modalPermisos = false" />
    <DocViewer ref="visor" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import PermisosPendientesModal from '@/components/PermisosPendientesModal.vue'
import DocViewer from '@/components/DocViewer.vue'

const props = withDefaults(defineProps<{
  licencias: { cod: number; detalle: string }[]
  tiposDoc: { cod: string; nombre: string }[]
  falta?: any | null                    // presente = editar
  empleadoPreset?: { cod: number; nombre: string } | null
}>(), { falta: null, empleadoPreset: null })

const emit = defineEmits<{ (e: 'close'): void; (e: 'saved', msg: string): void }>()

const esEditar = computed(() => !!props.falta)
const empBloqueado = computed(() => !!props.empleadoPreset)

const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''

const emp = reactive({ cod: 0, nombre: '' })
const fecha1 = ref(_iso(new Date())); const fecha2 = ref(_iso(new Date()))
const licencia = ref(0); const observacion = ref('')
const guardando = ref(false); const subiendo = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e }

// Documentos: existentes (server, en editar) + nuevos (locales, se suben al guardar) − eliminados.
interface DocFila { id?: number; tipo: string; tipoNombre?: string; detalle?: string; nombre: string; ext: string; observaciones: string; fecha: string; file?: File }
const docs = ref<DocFila[]>([])
const eliminados = ref<number[]>([])

const modalPermisos = ref(false); const permisosPendientes = ref<any[]>([]); const permisoCod = ref(0)

onMounted(async () => {
  if (esEditar.value) {
    const f = props.falta
    emp.cod = f.cod; emp.nombre = f.nombre
    fecha1.value = f.desde; fecha2.value = f.hasta
    licencia.value = f.lic; observacion.value = f.obs || ''
    await cargarDocsServidor(f.unico)
  } else if (props.empleadoPreset) {
    emp.cod = props.empleadoPreset.cod; emp.nombre = props.empleadoPreset.nombre
    cargarPendientes(emp.cod)
  }
})

const cargarDocsServidor = async (unico: number) => {
  try {
    const data = (await api.get(`/reloj/faltas/${unico}/documentos`)).data ?? []
    docs.value = data.map((d: any) => ({ id: d.id, tipo: d.tipo, detalle: d.detalle, nombre: `${d.nombre}.${d.ext}`, ext: d.ext, observaciones: d.observaciones, fecha: d.fecha }))
  } catch { docs.value = [] }
}

// ── Documentos: agregar/quitar/ver ──
const dTipo = ref(''); const dFecha = ref(_iso(new Date())); const dObs = ref(''); let dArchivo: File | null = null
const fileInput = ref<HTMLInputElement | null>(null)
const onFile = (e: Event) => { dArchivo = (e.target as HTMLInputElement).files?.[0] ?? null }

const agregarDoc = async () => {
  if (!dTipo.value) return flash('Elegí el tipo de documento.', true)
  if (!dArchivo) return flash('Seleccioná el archivo.', true)
  const nombreTipo = props.tiposDoc.find(t => t.cod === dTipo.value)?.nombre ?? dTipo.value
  const fila: DocFila = {
    tipo: dTipo.value, tipoNombre: nombreTipo, nombre: dArchivo.name,
    ext: (dArchivo.name.split('.').pop() || '').toUpperCase(), observaciones: dObs.value, fecha: dFecha.value, file: dArchivo,
  }
  if (esEditar.value) {
    // En edición se sube al instante (la falta ya existe).
    subiendo.value = true
    try {
      const fd = new FormData()
      fd.append('doc_tipo', dTipo.value); fd.append('doc_fecha', dFecha.value); fd.append('doc_obs', dObs.value); fd.append('archivo', dArchivo)
      await api.post(`/reloj/faltas/${props.falta.unico}/documento`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      await cargarDocsServidor(props.falta.unico)
      flash('Documento agregado.')
    } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo subir el documento.', true) }
    finally { subiendo.value = false }
  } else {
    docs.value.push(fila)   // se sube después de crear la falta
  }
  dTipo.value = ''; dObs.value = ''; dArchivo = null; if (fileInput.value) fileInput.value.value = ''
}

const quitarDoc = async (dc: DocFila, i: number) => {
  if (dc.id) {
    if (!confirm('¿Quitar este documento? Se elimina de la biblioteca digital.')) return
    try { await api.delete(`/reloj/faltas/documento/${dc.id}`); docs.value.splice(i, 1); flash('Documento eliminado.') }
    catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar el documento.', true) }
  } else {
    docs.value.splice(i, 1)
  }
}

const visor = ref<InstanceType<typeof DocViewer> | null>(null)
const verDoc = async (dc: DocFila) => {
  if (dc.file) { visor.value?.open(dc.file, dc.file.name); return }
  try {
    const blob = (await api.get(`/reloj/faltas/documento/${dc.id}/ver`, { responseType: 'blob' })).data
    visor.value?.open(blob, dc.nombre)
  } catch { flash('No se pudo abrir el documento.', true) }
}

// ── Búsqueda de empleado (crear) ──
const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8, activo: 1 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionar = (r: any) => {
  resultados.value = []
  if (r.PER_AOP !== undefined && String(r.PER_AOP).trim() !== 'A') {
    busqueda.value = ''; emp.cod = 0; emp.nombre = ''; permisosPendientes.value = []
    return flash(`${(r.PER_NOM || '').trim()} está dado de baja: no se pueden cargar faltas.`, true)
  }
  busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`
  emp.cod = r.PER_COD; emp.nombre = (r.PER_NOM || '').trim()
  cargarPendientes(r.PER_COD)
}
const cargarPendientes = async (cod: number) => {
  permisoCod.value = 0
  try { permisosPendientes.value = (await api.get(`/permisos-laborales/pendientes/${cod}`)).data.permisos ?? [] }
  catch { permisosPendientes.value = [] }
}
const usarPermiso = (p: any) => {
  fecha1.value = p.fecha_desde || fecha1.value
  fecha2.value = p.fecha_hasta || p.fecha_desde || fecha2.value
  const horas = p.hora_inicio ? `(${p.hora_inicio} A ${p.hora_fin || ''}) : ` : ''
  observacion.value = horas + (p.observaciones || '')
  const lic = props.licencias.find(l => l.detalle.trim().toUpperCase() === (p.falta || '').trim().toUpperCase())
  licencia.value = lic?.cod ?? 0
  permisoCod.value = p.cod
  modalPermisos.value = false
}

// ── Guardar ──
const guardar = async () => {
  if (!licencia.value) return flash('Indicá un tipo de licencia válido.', true)
  guardando.value = true
  try {
    if (esEditar.value) {
      const { data } = await api.put(`/reloj/faltas/${props.falta.unico}`, { licencia: licencia.value, observacion: observacion.value })
      emit('saved', data?.message ?? 'Falta modificada.')
    } else {
      if (!emp.cod) { flash('Seleccioná un empleado.', true); return }
      let permisoAUsar = 0
      if (permisoCod.value) permisoAUsar = confirm('¿Da por finalizado el permiso solicitado? (queda marcado como usado)') ? permisoCod.value : 0
      const { data } = await api.post('/reloj/faltas', { cod: emp.cod, fecha1: fecha1.value, fecha2: fecha2.value, licencia: licencia.value, observacion: observacion.value, permiso_cod: permisoAUsar })
      const unico = data?.unico
      // Subir los documentos que se hayan agregado localmente.
      let docErr = 0
      for (const dc of docs.value.filter(d => d.file)) {
        try {
          const fd = new FormData()
          fd.append('doc_tipo', dc.tipo); fd.append('doc_fecha', dc.fecha); fd.append('doc_obs', dc.observaciones || ''); fd.append('archivo', dc.file as File)
          await api.post(`/reloj/faltas/${unico}/documento`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        } catch { docErr++ }
      }
      emit('saved', (data?.message ?? 'Falta grabada.') + (docErr ? ` (${docErr} documento/s no se pudieron subir)` : ''))
    }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

const cerrar = () => emit('close')
</script>

<style scoped>
.fm-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:26px 16px; overflow:auto; }
.fm-md { width:min(760px,100%); background:#fff; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,.4); display:flex; flex-direction:column; }
.fm-head { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#1b4332; color:#fff; border-radius:12px 12px 0 0; font-size:15px; font-weight:700; }
.fm-x { background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer; }
.fm-body { padding:16px; display:flex; flex-direction:column; gap:12px; }
.fm-f { display:flex; flex-direction:column; gap:4px; position:relative; }
.fm-f label { font-size:12px; font-weight:700; color:#1b4332; }
.fm-f input, .fm-f select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; }
.fm-f input.ro { background:#f1f5f9; }
.fm-row { display:flex; gap:14px; flex-wrap:wrap; } .fm-row .fm-f { flex:1; min-width:150px; }
.fm-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:200px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.fm-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .fm-result li:hover { background:#f0faf4; }
.fm-info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; border-radius:6px; padding:8px 12px; font-size:13px; font-weight:600; }
.fm-perm { background:#fef3c7; color:#92600b; border:1.5px solid #fde68a; border-radius:8px; padding:10px 14px; cursor:pointer; font-size:13px; font-weight:700; text-align:left; }
.fm-nota { font-size:12px; color:#64748b; margin:0; }
.fm-docs { border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px; }
.fm-docs-h { font-size:13px; font-weight:800; color:#1b4332; } .fm-count { background:#1b4332; color:#fff; border-radius:10px; padding:1px 8px; font-size:12px; margin-left:4px; }
.fm-tabla { width:100%; border-collapse:collapse; font-size:12.5px; background:#fff; }
.fm-tabla th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11.5px; }
.fm-tabla td { padding:5px 8px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.fm-i { background:transparent; border:none; cursor:pointer; font-size:15px; } .fm-i.del { filter:grayscale(.2); }
.fm-vacio { font-size:12.5px; color:#94a3b8; margin:0; }
.fm-doc-add { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; } .fm-doc-add .grow { flex:1; min-width:180px; }
.fm-add { background:#16a34a; color:#fff; border:none; padding:8px 14px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; } .fm-add:disabled { background:#cbd5e1; }
.fm-mini { font-size:11px; color:#94a3b8; margin:0; }
.fm-msg { padding:8px 12px; font-size:13px; border-radius:6px; } .fm-msg.ok { background:#dcfce7; color:#166534; } .fm-msg.err { background:#fee2e2; color:#b91c1c; }
.fm-foot { display:flex; justify-content:flex-end; gap:10px; padding:12px 16px; border-top:1px solid #e2e8f0; }
.fm-btn { border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; }
.fm-btn.sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; } .fm-btn.ok { background:#16a34a; color:#fff; } .fm-btn.ok:disabled { background:#cbd5e1; }
</style>
