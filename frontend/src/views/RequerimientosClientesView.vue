<!-- RequerimientosClientesView.vue — Requerimientos por Cliente (requerimientos_clientes.scx). -->
<template>
  <div class="rc-view">
    <div class="rc-cab">
      <div class="rc-ico">🔐</div>
      <div class="rc-tx"><h1>Requerimientos por Cliente</h1><p>Asignación de requerimientos de acceso y documentación del cliente</p></div>
      <button class="rc-ia" @click="modalIA = true">🤖 IA</button>
      <button class="rc-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/requerimientos-clientes" titulo="Asistente IA — Requerimientos por Cliente"
            subtitulo="Preguntá sobre cómo asignar requerimientos a un cliente"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo asigno requerimientos?','¿Qué es la documentación exclusiva?','¿Cómo cargo los emails?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['rc-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="rc-body">
      <!-- Cabecera cliente -->
      <div class="rc-cli">
        <div class="rc-cli-row">
          <label>Cliente</label>
          <input v-model.number="codCli" type="number" class="rc-cod" @keyup.enter="cargarCliente" placeholder="Código" />
          <button class="rc-b" title="Buscar por nombre" @click="lupa = true">🔍 Buscar</button>
          <span v-if="cli.bloqueado" class="rc-bloq">⛔ CLIENTE BLOQUEADO — NO USAR</span>
        </div>
        <div class="rc-cli-grid">
          <div><label>Nombre</label><input :value="cli.nombre" readonly /></div>
          <div><label>Teléfono</label><input :value="cli.telefono" readonly /></div>
          <div><label>Domicilio</label><input :value="cli.domicilio" readonly /></div>
          <div><label>Localidad</label><input :value="cli.localidad" readonly /></div>
          <div class="rc-col2"><label>Em@il</label><input :value="cli.email" readonly /></div>
        </div>
      </div>

      <template v-if="codCliCargado">
        <!-- Grilla de requerimientos -->
        <div class="rc-sec-tit">Requerimientos de acceso</div>
        <div class="rc-grid-wrap">
          <table class="rc-grid">
            <thead><tr>
              <th style="width:38px;text-align:center">OK</th>
              <th>Requerimientos</th>
              <th style="width:50px;text-align:center">Cód.</th>
              <th style="width:90px;text-align:center">Días Venc.</th>
              <th style="width:100px;text-align:center">Último Envío</th>
            </tr></thead>
            <tbody>
              <tr v-for="r in requerimientos" :key="r.cod" :class="{ nocomun: !r.comun }">
                <td style="text-align:center"><input type="checkbox" v-model="r.elegir" /></td>
                <td>{{ r.descripcion }}</td>
                <td style="text-align:center" class="rc-cod-cel">{{ r.cod }}</td>
                <td style="text-align:center">{{ r.dias }}</td>
                <td style="text-align:center">{{ r.ult_envio ? fmt(r.ult_envio) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="rc-leyenda"><span class="rc-chip-nc"></span> En rojo: requerimiento no común (requiere documentación exclusiva del cliente).</p>

        <!-- Documentación exclusiva del cliente -->
        <div class="rc-sec-tit dark">📎 Documentación exclusiva del cliente</div>
        <div class="rc-doc-add">
          <select v-model="doc.tipo" class="rc-doc-tipo">
            <option value="">— Tipo —</option>
            <option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.nombre }}</option>
          </select>
          <input ref="fileInput" type="file" @change="onFile" class="rc-doc-file" />
          <button class="rc-doc-add-btn" :disabled="subiendo" @click="aceptarDoc">{{ subiendo ? '⟳…' : '＋ Agregar documento' }}</button>
        </div>
        <table v-if="documentos.length" class="rc-dt">
          <thead><tr><th style="width:44px">Ver</th><th style="width:50px">N#</th><th>Tipo</th><th>Nombre</th><th>Ext</th><th>Creado</th><th>Usuario</th><th style="width:44px"></th></tr></thead>
          <tbody>
            <tr v-for="d in documentos" :key="d.id">
              <td><button class="btn-ojo" title="Visualizar" @click="ver(d)">👁️</button></td>
              <td>{{ d.nro }}</td><td>{{ d.detalle || d.tipo }}</td><td>{{ d.nombre }}.{{ (d.ext || '').toLowerCase() }}</td>
              <td>{{ (d.ext || '').toLowerCase() }}</td><td>{{ d.creado }}</td><td>{{ d.usuario }}</td>
              <td><button class="rc-i del" title="Eliminar" @click="eliminarDoc(d)">🗑️</button></td>
            </tr>
          </tbody>
        </table>
        <div v-else class="rc-vacio">El cliente no tiene documentación exclusiva cargada.</div>

        <!-- Observaciones / contactos / emails -->
        <div class="rc-2col">
          <div class="rc-panel">
            <div class="rc-panel-tit">Observaciones</div>
            <textarea v-model="form.observaciones" rows="3" maxlength="200"></textarea>
            <div class="rc-cont">
              <label>Contacto 1</label><input v-model="form.contacto1" maxlength="50" />
              <label>Teléfono 1</label><input v-model="form.telefono1" maxlength="50" />
              <label>Contacto 2</label><input v-model="form.contacto2" maxlength="50" />
              <label>Teléfono 2</label><input v-model="form.telefono2" maxlength="50" />
            </div>
          </div>
          <div class="rc-panel">
            <div class="rc-panel-tit">Lista de email</div>
            <div class="rc-emails">
              <div v-for="(_, i) in form.emails" :key="i" class="rc-email">
                <span>{{ i + 1 }})</span><input v-model="form.emails[i]" maxlength="100" type="email" @blur="form.emails[i] = (form.emails[i] || '').toLowerCase().trim()" />
              </div>
            </div>
          </div>
        </div>

        <div class="rc-acc">
          <button class="rc-reset" @click="cargarCliente">↺ Reset</button>
          <span style="flex:1"></span>
          <button class="rc-guardar" :disabled="guardando || cli.bloqueado" @click="confirmar">{{ guardando ? '⟳ Guardando…' : '✔ CONFIRMAR REQUERIMIENTOS DE ACCESO' }}</button>
        </div>
      </template>
      <div v-else-if="!cargando" class="rc-elija">Ingrese o busque un cliente para comenzar.</div>
    </div>

    <ClienteBuscar v-if="lupa" @select="onClienteSel" @close="lupa = false" />
    <DocViewer ref="docVisor" />

    <Teleport to="body">
      <div v-if="ayuda" class="rc-ov" @click.self="ayuda = false">
        <div class="rc-help-md">
          <h3>❓ Ayuda — Requerimientos por Cliente</h3>
          <ul>
            <li>Busque el cliente (por código o con 🔍 por nombre). Sus datos se traen de la base de gestión.</li>
            <li>Tilde los <b>requerimientos de acceso</b> que el cliente debe cumplir. En rojo, los no comunes.</li>
            <li>Cargue <b>observaciones</b>, contactos y hasta <b>10 emails</b> de envío.</li>
            <li>Adjunte la <b>documentación exclusiva</b> del cliente (con 👁️ se visualiza; al eliminar queda en historial).</li>
            <li>Presione <b>Confirmar requerimientos de acceso</b> para guardar.</li>
          </ul>
          <div class="rc-acc"><span style="flex:1"></span><button class="rc-guardar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'
