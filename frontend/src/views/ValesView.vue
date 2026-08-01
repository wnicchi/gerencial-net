<!-- ValesView.vue — Vales de efectivo: ABM unificado (grilla + alta + reimprimir + cerrar + borrar + ver borrados).
     Reemplaza Agregar / Consultar / Pendientes / Cerrar / Reimprimir / Borrar / Borrados.
     Tesorería y Fondo Fijo quedan como accesos aparte.
     Preparado para abrirse desde la ficha del empleado (prop :empleado) — Propuesta "módulo madre". -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">🎟️</div>
      <div class="ab-tx"><h1>Vales de Efectivo</h1><p>Alta, consulta, cierre, reimpresión y baja de vales</p></div>
      <button class="ab-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/vales" titulo="Asistente IA — Vales"
            subtitulo="Preguntá sobre el módulo de vales"
            :sugerencias="['¿Cómo cargo un vale?','¿Cómo cierro (rindo) un vale?','¿Qué es el vuelto?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-if="!empBloqueado" v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por Nº, empleado o detalle…" />
      <select v-model="filtroEstado" class="ab-estado">
        <option value="">Todos</option>
        <option value="abierto">Sin cerrar (abiertos)</option>
        <option value="cerrado">Cerrados</option>
      </select>
      <span class="ab-count">{{ filtradas.length }} vale(s) · Total {{ money(totalFiltrado) }}</span>
      <span style="flex:1"></span>
      <button class="ab-sec" @click="abrirBorrados">🗑️ Vales borrados</button>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo vale</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:70px">Nº</th>
            <th style="width:140px">Fecha</th>
            <th>Empleado</th>
            <th>Detalle</th>
            <th style="width:90px">Fondo</th>
            <th style="width:80px" class="c">Estado</th>
            <th style="width:120px" class="r">Importe</th>
            <th style="width:140px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="8" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="8" class="ab-vacio">No hay vales para mostrar.</td></tr>
          <tr v-for="v in filtradas" :key="v.numero">
            <td class="ab-nro">{{ v.numero }}</td>
            <td>{{ v.fecha }}</td>
            <td>{{ v.codemp }} — {{ v.nombre }}</td>
            <td>{{ v.razon }}</td>
            <td>{{ v.fondo === 1 ? 'Silcar' : 'Logística' }}</td>
            <td class="c"><span :class="['ab-chip', v.cerrado ? 'green' : 'amber']">{{ v.cerrado ? 'Cerrado' : 'Abierto' }}</span></td>
            <td class="r">{{ money(v.importe) }}</td>
            <td class="c ab-acc">
              <button class="ab-b imp" title="Reimprimir" @click="imprimir(v)">🖨️</button>
              <button v-if="!v.cerrado" class="ab-b cer" title="Cerrar (rendir)" @click="abrirCerrar(v)">🔒</button>
              <button class="ab-b del" title="Borrar" @click="borrar(v)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ─────────── Modal alta ─────────── -->
    <Teleport to="body">
      <div v-if="modalAlta" class="ab-ov" @click.self="modalAlta = false">
        <div class="ab-md">
          <div class="ab-md-head"><span>＋ Nuevo vale</span><button class="ab-x" @click="modalAlta = false">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-form2">
              <div class="ab-campos">
                <div class="ab-campo ab-ancho">
                  <label>Nro. Personal</label>
                  <EmpleadoInput :codigo="fa.codigo || 0" :nombre="nombre" :disabled="empBloqueado" @select="onSelEmp" />
                </div>
                <div class="ab-campo ab-ancho"><label>Nombre</label><input :value="nombre" disabled /></div>
                <div class="ab-campo"><label>Fecha VALE</label><input v-model="fa.fecha" type="date" /></div>
                <div class="ab-campo"><label>Hora de Entrega</label><input v-model="horaTxt" type="time" /></div>
                <div class="ab-campo ab-ancho"><label>Detalle</label><input v-model="fa.detalle" type="text" maxlength="200" /></div>
                <div class="ab-campo"><label>Importe</label><input v-model.number="fa.importe" type="number" min="0" step="0.01" /></div>
                <div class="ab-campo">
                  <label>Fondo Fijo de</label>
                  <div class="ab-radios"><label><input v-model.number="fa.fondo" type="radio" :value="1" /> SILCAR</label><label><input v-model.number="fa.fondo" type="radio" :value="2" /> LOGÍSTICA</label></div>
                </div>
                <div class="ab-campo ab-ancho"><label>Autorizo</label><input v-model="fa.autorizo" type="text" maxlength="100" /></div>
              </div>
              <div class="ab-foto"><img v-if="fotoUrl" :src="fotoUrl" alt="foto" /><div v-else class="ab-foto-ph">Sin foto</div></div>
            </div>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span>
            <button class="ab-cancel" @click="modalAlta = false">Cancelar</button>
            <button class="ab-confirm" :disabled="grabando || !fa.codigo" @click="confirmarAlta">{{ grabando ? '⟳ Grabando…' : '✔ Confirmar' }}</button>
          </div>
        </div>
      </div>

      <!-- ─────────── Modal cierre ─────────── -->
      <div v-if="modalCierre && selCierre" class="ab-ov" @click.self="modalCierre = false">
        <div class="ab-md">
          <div class="ab-md-head"><span>🔒 Cierre del vale N° {{ selCierre.numero }} — {{ selCierre.nombre }}</span><button class="ab-x" @click="modalCierre = false">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-cierre">
              <div class="ab-c"><label>Fecha CIERRE</label><input v-model="c.fecha_cierre" type="date" /></div>
              <div class="ab-c"><label>Entregado</label><input v-model.number="c.entregado" type="number" step="0.01" @input="recalcular" /></div>
              <div class="ab-c"><label>Repuestos</label><input v-model.number="c.repuestos" type="number" step="0.01" @input="recalcular" /></div>
              <div class="ab-c"><label>Gastos</label><input v-model.number="c.gastos" type="number" step="0.01" @input="recalcular" /></div>
              <div class="ab-c"><label>Combustible</label><input v-model.number="c.combustible" type="number" step="0.01" @input="recalcular" /></div>
              <div class="ab-c"><label>Ajuste</label><input v-model.number="c.ajuste" type="number" step="0.01" @input="recalcular" /></div>
              <div class="ab-c ro"><label>Vuelto</label><input :value="money(c.vuelto)" disabled :class="{ neg: c.vuelto < 0 }" /></div>
              <div class="ab-c ro"><label>Comprobantes</label><input :value="money(c.comprobantes)" disabled /></div>
            </div>
            <p v-if="c.vuelto < 0" class="ab-aviso">⚠️ El vuelto es negativo: se registrará una <b>compensación</b> por {{ money(Math.abs(c.vuelto)) }} a favor del fondo.</p>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span>
            <button class="ab-cancel" @click="modalCierre = false">Cancelar</button>
            <button class="ab-confirm" :disabled="grabando" @click="confirmarCierre">{{ grabando ? '⟳ Cerrando…' : '✔ Confirmar cierre' }}</button>
          </div>
        </div>
      </div>

      <!-- ─────────── Modal vales borrados ─────────── -->
      <div v-if="modalBorrados" class="ab-ov" @click.self="modalBorrados = false">
        <div class="ab-md">
          <div class="ab-md-head"><span>🗑️ Vales borrados</span><button class="ab-x" @click="modalBorrados = false">✕</button></div>
          <div class="ab-md-body">
            <table class="ab-dt">
              <thead><tr><th>Nº</th><th>Fecha</th><th>Empleado</th><th class="r">Importe</th><th>Razón baja</th><th>Borrado por</th></tr></thead>
              <tbody>
                <tr v-if="!borrados.length"><td colspan="6" class="ab-vacio2">No hay vales borrados.</td></tr>
                <tr v-for="b in borrados" :key="b.numero">
                  <td class="ab-cod">{{ b.numero }}</td><td>{{ b.fecha }}</td><td>{{ b.codemp }} — {{ b.nombre }}</td>
                  <td class="r">{{ money(b.importe) }}</td><td>{{ b.borrado_razon }}</td><td>{{ b.borrado_por }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-cancel" @click="modalBorrados = false">Cerrar</button></div>
        </div>
      </div>

      <!-- Ayuda -->
      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — Vales de Efectivo</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista todos los vales (buscá por Nº, empleado o detalle; filtrá por estado).</li>
            <li><b>＋ Nuevo vale</b>: elegí el empleado, importe y fondo. Al confirmar sale el comprobante.</li>
            <li>En cada fila: <b>🖨️ Reimprimir</b>, <b>🔒 Cerrar</b> (rendir, solo abiertos) y <b>🗑️ Borrar</b> (pide motivo, se archiva).</li>
            <li><b>🗑️ Vales borrados</b> muestra el historial de bajas.</li>
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
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import ChatIA from '@/components/ChatIA.vue'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const hoy = new Date().toISOString().slice(0, 10)
const rows = ref<any[]>([]); const cargando = ref(false)
const filtroTxt = ref(''); const filtroEstado = ref('')
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

// Alta
const modalAlta = ref(false); const grabando = ref(false)
const fa = ref({ codigo: 0, fecha: hoy, detalle: '', importe: 0, fondo: 1, autorizo: '' })
const horaTxt = ref(new Date().toTimeString().slice(0, 5)); const nombre = ref(''); const fotoUrl = ref('')

// Cierre
const modalCierre = ref(false); const selCierre = ref<any>(null)
const c = ref({ fecha_cierre: hoy, entregado: 0, repuestos: 0, gastos: 0, combustible: 0, ajuste: 0, vuelto: 0, comprobantes: 0 })

// Borrados
const modalBorrados = ref(false); const borrados = ref<any[]>([])

const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

const filtradas = computed(() => {
  const q = filtroTxt.value.trim().toLowerCase()
  return rows.value.filter(v => {
    if (filtroEstado.value === 'abierto' && v.cerrado) return false
    if (filtroEstado.value === 'cerrado' && !v.cerrado) return false
    if (!q) return true
    return String(v.numero).includes(q) || (v.nombre || '').toLowerCase().includes(q) || String(v.codemp).includes(q) || (v.razon || '').toLowerCase().includes(q)
  })
})
const totalFiltrado = computed(() => filtradas.value.reduce((s, v) => s + (v.importe || 0), 0))

async function cargarGrilla () {
  cargando.value = true
  try {
    const params: any = { rango: 1, sin_cerrar: 0 }
    if (props.empleado) params.codigo = props.empleado
    rows.value = (await api.get('/vales/lista', { params })).data ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

// ── Alta ──
function abrirNuevo () {
  fa.value = { codigo: 0, fecha: hoy, detalle: '', importe: 0, fondo: 1, autorizo: '' }
  horaTxt.value = new Date().toTimeString().slice(0, 5); nombre.value = ''
  if (fotoUrl.value) { URL.revokeObjectURL(fotoUrl.value); fotoUrl.value = '' }
  if (props.empleado) { fa.value.codigo = props.empleado; nombre.value = props.empleadoNombre; cargarFoto(props.empleado) }
  modalAlta.value = true
}
const onSelEmp = (r: any) => { fa.value.codigo = Number(r.cod ?? r.PER_COD); nombre.value = (r.nombre ?? r.PER_NOM ?? '').trim(); cargarFoto(fa.value.codigo) }
async function cargarFoto (cod: number) { if (fotoUrl.value) { URL.revokeObjectURL(fotoUrl.value); fotoUrl.value = '' } try { const { data } = await api.get(`/empleados/${cod}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* */ } }

async function confirmarAlta () {
  if (!fa.value.codigo) { flash('Seleccioná un empleado.', true); return }
  if (!fa.value.importe || fa.value.importe <= 0) { flash('Verifique el importe del vale.', true); return }
  if (!confirm('¿Acepta el vale otorgado?')) return
  grabando.value = true
  try {
    const hora = parseInt(horaTxt.value.replace(':', ''), 10) || 0
    const { data } = await api.post('/vales', { codigo: fa.value.codigo, fecha: fa.value.fecha, hora, detalle: fa.value.detalle, importe: fa.value.importe, fondo: fa.value.fondo, autorizo: fa.value.autorizo })
    modalAlta.value = false
    await cargarGrilla()
    generarPdf(data.vale)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo grabar el vale.', true) }
  finally { grabando.value = false }
}

// ── Cierre ──
function abrirCerrar (v: any) {
  selCierre.value = v
  c.value = { fecha_cierre: hoy, entregado: v.importe, repuestos: 0, gastos: 0, combustible: 0, ajuste: 0, vuelto: v.importe, comprobantes: v.importe }
  recalcular()
  modalCierre.value = true
}
function recalcular () {
  const x = c.value
  x.vuelto = Number((x.entregado - x.repuestos - x.gastos - x.combustible).toFixed(2))
  x.comprobantes = Number(((x.repuestos + x.gastos + x.combustible) - x.ajuste).toFixed(2))
}
async function confirmarCierre () {
  if (!selCierre.value) return
  if (!confirm(`¿Confirma el CIERRE del vale N° ${selCierre.value.numero}?`)) return
  grabando.value = true
  try {
    await api.post('/vales/cerrar', { numero: selCierre.value.numero, ...c.value })
    flash(`Vale N° ${selCierre.value.numero} cerrado.`)
    modalCierre.value = false
    await cargarGrilla()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cerrar.', true) }
  finally { grabando.value = false }
}

// ── Borrar ──
async function borrar (v: any) {
  const razon = window.prompt(`Motivo de la baja del vale N° ${v.numero} (${v.nombre}):`, '')
  if (razon === null) return
  if (!razon.trim()) { flash('Debe indicar el motivo de la baja.', true); return }
  try { await api.post('/vales/borrar', { numeros: [v.numero], razon: razon.trim() }); flash(`Vale N° ${v.numero} borrado.`); await cargarGrilla() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
}

// ── Borrados ──
async function abrirBorrados () {
  modalBorrados.value = true
  try { borrados.value = (await api.get('/vales/borrados', { params: { rango: 1 } })).data ?? [] } catch { borrados.value = [] }
}

// ── Reimprimir ──
async function imprimir (v: any) {
  try { const { data } = await api.get(`/vales/${v.numero}/impresion`); generarPdf(data.vale) }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el comprobante.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function generarPdf (v: any) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const copia = (oy: number, etiqueta: string) => {
    const ML = 16, W = 178
    let y = oy + 10
    doc.setDrawColor(120); doc.setLineWidth(0.3); doc.rect(ML - 2, oy + 4, W + 4, 124)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50)
    doc.text('VALE POR DINERO EN EFECTIVO', ML, y); doc.setFontSize(8); doc.setTextColor(120, 120, 120)
    doc.text(etiqueta, ML + W, y, { align: 'right' }); y += 8
    doc.setTextColor(0, 0, 0)
    const lin = (lbl: string, val: string, salto = 6) => { doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(lbl, ML, y); doc.setFont('helvetica', 'normal'); doc.text(val, ML + 34, y); y += salto }
    lin('EMPRESA:', v.empresa_nombre)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(70, 70, 70)
    doc.text(`${v.empresa_domicilio}   CUIT: ${v.empresa_cuit}`, ML + 34, y - 2); doc.setTextColor(0, 0, 0); y += 2
    lin('PERSONAL:', v.personal_nombre); lin('CUIT:', v.personal_cuit); lin('FECHA:', v.fecha); lin('NÚMERO DE VALE:', String(v.numero))
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.text('IMPORTE:', ML, y)
    doc.setFontSize(13); doc.text('$ ' + nf.format(Number(v.importe)), ML + 34, y); y += 7
    doc.setFont('helvetica', 'italic'); doc.setFontSize(9); doc.text(v.en_letras, ML, y); y += 7
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('OBSERVACIONES:', ML, y)
    doc.setFont('helvetica', 'normal'); doc.text(v.observaciones || '', ML + 34, y); y += 7
    doc.setFont('helvetica', 'bold'); doc.text('FONDO:', ML, y); doc.setFont('helvetica', 'normal'); doc.text(v.fondo_salida, ML + 34, y); y += 9
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(150, 0, 0)
    doc.text('EL PRESENTE VALE DEBERÁ SER RENDIDO DENTRO DE LOS 10 DÍAS HÁBILES DE EMITIDO.-', ML, y); y += 12
    doc.setTextColor(0, 0, 0); doc.setDrawColor(120); doc.setLineWidth(0.2)
    doc.line(ML, y, ML + 70, y); doc.line(ML + 100, y, ML + W, y); y += 4
    doc.setFontSize(8); doc.text('Firma Empleado', ML, y); doc.text('Aclaración de Firma', ML + 100, y)
  }
  copia(8, 'ORIGINAL'); copia(150, 'DUPLICADO')
  cerrarPdf(); pdfNombre.value = `Vale_${v.numero}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
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
.ab-search { flex:1; min-width:220px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-estado { border:1px solid #c8d8ea; border-radius:8px; padding:9px 10px; font-size:13px; color:#1e293b; background:#fff; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-sec { background:#eef2f7; color:#475569; border:none; padding:10px 14px; border-radius:8px; cursor:pointer; font-weight:700; font-size:13px; }
.ab-nuevo { background:#2563eb; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.r, .ab-tabla td.r { text-align:right; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-nro { font-weight:800; color:#2563eb; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-chip { display:inline-block; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; }
.ab-chip.amber { background:#fef3c7; color:#92400e; } .ab-chip.green { background:#d1fae5; color:#065f46; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; }
.ab-b.del:hover { background:#fee2e2; } .ab-b.imp:hover { background:#e0eefc; } .ab-b.cer:hover { background:#dcfce7; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(820px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-form2 { display:flex; gap:18px; }
.ab-campos { flex:1; display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.ab-campo { display:flex; flex-direction:column; gap:5px; } .ab-campo.ab-ancho { grid-column:1 / -1; }
.ab-campo label { font-size:12px; font-weight:600; color:#374151; }
.ab-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; }
.ab-campo input:disabled { background:#f1f5f9; color:#1e293b; font-weight:600; }
.ab-lupa-row { display:flex; gap:8px; } .ab-lupa-row input { flex:1; }
.ab-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ab-radios { display:flex; gap:16px; align-items:center; padding-top:6px; } .ab-radios label { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
.ab-foto { width:150px; height:150px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ab-foto img { width:100%; height:100%; object-fit:cover; } .ab-foto-ph { font-size:12px; color:#9ca3af; }
.ab-cierre { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
.ab-c { display:flex; flex-direction:column; gap:4px; } .ab-c label { font-size:11px; font-weight:600; color:#374151; }
.ab-c input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:14px; text-align:right; outline:none; }
.ab-c.ro input { background:#f1f5f9; color:#1e293b; font-weight:700; } .ab-c.ro input.neg { color:#dc2626; }
.ab-aviso { margin:12px 0 0; padding:8px 12px; background:#fef3c7; color:#92400e; border-radius:6px; font-size:13px; }
.ab-dt { width:100%; border-collapse:collapse; font-size:12.5px; border:1px solid #e2e8f0; }
.ab-dt th { background:#1e293b; color:#fff; padding:7px 9px; text-align:left; font-size:11.5px; } .ab-dt th.r, .ab-dt td.r { text-align:right; }
.ab-dt td { padding:6px 9px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ab-cod { font-weight:700; color:#2563eb; }
.ab-vacio2 { text-align:center; color:#94a3b8; padding:16px; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(540px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ab-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ab-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ab-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ab-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ab-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ab-pdf-b.ok { background:#22c55e; color:#fff; } .ab-pdf-b.cancel { background:#ef4444; color:#fff; }
.ab-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
