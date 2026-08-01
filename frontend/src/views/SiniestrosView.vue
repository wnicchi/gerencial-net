<!-- SiniestrosView.vue — ART Siniestros: ABM unificado (grilla + alta/ver/editar/eliminar/imprimir).
     Reemplaza los módulos separados Agregar / Modificar / Consultar / Eliminar / Impresión.
     Preparado para abrirse desde la ficha del empleado (prop :empleado) — Propuesta "módulo madre". -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">⚠️</div>
      <div class="ab-tx"><h1>ART Siniestros</h1><p>Alta, consulta, edición, eliminación e impresión de siniestros</p></div>
      <button class="ab-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/siniestros-consultar" titulo="Asistente IA — Siniestros"
            subtitulo="Preguntá sobre el módulo de siniestros"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo cargo un siniestro?','¿Qué significa Propio/Terceros?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <!-- Barra de herramientas -->
    <div class="ab-tools">
      <input v-if="!empBloqueado" v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por Nº, empleado o detalle…" />
      <select v-model="filtroEstado" class="ab-estado">
        <option value="">Todos los estados</option>
        <option value="pendiente">Pendiente de resolución</option>
        <option value="cobrado">Cobrados</option>
        <option value="judicial">Reclamo judicial</option>
        <option value="cerrado">Cerrados</option>
      </select>
      <span class="ab-count">{{ filtradas.length }} siniestro(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo siniestro</button>
    </div>

    <!-- Grilla -->
    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:70px">Nº</th>
            <th style="width:100px">Fecha</th>
            <th>Empleado</th>
            <th>Estado</th>
            <th style="width:120px" class="r">Monto est.</th>
            <th style="width:120px" class="r">Monto cobr.</th>
            <th style="width:150px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="7" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="7" class="ab-vacio">No hay siniestros para mostrar.</td></tr>
          <tr v-for="s in filtradas" :key="s.nro" @dblclick="abrirEditar(s)">
            <td class="ab-nro">{{ s.nro }}</td>
            <td>{{ fmt(s.fecha) }}</td>
            <td>{{ s.empleado }} — {{ s.empleado_nombre }}</td>
            <td><span v-for="e in estadosDe(s)" :key="e.k" :class="['ab-chip', e.cls]">{{ e.t }}</span></td>
            <td class="r">{{ money(s.monto_estimado) }}</td>
            <td class="r">{{ money(s.monto_cobrado) }}</td>
            <td class="c ab-acc">
              <button class="ab-b ver"  title="Ver"      @click="abrirVer(s)">👁️</button>
              <button class="ab-b edi"  title="Editar"   @click="abrirEditar(s)">✏️</button>
              <button class="ab-b imp"  title="Imprimir" @click="imprimir(s)">🖨️</button>
              <button class="ab-b del"  title="Eliminar" @click="eliminar(s)">🗑️</button>
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
            <span>{{ modo === 'nuevo' ? '＋ Nuevo siniestro' : modo === 'ver' ? `👁️ Siniestro Nº ${form.nro}` : `✏️ Editar siniestro Nº ${form.nro}` }}</span>
            <button class="ab-x" @click="cerrar">✕</button>
          </div>

          <div class="ab-md-body">
            <div class="ab-grid">
              <!-- Columna izquierda -->
              <div class="ab-col">
                <div class="ab-row2">
                  <div><label>Fecha Siniestro *</label><input v-model="form.fecha" type="date" :disabled="ro" /></div>
                  <div><label>Fecha Alta</label><input v-model="form.fecha_alta" type="date" :disabled="ro" /></div>
                </div>

                <label>Empleado *</label>
                <template v-if="modo === 'nuevo'">
                  <div v-if="empBloqueado" class="ab-emp-sel">✔ {{ form.empleado }} — {{ nombreEmp }}</div>
                  <template v-else>
                    <div class="ab-emp">
                      <input v-model="busqueda" placeholder="Código, legajo o nombre…" @input="buscarEmp" @focus="buscarEmp" />
                      <ul v-if="resultados.length" class="ab-result">
                        <li v-for="r in resultados" :key="r.PER_COD" @click="elegirEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>Cód. {{ r.PER_COD }} · Leg. {{ (r.PER_LEG||'').toString().trim() }}</span></li>
                      </ul>
                    </div>
                    <div v-if="form.empleado" class="ab-emp-sel">✔ {{ form.empleado }} — {{ nombreEmp }}</div>
                  </template>
                </template>
                <template v-else>
                  <div class="ab-emp-sel">{{ form.empleado }} — {{ form.empleado_nombre }}
                    <button v-if="!ro" class="ab-cambiar" @click="cambiarEmp = !cambiarEmp">cambiar</button></div>
                  <div v-if="cambiarEmp && !ro" class="ab-emp">
                    <input v-model="busqueda" placeholder="Código, legajo o nombre…" @input="buscarEmp" />
                    <ul v-if="resultados.length" class="ab-result">
                      <li v-for="r in resultados" :key="r.PER_COD" @click="elegirEmp(r)"><b>{{ (r.PER_NOM||'').trim() }}</b><span>Cód. {{ r.PER_COD }}</span></li>
                    </ul>
                  </div>
                </template>

                <div class="ab-row2">
                  <div><label>Monto Estimado</label><input v-model.number="form.monto_estimado" type="number" step="0.01" :disabled="ro" /></div>
                  <div><label>Monto Cobrado</label><input v-model.number="form.monto_cobrado" type="number" step="0.01" :disabled="ro" /></div>
                </div>
                <div class="ab-row2">
                  <div><label>Fecha Cobro</label><input v-model="form.fecha_cobro" type="date" :disabled="ro" /></div>
                  <div><label>Banco Cobro</label><input v-model="form.banco_cobro" maxlength="100" :disabled="ro" /></div>
                </div>
                <div class="ab-row2">
                  <div><label>Nro. de Siniestro</label><input v-model="form.nro_siniestro" maxlength="30" :disabled="ro" /></div>
                  <div><label>Fecha Próximo Control</label><input v-model="form.fecha_proximo_control" type="date" :disabled="ro" /></div>
                </div>
                <label>Detalle *</label><textarea v-model="form.detalle" rows="4" maxlength="2000" :disabled="ro"></textarea>
              </div>

              <!-- Columna derecha -->
              <div class="ab-col">
                <div class="ab-estados">
                  <div class="ab-estados-tit">Estados</div>
                  <label class="ab-chk"><input type="checkbox" v-model="form.pendiente_resolucion" :disabled="ro" /> Pendiente de resolución</label>
                  <label class="ab-chk"><input type="checkbox" v-model="form.cobrado" :disabled="ro" /> Siniestro cobrado</label>
                  <label class="ab-chk"><input type="checkbox" v-model="form.pendiente_judicial" :disabled="ro" /> Pendiente reclamo judicial</label>
                  <label class="ab-chk"><input type="checkbox" v-model="form.denuncia_preventiva" :disabled="ro" /> Denuncia preventiva</label>
                  <div class="ab-radio-row">
                    <label class="ab-rad"><input type="radio" value="P" v-model="form.reclamo" :disabled="ro" /> Propio</label>
                    <label class="ab-rad"><input type="radio" value="T" v-model="form.reclamo" :disabled="ro" /> Terceros</label>
                    <button v-if="form.reclamo && !ro" class="ab-clear" @click="form.reclamo = ''">✕</button>
                  </div>
                </div>
                <label>Dictamen</label><textarea v-model="form.dictamen" rows="6" maxlength="2000" :disabled="ro"></textarea>
              </div>
            </div>

            <!-- Fotos + documentos (solo sobre un siniestro existente) -->
            <div v-if="modo !== 'nuevo'" class="ab-2col">
              <div>
                <div class="ab-sec-tit">Fotos {{ fotos.length ? `(${fotoIdx + 1}/${fotos.length})` : '' }}</div>
                <div v-if="!fotos.length" class="ab-vacio2">Sin fotos.</div>
                <div v-else class="ab-foto-box">
                  <button class="ab-nav" :disabled="fotoIdx <= 0" @click="fotoIdx--">◀</button>
                  <div class="ab-foto-wrap"><img v-if="fotos[fotoIdx].foto" :src="fotos[fotoIdx].foto" @click="ampliarFoto = fotos[fotoIdx].foto" /><div v-else class="ab-foto-ph">Sin imagen</div>
                    <div v-if="fotos[fotoIdx].comentario" class="ab-foto-com">{{ fotos[fotoIdx].comentario }}</div></div>
                  <button class="ab-nav" :disabled="fotoIdx >= fotos.length - 1" @click="fotoIdx++">▶</button>
                </div>
              </div>
              <div>
                <div class="ab-sec-tit">Documentos asociados</div>
                <table v-if="documentos.length" class="ab-dt">
                  <thead><tr><th style="width:40px">Ver</th><th>Orden</th><th>Tipo</th><th>Nombre</th><th>Creado</th></tr></thead>
                  <tbody>
                    <tr v-for="d in documentos" :key="d.orden">
                      <td><button class="ab-ojo" title="Visualizar" @click="verDoc(d)">👁️</button></td>
                      <td>{{ d.orden }}</td><td>{{ d.detalle_tipo || d.tipo }}</td><td>{{ d.nombre }}.{{ (d.ext||'').toLowerCase() }}</td><td>{{ d.creado }}</td>
                    </tr>
                  </tbody>
                </table>
                <div v-else class="ab-vacio2">Sin documentos.</div>
              </div>
            </div>

            <p v-if="formError" class="ab-md-err">{{ formError }}</p>
          </div>

          <div class="ab-md-foot">
            <button v-if="modo === 'editar'" class="ab-reintegro" @click="modalReintegro = true">＋ Agregar Reintegro</button>
            <button v-if="modo !== 'ver'" class="ab-imp2" @click="imprimirActual">🖨️ Imprimir</button>
            <span style="flex:1"></span>
            <button class="ab-cancel" @click="cerrar">{{ ro ? 'Cerrar' : 'Cancelar' }}</button>
            <button v-if="!ro" class="ab-confirm" :disabled="guardando" @click="guardar">{{ guardando ? '⟳ Grabando…' : (modo === 'nuevo' ? '✔ Confirmar siniestro' : '✔ Confirmar cambios') }}</button>
          </div>
        </div>
      </div>

      <!-- Modal reintegro -->
      <div v-if="modalReintegro" class="ab-ov ab-ov2" @click.self="modalReintegro = false">
        <div class="ab-md-small">
          <h3>Agregar reintegro</h3>
          <p class="ab-md-p">Marca el siniestro como <b>cobrado</b> y registra el monto.</p>
          <label>Monto cobrado</label>
          <input v-model.number="montoReintegro" type="number" step="0.01" />
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-cancel" @click="modalReintegro = false">Cancelar</button><button class="ab-confirm" :disabled="reintegrando" @click="agregarReintegro">{{ reintegrando ? '⟳' : 'Aceptar' }}</button></div>
        </div>
      </div>

      <!-- Foto ampliada -->
      <div v-if="ampliarFoto" class="ab-img-ov" @click.self="ampliarFoto = ''"><img :src="ampliarFoto" /></div>

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

      <!-- Ayuda -->
      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — ART Siniestros</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista todos los siniestros. Buscá por número, empleado o detalle, o filtrá por estado.</li>
            <li><b>＋ Nuevo siniestro</b> da de alta uno nuevo.</li>
            <li>En cada fila: <b>👁️ Ver</b> (solo lectura), <b>✏️ Editar</b>, <b>🖨️ Imprimir</b> el informe PDF y <b>🗑️ Eliminar</b>.</li>
            <li>En <b>Editar</b> podés registrar un <b>reintegro</b> y ver las fotos y documentos asociados.</li>
          </ul>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-confirm" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <DocViewer ref="docVisor" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'
import DocViewer from '@/components/DocViewer.vue'

// Prop opcional: al abrirse desde la ficha del empleado, filtra la grilla y precarga el empleado en el alta.
const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })

