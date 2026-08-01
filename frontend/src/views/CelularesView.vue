<!-- CelularesView.vue — Telefonía Celular: Equipos Celulares (celulares.scx). ABM 2 solapas. -->
<template>
  <div class="ce-view">
    <div class="ce-cab">
      <div class="ce-ico">📲</div>
      <div class="ce-tx"><h1>Equipos Telefónicos Celulares</h1><p>Alta, modificación e historial de equipos</p></div>
      <button class="ce-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ce-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/celulares-equipos" titulo="Asistente IA — Equipos Celulares"
            subtitulo="Preguntá sobre los equipos celulares"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo doy de alta un equipo?','¿Cómo doy de baja un equipo?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ce-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ce-body">
      <!-- Solapas -->
      <div class="ce-tabs">
        <button :class="['ce-tab', tab === 'detalles' && 'on']" @click="tab = 'detalles'">Detalles</button>
        <button :class="['ce-tab hist', tab === 'historial' && 'on']" :disabled="modo !== 'ver'" @click="verHistorial">Historial a quiénes se les asignó el equipo</button>
        <span v-if="e.baja" class="ce-badge baja">dado de baja</span>
        <span v-else-if="fueraGarantia" class="ce-badge fg">fuera de Garantía</span>
      </div>

      <!-- DETALLES -->
      <div v-if="tab === 'detalles'" :class="['ce-panel', e.baja ? 'p-baja' : fueraGarantia ? 'p-fg' : '']">
        <!-- Barra de navegación / acciones (arriba) -->
        <div class="ce-nav ce-nav-top">
          <template v-if="modo === 'ver'">
            <button class="ce-nb" :disabled="!lista.length" @click="ir(0)">⏮</button>
            <button class="ce-nb" :disabled="idx <= 0" @click="ir(idx - 1)">◀</button>
            <button class="ce-nb" :disabled="idx >= lista.length - 1" @click="ir(idx + 1)">▶</button>
            <button class="ce-nb" :disabled="!lista.length" @click="ir(lista.length - 1)">⏭</button>
            <button class="ce-nb" @click="buscar = true">🔍 Buscar</button>
            <button class="ce-nb" @click="imprimir">🖨 Imprimir</button>
            <span style="flex:1"></span>
            <button class="ce-nb nuevo" @click="nuevo">➕ Nuevo</button>
            <button class="ce-nb editar" :disabled="!e.cod" @click="editar">✏️ Modificar</button>
          </template>
          <template v-else>
            <button class="ce-guardar" :disabled="guardando" @click="guardar">{{ guardando ? '⟳…' : '💾 Guardar' }}</button>
            <button class="ce-cancel" @click="cancelar">✕ Cancelar</button>
          </template>
        </div>

        <div class="ce-cols">
          <div class="ce-cl">
            <div class="ce-f"><label>Código</label><input :value="e.cod || '(nuevo)'" class="cod" readonly /></div>
            <div class="ce-f"><label>IMEI</label><input v-model="e.imei" v-enter-next :readonly="ro" maxlength="30" /></div>
            <div class="ce-f"><label>Marca</label><input v-model="e.marca" v-enter-next :readonly="ro" maxlength="30" /></div>
            <div class="ce-f"><label>Modelo</label><input v-model="e.modelo" v-enter-next :readonly="ro" maxlength="30" /></div>
            <div class="ce-f"><label>Color</label><input v-model="e.color" v-enter-next :readonly="ro" maxlength="15" /></div>
            <div class="ce-f"><label>Tamaño de Pantalla en Pulgadas</label><input v-model.number="e.pantalla" v-enter-next :readonly="ro" type="number" step="0.1" class="chico" /></div>
            <div class="ce-f"><label>Sistema Operativo</label><input v-model="e.sistema" v-enter-next :readonly="ro" maxlength="30" /></div>
            <div class="ce-f2">
              <div class="ce-f"><label>Fecha de Compra</label><input v-model="e.compra" v-enter-next :readonly="ro" type="date" /></div>
              <div class="ce-f"><label>Meses de Garantía</label><input v-model.number="e.garantia" v-enter-next :readonly="ro" type="number" class="chico" /></div>
            </div>
            <div class="ce-info">
              <span>{{ antiguedad }}</span>
              <b :class="fueraGarantia ? 'fg' : 'eg'">{{ fueraGarantia ? 'FUERA DE GARANTÍA' : 'EN GARANTÍA' }}</b>
            </div>
          </div>
          <div class="ce-cr">
            <label class="ce-baja"><input v-model="e.baja" type="checkbox" :disabled="ro" /> CELULAR DADO DE BAJA</label>
            <div class="ce-acc-box">
              <div class="ce-acc-tit">ACCESORIOS</div>
              <label><input v-model="e.cargador" type="checkbox" :disabled="ro" /> Con Cargador</label>
              <label><input v-model="e.auricular" type="checkbox" :disabled="ro" /> Con Auricular</label>
              <label><input v-model="e.cableusb" type="checkbox" :disabled="ro" /> Con Cable USB</label>
              <label><input v-model="e.vidrio" type="checkbox" :disabled="ro" /> con Vidrio Templado</label>
              <label><input v-model="e.carcasa" type="checkbox" :disabled="ro" /> Con Carcasa</label>
            </div>
            <template v-if="e.baja">
              <div class="ce-f"><label>Fecha de Baja</label><input v-model="e.fecha_baja" :readonly="ro" type="date" /></div>
              <div class="ce-f"><label>Razón de la baja</label><input v-model="e.razon_baja" :readonly="ro" maxlength="100" /></div>
            </template>
          </div>
        </div>
      </div>

      <!-- HISTORIAL -->
      <div v-else class="ce-panel hist-panel">
        <div class="ce-hist-cab">
          <span>Código <b>{{ e.cod }}</b></span><span>IMEI <b>{{ e.imei }}</b></span>
          <span class="leg dev">Devueltos</span><span class="leg baja">Equipos dados de baja</span>
        </div>
        <table class="ce-tabla">
          <thead><tr><th style="width:56px">Empl.</th><th>Apellido y Nombres</th><th>Nro. Celular</th><th style="width:50px">Cod.</th><th>IMEI</th><th style="width:90px">F.Entrega</th><th style="width:90px">F.Devolución</th></tr></thead>
          <tbody>
            <tr v-for="(h, i) in historial" :key="i" :class="{ dev: h.devuelto }">
              <td class="c">{{ h.empleado }}</td><td>{{ h.nombre }}</td><td>{{ h.nro_celular }}</td>
              <td class="c">{{ h.cod }}</td><td>{{ h.imei }}</td><td class="c">{{ fmt(h.entrega) }}</td><td class="c">{{ fmt(h.devolucion) }}</td>
            </tr>
            <tr v-if="!historial.length"><td colspan="7" class="vacio">El equipo no fue asignado a ningún empleado.</td></tr>
          </tbody>
        </table>
        <div class="ce-nav"><button class="ce-nb" @click="tab = 'detalles'">◀ Volver a Detalles</button></div>
      </div>
    </div>

    <CelularBuscar v-if="buscar" @select="onBuscar" @close="buscar = false" />

    <Teleport to="body">
      <div v-if="ayuda" class="ce-ov" @click.self="ayuda = false">
        <div class="ce-help-md">
          <h3>❓ Ayuda — Equipos Celulares</h3>
          <ul>
            <li>Navegá los equipos con ⏮ ◀ ▶ ⏭ o buscá uno con la 🔍.</li>
            <li><b>Nuevo</b> da de alta un equipo (el código se asigna solo); <b>Modificar</b> edita el actual.</li>
            <li>Completá IMEI, marca, modelo, color, pantalla, sistema, accesorios, fecha de compra y meses de garantía.</li>
            <li>Marcá <b>CELULAR DADO DE BAJA</b> para registrar la baja con su fecha y razón.</li>
            <li>La solapa <b>Historial</b> muestra a qué empleados se asignó el equipo.</li>
          </ul>
          <div class="ce-nav"><span style="flex:1"></span><button class="ce-nb" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="pdfUrl" class="ce-pdf-ov" @click.self="cerrarPdf">
        <div class="ce-pdf-md">
          <div class="ce-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="ce-pdf-acc">
              <button class="ce-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="ce-pdf-b ok" @click="($refs.pf as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="ce-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pf" :src="pdfUrl" class="ce-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import ChatIA from '@/components/ChatIA.vue'
import CelularBuscar from '@/components/CelularBuscar.vue'

type Eq = { cod: number, imei: string, marca: string, modelo: string, color: string, pantalla: number, sistema: string,
  cargador: boolean, auricular: boolean, cableusb: boolean, vidrio: boolean, carcasa: boolean,
  compra: string, garantia: number, baja: boolean, fecha_baja: string, razon_baja: string }
const vacio = (): Eq => ({ cod: 0, imei: '', marca: '', modelo: '', color: '', pantalla: 0, sistema: 'ANDROID',
  cargador: false, auricular: false, cableusb: false, vidrio: false, carcasa: false, compra: '', garantia: 12, baja: false, fecha_baja: '', razon_baja: '' })

const lista = ref<Eq[]>([]); const idx = ref(-1)
const e = reactive<Eq>(vacio())
const modo = ref<'ver' | 'nuevo' | 'editar'>('ver')
const tab = ref<'detalles' | 'historial'>('detalles')
const historial = ref<any[]>([])
const buscar = ref(false); const guardando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const ro = computed(() => modo.value === 'ver')
const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const flash = (t: string, e2 = false) => { msg.value = t; msgErr.value = e2; if (t && !e2) setTimeout(() => msg.value = '', 3500) }
const set = (o: Eq) => Object.assign(e, JSON.parse(JSON.stringify(o)))

const fueraGarantia = computed(() => {
  if (!e.compra) return false
  const venc = new Date(e.compra + 'T00:00:00'); venc.setDate(venc.getDate() + (e.garantia || 0) * 30)
  return venc < new Date()
})
const antiguedad = computed(() => {
  if (!e.compra) return ''
  const dif = Math.floor((Date.now() - new Date(e.compra + 'T00:00:00').getTime()) / 86400000)
  if (dif < 31) return `Antigüedad ${dif} ${dif === 1 ? 'día' : 'días'}.`
  if (dif < 365) { const m = Math.floor(dif / 30); return `Antigüedad ${m} ${m === 1 ? 'mes' : 'meses'}.` }
  const a = Math.floor(dif / 365); const m = Math.floor((dif - a * 365) / 30)
  return `Antigüedad ${a} ${a === 1 ? 'año' : 'años'}${m > 0 ? ' y ' + m + (m === 1 ? ' mes' : ' meses') : ''}.`
})

onMounted(cargarLista)
async function cargarLista (focoCod?: number) {
  try {
    lista.value = (await api.get('/celulares/equipos-lista')).data ?? []
    if (lista.value.length) {
      const i = focoCod ? lista.value.findIndex(x => x.cod === focoCod) : 0
      ir(i >= 0 ? i : 0)
    }
  } catch { flash('No se pudo cargar la lista de equipos.', true) }
}
function ir (i: number) { if (i < 0 || i >= lista.value.length) return; idx.value = i; set(lista.value[i]); tab.value = 'detalles' }
const onBuscar = (cod: number) => { buscar.value = false; const i = lista.value.findIndex(x => x.cod === cod); if (i >= 0) ir(i) }

function nuevo () { modo.value = 'nuevo'; set(vacio()); tab.value = 'detalles' }
function editar () { if (!e.cod) return; modo.value = 'editar'; tab.value = 'detalles' }
function cancelar () { modo.value = 'ver'; if (idx.value >= 0) set(lista.value[idx.value]) }

async function guardar () {
  if (!e.imei.trim()) { flash('Debe ingresar el IMEI del celular.', true); return }
  guardando.value = true
  try {
    const payload = { ...e }
    if (modo.value === 'nuevo') {
      const { data } = await api.post('/celulares/equipos', payload)
      flash('Equipo dado de alta.'); modo.value = 'ver'; await cargarLista(data.cod)
    } else {
      await api.put(`/celulares/equipos/${e.cod}`, payload)
      flash('Equipo modificado.'); modo.value = 'ver'; await cargarLista(e.cod)
    }
  } catch (e2: any) { flash(e2?.response?.data?.message ?? 'No se pudo guardar el equipo.', true) }
  finally { guardando.value = false }
}

async function verHistorial () {
  if (modo.value !== 'ver' || !e.cod) return
  try { historial.value = (await api.get(`/celulares/equipos/${e.cod}/historial`)).data ?? []; tab.value = 'historial' }
  catch { flash('No se pudo cargar el historial.', true) }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
function imprimir () {
  generarPdf(lista.value)
}
function generarPdf (rows: Eq[]) {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const PW = 297, ML = 8; let y = 14
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('INFORME DE CELULARES', PW / 2, y, { align: 'center' }); y += 8
  const cols = [
    { t: 'Cod.', w: 12, k: (r: Eq) => r.cod },
    { t: 'IMEI', w: 34, k: (r: Eq) => r.imei },
    { t: 'Marca', w: 26, k: (r: Eq) => r.marca },
    { t: 'Modelo', w: 36, k: (r: Eq) => r.modelo },
    { t: 'Color', w: 26, k: (r: Eq) => r.color },
    { t: 'Pulg', w: 12, k: (r: Eq) => r.pantalla || '' },
    { t: 'Sistema', w: 26, k: (r: Eq) => r.sistema },
    { t: 'Carg', w: 10, k: (r: Eq) => r.cargador ? 'SI' : '' },
    { t: 'Aur', w: 10, k: (r: Eq) => r.auricular ? 'SI' : '' },
    { t: 'USB', w: 10, k: (r: Eq) => r.cableusb ? 'SI' : '' },
    { t: 'Compra', w: 20, k: (r: Eq) => fmt(r.compra) },
    { t: 'Gar.', w: 12, k: (r: Eq) => r.garantia },
    { t: 'Baja', w: 10, k: (r: Eq) => r.baja ? 'SI' : '' },
    { t: 'F.Baja', w: 20, k: (r: Eq) => r.baja ? fmt(r.fecha_baja) : '' },
  ]
  const header = () => {
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setFillColor(230, 230, 230)
    doc.rect(ML, y - 4, cols.reduce((s, c) => s + c.w, 0), 6, 'F'); let x = ML
    for (const c of cols) { doc.text(c.t, x + 1, y); x += c.w }; y += 5
  }
  header(); doc.setFont('helvetica', 'normal'); doc.setFontSize(7)
  for (const r of rows) {
    if (y > 195) { doc.addPage(); y = 14; header(); doc.setFont('helvetica', 'normal'); doc.setFontSize(7) }
    let x = ML
    for (const c of cols) { doc.text(doc.splitTextToSize(String(c.k(r) ?? ''), c.w - 1).slice(0, 1), x + 1, y); x += c.w }
    y += 4.6
  }
  cerrarPdf(); pdfNombre.value = 'Lista_de_Celulares.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ce-view { display:flex; flex-direction:column; min-height:100%; }
.ce-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ce-ico { font-size:28px; } .ce-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ce-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ce-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ce-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ce-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ce-msg.ok { background:#d1fae5; color:#065f46; } .ce-msg.err { background:#fee2e2; color:#991b1b; }
.ce-body { padding:16px 18px; max-width:900px; }
.ce-tabs { display:flex; align-items:center; gap:4px; }
.ce-tab { background:#e2e8f0; border:none; padding:9px 16px; border-radius:8px 8px 0 0; cursor:pointer; font-weight:700; font-size:13px; color:#475569; }
.ce-tab.on { background:#0a7d32; color:#fff; } .ce-tab.hist { background:#d1fae5; color:#065f46; } .ce-tab.hist.on { background:#0a7d32; color:#fff; } .ce-tab:disabled { opacity:.5; cursor:default; }
.ce-badge { margin-left:8px; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:800; color:#fff; }
.ce-badge.baja { background:#dc2626; } .ce-badge.fg { background:#ea580c; }
.ce-panel { border:2px solid #0a7d32; border-radius:0 10px 10px 10px; padding:18px; background:#f0fdf4; }
.ce-panel.p-fg { border-color:#ea580c; background:#fff7ed; } .ce-panel.p-baja { border-color:#dc2626; background:#fef2f2; }
.ce-cols { display:flex; gap:22px; }
.ce-cl { flex:1; } .ce-cr { width:230px; }
.ce-f { margin-bottom:9px; } .ce-f label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:3px; }
.ce-f input { width:100%; box-sizing:border-box; border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; background:#fff; }
.ce-f input[readonly] { background:#f1f5f9; } .ce-f input.cod { width:80px; font-weight:800; background:#fef9c3; } .ce-f input.chico { width:90px; }
.ce-f2 { display:flex; gap:12px; } .ce-f2 .ce-f { flex:1; }
.ce-info { margin-top:6px; display:flex; flex-direction:column; gap:2px; } .ce-info span { font-size:13px; font-weight:700; color:#1e293b; } .ce-info b { font-size:14px; } .ce-info b.eg { color:#16a34a; } .ce-info b.fg { color:#ea580c; }
.ce-baja { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:800; color:#dc2626; margin-bottom:12px; }
.ce-acc-box { border:1px solid #cbd5e1; border-radius:8px; padding:10px; background:#fff; }
.ce-acc-tit { background:#0a6a00; color:#fff; text-align:center; font-size:12px; font-weight:800; border-radius:4px; padding:3px; margin-bottom:8px; }
.ce-acc-box label { display:flex; align-items:center; gap:7px; font-size:13px; color:#1e293b; margin:5px 0; }
.ce-nav { display:flex; align-items:center; gap:6px; margin-top:16px; flex-wrap:wrap; }
.ce-nav-top { margin-top:0; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid rgba(10,125,50,.25); }
.ce-nb { background:#eef2f7; border:none; border-radius:7px; padding:8px 12px; cursor:pointer; font-weight:700; font-size:13px; color:#334155; } .ce-nb:disabled { opacity:.4; cursor:default; }
.ce-nb.nuevo { background:#22c55e; color:#0f3d22; } .ce-nb.editar { background:#2563eb; color:#fff; }
.ce-guardar { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:9px 22px; cursor:pointer; font-weight:800; font-size:13px; } .ce-guardar:disabled { opacity:.5; }
.ce-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:700; font-size:13px; }
.hist-panel { border-color:#0a7d32; background:#f0fdf4; }
.ce-hist-cab { display:flex; align-items:center; gap:16px; margin-bottom:10px; font-size:13px; color:#1e293b; } .ce-hist-cab b { color:#0a7d32; }
.ce-hist-cab .leg { padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700; color:#1e293b; } .ce-hist-cab .leg.dev { background:#fde047; } .ce-hist-cab .leg.baja { background:#fca5a5; }
.ce-tabla { width:100%; border-collapse:collapse; font-size:12.5px; background:#fff; }
.ce-tabla th { background:#c8c8c8; color:#1e293b; text-align:left; padding:6px 9px; font-size:11.5px; white-space:nowrap; }
.ce-tabla td { border-bottom:1px solid #eef2f7; padding:6px 9px; color:#1e293b; } .ce-tabla td.c { text-align:center; }
.ce-tabla tr.dev td { background:#fef9c3; } .ce-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; }
.ce-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ce-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .ce-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ce-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.ce-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ce-pdf-md { width:min(960px,98vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.ce-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#0a7d32; color:#fff; font-size:13px; } .ce-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.ce-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ce-pdf-b.ok { background:#22c55e; color:#fff; } .ce-pdf-b.cancel { background:#ef4444; color:#fff; }
.ce-pdf-frame { flex:1; border:none; width:100%; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