import ClienteBuscar from '@/components/ClienteBuscar.vue'
import DocViewer from '@/components/DocViewer.vue'

const codCli = ref<number | null>(null)
const codCliCargado = ref(0)
const cli = ref<any>({ cod: 0, nombre: '', domicilio: '', email: '', telefono: '', localidad: '', bloqueado: false })
const requerimientos = ref<any[]>([])
const documentos = ref<any[]>([])
const tipos = ref<{ cod: string; nombre: string }[]>([])
const form = ref<any>({ observaciones: '', contacto1: '', telefono1: '', contacto2: '', telefono2: '', emails: Array(10).fill('') })
const cargando = ref(false); const guardando = ref(false); const subiendo = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false); const lupa = ref(false)
const doc = ref<{ tipo: string; file: File | null }>({ tipo: '', file: null })
const fileInput = ref<HTMLInputElement | null>(null)
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)

const fmt = (s: string) => s ? s.split('-').reverse().join('/') : '—'
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

async function cargarInit () { try { const { data } = await api.get('/requerimientos-clientes/init'); tipos.value = data.tipos ?? [] } catch { /* */ } }

async function cargarCliente () {
  if (!codCli.value || codCli.value <= 0) { flash('Ingrese el código de cliente.', true); return }
  cargando.value = true
  try {
    const { data } = await api.get(`/requerimientos-clientes/cliente/${codCli.value}`)
    cli.value = data.cliente
    requerimientos.value = data.requerimientos ?? []
    documentos.value = data.documentos ?? []
    form.value = {
      observaciones: data.datos.observaciones, contacto1: data.datos.contacto1, telefono1: data.datos.telefono1,
      contacto2: data.datos.contacto2, telefono2: data.datos.telefono2,
      emails: (data.datos.emails ?? []).concat(Array(10).fill('')).slice(0, 10),
    }
    codCliCargado.value = cli.value.cod
    if (cli.value.bloqueado) flash('Atención: cliente bloqueado (no usar).', true)
  } catch (e: any) {
    codCliCargado.value = 0
    flash(e?.response?.data?.message ?? 'No se pudo cargar el cliente.', true)
  } finally { cargando.value = false }
}
function onClienteSel (c: any) { lupa.value = false; codCli.value = c.cod; cargarCliente() }

