<!-- EntrevistasView.vue — ABM de Entrevistas (entrevistas.scx). 2 solapas + foto + documentación. -->
<template>
  <div class="ev-view">
    <div class="ev-cab">
      <div class="ev-ico">🤝</div>
      <div class="ev-tx"><h1>Entrevistas</h1><p>Entrevistados para posibles ingresos</p></div>
      <button class="ev-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ev-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="ev-nuevo" @click="nuevo">＋ Nuevo</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/entrevistas" titulo="Asistente IA — Entrevistas"
            subtitulo="Preguntá sobre el registro de entrevistados"
            :sugerencias="['¿Para qué sirve este módulo?','¿Qué datos cargo de un entrevistado?','¿Cómo agrego la foto?','¿Cómo adjunto documentación?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ev-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ev-body">
      <!-- Barra de navegación / búsqueda -->
      <div class="ev-nav">
        <button class="ev-b" title="Primero" :disabled="!lista.length" @click="ir(0)">⏮</button>
        <button class="ev-b" title="Anterior" :disabled="idx <= 0" @click="ir(idx - 1)">◀</button>
        <button class="ev-b" title="Siguiente" :disabled="idx < 0 || idx >= lista.length - 1" @click="ir(idx + 1)">▶</button>
        <button class="ev-b" title="Último" :disabled="!lista.length" @click="ir(lista.length - 1)">⏭</button>
        <select class="ev-sel" :value="form.cod ?? ''" @change="seleccionar(Number(($event.target as HTMLSelectElement).value))">
          <option value="" disabled>— Entrevistado —</option>
          <option v-for="e in lista" :key="e.cod" :value="e.cod">{{ e.cod }} — {{ e.nombre }}</option>
        </select>
        <input v-model="filtro" class="ev-search" placeholder="🔍 Buscar por nombre…" @keyup.enter="buscar" />
        <button class="ev-b buscar" @click="buscar">Buscar</button>
      </div>

      <!-- Solapas -->
      <div class="ev-tabs">
        <button :class="['ev-tab', tab === 'datos' && 'on']" @click="tab = 'datos'">Datos del Entrevistado</button>
        <button :class="['ev-tab', tab === 'docs' && 'on']" :disabled="!form.cod" @click="tab = 'docs'">Documentación Recibida</button>
      </div>

      <!-- Solapa Datos -->
      <div v-show="tab === 'datos'" class="ev-pane">
        <div class="ev-form">
          <div class="ev-col">
            <div class="ev-row2"><div><label>Tipo Documento</label><input v-model="form.tipo_documento" maxlength="3" class="ev-corto" /></div>
              <div><label>Nro. Documento</label><input v-model.number="form.numero_documento" type="number" class="ev-corto" /></div></div>
            <label>Nombre *</label><input ref="inputNom" v-model="form.nombre" maxlength="100" v-enter-next />
            <label>Domicilio *</label><input v-model="form.domicilio" maxlength="100" v-enter-next />
            <label>Email</label><input v-model="form.email" maxlength="100" type="email" v-enter-next />
            <label>Teléfonos *</label><input v-model="form.telefono" maxlength="100" v-enter-next />
            <label>Fecha</label><input v-model="form.fecha" type="date" class="ev-corto" />
            <label>Lugar Entrevista</label><input v-model="form.lugar" maxlength="100" v-enter-next />
            <div class="ev-row2">
              <div><label>Sector Laboral *</label>
                <select v-model.number="form.sector_cod" @change="onSector">
                  <option :value="0" disabled>— Sector —</option>
                  <option v-for="s in sectores" :key="s.cod" :value="s.cod">{{ s.nombre }}</option>
                </select></div>
              <div><label>Subsector / Puesto *</label><input v-model="form.subsector" maxlength="100" /></div>
            </div>
            <label>Formación Académica *</label><input v-model="form.formacion" maxlength="100" />
            <label>Notas</label><textarea v-model="form.notas" rows="10" maxlength="2000" class="ev-notas"></textarea>
          </div>
          <div class="ev-foto-col">
            <div class="ev-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl=''" /><div v-else class="ev-foto-ph">Sin foto</div></div>
            <div class="ev-foto-acc">
              <label class="ev-foto-btn">📷 {{ subiendoFoto ? '…' : 'Agregar Fotografía' }}<input type="file" accept="image/*" hidden @change="onFoto" :disabled="!form.cod" /></label>
              <button class="ev-foto-del" :disabled="!form.cod || !fotoUrl" @click="eliminarFoto">🗑 Eliminar Foto</button>
            </div>
            <p v-if="!form.cod" class="ev-foto-nota">Guardá el entrevistado para poder cargar la foto.</p>
          </div>
        </div>
        <p v-if="formError" class="ev-md-err">{{ formError }}</p>
        <div class="ev-acc"><span style="flex:1"></span><button class="ev-guardar" :disabled="guardando" @click="guardar">{{ guardando ? '⟳' : (form.cod ? '💾 Guardar cambios' : '💾 Guardar nuevo') }}</button></div>
      </div>

      <!-- Solapa Documentación -->
      <div v-show="tab === 'docs'" class="ev-pane">
        <div class="ev-doc-add">
          <select v-model="doc.tipo" class="ev-doc-tipo">
            <option value="">— Tipo documento —</option>
            <option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.nombre }}</option>
          </select>
          <input ref="fileInput" type="file" @change="onFile" class="ev-doc-file" />
          <input v-model="doc.obs" maxlength="60" placeholder="Observación" class="ev-doc-obs" />
          <button class="ev-doc-add-btn" :disabled="subiendo" @click="aceptarDoc">{{ subiendo ? '⟳…' : '＋ Aceptar' }}</button>
        </div>
        <table v-if="documentos.length" class="ev-dt">
          <thead><tr><th style="width:44px">Ver</th><th style="width:46px">N#</th><th>Tipo</th><th>Nombre</th><th>Ext</th><th>Creado</th><th>Observaciones</th><th>Usuario</th><th style="width:44px"></th></tr></thead>
          <tbody>
            <tr v-for="d in documentos" :key="d.id">
              <td><button class="btn-ojo" title="Visualizar" @click="verDoc(d)">👁️</button></td>
              <td>{{ d.nro }}</td><td>{{ d.detalle || d.tipo }}</td><td>{{ d.nombre }}.{{ (d.ext || '').toLowerCase() }}</td>
              <td>{{ (d.ext || '').toLowerCase() }}</td><td>{{ d.creado }}</td><td>{{ d.observaciones }}</td><td>{{ d.usuario }}</td>
              <td><button class="ev-i del" title="Eliminar" @click="eliminarDoc(d)">🗑️</button></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="ev-vacio">El entrevistado no tiene documentación cargada.</div>
      </div>
    </div>

    <DocViewer ref="docVisor" />

    <Teleport to="body">
      <div v-if="ayuda" class="ev-ov" @click.self="ayuda = false">
        <div class="ev-help-md">
          <h3>❓ Ayuda — Entrevistas</h3>
          <ul>
            <li>Registra los <b>entrevistados</b>: datos personales, sector/subsector para el que se los entrevista, formación y notas.</li>
            <li>Navegá con ⏮ ◀ ▶ ⏭ o buscá por nombre. Con <b>Nuevo</b> cargás un entrevistado.</li>
            <li>Podés adjuntar una <b>foto</b> y la <b>documentación recibida</b> (con 👁️ se visualiza).</li>
          </ul>
          <div class="ev-acc"><span style="flex:1"></span><button class="ev-guardar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

