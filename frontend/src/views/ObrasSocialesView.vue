<!-- ObrasSocialesView.vue — ABM de Obras Sociales (2 solapas: Datos Generales + Comprobantes). -->
<template>
  <div class="os-view">
    <div class="os-cab">
      <div class="os-cab-ico">🏥</div>
      <div class="os-cab-tx"><h1>Obras Sociales</h1><p>{{ lista.length }} obra{{ lista.length === 1 ? '' : 's' }} social{{ lista.length === 1 ? '' : 'es' }}</p></div>
      <button class="os-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="os-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="os-btn-nuevo" @click="nuevo">＋ Nueva</button>
    </div>

    <ObrasSocialesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/obras-sociales" titulo="Asistente IA — Obras Sociales"
            subtitulo="Preguntá sobre el ABM y los comprobantes"
            :sugerencias="['¿Qué hay en cada solapa?','¿Cómo se calcula el saldo?','¿Qué tipo va al haber?','¿Por qué no me deja eliminar una?']"
            @close="modalIA = false" />

    <div class="os-body">
      <aside class="os-aside">
        <input v-model="filtro" class="os-busc" type="text" placeholder="Buscar…" />
        <ul class="os-lista">
          <li v-for="o in listaFiltrada" :key="o.cod" :class="{ on: sel?.cod === o.cod }" @click="abrir(o)">
            <b>{{ o.nombre }}</b>
            <span><i :class="['os-est', o.estado === 'A' ? 'a' : 'p']">{{ o.estado === 'A' ? 'ACTIVA' : 'PASIVA' }}</i> {{ o.telefono }}</span>
          </li>
          <li v-if="!listaFiltrada.length" class="os-vacio">Sin obras sociales</li>
        </ul>
      </aside>

      <section class="os-detalle" v-if="form">
        <div class="os-tabs">
          <button :class="{ on: tab === 1 }" @click="tab = 1">Datos Generales</button>
          <button :class="{ on: tab === 2 }" :disabled="!form.cod" @click="tab = 2; cargarComprobantes()">Comprobantes</button>
        </div>

        <!-- Tab 1 -->
        <div v-show="tab === 1" class="os-tab" v-enter-next>
          <div class="os-fila">
            <div class="os-c os-chico"><label>Código</label><input :value="form.cod || '(automático)'" disabled /></div>
            <div class="os-c"><label>Estado</label><div class="os-radios"><label><input v-model="form.estado" type="radio" value="A" /> ACTIVA</label><label><input v-model="form.estado" type="radio" value="P" /> PASIVA</label></div></div>
          </div>
          <div class="os-c"><label>Nombre o Razón Social <span class="req">*</span></label><input v-model="form.nombre" type="text" maxlength="100" /></div>
          <div class="os-c"><label>Domicilio</label><input v-model="form.domicilio" type="text" maxlength="100" /></div>
          <div class="os-fila">
            <div class="os-c"><label>Teléfonos</label><input v-model="form.telefono" type="text" maxlength="50" /></div>
            <div class="os-c"><label>Email</label><input v-model="form.email" type="text" maxlength="100" /></div>
          </div>
          <p v-if="err" class="os-err">⚠️ {{ err }}</p>
          <div class="os-acc1">
            <button class="os-btn-guardar" :disabled="guardando" @click="guardarDatos">💾 {{ guardando ? 'Guardando…' : 'Guardar datos' }}</button>
            <button v-if="form.cod" class="os-btn-del" @click="eliminar">🗑️ Eliminar</button>
          </div>
        </div>

        <!-- Tab 2 -->
        <div v-show="tab === 2" class="os-tab">
          <div class="os-cc-top">
            <div class="os-filtros">
              <span class="os-flbl">Filtrar:</span>
              <label><input v-model="fPeriodo" type="checkbox" /> Período</label>
              <template v-if="fPeriodo"><input v-model="fFecha1" type="date" class="os-fin" /> al <input v-model="fFecha2" type="date" class="os-fin" /></template>
              <label><input v-model="fTipoOn" type="checkbox" /> Tipo</label>
              <input v-if="fTipoOn" v-model="fTipo" type="text" class="os-fin" placeholder="Tipo" style="width:80px" />
              <label><input v-model="fEmpOn" type="checkbox" /> Empresa</label>
              <select v-if="fEmpOn" v-model.number="fEmp" class="os-fin"><option :value="0">—</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
              <button class="os-mini" @click="cargarComprobantes">🔍 Filtrar</button>
            </div>
            <div class="os-saldo">Saldo: <b :class="saldo >= 0 ? 'pos' : 'neg'">{{ money(saldo) }}</b></div>
          </div>

          <div class="os-cc-acc">
            <button class="os-mini del" :disabled="!compSel.length" @click="eliminarComprobantes">🗑️ Eliminar marcados ({{ compSel.length }})</button>
            <button class="os-mini" @click="imprimir">🖨 Imprimir informe</button>
          </div>

          <div class="os-wrap">
            <table class="os-grid">
              <thead><tr><th style="width:30px"></th><th>Fecha</th><th>T.Doc</th><th>Suc.</th><th>Número</th><th class="r">Debe</th><th class="r">Haber</th><th>Empresa</th></tr></thead>
              <tbody>
                <tr v-for="c in comprobantes" :key="c.unico" :class="{ sel: c._sel }">
                  <td style="text-align:center"><input v-model="c._sel" type="checkbox" /></td>
                  <td>{{ fmt(c.fecha) }}</td><td>{{ c.tipo }}</td><td>{{ c.sucursal || '' }}</td><td>{{ c.numero }}</td>
                  <td class="r">{{ c.debe ? money(c.debe) : '' }}</td><td class="r">{{ c.haber ? money(c.haber) : '' }}</td><td>{{ c.empresa }}</td>
                </tr>
                <tr v-if="!comprobantes.length"><td colspan="8" class="os-vacio">Sin comprobantes</td></tr>
              </tbody>
            </table>
          </div>

          <!-- Agregar comprobante -->
          <div class="os-agregar">
            <span class="os-agtit">Agregar comprobante</span>
            <div class="os-agfila" v-enter-next>
              <select v-model.number="nc.empresa"><option :value="0">— Empresa —</option><option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option></select>
              <input v-model="nc.tipo" type="text" placeholder="Tipo (ej. FAC, N.C)" style="width:120px" />
              <input v-model.number="nc.sucursal" type="number" placeholder="Suc." style="width:70px" />
              <input v-model.number="nc.numero" type="number" placeholder="Número" style="width:110px" />
              <input v-model="nc.fecha" type="date" />
              <input v-model.number="nc.importe" type="number" step="0.01" placeholder="Importe" style="width:120px" />
              <button class="os-btn-conf" :disabled="guardandoComp" @click="confirmarComprobante">✔ Confirmar</button>
            </div>
            <p v-if="errComp" class="os-err">⚠️ {{ errComp }}</p>
          </div>
        </div>
      </section>
      <section class="os-detalle os-placeholder" v-else>Elegí una obra social o creá una nueva.</section>
    </div>
    <p v-if="msg" :class="['os-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>

    <Teleport to="body">
      <div v-if="pdfUrl" class="os-pdf-ov" @click.self="cerrarPdf">
        <div class="os-pdf-md">
          <div class="os-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="os-pdf-acc">
              <button class="os-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="os-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="os-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="os-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ObrasSocialesAyuda from '@/components/ObrasSocialesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface OS { cod: number; nombre: string; domicilio: string; telefono: string; email: string; estado: string }
