<!-- RelojFaltasView.vue — Reloj / Ajuste Faltas Diarias (reloj_faltas_diarias). -->
<template>
  <div class="rf-view">
    <div class="rf-cab">
      <div class="rf-cab-ico">📅</div>
      <div class="rf-cab-tx"><h1>Faltas Diarias</h1><p>Cargar una falta o licencia, adjuntar documentación y administrar las faltas cargadas</p></div>
      <button class="rf-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="rf-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojFaltasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-faltas" titulo="Asistente IA — Faltas Diarias"
            subtitulo="Preguntá sobre la carga de faltas" :sugerencias="['¿Cómo cargo una licencia?','¿Cómo adjunto un certificado?','¿Cómo modifico una falta?']"
            @close="modalIA = false" />

    <!-- ── Faltas cargadas ── -->
    <div class="rf-lista">
      <div class="rf-lista-tools">
        <b>Faltas / licencias cargadas</b>
        <button class="rf-btn-nueva" @click="abrirNueva">➕ Nueva falta</button>
        <label class="rf-rango">Desde <input v-model="eDesde" type="date" @change="cargarEdicion" /></label>
        <label class="rf-rango">Hasta <input v-model="eHasta" type="date" @change="cargarEdicion" /></label>
        <button class="rf-btn-sec" @click="cargarEdicion">Buscar</button>
        <div class="rf-filtro">
          <input v-model="filtroTexto" type="text" placeholder="Filtrar por nombre o código…" />
          <button v-if="filtroTexto" class="rf-filtro-x" title="Limpiar" @click="filtroTexto = ''">✕</button>
        </div>
        <span class="rf-count">{{ faltasVisibles.length }} de {{ faltasEdicion.length }} registro(s)</span>
      </div>
      <div class="rf-lista-wrap">
        <table class="rf-tabla2">
          <thead><tr><th style="width:60px">Cód.</th><th>Empleado</th><th>Licencia</th><th style="width:100px">Desde</th><th style="width:100px">Hasta</th><th>Observación</th><th style="width:60px" class="c">Doc.</th><th style="width:96px" class="c">Acciones</th></tr></thead>
          <tbody>
            <tr v-if="!faltasVisibles.length"><td colspan="8" class="rf-vacio">{{ faltasEdicion.length ? 'Ningún registro coincide con el filtro.' : 'Sin faltas en el rango.' }}</td></tr>
            <tr v-for="(fa, i) in faltasVisibles" :key="i" :class="{ vac: fa.esVacacion }">
              <td>{{ fa.cod }}</td><td>{{ fa.nombre }}</td>
              <td>{{ fa.esVacacion ? '— VACACIONES —' : (fa.lic + ' — ' + fa.detalle) }}</td>
              <td>{{ fmt(fa.desde) }}</td><td>{{ fmt(fa.hasta) }}</td><td>{{ fa.obs }}</td>
              <td class="c">
                <button v-if="fa.conDocu && !fa.esVacacion" class="rf-clip" title="Ver documentación" @click="verDocsFalta(fa)">📎</button>
              </td>
              <td class="c">
                <template v-if="!fa.esVacacion">
                  <button class="rf-btn-acc" title="Editar / documentos" @click="abrirEditar(fa)">✏️</button>
                  <button class="rf-btn-acc del" title="Borrar falta" @click="borrarFila(fa)">🗑️</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="msg" :class="['rf-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <RelojFaltaModal
      v-if="modalFalta"
      :licencias="licencias"
      :tipos-doc="tiposDoc"
      :falta="faltaEditar"
      :empleado-preset="empleadoPreset"
      @close="modalFalta = false"
      @saved="onSaved"
    />

    <!-- Selector de documentos de una falta (cuando hay más de uno) -->
    <div v-if="docsFila" class="rf-docs-ov" @click.self="docsFila = null">
      <div class="rf-docs-md">
        <div class="rf-docs-head"><span>Documentos — {{ docsFila.nombre }}</span><button class="rf-docs-x" @click="docsFila = null">✕</button></div>
        <ul class="rf-docs-list">
          <li v-for="d in docsFila.docs" :key="d.id">
            <span class="rf-docs-tipo">{{ d.detalle || d.tipo }}</span>
            <span class="rf-docs-nom">{{ d.nombre }}.{{ d.ext }}</span>
            <span class="rf-docs-fec">{{ fmt(d.fecha) }}</span>
            <button class="rf-docs-ver" @click="abrirDoc(d)">👁️ Ver</button>
          </li>
        </ul>
      </div>
    </div>

    <DocViewer ref="visor" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import RelojFaltasAyuda from '@/components/RelojFaltasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import RelojFaltaModal from '@/components/RelojFaltaModal.vue'
import DocViewer from '@/components/DocViewer.vue'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

const licencias = ref<{ cod: number; detalle: string }[]>([])
const tiposDoc = ref<{ cod: string; nombre: string }[]>([])

// ── Modal de ficha (crear / editar) ──
const modalFalta = ref(false)
const faltaEditar = ref<any | null>(null)
const empleadoPreset = computed(() => props.empleado ? { cod: props.empleado, nombre: props.empleadoNombre } : null)
const abrirNueva = () => { faltaEditar.value = null; modalFalta.value = true }
const abrirEditar = (fa: any) => { faltaEditar.value = { ...fa }; modalFalta.value = true }
const onSaved = (m: string) => { modalFalta.value = false; flash(m); cargarEdicion() }