interface Item { cod: number; nombre: string; fecha: string; lugar: string; sector: string }
const lista = ref<Item[]>([]); const idx = ref(-1); const filtro = ref('')
const sectores = ref<{ cod: number; nombre: string }[]>([]); const tipos = ref<{ cod: string; nombre: string }[]>([])
const vacio = (): any => ({ cod: null, tipo_documento: 'DNI', numero_documento: 0, nombre: '', domicilio: '', email: '', telefono: '', fecha: '', lugar: '', sector_cod: 0, sector_desc: '', subsector: '', formacion: '', notas: '' })
const form = ref<any>(vacio())
const documentos = ref<any[]>([]); const fotoUrl = ref('')
const tab = ref<'datos' | 'docs'>('datos')
const guardando = ref(false); const subiendo = ref(false); const subiendoFoto = ref(false)
const msg = ref(''); const msgErr = ref(false); const formError = ref(''); const modalIA = ref(false); const ayuda = ref(false)
const inputNom = ref<HTMLInputElement | null>(null)
const doc = ref<{ tipo: string; obs: string; file: File | null }>({ tipo: '', obs: '', file: null })
const fileInput = ref<HTMLInputElement | null>(null)
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)

const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

async function cargarInit () { try { const { data } = await api.get('/entrevistas/init'); sectores.value = data.sectores ?? []; tipos.value = data.tipos ?? [] } catch { /* */ } }
async function cargarLista (buscar = '') {
  try { lista.value = (await api.get('/entrevistas', { params: buscar ? { buscar } : {} })).data ?? [] }
  catch { flash('No se pudo cargar la lista.', true) }
}
const buscar = async () => { await cargarLista(filtro.value.trim()); if (lista.value.length) ir(0); else flash('Sin resultados.', true) }

