<!-- AlmuerzosView.vue — Editar Almuerzos del Personal. -->
<template>
  <div class="al-view">
    <div class="al-cab">
      <div class="al-cab-ico">🍽️</div>
      <div class="al-cab-tx"><h1>Almuerzos del Personal</h1><p>Cargá los comensales del comedor para la fecha</p></div>
      <button class="al-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="al-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <AlmuerzosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/almuerzos" titulo="Asistente IA — Almuerzos"
            subtitulo="Preguntá sobre la carga de almuerzos"
            :sugerencias="['¿Cómo cargo los almuerzos del día?','¿Por qué no aparece un empleado?','¿Qué significan los colores?','¿Qué es Descuenta?']"
            @close="modalIA = false" />

    <!-- Filtros -->
    <div class="al-filtros">
      <label>Empresa
        <select v-model.number="f.empresa">
          <option v-for="e in empresas" :key="e.cod" :value="Number(e.cod)">{{ e.nombre }}</option>
        </select>
      </label>
      <label>Comedor
        <select v-model.number="f.comedor">
          <option :value="0">— Elegir —</option>
          <option v-for="c in comedores" :key="c.cod" :value="Number(c.cod)">{{ c.descripcion }}</option>
        </select>
      </label>
      <label>Fecha del Almuerzo <input v-model="f.fecha" type="date" /></label>
      <button class="al-btn-aceptar" :disabled="cargando || !f.comedor" @click="aceptar">{{ cargando ? '⟳…' : 'ACEPTAR' }}</button>
    </div>

    <!-- Grilla -->
    <div v-if="rows.length" class="al-grid-wrap">
      <table class="al-grid">
        <thead><tr><th>Código</th><th>Empleado</th><th style="width:80px">Almuerza</th><th style="width:80px">Descuenta</th><th style="width:80px">Cantidad</th><th>Observaciones</th></tr></thead>
        <tbody>
          <tr v-for="r in rows" :key="r.tipo + '-' + r.cod_emp" :class="filaClase(r)">
            <td class="al-num">{{ r.cod_emp }}</td>
            <td>{{ r.empleado }}</td>
            <td style="text-align:center"><input v-model="r.almuerza" type="checkbox" /></td>
            <td style="text-align:center"><input v-model="r.descuenta" type="checkbox" /></td>
            <td style="text-align:center"><input v-model.number="r.cantidad" type="number" min="0" class="al-can" /></td>
            <td><input v-model="r.observacion" type="text" maxlength="100" class="al-obs" /></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!cargando && consultado" class="al-vacio">No hay comensales para el comedor y fecha seleccionados.</div>

    <!-- Acciones -->
    <div v-if="rows.length" class="al-acc">
      <label class="al-chk"><input type="checkbox" :checked="todosAlm" @change="setTodos('almuerza', ($event.target as HTMLInputElement).checked)" /> Todos Almuerzan</label>
      <label class="al-chk"><input type="checkbox" :checked="todosDes" @change="setTodos('descuenta', ($event.target as HTMLInputElement).checked)" /> Todos Descuentan</label>
      <span class="al-leyenda"><i class="al-i-inv"></i> Invitado &nbsp; <i class="al-i-com"></i> Con cantidad</span>
      <button class="al-btn-imprimir" @click="imprimir">🖨 Imprimir</button>
      <button class="al-btn-confirmar" :disabled="confirmando" @click="confirmar">{{ confirmando ? '⟳ Grabando…' : '✔ CONFIRMAR' }}</button>
    </div>

    <!-- Modal PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="al-pdf-ov" @click.self="cerrarPdf">
        <div class="al-pdf-md">
          <div class="al-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="al-pdf-acc">
              <button class="al-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="al-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="al-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="al-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import { useAuthStore } from '@/stores/auth'
import AlmuerzosAyuda from '@/components/AlmuerzosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarDesdeUrl } from '@/utils/descargas'