// ── Grilla de faltas cargadas ──
const eDesde = ref(_iso(new Date(Date.now() - 30 * 864e5))); const eHasta = ref(_iso(new Date()))
const faltasEdicion = ref<any[]>([])
const filtroTexto = ref('')
const faltasVisibles = computed(() => {
  const q = filtroTexto.value.trim().toLowerCase()
  if (!q) return faltasEdicion.value
  return faltasEdicion.value.filter(f => (f.nombre || '').toLowerCase().includes(q) || String(f.cod).includes(q))
})
const cargarEdicion = async () => {
  try {
    const params: any = { desde: eDesde.value, hasta: eHasta.value }
    if (props.empleado) params.cod = props.empleado
    faltasEdicion.value = (await api.get('/reloj/faltas/edicion', { params })).data.faltas ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar las faltas.', true) }
}
// ── Ver documentación de una falta desde la grilla (📎) ──
const visor = ref<InstanceType<typeof DocViewer> | null>(null)
const docsFila = ref<{ nombre: string; docs: any[] } | null>(null)
const verDocsFalta = async (fa: any) => {
  if (!fa.unico) return
  try {
    const docs = (await api.get(`/reloj/faltas/${fa.unico}/documentos`)).data ?? []
    if (!docs.length) return flash('La falta no tiene documentos.', true)
    if (docs.length === 1) return abrirDoc(docs[0])
    docsFila.value = { nombre: fa.nombre, docs }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo leer la documentación.', true) }
}
const abrirDoc = async (d: any) => {
  try {
    const blob = (await api.get(`/reloj/faltas/documento/${d.id}/ver`, { responseType: 'blob' })).data
    visor.value?.open(blob, `${d.nombre}.${d.ext}`)
  } catch { flash('No se pudo abrir el documento.', true) }
}

const borrarFila = async (fa: any) => {
  if (fa.esVacacion || !fa.unico) return
  if (!confirm(`¿Borrar la falta de ${fa.nombre} (${fmt(fa.desde)} a ${fmt(fa.hasta)})?`)) return
  try { const { data } = await api.post('/reloj/faltas/edicion/eliminar', { unicos: [fa.unico] }); flash(data.message); await cargarEdicion() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/reloj/faltas/licencias')
    licencias.value = data.licencias ?? []; tiposDoc.value = data.tipos_doc ?? []
  } catch { /* */ }
  cargarEdicion()
})
</script>

<style scoped>
.rf-view { display:flex; flex-direction:column; min-height:100%; }
.rf-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.rf-cab-ico { font-size:28px; } .rf-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .rf-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.rf-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rf-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rf-lista { margin:16px 18px 18px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.rf-lista-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:13px; color:#1e293b; }
.rf-btn-nueva { background:#16a34a; color:#fff; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:800; }
.rf-rango { font-size:12px; color:#475569; display:flex; align-items:center; gap:5px; } .rf-rango input { border:1px solid #c8d8ea; border-radius:6px; padding:5px 7px; font-size:13px; }
.rf-btn-sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:8px 14px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; }
.rf-filtro { display:flex; align-items:center; gap:4px; }
.rf-filtro input { border:1px solid #c8d8ea; border-radius:6px; padding:6px 9px; font-size:13px; min-width:220px; }
.rf-filtro-x { background:#e2e8f0; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:11px; color:#475569; }
.rf-count { font-size:12px; color:#64748b; font-weight:600; }
.rf-lista-wrap { overflow-x:auto; }
.rf-tabla2 { width:100%; border-collapse:collapse; font-size:13px; }
.rf-tabla2 th { position:sticky; top:0; background:#1e293b; color:#fff; padding:8px 10px; text-align:left; font-size:12px; } .rf-tabla2 th.c, .rf-tabla2 td.c { text-align:center; }
.rf-tabla2 td { padding:6px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.rf-tabla2 tr.vac td { background:#eff6ff; color:#1e3a8a; font-style:italic; }
.rf-btn-acc { background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:3px 7px; cursor:pointer; font-size:14px; margin:0 1px; }
.rf-btn-acc:hover { background:#f1f5f9; } .rf-btn-acc.del:hover { background:#fee2e2; border-color:#fca5a5; }
.rf-clip { background:transparent; border:none; cursor:pointer; font-size:16px; padding:2px 4px; border-radius:6px; } .rf-clip:hover { background:#e0f2fe; }
.rf-docs-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9500; display:flex; align-items:center; justify-content:center; padding:20px; }
.rf-docs-md { width:min(560px,100%); background:#fff; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,.4); overflow:hidden; }
.rf-docs-head { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#1b4332; color:#fff; font-weight:700; font-size:14px; }
.rf-docs-x { background:transparent; border:none; color:#fff; font-size:17px; cursor:pointer; }
.rf-docs-list { list-style:none; margin:0; padding:8px; max-height:60vh; overflow:auto; }
.rf-docs-list li { display:flex; align-items:center; gap:10px; padding:8px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; }
.rf-docs-tipo { font-weight:700; color:#1b4332; min-width:100px; } .rf-docs-nom { flex:1; color:#1e293b; } .rf-docs-fec { color:#64748b; font-size:12px; }
.rf-docs-ver { background:#16a34a; color:#fff; border:none; padding:5px 12px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:700; }
.rf-vacio { color:#94a3b8; padding:14px; text-align:center; }
.rf-msg { margin:12px 14px; padding:9px 14px; font-size:13px; border-radius:6px; } .rf-msg.ok { background:#dcfce7; color:#166534; } .rf-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
