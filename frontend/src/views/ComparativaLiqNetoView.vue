<!-- ComparativaLiqNetoView.vue — Comparativa Liquidaciones vs Neto (sueldos_comparar_liq_neto). -->
<template>
  <div class="cn-view">
    <div class="cn-cab">
      <div class="cn-cab-ico">⚖️</div>
      <div class="cn-cab-tx"><h1>Comparativa Liq. vs Neto</h1><p>Compara lo liquidado del período contra el neto de ficha</p></div>
      <button class="cn-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cn-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ComparativaLiqNetoAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/comparativa-liquidaciones" titulo="Asistente IA — Comparativa Liq. vs Neto"
            subtitulo="Preguntá sobre la comparativa"
            :sugerencias="['¿Qué compara?','¿Qué significa la diferencia?','¿Cómo filtro por empresa?']"
            @close="modalIA = false" />

    <div class="cn-filtros">
      <div class="cn-f"><span class="cn-lbl">Empresa</span><select v-model.number="f.empresa" class="cn-sel"><option :value="0">Todas</option><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Contratista</span><select v-model.number="f.contratista" class="cn-sel"><option :value="0">Todas</option><option v-for="o in cat.contratistas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Lugar</span><select v-model.number="f.lugar" class="cn-sel"><option :value="0">Todos</option><option v-for="o in cat.lugares" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Convenio</span><select v-model.number="f.convenio" class="cn-sel"><option :value="0">Todos</option><option v-for="o in cat.convenios" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Categoría</span><select v-model.number="f.categoria" class="cn-sel"><option :value="0">Todas</option><option v-for="o in cat.categorias" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Banco</span><select v-model.number="f.banco" class="cn-sel"><option :value="0">Todos</option><option v-for="o in cat.bancos" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Mes</span><select v-model.number="mes" class="cn-sel chico"><option v-for="m in 12" :key="m" :value="m">{{ m }}</option></select></div>
      <div class="cn-f"><span class="cn-lbl">Año</span><input v-model.number="anio" type="number" class="cn-anio" /></div>
      <button class="cn-btn-ok" :disabled="cargando" @click="visualizar">{{ cargando ? '⟳…' : 'VISUALIZAR' }}</button>
    </div>

    <div v-if="filas.length" class="cn-wrap">
      <table class="cn-grid">
        <thead><tr><th>Legajo</th><th>Nombre</th><th class="r">Liquidado</th><th class="r">Neto</th><th class="r">Diferencia</th></tr></thead>
        <tbody>
          <tr v-for="(r, i) in filas" :key="i" :class="{ dif: Math.abs(r.diferencia) >= 0.01 }">
            <td>{{ r.legajo }}</td><td>{{ r.nombre }}</td><td class="r">{{ money(r.liquidado) }}</td><td class="r">{{ money(r.neto) }}</td>
            <td class="r" :class="r.diferencia < 0 ? 'neg' : (r.diferencia > 0 ? 'pos' : '')">{{ money(r.diferencia) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="consultado" class="cn-vacio">No hay liquidaciones para los filtros y período seleccionados.</div>

    <div v-if="filas.length" class="cn-pie">
      <span class="cn-tot">{{ filas.length }} empleados · con diferencia: {{ conDif }}</span>
      <button class="cn-btn-pdf" @click="imprimir">📄 Visualizar / Imprimir PDF</button>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="cn-pdf-ov" @click.self="cerrarPdf">
        <div class="cn-pdf-md">
          <div class="cn-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="cn-pdf-acc">
              <button class="cn-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="cn-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="cn-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="cn-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import ComparativaLiqNetoAyuda from '@/components/ComparativaLiqNetoAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Fila { legajo: number; nombre: string; liquidado: number; neto: number; diferencia: number }
const hoy = new Date()
// Por defecto: mes anterior (la comparativa filtra por mes/año de liquidación).
const _prev = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)
const mes = ref(_prev.getMonth() + 1); const anio = ref(_prev.getFullYear())
const f = reactive({ empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, banco: 0 })
const cat = reactive<{ empresas: any[]; contratistas: any[]; lugares: any[]; convenios: any[]; categorias: any[]; bancos: any[] }>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], bancos: [] })
const filas = ref<Fila[]>([]); const cargando = ref(false); const consultado = ref(false)
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const money = (v: number) => nf.format(Number(v || 0))
const conDif = computed(() => filas.value.filter(r => Math.abs(r.diferencia) >= 0.01).length)