const vacio = () => ({ nro: 0, fecha: '', fecha_alta: '', empleado: 0, empleado_nombre: '', monto_estimado: 0, monto_cobrado: 0, fecha_cobro: '', banco_cobro: '', nro_siniestro: '', fecha_proximo_control: '', detalle: '', dictamen: '', pendiente_resolucion: false, cobrado: false, pendiente_judicial: false, denuncia_preventiva: false, reclamo: '' })

const rows = ref<any[]>([]); const cargando = ref(false)
const filtroTxt = ref(''); const filtroEstado = ref('')
const modo = ref<'' | 'nuevo' | 'ver' | 'editar'>('')
const form = ref<any>(vacio())
const fotos = ref<any[]>([]); const fotoIdx = ref(0); const documentos = ref<any[]>([]); const ampliarFoto = ref('')
const guardando = ref(false); const formError = ref('')
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const cambiarEmp = ref(false); const nombreEmp = ref('')
const modalReintegro = ref(false); const montoReintegro = ref(0); const reintegrando = ref(false)
const docVisor = ref<InstanceType<typeof DocViewer> | null>(null)
const empBloqueado = computed(() => !!props.empleado)

const ro = computed(() => modo.value === 'ver')

const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

const filtradas = computed(() => {
  const q = filtroTxt.value.trim().toLowerCase()
  return rows.value.filter(s => {
    if (filtroEstado.value === 'pendiente' && !s.pendiente_resolucion) return false
    if (filtroEstado.value === 'cobrado' && !s.cobrado) return false
    if (filtroEstado.value === 'judicial' && !s.pendiente_judicial) return false
    if (filtroEstado.value === 'cerrado' && !s.cerrado) return false
    if (!q) return true
    return String(s.nro).includes(q) || (s.empleado_nombre || '').toLowerCase().includes(q)
      || String(s.empleado).includes(q) || (s.detalle || '').toLowerCase().includes(q)
  })
})