const lista = ref<OS[]>([]); const filtro = ref(''); const sel = ref<OS | null>(null)
const tab = ref(1); const form = ref<any>(null); const guardando = ref(false); const err = ref('')
const empresas = ref<{ cod: number; nombre: string }[]>([])
const msg = ref(''); const msgError = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 3500) }

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(o => o.nombre.toLowerCase().includes(q)) : lista.value
})

const cargarLista = async () => { try { lista.value = (await api.get('/obras-sociales')).data } catch (e) { console.error(e) } }
const formVacio = () => ({ cod: 0, nombre: '', domicilio: '', telefono: '', email: '', estado: 'A' })
const nuevo = () => { sel.value = null; form.value = formVacio(); tab.value = 1; err.value = '' }
const abrir = (o: OS) => { sel.value = o; form.value = { ...o }; tab.value = 1; err.value = '' }

const guardarDatos = async () => {
  if (!form.value.nombre.trim()) { err.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; err.value = ''
  try {
    const p = { nombre: form.value.nombre, domicilio: form.value.domicilio, telefono: form.value.telefono, email: form.value.email, estado: form.value.estado }
    if (form.value.cod) { await api.put(`/obras-sociales/${form.value.cod}`, p); flash('Datos guardados') }
    else { const { data } = await api.post('/obras-sociales', p); form.value.cod = data.cod; flash('Obra social creada') }
    await cargarLista(); sel.value = lista.value.find(o => o.cod === form.value.cod) ?? sel.value
  } catch (e: any) { err.value = e?.response?.data?.message ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async () => {
  if (!confirm('¿Eliminar esta obra social?')) return
  try { await api.delete(`/obras-sociales/${form.value.cod}`); flash('Obra social eliminada'); form.value = null; sel.value = null; await cargarLista() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

// Tab 2 — comprobantes
const comprobantes = ref<any[]>([]); const saldo = ref(0)
const fPeriodo = ref(false); const fFecha1 = ref(''); const fFecha2 = ref('')
const fTipoOn = ref(false); const fTipo = ref(''); const fEmpOn = ref(false); const fEmp = ref(0)
const compSel = computed(() => comprobantes.value.filter(c => c._sel))
const guardandoComp = ref(false); const errComp = ref('')
const nc = reactive({ empresa: 0, tipo: '', sucursal: 0, numero: 0, fecha: new Date().toISOString().slice(0, 10), importe: 0 })

const cargarComprobantes = async () => {
  if (!form.value?.cod) return
  try {
    const params: any = { periodo: fPeriodo.value ? 1 : 0 }
    if (fPeriodo.value) { params.fecha1 = fFecha1.value; params.fecha2 = fFecha2.value }
    if (fTipoOn.value) params.tipo = fTipo.value
    if (fEmpOn.value) params.empresa = fEmp.value
    const { data } = await api.get(`/obras-sociales/${form.value.cod}/comprobantes`, { params })
    comprobantes.value = data.comprobantes.map((c: any) => ({ ...c, _sel: false })); saldo.value = data.saldo
  } catch (e) { console.error(e) }
}
const confirmarComprobante = async () => {
  errComp.value = ''
  if (!nc.empresa) { errComp.value = 'Debe seleccionar la empresa.'; return }
  if (!nc.tipo.trim()) { errComp.value = 'Debe indicar el tipo de comprobante.'; return }
  if (!nc.numero) { errComp.value = 'Debe ingresar el número.'; return }
  if (!nc.fecha) { errComp.value = 'Debe ingresar la fecha.'; return }
  guardandoComp.value = true
  try {
    await api.post(`/obras-sociales/${form.value.cod}/comprobantes`, { empresa: nc.empresa, tipo: nc.tipo, sucursal: nc.sucursal, numero: nc.numero, fecha: nc.fecha, importe: nc.importe })
    flash('Comprobante grabado'); nc.tipo = ''; nc.sucursal = 0; nc.numero = 0; nc.importe = 0
    await cargarComprobantes()
  } catch (e: any) { errComp.value = e?.response?.data?.message ?? 'No se pudo grabar.' }
  finally { guardandoComp.value = false }
}
const eliminarComprobantes = async () => {
  if (!confirm(`¿Eliminar ${compSel.value.length} comprobante(s)?`)) return
  try { await api.delete(`/obras-sociales/${form.value.cod}/comprobantes`, { data: { unicos: compSel.value.map(c => c.unico) } }); flash('Comprobantes eliminados'); await cargarComprobantes() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

// PDF informe comprobantes
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  if (!comprobantes.value.length) { flash('No hay comprobantes para imprimir.', true); return }
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; let y = 22
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('COMPROBANTES DE OBRAS SOCIALES', mL, 15)
  doc.setFontSize(10); doc.text(sel.value?.nombre ?? '', mL, 21)
  doc.setLineWidth(0.4); doc.line(mL, 24, mR, 24); y = 30
  doc.setFontSize(8.5)
  const cols = [mL, mL + 26, mL + 46, mL + 64, mL + 92, mL + 132]
  doc.setFont('helvetica', 'bold')
  doc.text('Fecha', cols[0], y); doc.text('Tipo', cols[1], y); doc.text('Suc', cols[2], y); doc.text('Número', cols[3], y)
  doc.text('Debe', cols[4] + 22, y, { align: 'right' }); doc.text('Haber', mR, y, { align: 'right' }); y += 1
  doc.line(mL, y, mR, y); y += 4; doc.setFont('helvetica', 'normal')
  let td = 0, th = 0
  for (const c of comprobantes.value) {
    if (y > 280) { doc.addPage(); y = 20 }
    doc.text(fmt(c.fecha), cols[0], y); doc.text(c.tipo, cols[1], y); doc.text(String(c.sucursal || ''), cols[2], y); doc.text(String(c.numero), cols[3], y)
    doc.text(c.debe ? money(c.debe) : '', cols[4] + 22, y, { align: 'right' }); doc.text(c.haber ? money(c.haber) : '', mR, y, { align: 'right' })
    td += c.debe; th += c.haber; y += 4.6
  }
  doc.setLineWidth(0.2); doc.line(mL, y, mR, y); y += 5; doc.setFont('helvetica', 'bold')
  doc.text('TOTALES', cols[0], y); doc.text(money(td), cols[4] + 22, y, { align: 'right' }); doc.text(money(th), mR, y, { align: 'right' }); y += 6
  doc.text(`SALDO: ${money(td - th)}`, mR, y, { align: 'right' })
  cerrarPdf(); pdfNombre.value = 'Comprobantes_Obra_Social.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(async () => {
  await cargarLista()
  try { empresas.value = (await api.get('/empresas')).data } catch (e) { console.error(e) }
})
</script>

<style scoped>
.os-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.os-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.os-cab-ico { font-size:28px; } .os-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .os-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.os-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.os-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.os-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.os-body { display:flex; flex:1; overflow:hidden; }
.os-aside { width:290px; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; background:#f8fafc; }
.os-busc { margin:12px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; outline:none; }
.os-lista { flex:1; overflow:auto; list-style:none; margin:0; padding:0; }
.os-lista li { padding:9px 14px; border-bottom:1px solid #eef2f7; cursor:pointer; display:flex; flex-direction:column; gap:3px; }
.os-lista li:hover { background:#eef6ee; } .os-lista li.on { background:#1b4332; } .os-lista li.on b, .os-lista li.on span { color:#fff; }
.os-lista li b { font-size:13px; color:#1e293b; } .os-lista li span { font-size:11px; color:#6b7280; display:flex; align-items:center; gap:6px; }
.os-est { font-weight:700; padding:1px 6px; border-radius:999px; font-size:10px; } .os-est.a { background:#dcfce7; color:#166534; } .os-est.p { background:#fee2e2; color:#b91c1c; }
.os-vacio { color:#9ca3af; font-size:13px; text-align:center; padding:20px; cursor:default; }
.os-detalle { flex:1; overflow:auto; padding:18px; }
.os-placeholder { display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:14px; }
.os-tabs { display:flex; gap:4px; border-bottom:2px solid #e2e8f0; margin-bottom:18px; }
.os-tabs button { background:transparent; border:none; padding:10px 16px; font-size:13px; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; }
.os-tabs button.on { color:#1b4332; border-bottom-color:#1b4332; } .os-tabs button:disabled { color:#cbd5e1; cursor:not-allowed; }
.os-tab { max-width:920px; }
.os-fila { display:flex; gap:14px; flex-wrap:wrap; } .os-fila .os-c { flex:1; min-width:160px; }
.os-c { margin-bottom:14px; display:flex; flex-direction:column; gap:5px; } .os-c.os-chico { max-width:140px; }
.os-c label { font-size:12px; font-weight:600; color:#374151; } .os-c .req { color:#dc2626; }
.os-c input[type=text], .os-c input:not([type]) { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.os-c input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); } .os-c input:disabled { background:#f1f5f9; color:#94a3b8; }
.os-radios { display:flex; gap:16px; padding-top:6px; } .os-radios label { flex-direction:row; align-items:center; gap:5px; font-size:14px; font-weight:600; color:#1b4332; cursor:pointer; }
.os-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0; }
.os-acc1 { display:flex; gap:12px; align-items:center; }
.os-btn-guardar { background:#16a34a; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .os-btn-guardar:disabled { background:#cbd5e1; }
.os-btn-del { background:#fff; border:1px solid #ef4444; color:#dc2626; padding:10px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.os-cc-top { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:10px; }
.os-filtros { display:flex; align-items:center; gap:10px; flex-wrap:wrap; font-size:12px; }
.os-flbl { font-weight:700; color:#1b4332; } .os-filtros label { display:flex; align-items:center; gap:4px; font-weight:600; color:#374151; }
.os-fin { border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:12px; }
.os-saldo { font-size:14px; color:#374151; } .os-saldo b.pos { color:#166534; } .os-saldo b.neg { color:#b91c1c; }
.os-cc-acc { display:flex; gap:10px; margin-bottom:8px; }
.os-mini { background:#fff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .os-mini:hover { background:#f0faf4; } .os-mini.del:hover { background:#fee2e2; } .os-mini:disabled { opacity:.5; cursor:not-allowed; }
.os-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:38vh; margin-bottom:14px; }
.os-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.os-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .os-grid th.r { text-align:right; }
.os-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .os-grid td.r { text-align:right; }
.os-grid tr:nth-child(even) td { background:#f8fafc; } .os-grid tr.sel td { background:#fff200; }
.os-agregar { border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; }
.os-agtit { font-size:12px; font-weight:700; color:#1b4332; }
.os-agfila { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:8px; }
.os-agfila select, .os-agfila input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.os-btn-conf { background:#16a34a; color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .os-btn-conf:disabled { background:#cbd5e1; }
.os-msg { padding:8px 14px; margin:0 18px 12px; font-size:13px; border-radius:6px; } .os-msg.ok { background:#dcfce7; color:#166534; } .os-msg.err { background:#fee2e2; color:#b91c1c; }
.os-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.os-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.os-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.os-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.os-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .os-pdf-b.ok { background:#22c55e; color:#fff; } .os-pdf-b.cancel { background:#ef4444; color:#fff; }
.os-pdf-frame { flex:1; border:none; width:100%; }
</style>
