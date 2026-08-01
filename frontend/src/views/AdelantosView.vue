<!-- AdelantosView.vue — Adelantos / Anticipos: ABM unificado (grilla + alta + imprimir comprobante + borrar).
     Reemplaza los módulos separados Agregar / Consultar / Imprimir / Borrar.
     Preparado para abrirse desde la ficha del empleado (prop :empleado) — Propuesta "módulo madre". -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">💵</div>
      <div class="ab-tx"><h1>Adelantos al Personal</h1><p>Alta, consulta, impresión y baja de adelantos / anticipos</p></div>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <!-- Barra de herramientas -->
    <div class="ab-tools">
      <input v-if="!empBloqueado" v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por Nº, empleado o detalle…" />
      <span class="ab-count">{{ filtradas.length }} adelanto(s) · Total {{ money(totalFiltrado) }}</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo adelanto</button>
    </div>

    <!-- Grilla -->
    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th style="width:70px">Nº</th>
            <th style="width:100px">Fecha</th>
            <th>Empleado</th>
            <th>Detalle</th>
            <th style="width:80px" class="c">Imputa</th>
            <th style="width:90px">Forma</th>
            <th style="width:130px" class="r">Importe</th>
            <th style="width:110px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="8" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="8" class="ab-vacio">No hay adelantos para mostrar.</td></tr>
          <tr v-for="a in filtradas" :key="a.numero">
            <td class="ab-nro">{{ a.numero }}</td>
            <td>{{ a.fecha }}</td>
            <td>{{ a.empleado }} — {{ a.empleado_nombre }}</td>
            <td>{{ a.detalle }}</td>
            <td class="c">{{ a.imputa }}</td>
            <td>{{ a.forma_pago_txt }}</td>
            <td class="r">{{ money(a.importe) }}</td>
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
          <div class="ab-md-head"><span>＋ Nuevo adelanto</span><button class="ab-x" @click="cerrarAlta">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-form">
              <div class="ab-campos">
                <div class="ab-campo ab-ancho">
                  <label>Nro. Personal</label>
                  <EmpleadoInput :codigo="f.codigo || 0" :nombre="nombre" :disabled="empBloqueado" @select="onSelEmp" />
                </div>
                <div class="ab-campo ab-ancho"><label>Nombre</label><input :value="nombre" disabled /></div>

                <div class="ab-campo"><label>Fecha Adelanto</label><input v-model="f.fecha_adelanto" type="date" /></div>
                <div class="ab-campo" v-if="esJornalero">
                  <label>Quincena</label>
                  <div class="ab-radios"><label><input v-model.number="f.quincena" type="radio" :value="1" /> 1ª</label><label><input v-model.number="f.quincena" type="radio" :value="2" /> 2ª</label></div>
                </div>
                <div class="ab-campo"><label>Imputa a Mes</label><input v-model.number="f.mes" type="number" min="1" max="12" /></div>
                <div class="ab-campo"><label>Año</label><input v-model.number="f.anio" type="number" min="2000" max="2100" /></div>

                <div class="ab-campo ab-ancho">
                  <label>Tipo de Adelanto</label>
                  <div class="ab-radios"><label><input v-model.number="f.tipo" type="radio" :value="1" /> ADELANTO SUELDO</label><label><input v-model.number="f.tipo" type="radio" :value="2" /> ADELANTO SAC</label></div>
                </div>
                <div class="ab-campo ab-ancho">
                  <label>Forma de Pago</label>
                  <div class="ab-radios"><label><input v-model.number="f.forma_pago" type="radio" :value="1" /> EFECTIVO</label><label><input v-model.number="f.forma_pago" type="radio" :value="2" /> BANCO</label><label><input v-model.number="f.forma_pago" type="radio" :value="3" /> OTROS</label></div>
                </div>
                <div class="ab-campo ab-ancho"><label>Detalle</label><input v-model="f.detalle" type="text" maxlength="100" /></div>
                <div class="ab-campo"><label>Importe</label><input v-model.number="f.importe" type="number" min="0" step="0.01" /></div>
              </div>
              <div class="ab-foto"><img v-if="fotoUrl" :src="fotoUrl" alt="foto" /><div v-else class="ab-foto-ph">Sin foto</div></div>
            </div>
          </div>
          <div class="ab-md-foot">
            <span style="flex:1"></span>
            <button class="ab-cancel" @click="cerrarAlta">Cancelar</button>
            <button class="ab-confirm" :disabled="grabando || !f.codigo" @click="confirmar">{{ grabando ? '⟳ Grabando…' : '✔ Confirmar' }}</button>
          </div>
        </div>
      </div>

      <!-- Ayuda -->
      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — Adelantos al Personal</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista todos los adelantos. Buscá por número, empleado o detalle.</li>
            <li><b>＋ Nuevo adelanto</b>: elegí el empleado, indicá fecha (no anterior a hoy), mes/año, tipo y forma de pago. Al confirmar se genera la liquidación y el comprobante.</li>
            <li>En cada fila: <b>🖨️ Imprimir</b> el comprobante y <b>🗑️ Borrar</b> el adelanto (elimina también su liquidación).</li>
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

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const hoy = new Date()
const vacio = () => ({ codigo: 0, fecha_adelanto: hoy.toISOString().slice(0, 10), mes: hoy.getMonth() + 1, anio: hoy.getFullYear(), tipo: 1, forma_pago: 1, quincena: 0, detalle: '', importe: 0 })

