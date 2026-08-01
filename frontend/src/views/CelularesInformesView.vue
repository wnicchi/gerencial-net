<!-- CelularesInformesView.vue — Telefonía Celular: Informes (celulares_informes.scx). Filtros + PDF. -->
<template>
  <div class="ci-view">
    <div class="ci-cab">
      <div class="ci-ico">📊</div>
      <div class="ci-tx"><h1>Informe de Celulares</h1><p>Listado de celulares entregados</p></div>
      <button class="ci-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ci-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/celulares-informes" titulo="Asistente IA — Informe de Celulares"
            subtitulo="Preguntá sobre el informe de celulares"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo filtro el informe?','¿Qué incluye el listado?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ci-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ci-body">
      <div class="ci-filtros">
        <div class="ci-fila">
          <span class="ci-lbl">Empleados</span>
          <label><input v-model="empleadoModo" type="radio" value="todos" /> Todos</label>
          <label><input v-model="empleadoModo" type="radio" value="uno" /> Un</label>
          <EmpleadoInput v-if="empleadoModo === 'uno'" :codigo="emp || 0" :nombre="empNombre" @select="onLupaEmp" />
        </div>
        <div class="ci-fila">
          <span class="ci-lbl">Período de Entrega</span>
          <label><input v-model="periodoModo" type="radio" value="historico" /> Histórico</label>
          <label><input v-model="periodoModo" type="radio" value="rango" /> Rango</label>
          <template v-if="periodoModo === 'rango'">
            <input v-model="desde" type="date" /> <span>al</span> <input v-model="hasta" type="date" />
          </template>
        </div>
        <label class="ci-chk"><input v-model="soloActivos" type="checkbox" /> Sólo celulares entregados activos</label>
        <label class="ci-chk"><input v-model="incluirBajas" type="checkbox" /> Incluir celulares dados de baja</label>
        <button class="ci-btn" :disabled="cargando || generando" @click="consultar">
          {{ cargando ? '⟳ Consultando…' : generando ? '⟳ Generando…' : 'INFORME DE CELULARES' }}
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ci-ov" @click.self="ayuda = false">
        <div class="ci-help-md">
          <h3>❓ Ayuda — Informe de Celulares</h3>
          <ul>
            <li>Elegí <b>Todos</b> los empleados o <b>Un</b> empleado en particular.</li>
            <li>Elegí el período de entrega: <b>Histórico</b> (todo) o <b>Rango</b> de fechas.</li>
            <li>Marcá <b>Sólo entregados activos</b> para excluir los ya devueltos.</li>
            <li>Marcá <b>Incluir dados de baja</b> para sumar los equipos de baja.</li>
            <li>Presioná <b>INFORME DE CELULARES</b> para generar el PDF.</li>
          </ul>
          <div class="ci-acc"><span style="flex:1"></span><button class="ci-btn chico" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="pdfUrl" class="ci-pdf-ov" @click.self="cerrarPdf">
        <div class="ci-pdf-md">
          <div class="ci-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ci-pdf-acc">
              <button class="ci-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ci-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ci-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ci-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const empleadoModo = ref('todos'); const emp = ref<number | null>(null); const empNombre = ref('')
const onLupaEmp = (r: any) => { emp.value = r.cod; empleadoModo.value = 'uno'; cargarNombre() }
const periodoModo = ref('historico'); const desde = ref(''); const hasta = ref('')
const soloActivos = ref(true); const incluirBajas = ref(false)
const cargando = ref(false); const generando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

async function cargarNombre () {
  empNombre.value = ''
  if (!emp.value || emp.value <= 0) return
  try { const { data } = await api.get(`/celulares/empleado/${emp.value}`); empNombre.value = data.empleado.nombre }
  catch { flash('Código de empleado erróneo.', true); emp.value = null }
}

