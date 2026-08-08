<template>
  <div class="pg">
    <div class="pg-cab">
      <div class="pg-cab-ico">📊</div>
      <div class="pg-cab-tx">
        <h1>Proyección Gráfica Mensual</h1>
        <p>Total proyectado por día (en miles de pesos), sobre las próximas semanas</p>
      </div>
      <div class="pg-acc">
        <label class="pg-f">Semanas
          <select v-model.number="nSemanas" class="pg-sel">
            <option v-for="n in [2,3,4,5]" :key="n" :value="n">{{ n }}</option>
          </select>
        </label>
        <button class="pg-btn" :disabled="cargando" @click="generar">{{ cargando ? 'Generando…' : '↻ Generar' }}</button>
        <button v-if="dias.length" class="pg-btn" @click="imprimir">🖨 Crear PDF</button>
        <ModuloAyudaIA modulo="Proyección Gráfica Mensual" icono="📊"
          descripcion="Muestra un gráfico de barras con el total proyectado por día (en miles de pesos) a lo largo de la cantidad de semanas elegida (entre 2 y 5). Cada barra es la suma de todos los conceptos y bancos de ese día, en valor absoluto. Usa el mismo cálculo que la Proyección Semanal (sin Cobranzas ni Cheques en Cartera). Se puede exportar el gráfico a PDF."
          intro='Grafica el <b>total proyectado por día</b> (en miles) de varias semanas.'
          :pasos="['Elegí cuántas <b>semanas</b> analizar (2 a 5).', '<b>↻ Generar</b> arma el gráfico.', '<b>🖨 Crear PDF</b> lo exporta.']"
          :notas="['Cada barra es el total de ese día en miles de pesos.']" />
      </div>
    </div>

    <transition name="msg"><div v-if="msg" class="pg-msg">{{ msg }}</div></transition>
    <div v-if="cargando" class="pg-load">Calculando {{ nSemanas }} semana(s)…</div>

    <div v-if="dias.length" class="pg-graf">
      <div class="pg-graf-tit">PROYECCIÓN MENSUAL EN MILES DE PESOS</div>
      <svg :viewBox="`0 0 ${gW} ${gH}`" class="pg-svg" preserveAspectRatio="xMidYMid meet">
        <line v-for="(gl, i) in gridY" :key="'g'+i" :x1="mL" :y1="gl.y" :x2="gW - mR" :y2="gl.y" class="pg-grid" />
        <text v-for="(gl, i) in gridY" :key="'t'+i" :x="mL - 6" :y="gl.y + 3" class="pg-yl">{{ gl.v }}</text>
        <g v-for="(d, i) in dias" :key="i">
          <rect :x="barX(i)" :y="barY(d.miles)" :width="barW" :height="barH(d.miles)" class="pg-bar" />
          <text :x="barX(i) + barW / 2" :y="gH - mB + 4" class="pg-xl" :transform="`rotate(-60 ${barX(i) + barW/2} ${gH - mB + 4})`">{{ d.label }}</text>
        </g>
      </svg>
    </div>
    <div v-else-if="!cargando" class="pg-vacio">Elegí cuántas semanas y tocá Generar.</div>

    <!-- Modal PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pdf-ov" @click.self="cerrarPdf">
        <div class="pdf-md">
          <div class="pdf-head"><span>{{ pdfNombre }}</span>
            <div class="pdf-acc">
              <button class="pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