async function ir (i: number) { if (i < 0 || i >= lista.value.length) return; idx.value = i; await seleccionar(lista.value[i].cod) }
async function seleccionar (cod: number) {
  try {
    const { data } = await api.get(`/entrevistas/${cod}`)
    form.value = { ...data.entrevista }
    documentos.value = data.documentos ?? []
    idx.value = lista.value.findIndex(e => e.cod === cod)
    formError.value = ''; tab.value = 'datos'
    await cargarFoto(cod)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar.', true) }
}
async function cargarFoto (cod: number) {
  fotoUrl.value = ''
  try { const { data } = await api.get(`/entrevistas/${cod}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* */ }
}
const nuevo = async () => { form.value = vacio(); documentos.value = []; fotoUrl.value = ''; idx.value = -1; formError.value = ''; tab.value = 'datos'; await nextTick(); inputNom.value?.focus() }
const onSector = () => { const s = sectores.value.find(x => x.cod === form.value.sector_cod); form.value.sector_desc = s?.nombre ?? '' }

async function guardar () {
  const f = form.value
  if (!f.nombre?.trim()) { formError.value = 'Debe ingresar el nombre del entrevistado.'; return }
  if (!f.domicilio?.trim()) { formError.value = 'Debe ingresar el domicilio.'; return }
  if (!f.telefono?.trim()) { formError.value = 'Debe ingresar el teléfono.'; return }
  if (!f.sector_cod) { formError.value = 'Debe ingresar el sector.'; return }
  if (!f.subsector?.trim()) { formError.value = 'Debe ingresar el subsector / puesto.'; return }
  if (!f.formacion?.trim()) { formError.value = 'Debe ingresar la formación académica.'; return }
  guardando.value = true; formError.value = ''
  const body = { ...f }
  try {
    if (f.cod) { await api.put(`/entrevistas/${f.cod}`, body); flash('Entrevistado actualizado') }
    else { const { data } = await api.post('/entrevistas', body); form.value.cod = data.cod; flash('Entrevistado creado') }
    await cargarLista(); idx.value = lista.value.findIndex(e => e.cod === form.value.cod)
  } catch (e: any) { formError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}

// Foto
async function onFoto (ev: Event) {
  const file = (ev.target as HTMLInputElement).files?.[0]; if (!file || !form.value.cod) return
  subiendoFoto.value = true
  const fd = new FormData(); fd.append('foto', file)
  try { await api.post(`/entrevistas/${form.value.cod}/foto`, fd); await cargarFoto(form.value.cod); flash('Foto guardada') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar la foto.', true) }
  finally { subiendoFoto.value = false; (ev.target as HTMLInputElement).value = '' }
}
async function eliminarFoto () {
  if (!form.value.cod || !confirm('¿Eliminar la foto del entrevistado?')) return
  try { await api.delete(`/entrevistas/${form.value.cod}/foto`); fotoUrl.value = ''; flash('Foto eliminada') }
  catch { flash('No se pudo eliminar la foto.', true) }
}

// Documentos
const onFile = (ev: Event) => { doc.value.file = (ev.target as HTMLInputElement).files?.[0] ?? null }
async function aceptarDoc () {
  if (!form.value.cod) { flash('Guardá el entrevistado primero.', true); return }
  if (!doc.value.tipo) { flash('Debe ingresar el tipo de documento.', true); return }
  if (!doc.value.file) { flash('Debe seleccionar el documento.', true); return }
  if (!confirm(`¿Desea importar el archivo "${doc.value.file.name}"?`)) return
  subiendo.value = true
  const fd = new FormData(); fd.append('tipo', doc.value.tipo); fd.append('obs', doc.value.obs); fd.append('archivo', doc.value.file)
  try {
    const { data } = await api.post(`/entrevistas/${form.value.cod}/documento`, fd)
    documentos.value = data.documentos ?? []
    doc.value = { tipo: '', obs: '', file: null }; if (fileInput.value) fileInput.value.value = ''
    flash('Documento agregado')
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo agregar.', true) }
  finally { subiendo.value = false }
}
async function verDoc (d: any) {
  try { const resp = await api.get(`/entrevistas/documento/${d.id}/ver`, { responseType: 'blob' }); docVisor.value?.open(resp.data as Blob, `${d.nombre}.${(d.ext || '').toLowerCase()}`) }
  catch { flash('No se pudo abrir el documento.', true) }
}
async function eliminarDoc (d: any) {
  if (!confirm(`¿Elimina el documento "${d.nombre}.${(d.ext || '').toLowerCase()}"?`)) return
  try { const { data } = await api.delete(`/entrevistas/documento/${d.id}`); documentos.value = data.documentos ?? []; flash('Documento eliminado') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

;(async () => { await cargarInit(); await cargarLista(); if (lista.value.length) ir(0) })()
</script>

<style scoped>
.ev-view { display:flex; flex-direction:column; min-height:100%; }
.ev-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ev-ico { font-size:28px; } .ev-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ev-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ev-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ev-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ev-nuevo { background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ev-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ev-msg.ok { background:#d1fae5; color:#065f46; } .ev-msg.err { background:#fee2e2; color:#991b1b; }
.ev-body { padding:14px 18px; max-width:960px; }
.ev-nav { display:flex; align-items:center; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.ev-b { background:#eef2f7; color:#334155; border:none; padding:7px 11px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ev-b:disabled { opacity:.4; cursor:default; } .ev-b.buscar { background:#2d6a9f; color:#fff; }
.ev-sel { border:1px solid #c8d8ea; border-radius:6px; padding:7px 10px; font-size:13px; min-width:260px; color:#1e293b; }
.ev-search { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; flex:1; min-width:160px; }
.ev-tabs { display:flex; gap:4px; border-bottom:2px solid #e2e8f0; margin-bottom:14px; }
.ev-tab { background:none; border:none; padding:9px 16px; cursor:pointer; font-size:13px; font-weight:700; color:#64748b; border-bottom:3px solid transparent; margin-bottom:-2px; }
.ev-tab.on { color:#1b4332; border-bottom-color:#40916c; } .ev-tab:disabled { opacity:.4; cursor:default; }
.ev-form { display:flex; gap:20px; }
.ev-col { flex:1; } .ev-col label { font-size:12px; font-weight:600; color:#374151; display:block; margin-top:10px; }
.ev-col input, .ev-col select, .ev-col textarea { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; margin-top:4px; box-sizing:border-box; color:#1e293b; font-family:inherit; resize:vertical; outline:none; }
/* Notas: alto amplio para leer varias líneas sin scrollear (se puede agrandar con el asa). */
.ev-col textarea.ev-notas { min-height:190px; line-height:1.45; }
.ev-col input.ev-corto { width:140px; }
.ev-row2 { display:flex; gap:12px; } .ev-row2 > div { flex:1; }
.ev-foto-col { width:220px; flex-shrink:0; }
.ev-foto { width:100%; height:230px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; }
.ev-foto img { width:100%; height:100%; object-fit:cover; } .ev-foto-ph { color:#9ca3af; font-size:13px; }
.ev-foto-acc { display:flex; flex-direction:column; gap:6px; margin-top:8px; }
.ev-foto-btn { background:#1b4332; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:700; text-align:center; }
.ev-foto-del { background:#fee2e2; color:#991b1b; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:700; } .ev-foto-del:disabled { opacity:.5; cursor:default; }
.ev-foto-nota { font-size:11px; color:#94a3b8; margin:6px 0 0; }
.ev-md-err { color:#991b1b; font-size:13px; margin:10px 0 0; }
.ev-acc { display:flex; align-items:center; gap:8px; margin-top:16px; }
.ev-guardar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ev-guardar:disabled { opacity:.5; }
.ev-doc-add { display:flex; gap:8px; align-items:center; margin-bottom:10px; flex-wrap:wrap; }
.ev-doc-tipo { border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:13px; min-width:180px; }
.ev-doc-obs { border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:13px; flex:1; min-width:140px; }
.ev-doc-file { font-size:12px; }
.ev-doc-add-btn { background:#1b4332; color:#fff; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ev-doc-add-btn:disabled { opacity:.5; }
.ev-dt { width:100%; border-collapse:collapse; font-size:12.5px; border:1px solid #e2e8f0; }
.ev-dt th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11px; }
.ev-dt td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.btn-ojo { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 4px; }
.ev-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; }
.ev-vacio { text-align:center; color:#94a3b8; padding:16px; border:1px dashed #e2e8f0; border-radius:8px; }
.ev-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ev-help-md { background:#fff; border-radius:14px; padding:22px; width:min(540px,94vw); } .ev-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ev-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