async function consultar () {
  if (empleadoModo.value === 'uno' && (!emp.value || emp.value <= 0)) { flash('Ingrese el empleado.', true); return }
  if (periodoModo.value === 'rango' && (!desde.value || !hasta.value)) { flash('Ingrese el rango de fechas.', true); return }
  cargando.value = true
  try {
    const { data } = await api.get('/celulares/informe', {
      params: {
        empleado: empleadoModo.value === 'uno' ? emp.value : 0,
        desde: periodoModo.value === 'rango' ? desde.value : null,
        hasta: periodoModo.value === 'rango' ? hasta.value : null,
        activos: soloActivos.value ? 1 : 0, bajas: incluirBajas.value ? 1 : 0,
      },
    })
    const lista = data.celulares ?? []
    if (!lista.length) { flash('No existen celulares con los parámetros seleccionados.', true); return }
    generarPdf(lista)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar el informe.', true) }
  finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function generarPdf (lista: any[]) {
  generando.value = true
  try {
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
    const PW = 297, ML = 8; let y = 14
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('INFORME DE CELULARES', PW / 2, y, { align: 'center' }); y += 8

    const cols = [
      { t: 'Empleado', w: 44, k: (r: any) => r.empleado },
      { t: 'N°Línea', w: 22, k: (r: any) => r.nro_celular },
      { t: 'Eq.', w: 10, k: (r: any) => r.equipo },
      { t: 'IMEI', w: 30, k: (r: any) => r.imei },
      { t: 'Entrega', w: 18, k: (r: any) => fmt(r.entrega) },
      { t: 'Devol.', w: 18, k: (r: any) => fmt(r.devolucion) },
      { t: 'Marca', w: 24, k: (r: any) => r.marca },
      { t: 'Modelo', w: 30, k: (r: any) => r.modelo },
      { t: 'Color', w: 22, k: (r: any) => r.color },
      { t: 'Pulg', w: 11, k: (r: any) => r.pantalla || '' },
      { t: 'Sistema', w: 22, k: (r: any) => r.sistema },
      { t: 'Carg', w: 9, k: (r: any) => r.cargador ? 'SI' : '' },
      { t: 'Aur', w: 9, k: (r: any) => r.auricular ? 'SI' : '' },
      { t: 'USB', w: 9, k: (r: any) => r.cableusb ? 'SI' : '' },
      { t: 'Baja', w: 9, k: (r: any) => r.baja ? 'SI' : '' },
    ]
    const header = () => {
      doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setFillColor(230, 230, 230)
      let x = ML; doc.rect(ML, y - 4, cols.reduce((s, c) => s + c.w, 0), 6, 'F')
      for (const c of cols) { doc.text(c.t, x + 1, y); x += c.w }
      y += 5
    }
    header()
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7)
    for (const r of lista) {
      if (y > 195) { doc.addPage(); y = 14; header(); doc.setFont('helvetica', 'normal'); doc.setFontSize(7) }
      let x = ML
      for (const c of cols) { doc.text(doc.splitTextToSize(String(c.k(r) ?? ''), c.w - 1).slice(0, 1), x + 1, y); x += c.w }
      y += 4.6
    }

    cerrarPdf(); pdfNombre.value = 'Informe_Celulares.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } finally { generando.value = false }
}
</script>

<style scoped>
.ci-view { display:flex; flex-direction:column; min-height:100%; }
.ci-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ci-ico { font-size:28px; } .ci-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ci-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ci-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ci-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ci-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ci-msg.ok { background:#d1fae5; color:#065f46; } .ci-msg.err { background:#fee2e2; color:#991b1b; }
.ci-body { padding:16px 18px; max-width:640px; }
.ci-filtros { border:1px solid #e2e8f0; border-radius:12px; padding:20px; background:#fafdff; display:flex; flex-direction:column; gap:16px; }
.ci-fila { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.ci-lbl { font-size:13px; font-weight:700; color:#374151; width:130px; }
.ci-fila label, .ci-chk { display:flex; align-items:center; gap:6px; font-size:14px; color:#1e293b; cursor:pointer; }
.ci-num { width:80px; border:1px solid #c8d8ea; border-radius:6px; padding:6px 8px; font-weight:700; }
.ci-lupa { background:#394959; color:#fff; border:none; padding:6px 10px; border-radius:6px; cursor:pointer; font-size:13px; }
.ci-nom { font-size:13px; color:#334155; font-weight:600; }
.ci-fila input[type=date] { border:1px solid #c8d8ea; border-radius:6px; padding:6px 8px; font-size:13px; }
.ci-chk { font-weight:600; }
.ci-btn { background:#22c55e; color:#0f3d22; border:none; border-radius:8px; padding:12px; cursor:pointer; font-weight:800; font-size:14px; margin-top:4px; } .ci-btn:disabled { opacity:.5; } .ci-btn.chico { background:#7f1d1d; color:#fff; padding:9px 18px; }
.ci-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.ci-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ci-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .ci-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ci-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ci-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ci-pdf-md { width:min(960px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ci-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .ci-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ci-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ci-pdf-b.ok { background:#22c55e; color:#fff; } .ci-pdf-b.cancel { background:#ef4444; color:#fff; }
.ci-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
