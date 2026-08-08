<template>
  <div class="ie">
    <div class="ie-cab">
      <div class="ie-cab-ico">📊</div>
      <div class="ie-cab-tx">
        <h1>Proyección Ingresos y Egresos</h1>
        <p>Egresos (a pagar) vs Ingresos (a cobrar) por semana, en miles de pesos</p>
      </div>
      <div class="ie-acc">
        <label class="ie-f">Semanas
          <select v-model.number="nSemanas" class="ie-sel">
            <option v-for="n in [2,3,4,5]" :key="n" :value="n">{{ n }}</option>
          </select>
        </label>
        <button class="ie-btn" :disabled="cargando" @click="generar">{{ cargando ? 'Generando…' : '↻ Generar' }}</button>
        <button v-if="semanas.length" class="ie-btn" @click="imprimir">🖨 Crear PDF</button>
        <ModuloAyudaIA modulo="Proyección Ingresos y Egresos" icono="📊"
          descripcion="Muestra un gráfico de barras que compara, semana a semana, los EGRESOS (lo que se proyecta pagar: impuestos, leasing, créditos, fondo fijo, seguros, haberes, cheques diferidos, transferencias, cheques a proveedores, órdenes de compra, interbanking) contra los INGRESOS (lo que se proyecta cobrar: cobranzas de clientes y cheques en cartera), en miles de pesos. Sirve para ver de un vistazo si en cada semana se cobra más de lo que se paga. Se puede exportar a PDF."
          intro='Compara por semana los <b>egresos (rojo)</b> vs los <b>ingresos (verde)</b>.'
          :pasos="['Elegí cuántas <b>semanas</b> (2 a 5).', '<b>↻ Generar</b> arma el gráfico.', '<b>🖨 Crear PDF</b> lo exporta.']"
          :notas="['Egresos = lo proyectado a pagar; Ingresos = cobranzas + cheques en cartera.', 'Los ingresos se calculan sobre las primeras 4 semanas.']" />
      </div>
    </div>

    <transition name="msg"><div v-if="msg" class="ie-msg">{{ msg }}</div></transition>
    <div v-if="cargando" class="ie-load">Calculando {{ nSemanas }} semana(s)…</div>

    <div v-if="semanas.length" class="ie-graf">
      <div class="ie-graf-tit">PROYECCIÓN DE INGRESOS Y EGRESOS (EN MILES DE PESOS)</div>
      <div class="ie-leyenda"><span class="ie-lg egr">■ Egresos</span><span class="ie-lg ing">■ Ingresos</span></div>
      <svg :viewBox="`0 0 ${gW} ${gH}`" class="ie-svg" preserveAspectRatio="xMidYMid meet">
        <line v-for="(gl, i) in gridY" :key="'g'+i" :x1="mL" :y1="gl.y" :x2="gW - mR" :y2="gl.y" class="ie-grid" />
        <text v-for="(gl, i) in gridY" :key="'t'+i" :x="mL - 6" :y="gl.y + 3" class="ie-yl">{{ gl.v }}</text>
        <g v-for="(s, i) in semanas" :key="i">
          <rect :x="barX(i, 0)" :y="barY(s.egresos)" :width="barW" :height="barH(s.egresos)" class="ie-bar egr" />
          <rect :x="barX(i, 1)" :y="barY(s.ingresos)" :width="barW" :height="barH(s.ingresos)" class="ie-bar ing" />
          <text :x="grupoCx(i)" :y="gH - mB + 14" class="ie-xl">{{ s.label }}</text>
        </g>
      </svg>
    </div>
    <div v-else-if="!cargando" class="ie-vacio">Elegí cuántas semanas y tocá Generar.</div>

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
 * ProyeccionIngresosEgresosView.vue — Estadísticas › Proyecciones › Gráfica Ingresos y Egresos.
 * Barras por semana: egresos (rojo, = total de la Proyección Semanal) vs ingresos
 * (verde, = Cobranzas + Cheques en Cartera de la Proyección Mensual), en miles.
 * Reutiliza los endpoints Semanal y Mensual. Migra proyecciones_ingresos_egresos.scx.
 */
import { ref, computed } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { guardarDesdeUrl } from '@/utils/descargas'
import { useAuthStore } from '@/stores/auth'
import ModuloAyudaIA from '@/components/ModuloAyudaIA.vue'

const auth = useAuthStore()

interface Sem { label: string; egresos: number; ingresos: number }