function estadosDe (s: any) {
  const e: Array<{ k: string; t: string; cls: string }> = []
  if (s.pendiente_resolucion) e.push({ k: 'p', t: 'Pendiente', cls: 'amber' })
  if (s.cobrado) e.push({ k: 'c', t: 'Cobrado', cls: 'green' })
  if (s.pendiente_judicial) e.push({ k: 'j', t: 'Judicial', cls: 'red' })
  if (s.denuncia_preventiva) e.push({ k: 'd', t: 'Denuncia', cls: 'slate' })
  if (s.cerrado) e.push({ k: 'x', t: 'Cerrado', cls: 'dark' })
  return e
}

async function cargarGrilla () {
  cargando.value = true
  try {
    const params: any = {}
    if (props.empleado) params.emp = props.empleado
    rows.value = (await api.get('/siniestros/grilla', { params })).data ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

// ── Búsqueda de empleado (alta / cambio) ──
const busqueda = ref(''); const resultados = ref<any[]>([]); let dq: any = null
const buscarEmp = () => {
  clearTimeout(dq); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  dq = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8, activo: 1 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
function elegirEmp (r: any) {
  form.value.empleado = Number(r.PER_COD)
  form.value.empleado_nombre = (r.PER_NOM || '').trim()
  nombreEmp.value = (r.PER_NOM || '').trim()
  busqueda.value = ''; resultados.value = []; cambiarEmp.value = false
}

// ── Abrir modal ──
function abrirNuevo () {
  form.value = vacio()
  if (props.empleado) { form.value.empleado = props.empleado; form.value.empleado_nombre = props.empleadoNombre; nombreEmp.value = props.empleadoNombre }
  fotos.value = []; documentos.value = []; fotoIdx.value = 0; nombreEmp.value = props.empleadoNombre || ''
  busqueda.value = ''; resultados.value = []; cambiarEmp.value = false; formError.value = ''
  modo.value = 'nuevo'
}
async function abrirDetalle (nro: number, m: 'ver' | 'editar') {
  formError.value = ''; cambiarEmp.value = false; busqueda.value = ''; resultados.value = []; fotoIdx.value = 0
  try {
    const { data } = await api.get(`/siniestros/${nro}`)
    form.value = { ...data.siniestro }
    fotos.value = data.fotos ?? []; documentos.value = data.documentos ?? []
    modo.value = m
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo abrir el siniestro.', true) }
}
const abrirVer = (s: any) => abrirDetalle(s.nro, 'ver')
const abrirEditar = (s: any) => abrirDetalle(s.nro, 'editar')
function cerrar () { modo.value = ''; form.value = vacio(); fotos.value = []; documentos.value = [] }

// ── Guardar (alta / edición) ──
async function guardar () {
  const f = form.value
  if (!f.fecha) { formError.value = 'Debe ingresar la fecha.'; return }
  if (!f.empleado) { formError.value = 'Debe ingresar el empleado.'; return }
  if (!f.detalle?.trim()) { formError.value = 'Debe ingresar el detalle.'; return }
  if (!confirm('¿Procede con la grabación del siniestro?')) return
  guardando.value = true; formError.value = ''
  try {
    if (modo.value === 'nuevo') {
      const { data } = await api.post('/siniestros', { ...f })
      flash(`Siniestro ${data.nro} grabado correctamente.`)
    } else {
      await api.put(`/siniestros/${f.nro}`, { ...f })
      flash('Siniestro modificado.')
    }
    await cargarGrilla()
    cerrar()
  } catch (e: any) { formError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo grabar.' }
  finally { guardando.value = false }
}

// ── Eliminar ──
async function eliminar (s: any) {
  if (!confirm(`¿Eliminar el siniestro Nº ${s.nro} de ${s.empleado_nombre}?\nSe borrarán también sus fotos y documentos.`)) return
  try {
    await api.delete(`/siniestros/${s.nro}`)
    flash(`Siniestro ${s.nro} eliminado.`)
    await cargarGrilla()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

// ── Reintegro ──
async function agregarReintegro () {
  if (!montoReintegro.value || montoReintegro.value <= 0) { flash('Debe ingresar el monto cobrado.', true); return }
  reintegrando.value = true
  try {
    await api.post(`/siniestros/${form.value.nro}/reintegro`, { monto_cobrado: montoReintegro.value })
    form.value.cobrado = true; form.value.monto_cobrado = montoReintegro.value
    modalReintegro.value = false; montoReintegro.value = 0
    flash('Reintegro registrado (siniestro marcado como cobrado).')
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo registrar el reintegro.', true) }
  finally { reintegrando.value = false }
}

// ── Documentos ──
async function verDoc (d: any) {
  try { const resp = await api.get(`/siniestros/${form.value.nro}/documento/${d.orden}/ver`, { responseType: 'blob' }); docVisor.value?.open(resp.data as Blob, `${d.nombre}.${(d.ext || '').toLowerCase()}`) }
  catch { flash('No se pudo abrir el documento.', true) }
}

// ── Impresión PDF ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimirActual = () => generarPdf(form.value.nro)
const imprimir = (s: any) => generarPdf(s.nro)

async function generarPdf (nro: number) {
  try {
    const { data } = await api.get(`/siniestros/${nro}`)
    const s = data.siniestro; const fs = data.fotos ?? []; const docs = data.documentos ?? []
    let fotoEmpleado = ''
    try { fotoEmpleado = (await api.get(`/empleados/${s.empleado}/foto`)).data?.foto || '' } catch { /* */ }

    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const ML = 15, PW = 210, PH = 297; let y = 16
    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(127, 29, 29)
    doc.text(`SINIESTRO ART Nº ${s.nro}`, ML, y); doc.setTextColor(0, 0, 0)
    if (fotoEmpleado) { try { doc.addImage(fotoEmpleado, 'JPEG', PW - ML - 26, y - 10, 26, 30) } catch { /* */ } }
    y += 10
    const linea = (lbl: string, val: string) => { doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(lbl, ML, y); doc.setFont('helvetica', 'normal'); doc.text(String(val || '—'), ML + 42, y); y += 6 }
    linea('Empleado:', `${s.empleado} — ${s.empleado_nombre}`)
    linea('Fecha siniestro:', fmt(s.fecha)); linea('Fecha alta:', fmt(s.fecha_alta))
    linea('Monto estimado:', money(s.monto_estimado)); linea('Monto cobrado:', money(s.monto_cobrado))
    linea('Fecha cobro:', fmt(s.fecha_cobro)); linea('Banco cobro:', s.banco_cobro)
    linea('Nro. siniestro:', s.nro_siniestro); linea('Próximo control:', fmt(s.fecha_proximo_control))
    const est = [s.pendiente_resolucion && 'Pendiente resolución', s.cobrado && 'Cobrado', s.pendiente_judicial && 'Reclamo judicial', s.denuncia_preventiva && 'Denuncia preventiva'].filter(Boolean).join(', ')
    linea('Estados:', est || 'ninguno'); linea('Reclamo:', s.reclamo === 'P' ? 'Propio' : s.reclamo === 'T' ? 'Terceros' : '—')
    y += 2
    const memo = (titulo: string, texto: string) => {
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(titulo, ML, y); y += 5
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9)
      for (const ln of doc.splitTextToSize(texto || '—', PW - 2 * ML)) { if (y > PH - 14) { doc.addPage(); y = 16 } doc.text(ln, ML, y); y += 5 }
      y += 3
    }
    memo('DETALLE:', s.detalle); memo('DICTAMEN:', s.dictamen)

    if (docs.length) {
      if (y > PH - 30) { doc.addPage(); y = 16 }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('DOCUMENTOS ASOCIADOS:', ML, y); y += 6
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5)
      for (const d of docs) { if (y > PH - 12) { doc.addPage(); y = 16 } doc.text(`• [${d.tipo}] ${d.nombre}.${(d.ext || '').toLowerCase()}  (${d.creado})`, ML + 2, y); y += 5 }
      y += 3
    }
    const conFoto = fs.filter((f: any) => f.foto)
    if (conFoto.length) {
      doc.addPage(); y = 16; doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.text('FOTOS DEL SINIESTRO', ML, y); y += 8
      let x = ML
      for (const f of conFoto) {
        if (y > PH - 60) { doc.addPage(); y = 16; x = ML }
        try { doc.addImage(f.foto, 'JPEG', x, y, 85, 60) } catch { /* */ }
        if (f.comentario) { doc.setFont('helvetica', 'italic'); doc.setFontSize(7.5); doc.text(doc.splitTextToSize(f.comentario, 85).slice(0, 2), x, y + 64) }
        if (x === ML) { x = ML + 95 } else { x = ML; y += 72 }
      }
    }

    cerrarPdf(); pdfNombre.value = `Siniestro_${s.nro}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el informe.', true) }
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
.ab-estado { border:1px solid #c8d8ea; border-radius:8px; padding:9px 10px; font-size:13px; color:#1e293b; background:#fff; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#7f1d1d; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.r, .ab-tabla td.r { text-align:right; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover, .ab-tabla tr:hover { background:#f8fafc; }
.ab-nro { font-weight:800; color:#7f1d1d; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-chip { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 7px; border-radius:999px; margin:1px 3px 1px 0; }
.ab-chip.amber { background:#fef3c7; color:#92400e; } .ab-chip.green { background:#d1fae5; color:#065f46; } .ab-chip.red { background:#fee2e2; color:#991b1b; } .ab-chip.slate { background:#e2e8f0; color:#334155; } .ab-chip.dark { background:#1e293b; color:#fff; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; }
.ab-b.del:hover { background:#fee2e2; } .ab-b.edi:hover { background:#e0eefc; } .ab-b.ver:hover { background:#e0f2fe; } .ab-b.imp:hover { background:#f1f5f9; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(940px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.ab-col label { font-size:12px; font-weight:600; color:#374151; display:block; margin-top:10px; }
.ab-col input, .ab-col textarea { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; margin-top:4px; box-sizing:border-box; color:#1e293b; font-family:inherit; resize:vertical; outline:none; }
.ab-col input:disabled, .ab-col textarea:disabled { background:#f1f5f9; color:#475569; }
.ab-row2 { display:flex; gap:12px; } .ab-row2 > div { flex:1; }
.ab-emp { position:relative; margin-top:4px; } .ab-emp input { width:100%; border:1px solid #c8d8ea; border-radius:6px; padding:7px 10px; font-size:13px; box-sizing:border-box; }
.ab-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:220px; overflow:auto; }
.ab-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; color:#1e293b; } .ab-result li:hover { background:#f0faf4; } .ab-result li b { font-size:13px; } .ab-result li span { font-size:11px; color:#6b7280; }
.ab-emp-sel { margin-top:6px; font-size:13px; font-weight:700; color:#14532d; display:flex; align-items:center; gap:8px; }
.ab-cambiar { background:#eef2f7; border:none; border-radius:5px; cursor:pointer; font-size:11px; padding:3px 8px; color:#2d6a9f; font-weight:700; }
.ab-estados { border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#fafdff; }
.ab-estados-tit { font-size:12px; font-weight:800; color:#1e293b; margin-bottom:8px; }
.ab-chk { display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; margin:6px 0; cursor:pointer; }
.ab-radio-row { display:flex; align-items:center; gap:16px; margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0; }
.ab-rad { display:flex; align-items:center; gap:5px; font-size:13px; color:#1e293b; cursor:pointer; }
.ab-clear { background:#eef2f7; border:none; border-radius:5px; cursor:pointer; font-size:11px; padding:2px 6px; color:#64748b; }
.ab-2col { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:18px; }
.ab-sec-tit { font-size:13px; font-weight:800; color:#14532d; margin-bottom:8px; }
.ab-vacio2 { color:#94a3b8; font-size:13px; border:1px dashed #e2e8f0; border-radius:8px; padding:14px; text-align:center; }
.ab-foto-box { display:flex; align-items:center; gap:8px; }
.ab-nav { background:#eef2f7; border:none; border-radius:8px; padding:10px 12px; cursor:pointer; font-size:15px; font-weight:700; color:#334155; } .ab-nav:disabled { opacity:.35; cursor:default; }
.ab-foto-wrap { flex:1; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f1f5f9; display:flex; flex-direction:column; align-items:center; }
.ab-foto-wrap img { max-width:100%; max-height:220px; object-fit:contain; cursor:zoom-in; } .ab-foto-ph { padding:36px; color:#9ca3af; }
.ab-foto-com { width:100%; background:#1e293b; color:#fff; font-size:12px; padding:5px 9px; }
.ab-dt { width:100%; border-collapse:collapse; font-size:12px; border:1px solid #e2e8f0; }
.ab-dt th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-size:11px; }
.ab-dt td { padding:5px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-ojo { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 4px; }
.ab-md-err { color:#991b1b; font-size:13px; margin:12px 0 0; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-reintegro { background:#e0eefc; color:#2d6a9f; border:none; border-radius:8px; padding:10px 16px; cursor:pointer; font-weight:700; font-size:13px; }
.ab-imp2 { background:#f1f5f9; color:#334155; border:none; border-radius:8px; padding:10px 16px; cursor:pointer; font-weight:700; font-size:13px; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#7f1d1d; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(440px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; } .ab-md-p { font-size:13px; color:#475569; margin:0 0 10px; } .ab-md-small label { font-size:12px; font-weight:600; color:#374151; display:block; margin-top:6px; } .ab-md-small input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; margin-top:4px; box-sizing:border-box; color:#1e293b; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ab-img-ov { position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:10000; display:flex; align-items:center; justify-content:center; padding:24px; cursor:zoom-out; } .ab-img-ov img { max-width:96vw; max-height:94vh; object-fit:contain; }
.ab-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ab-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ab-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#7f1d1d; color:#fff; font-size:13px; } .ab-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ab-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ab-pdf-b.ok { background:#22c55e; color:#fff; } .ab-pdf-b.cancel { background:#ef4444; color:#fff; }
.ab-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