const visualizar = async () => {
  cargando.value = true; consultado.value = true
  try { filas.value = (await api.get('/comparativa-liquidaciones', { params: { mes: mes.value, anio: anio.value, ...f } })).data.filas }
  catch (e) { console.error(e); filas.value = [] } finally { cargando.value = false }
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const mL = 14, mR = 196; let y = 16
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('COMPARATIVA LIQUIDACIONES DE SUELDO', mL, y); y += 6
  doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.text(`Mes ${mes.value} Año ${anio.value}`, mL, y); y += 4
  doc.setLineWidth(0.4); doc.line(mL, y, mR, y); y += 5
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5)
  doc.text('Legajo', mL, y); doc.text('Nombre', mL + 22, y); doc.text('Liquidado', mL + 110, y, { align: 'right' }); doc.text('Neto', mL + 145, y, { align: 'right' }); doc.text('Diferencia', mR, y, { align: 'right' }); y += 1
  doc.line(mL, y, mR, y); y += 4; doc.setFont('helvetica', 'normal'); doc.setFontSize(8)
  for (const r of filas.value) {
    if (y > 282) { doc.addPage(); y = 20 }
    doc.text(String(r.legajo), mL, y); doc.text(r.nombre.slice(0, 40), mL + 22, y)
    doc.text(money(r.liquidado), mL + 110, y, { align: 'right' }); doc.text(money(r.neto), mL + 145, y, { align: 'right' })
    if (Math.abs(r.diferencia) >= 0.01) doc.setTextColor(185, 28, 28)
    doc.text(money(r.diferencia), mR, y, { align: 'right' }); doc.setTextColor(0, 0, 0); y += 4.4
  }
  cerrarPdf(); pdfNombre.value = 'Comparativa_Liq_Neto.pdf'; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))
onMounted(async () => {
  const fetch = async (url: string) => { try { return (await api.get(url)).data } catch { return [] } }
  cat.empresas = mapCat(await fetch('/empresas'))
  cat.contratistas = mapCat(await fetch('/contratistas'))
  cat.lugares = mapCat(await fetch('/lugares'))
  cat.convenios = mapCat(await fetch('/convenios'))
  cat.categorias = mapCat(await fetch('/categorias'))
  cat.bancos = mapCat(await fetch('/ctas-bancarias'))
  const autoelev = cat.empresas.find((e: any) => e.nombre.toUpperCase().includes('AUTOELEVADORES'))
  if (autoelev) f.empresa = autoelev.cod
})
</script>

<style scoped>
.cn-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cn-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cn-cab-ico { font-size:28px; } .cn-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cn-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cn-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cn-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cn-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:12px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.cn-f { display:flex; flex-direction:column; gap:4px; } .cn-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.cn-sel { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; min-width:160px; } .cn-sel.chico { min-width:70px; }
.cn-anio { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; width:80px; }
.cn-btn-ok { background:#16a34a; color:#fff; border:none; padding:8px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .cn-btn-ok:disabled { background:#cbd5e1; }
.cn-wrap { margin:12px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:62vh; }
.cn-grid { width:100%; border-collapse:collapse; font-size:13px; }
.cn-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; text-align:left; } .cn-grid th.r { text-align:right; }
.cn-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .cn-grid td.r { text-align:right; }
.cn-grid tr:nth-child(even) td { background:#f8fafc; } .cn-grid tr.dif td { background:#fef9c3; }
.cn-grid td.neg { color:#b91c1c; font-weight:600; } .cn-grid td.pos { color:#166534; font-weight:600; }
.cn-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.cn-pie { display:flex; align-items:center; gap:14px; padding:0 18px 16px; }
.cn-tot { font-size:13px; color:#475569; }
.cn-btn-pdf { margin-left:auto; background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cn-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cn-pdf-md { width:min(820px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.cn-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.cn-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.cn-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .cn-pdf-b.ok { background:#22c55e; color:#fff; } .cn-pdf-b.cancel { background:#ef4444; color:#fff; }
.cn-pdf-frame { flex:1; border:none; width:100%; }
</style>