/**
 * ProyeccionGraficaView.vue — Estadísticas › Proyecciones › Proyección Gráfica Mensual.
 * Gráfico de barras del total proyectado por día (en miles) sobre N semanas (2-5).
 * Reutiliza el endpoint de la Proyección Semanal (sus totales por día) una vez por
 * semana. Migra proyecciones_mensuales.scx.
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { useAuthStore } from '@/stores/auth'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'

const auth = useAuthStore()

interface Dia { fecha: Date; label: string; miles: number }

const nSemanas = ref(4)
const dias = ref<Dia[]>([])
const cargando = ref(false)
const msg = ref('')

const DIAS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
const MES3 = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC']
function lunesDe (d: Date): Date { const x = new Date(d); x.setHours(0, 0, 0, 0); const dow = x.getDay(); x.setDate(x.getDate() - ((dow + 6) % 7)); return x }
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

async function generar () {
  cargando.value = true; msg.value = ''; dias.value = []
  try {
    const base = lunesDe(new Date())
    const acc: Dia[] = []
    for (let k = 0; k < nSemanas.value; k++) {
      const mon = new Date(base); mon.setDate(base.getDate() + k * 7)
      const d = (await api.get('/tablero/gestion/proyecciones-semanal', { params: { lunes: iso(mon) } })).data
      // d.dias = [{label,total}] Lun..Vie de esa semana (total en pesos, absoluto)
      d.dias.forEach((x: { total: number }, i: number) => {
        const f = new Date(mon); f.setDate(mon.getDate() + i)
        acc.push({ fecha: f, label: `${DIAS[f.getDay()]} ${f.getDate()} de ${MES3[f.getMonth()]}`, miles: (x.total || 0) / 1000 })
      })
    }
    dias.value = acc
    if (!acc.some(d => d.miles > 0)) msg.value = 'No hay movimientos proyectados en el período elegido.'
  } catch { msg.value = 'No se pudo generar el gráfico.' }
  finally { cargando.value = false }
}

// ── Gráfico ──
const gW = computed(() => Math.max(720, 60 + dias.value.length * 34 + 20))
const gH = 420, mL = 60, mR = 20, mT = 20, mB = 90
const escala = computed(() => { const m = Math.max(1, ...dias.value.map(d => d.miles)); const p = Math.pow(10, Math.floor(Math.log10(m))); return Math.ceil(m / p) * p })
const barW = 22
function barX (i: number) { const paso = (gW.value - mL - mR) / Math.max(1, dias.value.length); return mL + i * paso + (paso - barW) / 2 }
function barH (m: number) { return Math.max(0, m) / escala.value * (gH - mT - mB) }
function barY (m: number) { return gH - mB - barH(m) }
const gridY = computed(() => { const out: { y: number; v: string }[] = []; for (let i = 0; i <= 5; i++) { const val = escala.value * i / 5; out.push({ y: gH - mB - (val / escala.value) * (gH - mT - mB), v: new Intl.NumberFormat('es-AR').format(Math.round(val)) }) } return out })

// ── PDF ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const pdfFrame = ref<HTMLIFrameElement>()
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function imprimir () {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 12, MR = 285; let y = 15
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.text('PROYECCIÓN GRÁFICA MENSUAL', 148.5, y, { align: 'center' })
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  doc.text('SILCAR Logística y Representaciones S.A', ML, y); doc.text(new Date().toLocaleString('es-AR'), MR, y, { align: 'right' })
  doc.setFontSize(9); doc.text('En miles de pesos', 148.5, y + 6, { align: 'center' }); y += 12

  const x0 = ML + 18, x1 = MR, y0 = y, gh = 150
  const maxM = Math.max(1, ...dias.value.map(d => d.miles))
  const esc = (() => { const p = Math.pow(10, Math.floor(Math.log10(maxM))); return Math.ceil(maxM / p) * p })()
  doc.setDrawColor(210, 210, 210); doc.setFontSize(7); doc.setTextColor(110, 110, 110)
  for (let i = 0; i <= 5; i++) { const yy = y0 + gh - (i / 5) * gh; doc.line(x0, yy, x1, yy); doc.text(new Intl.NumberFormat('es-AR').format(Math.round(esc * i / 5)), x0 - 2, yy + 1.5, { align: 'right' }) }
  const paso = (x1 - x0) / dias.value.length, bw = Math.min(paso * 0.6, 8)
  doc.setFillColor(220, 38, 38)
  dias.value.forEach((d, i) => {
    const h = d.miles / esc * gh, bx = x0 + i * paso + (paso - bw) / 2
    doc.rect(bx, y0 + gh - h, bw, h, 'F')
    doc.setTextColor(60, 60, 60); doc.setFontSize(6)
    doc.text(d.label, bx + bw / 2, y0 + gh + 3, { align: 'right', angle: 60 })
    doc.setFillColor(220, 38, 38)
  })
  const usuario = auth.usuario?.NOMBRE || ''
  doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(120, 120, 120)
  doc.text(usuario, ML, 200); doc.text(`Generado ${new Date().toLocaleDateString('es-AR')}`, MR, 200, { align: 'right' })
  cerrarPdf(); pdfNombre.value = 'PROYECCION_GRAFICA_MENSUAL.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.pg { padding: 14px 16px 40px; color: #1e293b; }
.pg-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.pg-cab-ico { font-size: 28px; } .pg-cab-tx h1 { margin: 0; font-size: 19px; } .pg-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.pg-acc { margin-left: auto; display: flex; gap: 8px; align-items: flex-end; }
.pg-f { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 700; color: #d1fae5; }
.pg-sel { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 9px; font-size: 13px; color: #1e293b; background: #fff; }
.pg-btn { border: none; border-radius: 7px; padding: 8px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.pg-btn:disabled { opacity: .6; cursor: default; }
.pg-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #fef9c3; color: #854d0e; font-weight: 600; font-size: 13px; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.pg-load { margin-top: 16px; color: #64748b; font-size: 13px; }
.pg-vacio { margin-top: 40px; text-align: center; color: #94a3b8; font-size: 14px; }
.pg-graf { margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; background: #fff; overflow-x: auto; }
.pg-graf-tit { text-align: center; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px; }
.pg-svg { width: 100%; height: auto; min-width: 700px; }
.pg-grid { stroke: #e2e8f0; stroke-width: 1; }
.pg-yl { fill: #64748b; font-size: 10px; text-anchor: end; }
.pg-xl { fill: #334155; font-size: 9px; font-weight: 600; text-anchor: end; }
.pg-bar { fill: #dc2626; }
/* Modal PDF */
.pdf-ov { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.pdf-md { width: min(1040px, 96vw); height: 90vh; background: #fff; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; }
.pdf-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #f4f7fc; border-bottom: 1px solid #dde4ee; font-weight: 700; color: #1a3a5c; }
.pdf-acc { display: flex; gap: 8px; } .pdf-b { border: none; border-radius: 6px; padding: 7px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; }
.pdf-b.ok { background: #1b4332; color: #fff; } .pdf-b.cancel { background: #e2e8f0; color: #334155; } .pdf-frame { flex: 1; border: none; width: 100%; }
</style>