const nSemanas = ref(4)
const semanas = ref<Sem[]>([])
const cargando = ref(false)
const msg = ref('')

function lunesDe (d: Date): Date { const x = new Date(d); x.setHours(0, 0, 0, 0); const dow = x.getDay(); x.setDate(x.getDate() - ((dow + 6) % 7)); return x }
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const ddmmyyyy = (d: Date) => `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`

async function generar () {
  cargando.value = true; msg.value = ''; semanas.value = []
  try {
    const base = lunesDe(new Date())
    // INGRESOS: del endpoint Mensual (Cobranzas O + Cheques en Cartera R por semana s1..s4).
    const men = (await api.get('/tablero/gestion/proyecciones')).data
    const ingSem = [0, 0, 0, 0]
    for (const f of men.filas as any[]) if (f.tipo === 'O' || f.tipo === 'R') { ingSem[0] += f.s1 || 0; ingSem[1] += f.s2 || 0; ingSem[2] += f.s3 || 0; ingSem[3] += f.s4 || 0 }
    // EGRESOS: del endpoint Semanal por cada lunes (total de la semana).
    const out: Sem[] = []
    for (let k = 0; k < nSemanas.value; k++) {
      const mon = new Date(base); mon.setDate(base.getDate() + k * 7)
      const sem = (await api.get('/tablero/gestion/proyecciones-semanal', { params: { lunes: iso(mon) } })).data
      out.push({ label: 'Semana del ' + ddmmyyyy(mon), egresos: (sem.total_gral || 0) / 1000, ingresos: (ingSem[k] || 0) / 1000 })
    }
    semanas.value = out
    if (!out.some(s => s.egresos > 0 || s.ingresos > 0)) msg.value = 'No hay movimientos proyectados en el período elegido.'
  } catch { msg.value = 'No se pudo generar el gráfico.' }
  finally { cargando.value = false }
}

// ── Gráfico ──
const gW = computed(() => Math.max(720, 60 + semanas.value.length * 130 + 20))
const gH = 440, mL = 70, mR = 20, mT = 20, mB = 40
const escala = computed(() => { const m = Math.max(1, ...semanas.value.flatMap(s => [s.egresos, s.ingresos])); const p = Math.pow(10, Math.floor(Math.log10(m))); return Math.ceil(m / p) * p })
const barW = 42
function grupoX (i: number) { const paso = (gW.value - mL - mR) / Math.max(1, semanas.value.length); return mL + i * paso + paso / 2 }
function grupoCx (i: number) { return grupoX(i) }
function barX (i: number, serie: number) { return grupoX(i) - barW - 3 + serie * (barW + 6) }
function barH (m: number) { return Math.max(0, m) / escala.value * (gH - mT - mB) }
function barY (m: number) { return gH - mB - barH(m) }
const gridY = computed(() => { const out: { y: number; v: string }[] = []; for (let i = 0; i <= 6; i++) { const val = escala.value * i / 6; out.push({ y: gH - mB - (val / escala.value) * (gH - mT - mB), v: new Intl.NumberFormat('es-AR').format(Math.round(val)) }) } return out })

// ── PDF ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const pdfFrame = ref<HTMLIFrameElement>()
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