const authStore = useAuthStore()
const modalAyuda = ref(false); const modalIA = ref(false)

interface Opcion { cod: string | number; nombre?: string; descripcion?: string }
interface Row { cod_emp: number; empleado: string; almuerza: boolean; descuenta: boolean; cantidad: number; observacion: string; tipo: string }
const empresas = ref<Opcion[]>([]); const comedores = ref<Opcion[]>([])
const f = ref({ empresa: 1, comedor: 0, fecha: new Date().toISOString().slice(0, 10) })
const rows = ref<Row[]>([])
const cargando = ref(false); const consultado = ref(false); const confirmando = ref(false)

const filaClase = (r: Row) => r.tipo === 'I' ? 'al-inv' : (r.cantidad > 0 ? 'al-con' : '')
const todosAlm = computed(() => rows.value.length > 0 && rows.value.every(r => r.almuerza))
const todosDes = computed(() => rows.value.length > 0 && rows.value.every(r => r.descuenta))
const setTodos = (campo: 'almuerza' | 'descuenta', v: boolean) => rows.value.forEach(r => r[campo] = v)

const aceptar = async () => {
  cargando.value = true; consultado.value = true
  try {
    const { data } = await api.get('/almuerzos', { params: { empresa: f.value.empresa, comedor: f.value.comedor, fecha: f.value.fecha } })
    rows.value = data.rows
  } catch (e) { console.error(e); rows.value = [] }
  finally { cargando.value = false }
}

const confirmar = async () => {
  if (!confirm(`¿Confirma el almuerzo del día ${fechaTxt()}?`)) return
  confirmando.value = true
  try {
    const { data } = await api.post('/almuerzos/confirmar', { fecha: f.value.fecha, rows: rows.value })
    alert(`✔ Confirmado. Se grabaron ${data.grabados} comensales.`)
  } catch (e: any) { alert('⚠️ ' + (e?.response?.data?.message ?? 'No se pudo confirmar.')) }
  finally { confirmando.value = false }
}

const fechaTxt = () => { const [y, m, d] = f.value.fecha.split('-'); return `${d}/${m}/${y}` }