const rows = ref<any[]>([]); const cargando = ref(false)
const filtroTxt = ref('')
const modalAlta = ref(false); const ayuda = ref(false)
const f = ref<any>(vacio())
const nombre = ref(''); const fotoUrl = ref(''); const esJornalero = ref(false); const grabando = ref(false)
const msg = ref(''); const msgErr = ref(false)

const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

const filtradas = computed(() => {
  const q = filtroTxt.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter(a => String(a.numero).includes(q) || (a.empleado_nombre || '').toLowerCase().includes(q)
    || String(a.empleado).includes(q) || (a.detalle || '').toLowerCase().includes(q))
})
const totalFiltrado = computed(() => filtradas.value.reduce((s, a) => s + (a.importe || 0), 0))

async function cargarGrilla () {
  cargando.value = true
  try {
    const params: any = {}
    if (props.empleado) params.emp = props.empleado
    rows.value = (await api.get('/adelantos/grilla', { params })).data ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

// ── Alta ──
function abrirNuevo () {
  f.value = vacio(); nombre.value = ''; esJornalero.value = false
  if (fotoUrl.value) { URL.revokeObjectURL(fotoUrl.value); fotoUrl.value = '' }
  if (props.empleado) { f.value.codigo = props.empleado; nombre.value = props.empleadoNombre; cargarDatosEmp(props.empleado) }
  modalAlta.value = true
}
function cerrarAlta () { modalAlta.value = false }

const onSelEmp = (r: any) => seleccionar(r)
async function seleccionar (r: any) {
  f.value.codigo = Number(r.PER_COD); nombre.value = (r.PER_NOM || '').trim()
  await cargarDatosEmp(Number(r.PER_COD))
}
async function cargarDatosEmp (cod: number) {
  if (fotoUrl.value) { URL.revokeObjectURL(fotoUrl.value); fotoUrl.value = '' }
  esJornalero.value = false; f.value.quincena = 0
  try { const { data } = await api.get(`/adelantos/empleado/${cod}`); esJornalero.value = !!data.es_jornalero; if (esJornalero.value) f.value.quincena = 1 } catch { /* */ }
  try { const { data } = await api.get(`/empleados/${cod}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* */ }
}

async function confirmar () {
  if (!f.value.codigo) { flash('Seleccioná un empleado.', true); return }
  if (!f.value.importe || f.value.importe <= 0) { flash('Verifique el importe del anticipo.', true); return }
  if (new Date(f.value.fecha_adelanto) < new Date(hoy.toISOString().slice(0, 10))) { flash('No puede ingresar un adelanto de fecha anterior a la presente.', true); return }
  const avisos: string[] = []
  if (f.value.mes !== hoy.getMonth() + 1) avisos.push('El mes imputado difiere del mes en curso.')
  if (f.value.anio !== hoy.getFullYear()) avisos.push('El año imputado difiere del año en curso.')
  if (!confirm((avisos.length ? '⚠️ ' + avisos.join('\n') + '\n\n' : '') + '¿Acepta el anticipo otorgado?')) return
  grabando.value = true
  try {
    const { data } = await api.post('/adelantos', { ...f.value })
    modalAlta.value = false
    await cargarGrilla()
    generarPdf(data.comprobante)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo ingresar el adelanto.', true) }
  finally { grabando.value = false }
}

// ── Borrar ──
async function borrar (a: any) {
  if (!confirm(`¿Borrar el adelanto Nº ${a.numero} de ${a.empleado_nombre} por ${money(a.importe)}?\nSe eliminará también su liquidación.`)) return
  try { await api.delete(`/adelantos/${a.numero}`); flash(`Adelanto ${a.numero} borrado.`); await cargarGrilla() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
}

// ── Imprimir comprobante ──
async function imprimir (a: any) {
  try { const { data } = await api.get(`/adelantos/${a.numero}/comprobante`); generarPdf(data.comprobante) }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el comprobante.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function generarPdf (c: any) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const copia = (oy: number, etiqueta: string) => {
    const ML = 16, W = 178
    let y = oy + 10
    doc.setDrawColor(120); doc.setLineWidth(0.3); doc.rect(ML - 2, oy + 4, W + 4, 124)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(27, 67, 50)
    doc.text('COMPROBANTE DE ADELANTO', ML, y); doc.setFontSize(8); doc.setTextColor(120, 120, 120)
    doc.text(etiqueta, ML + W, y, { align: 'right' }); y += 8
    doc.setTextColor(0, 0, 0)
    const lin = (lbl: string, val: string, salto = 6) => { doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(lbl, ML, y); doc.setFont('helvetica', 'normal'); doc.text(val, ML + 40, y); y += salto }
    lin('PERSONAL:', c.personal_nombre); lin('CUIT:', c.personal_cuit)
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text(c.linea, ML, y); y += 7
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.text('IMPORTE:', ML, y)
    doc.setFontSize(13); doc.text('$ ' + nf.format(Number(c.importe)), ML + 40, y); y += 7
    doc.setFont('helvetica', 'italic'); doc.setFontSize(9); doc.text(c.en_letras, ML, y); y += 7
    lin('FECHA:', c.fecha); lin('APLICAR EL MES:', c.aplicar_mes); lin('NÚMERO DE VALE:', String(c.numero))
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.text('OBSERVACIONES:', ML, y)
    doc.setFont('helvetica', 'normal'); doc.text(c.observaciones || '', ML + 40, y); y += 14
    doc.setDrawColor(120); doc.setLineWidth(0.2); doc.line(ML, y, ML + 70, y); doc.line(ML + 100, y, ML + W, y); y += 4
    doc.setFontSize(8); doc.text('Firma Empleado', ML, y); doc.text('Aclaración de Firma', ML + 100, y)
  }
  copia(8, 'ORIGINAL'); copia(150, 'DUPLICADO')
  cerrarPdf(); pdfNombre.value = `Adelanto_${c.numero}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

cargarGrilla()
</script>

<style scoped>
.ab-view { display:flex; flex-direction:column; min-height:100%; }
.ab-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ab-ico { font-size:28px; } .ab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ab-ayuda { margin-left:auto; background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ab-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ab-msg.ok { background:#d1fae5; color:#065f46; } .ab-msg.err { background:#fee2e2; color:#991b1b; }
.ab-tools { display:flex; align-items:center; gap:10px; padding:14px 18px 8px; flex-wrap:wrap; }
.ab-search { flex:1; min-width:240px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#2563eb; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.r, .ab-tabla td.r { text-align:right; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-nro { font-weight:800; color:#2563eb; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; }
.ab-b.del:hover { background:#fee2e2; } .ab-b.imp:hover { background:#e0eefc; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(760px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-form { display:flex; gap:18px; }
.ab-campos { flex:1; display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.ab-campo { display:flex; flex-direction:column; gap:5px; } .ab-campo.ab-ancho { grid-column:1 / -1; }
.ab-campo label { font-size:12px; font-weight:600; color:#374151; }
.ab-campo input[type=text], .ab-campo input[type=date], .ab-campo input[type=number] { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; }
.ab-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ab-campo input:disabled { background:#f1f5f9; color:#1e293b; font-weight:600; }
.ab-lupa-row { display:flex; gap:8px; } .ab-lupa-row input { flex:1; }
.ab-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ab-radios { display:flex; gap:16px; align-items:center; padding-top:6px; flex-wrap:wrap; }
.ab-radios label { font-size:13px; font-weight:600; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; }
.ab-foto { width:150px; height:160px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ab-foto img { width:100%; height:100%; object-fit:cover; } .ab-foto-ph { font-size:12px; color:#9ca3af; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(520px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ab-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ab-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ab-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ab-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ab-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ab-pdf-b.ok { background:#22c55e; color:#fff; } .ab-pdf-b.cancel { background:#ef4444; color:#fff; }
.ab-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