async function confirmar () {
  if (!codCliCargado.value) return
  if (!confirm('¿Confirma los requerimientos de acceso de este cliente?')) return
  guardando.value = true
  const body = {
    nombre: cli.value.nombre,
    observaciones: form.value.observaciones, contacto1: form.value.contacto1, telefono1: form.value.telefono1,
    contacto2: form.value.contacto2, telefono2: form.value.telefono2,
    emails: form.value.emails,
    requerimientos: requerimientos.value.filter((r: any) => r.elegir).map((r: any) => r.cod),
  }
  try { await api.post(`/requerimientos-clientes/cliente/${codCliCargado.value}`, body); flash('Requerimientos confirmados.'); await cargarCliente() }
  catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

const onFile = (ev: Event) => { doc.value.file = (ev.target as HTMLInputElement).files?.[0] ?? null }
async function aceptarDoc () {
  if (!doc.value.tipo) { flash('Debe ingresar el tipo de documento.', true); return }
  if (!doc.value.file) { flash('Debe seleccionar el documento.', true); return }
  if (!confirm(`¿Desea agregar el archivo "${doc.value.file.name}"?`)) return
  subiendo.value = true
  const fd = new FormData(); fd.append('tipo', doc.value.tipo); fd.append('archivo', doc.value.file)
  try {
    const { data } = await api.post(`/requerimientos-clientes/cliente/${codCliCargado.value}/documento`, fd)
    documentos.value = data.documentos ?? []
    doc.value = { tipo: '', file: null }; if (fileInput.value) fileInput.value.value = ''
    flash('Documento agregado.')
  } catch (e: any) { flash(e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo agregar.', true) }
  finally { subiendo.value = false }
}
async function ver (d: any) {
  try { const resp = await api.get(`/requerimientos-clientes/documento/${d.id}/ver`, { responseType: 'blob' }); docVisor.value?.open(resp.data as Blob, `${d.nombre}.${(d.ext || '').toLowerCase()}`) }
  catch { flash('No se pudo abrir el documento.', true) }
}
async function eliminarDoc (d: any) {
  if (!confirm(`¿Elimina el documento "${d.nombre}.${(d.ext || '').toLowerCase()}"? Quedará en el historial.`)) return
  try { const { data } = await api.delete(`/requerimientos-clientes/documento/${d.id}`); documentos.value = data.documentos ?? []; flash('Documento eliminado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

cargarInit()
</script>

<style scoped>
.rc-view { display:flex; flex-direction:column; min-height:100%; }
.rc-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.rc-ico { font-size:28px; } .rc-tx h1 { margin:0; font-size:19px; color:#1e293b; } .rc-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.rc-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rc-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rc-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .rc-msg.ok { background:#d1fae5; color:#065f46; } .rc-msg.err { background:#fee2e2; color:#991b1b; }
.rc-body { padding:16px 18px; max-width:1000px; }
.rc-cli { background:#eef2ff; border:1px solid #c7d2fe; border-radius:10px; padding:14px; }
.rc-cli-row { display:flex; align-items:center; gap:10px; margin-bottom:10px; } .rc-cli-row label { font-size:12px; font-weight:700; color:#3730a3; }
.rc-cod { width:110px; border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:14px; }
.rc-b { background:#2d6a9f; color:#fff; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.rc-bloq { color:#991b1b; font-weight:800; font-size:13px; }
.rc-cli-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 14px; }
.rc-cli-grid label { font-size:11px; font-weight:600; color:#475569; display:block; }
.rc-cli-grid input { width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:7px 9px; font-size:13px; background:#fff; color:#1e293b; box-sizing:border-box; }
.rc-col2 { grid-column:1 / -1; }
.rc-sec-tit { margin:18px 0 8px; font-size:14px; font-weight:800; color:#14532d; } .rc-sec-tit.dark { color:#1e293b; }
.rc-grid-wrap { max-height:230px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.rc-grid { width:100%; border-collapse:collapse; font-size:13px; }
.rc-grid th { position:sticky; top:0; background:#1e293b; color:#fff; padding:6px 9px; text-align:left; font-size:11.5px; }
.rc-grid td { padding:5px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.rc-grid tr.nocomun td { background:#fee2e2; } .rc-cod-cel { color:#2d6a9f; font-weight:700; }
.rc-grid input[type=checkbox] { width:15px; height:15px; accent-color:#1b4332; }
.rc-leyenda { font-size:12px; color:#64748b; margin:6px 2px; display:flex; align-items:center; gap:6px; }
.rc-chip-nc { width:13px; height:13px; border-radius:3px; background:#fee2e2; border:1px solid #fca5a5; display:inline-block; }
.rc-doc-add { display:flex; gap:8px; align-items:center; margin-bottom:8px; flex-wrap:wrap; }
.rc-doc-tipo { border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:13px; min-width:200px; }
.rc-doc-file { font-size:12px; }
.rc-doc-add-btn { background:#1b4332; color:#fff; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .rc-doc-add-btn:disabled { opacity:.5; }
.rc-dt { width:100%; border-collapse:collapse; font-size:12.5px; border:1px solid #e2e8f0; }
.rc-dt th { background:#475569; color:#fff; padding:6px 8px; text-align:left; font-size:11px; }
.rc-dt td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.btn-ojo { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 4px; }
.rc-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; }
.rc-vacio { text-align:center; color:#94a3b8; padding:14px; border:1px dashed #e2e8f0; border-radius:8px; }
.rc-2col { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px; }
.rc-panel { border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#fafdff; }
.rc-panel-tit { font-size:12px; font-weight:800; color:#1e293b; margin-bottom:8px; letter-spacing:.3px; }
.rc-panel textarea { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:13px; box-sizing:border-box; color:#1e293b; font-family:inherit; resize:vertical; }
.rc-cont { display:grid; grid-template-columns:auto 1fr; gap:6px 8px; align-items:center; margin-top:10px; }
.rc-cont label { font-size:11.5px; font-weight:600; color:#475569; }
.rc-cont input { border:1px solid #c8d8ea; border-radius:6px; padding:6px 9px; font-size:13px; color:#1e293b; }
.rc-emails { display:grid; grid-template-columns:1fr 1fr; gap:6px 10px; }
.rc-email { display:flex; align-items:center; gap:5px; } .rc-email span { font-size:11px; color:#64748b; width:20px; text-align:right; }
.rc-email input { flex:1; border:1px solid #c8d8ea; border-radius:6px; padding:6px 8px; font-size:12.5px; color:#1e293b; box-sizing:border-box; }
.rc-acc { display:flex; align-items:center; gap:8px; margin-top:18px; }
.rc-reset { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:600; }
.rc-guardar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:10px 22px; cursor:pointer; font-weight:800; font-size:13px; } .rc-guardar:disabled { opacity:.5; cursor:default; }
.rc-elija { text-align:center; color:#94a3b8; padding:30px; }
.rc-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.rc-help-md { background:#fff; border-radius:14px; padding:22px; width:min(540px,94vw); } .rc-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .rc-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