function imprimir () {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const ML = 12, MR = 285; let y = 15
  doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.text('PROYECCIONES DE INGRESOS Y EGRESOS', 148.5, y, { align: 'center' })
  doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  doc.text('SILCAR Logística y Representaciones S.A', ML, y); doc.text(new Date().toLocaleString('es-AR'), MR, y, { align: 'right' })
  doc.setFontSize(9); doc.text('En miles de pesos', 148.5, y + 6, { align: 'center' }); y += 12
  // leyenda
  doc.setFillColor(220, 38, 38); doc.rect(ML, y - 2, 4, 4, 'F'); doc.setTextColor(60, 60, 60); doc.setFontSize(8); doc.text('Egresos', ML + 6, y + 1.5)
  doc.setFillColor(34, 197, 94); doc.rect(ML + 26, y - 2, 4, 4, 'F'); doc.text('Ingresos', ML + 32, y + 1.5); y += 8

  const x0 = ML + 18, x1 = MR, y0 = y, gh = 150
  const maxM = Math.max(1, ...semanas.value.flatMap(s => [s.egresos, s.ingresos]))
  const esc = (() => { const p = Math.pow(10, Math.floor(Math.log10(maxM))); return Math.ceil(maxM / p) * p })()
  doc.setDrawColor(210, 210, 210); doc.setFontSize(7); doc.setTextColor(110, 110, 110)
  for (let i = 0; i <= 6; i++) { const yy = y0 + gh - (i / 6) * gh; doc.line(x0, yy, x1, yy); doc.text(new Intl.NumberFormat('es-AR').format(Math.round(esc * i / 6)), x0 - 2, yy + 1.5, { align: 'right' }) }
  const paso = (x1 - x0) / semanas.value.length, bw = 12
  semanas.value.forEach((s, i) => {
    const cx = x0 + i * paso + paso / 2
    doc.setFillColor(220, 38, 38); const he = s.egresos / esc * gh; doc.rect(cx - bw - 2, y0 + gh - he, bw, he, 'F')
    doc.setFillColor(34, 197, 94); const hi = s.ingresos / esc * gh; doc.rect(cx + 2, y0 + gh - hi, bw, hi, 'F')
    doc.setTextColor(60, 60, 60); doc.setFontSize(7); doc.text(s.label, cx, y0 + gh + 5, { align: 'center' })
  })
  const usuario = auth.usuario?.NOMBRE || ''
  doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(120, 120, 120)
  doc.text(usuario, ML, 200); doc.text(`Generado ${new Date().toLocaleDateString('es-AR')}`, MR, 200, { align: 'right' })
  cerrarPdf(); pdfNombre.value = 'PROYECCION_INGRESOS_EGRESOS.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}
</script>

<style scoped>
.ie { padding: 14px 16px 40px; color: #1e293b; }
.ie-cab { display: flex; align-items: center; gap: 12px; background: #1b4332; color: #fff; padding: 12px 16px; border-radius: 10px; }
.ie-cab-ico { font-size: 28px; } .ie-cab-tx h1 { margin: 0; font-size: 19px; } .ie-cab-tx p { margin: 2px 0 0; font-size: 12px; opacity: .85; }
.ie-acc { margin-left: auto; display: flex; gap: 8px; align-items: flex-end; }
.ie-f { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 700; color: #d1fae5; }
.ie-sel { border: 1px solid #cbd5e1; border-radius: 7px; padding: 6px 9px; font-size: 13px; color: #1e293b; background: #fff; }
.ie-btn { border: none; border-radius: 7px; padding: 8px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; background: #2d6a4f; color: #fff; }
.ie-btn:disabled { opacity: .6; cursor: default; }
.ie-msg { margin-top: 10px; padding: 9px 14px; border-radius: 8px; background: #fef9c3; color: #854d0e; font-weight: 600; font-size: 13px; }
.msg-enter-active, .msg-leave-active { transition: opacity .25s; } .msg-enter-from, .msg-leave-to { opacity: 0; }
.ie-load { margin-top: 16px; color: #64748b; font-size: 13px; }
.ie-vacio { margin-top: 40px; text-align: center; color: #94a3b8; font-size: 14px; }
.ie-graf { margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; background: #fff; overflow-x: auto; }
.ie-graf-tit { text-align: center; font-size: 13px; font-weight: 700; color: #334155; }
.ie-leyenda { display: flex; justify-content: center; gap: 18px; margin: 4px 0 8px; font-size: 12px; font-weight: 700; }
.ie-lg.egr { color: #dc2626; } .ie-lg.ing { color: #16a34a; }
.ie-svg { width: 100%; height: auto; min-width: 700px; }
.ie-grid { stroke: #e2e8f0; stroke-width: 1; }
.ie-yl { fill: #64748b; font-size: 10px; text-anchor: end; }
.ie-xl { fill: #334155; font-size: 11px; font-weight: 600; text-anchor: middle; }
.ie-bar.egr { fill: #dc2626; } .ie-bar.ing { fill: #16a34a; }
/* Modal PDF */
.pdf-ov { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.pdf-md { width: min(1040px, 96vw); height: 90vh; background: #fff; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; }
.pdf-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #f4f7fc; border-bottom: 1px solid #dde4ee; font-weight: 700; color: #1a3a5c; }
.pdf-acc { display: flex; gap: 8px; } .pdf-b { border: none; border-radius: 6px; padding: 7px 12px; font-weight: 700; font-size: 12.5px; cursor: pointer; }
.pdf-b.ok { background: #1b4332; color: #fff; } .pdf-b.cancel { background: #e2e8f0; color: #334155; } .pdf-frame { flex: 1; border: none; width: 100%; }
</style>