// ── IMPRIMIR (PDF A4) ──
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }
const imprimir = () => {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, PH = 297, ML = 12, MR = 12
  const empresaNom = empresas.value.find(e => Number(e.cod) === f.value.empresa)?.nombre ?? ''
  const lista = [...rows.value].sort((a, b) => a.empleado.localeCompare(b.empleado))
  const cols = [{ t: 'Código', w: 22, k: 'cod_emp' }, { t: 'Empleado', w: 92, k: 'empleado' }, { t: 'Cantidad', w: 24, k: 'cantidad' }, { t: 'Observaciones', w: 48, k: 'observacion' }]
  let y = 0
  const cab = () => {
    y = 16
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(27, 67, 50)
    doc.text(`ALMUERZOS correspondientes al ${fechaTxt()}`, PW / 2, y, { align: 'center' }); y += 6
    doc.setFontSize(9); doc.setTextColor(60, 60, 60); doc.text('EMPRESA :  ' + empresaNom.trim(), ML, y); y += 5
    doc.setFontSize(8); doc.setTextColor(0, 0, 0); let x = ML
    doc.setFillColor(229, 231, 235); doc.rect(ML, y - 4, PW - ML - MR, 6, 'F')
    for (const c of cols) { doc.text(c.t, c.k === 'cod_emp' || c.k === 'cantidad' ? x + c.w - 1 : x + 1, y, c.k === 'cod_emp' || c.k === 'cantidad' ? { align: 'right' } : {}); x += c.w }
    y += 4; doc.setDrawColor(180); doc.line(ML, y - 2.5, PW - MR, y - 2.5)
  }
  cab(); doc.setFont('helvetica', 'normal')
  let total = 0; let n = 0
  for (const r of lista) {
    if (y > PH - 18) { doc.addPage(); cab(); doc.setFont('helvetica', 'normal') }
    if (n % 2 === 1) { doc.setFillColor(247, 250, 252); doc.rect(ML, y - 3.5, PW - ML - MR, 5, 'F') }
    let x = ML; doc.setTextColor(30, 41, 59); doc.setFontSize(8)
    doc.text(String(r.cod_emp), x + cols[0].w - 1, y, { align: 'right' }); x += cols[0].w
    let nom = r.empleado; while (nom && doc.getTextWidth(nom) > cols[1].w - 2) nom = nom.slice(0, -1)
    doc.text(nom, x + 1, y); x += cols[1].w
    doc.text(String(r.cantidad), x + cols[2].w - 1, y, { align: 'right' }); x += cols[2].w
    let obs = r.observacion ?? ''; while (obs && doc.getTextWidth(obs) > cols[3].w - 2) obs = obs.slice(0, -1)
    doc.text(obs, x + 1, y)
    total += Number(r.cantidad) || 0; y += 5; n++
  }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(27, 67, 50)
  doc.text(`Total de Almuerzos :  ${total}`, ML, y + 4)
  const tot = doc.getNumberOfPages()
  const pie = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
  for (let p = 1; p <= tot; p++) { doc.setPage(p); doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90); doc.text(pie, ML, PH - 8); doc.text(`Página ${p} de ${tot}`, PW - MR, PH - 8, { align: 'right' }) }
  cerrarPdf(); pdfNombre.value = `Almuerzos_${f.value.fecha}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

onMounted(async () => {
  try {
    const [e, c] = await Promise.all([api.get('/empresas'), api.get('/comedores')])
    empresas.value = e.data; comedores.value = c.data
    const cods = empresas.value.map((x: any) => Number(x.cod))
    if (!cods.includes(f.value.empresa)) f.value.empresa = cods[0] ?? 0
  } catch (err) { console.error(err) }
})
</script>

<style scoped>
.al-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.al-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.al-cab-ico { font-size:28px; } .al-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .al-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.al-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.al-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.al-filtros { display:flex; flex-wrap:wrap; align-items:end; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.al-filtros label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.al-filtros input, .al-filtros select { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.al-filtros input:focus, .al-filtros select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.al-btn-aceptar { background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.al-btn-aceptar:hover:not(:disabled){ background:#15803d; } .al-btn-aceptar:disabled { background:#cbd5e1; }
.al-grid-wrap { margin:14px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:60vh; }
.al-grid { width:100%; border-collapse:collapse; font-size:13px; }
.al-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 12px; font-size:12px; }
.al-grid td { padding:4px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.al-num { text-align:center; font-variant-numeric:tabular-nums; }
.al-inv td { background:#86efac; } .al-con td { background:#fff7ae; }
.al-can { width:60px; border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:13px; text-align:center; }
.al-obs { width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:13px; }
.al-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.al-acc { display:flex; align-items:center; gap:16px; padding:0 18px 18px; flex-wrap:wrap; }
.al-chk { font-size:13px; font-weight:600; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer; }
.al-leyenda { font-size:12px; color:#6b7280; display:flex; align-items:center; }
.al-i-inv, .al-i-com { display:inline-block; width:13px; height:13px; border-radius:3px; margin-right:4px; vertical-align:middle; }
.al-i-inv { background:#86efac; } .al-i-com { background:#fff7ae; }
.al-btn-imprimir { margin-left:auto; background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.al-btn-confirmar { background:#2563eb; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.al-btn-confirmar:hover:not(:disabled){ background:#1d4ed8; } .al-btn-confirmar:disabled { background:#cbd5e1; }
.al-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.al-pdf-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.al-pdf-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; }
.al-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.al-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.al-pdf-b.ok { background:#22c55e; color:#fff; } .al-pdf-b.cancel { background:#ef4444; color:#fff; }
.al-pdf-frame { flex:1; border:none; width:100%; }
</style>
