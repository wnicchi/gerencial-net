<!-- VacacionesPlanillaView.vue — Planilla General de Vacaciones (vacaciones_planilla). -->
<template>
  <div class="vg-view">
    <div class="vg-cab">
      <div class="vg-cab-ico">📋</div>
      <div class="vg-cab-tx"><h1>Planilla General de Vacaciones</h1><p>Días que corresponden, liquidados, gozados y pendientes por empresa y año</p></div>
      <button class="vg-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="vg-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <VacacionesPlanillaAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/vacaciones-planilla" titulo="Asistente IA — Planilla General"
            subtitulo="Preguntá sobre los días que corresponden y pendientes"
            :sugerencias="['¿Qué significan Sin Liquidar y Sin Gozar?','¿Qué muestran los colores?','¿Cómo exporto a Excel?']"
            @close="modalIA = false" />

    <div class="vg-card">
      <div class="vg-toolbar">
        <div class="vg-f"><span class="vg-lbl">Empresa</span>
          <select v-model.number="emp" class="vg-inp ancho">
            <option v-for="e in empresas" :key="e.cod" :value="e.cod">{{ e.nombre }}</option>
          </select>
        </div>
        <div class="vg-f"><span class="vg-lbl">Año</span><input v-model.number="anio" type="number" class="vg-inp chico" /></div>
        <button class="vg-btn ok" :disabled="!emp || cargando" @click="cargar">{{ cargando ? '⟳…' : 'ACEPTAR' }}</button>
        <div class="vg-acc" v-if="filas.length">
          <button class="vg-btn-sm" @click="marcarTodos(true)">Todo</button>
          <button class="vg-btn-sm" @click="marcarTodos(false)">Nada</button>
          <button class="vg-btn ok" :disabled="!elegidos" @click="imprimir">🖨 Imprimir</button>
          <button class="vg-btn excel" :disabled="!elegidos" @click="exportarExcel">📊 Exportar a Excel</button>
        </div>
      </div>

      <div v-if="filas.length" class="vg-grid-wrap">
        <table class="vg-tabla">
          <thead><tr>
            <th>OK</th><th>Código</th><th>Personal</th><th>Legajo</th><th>F. Ingreso</th><th>Puesto</th>
            <th>Total Días</th><th>Liquidadas</th><th>Gozadas</th><th>Sin Liquidar</th><th>Sin Gozar</th>
          </tr></thead>
          <tbody>
            <tr v-for="f in filas" :key="f.cod" :class="color(f)">
              <td class="c"><input type="checkbox" v-model="f.ok" /></td>
              <td>{{ f.cod }}</td><td>{{ f.nombre }}</td><td>{{ f.legajo }}</td><td>{{ f.ingreso }}</td><td>{{ f.puesto }}</td>
              <td class="c">{{ f.corresponden }}</td><td class="c">{{ f.liquidadas }}</td><td class="c">{{ f.gozadas }}</td>
              <td class="c">{{ f.noLiq }}</td><td class="c">{{ f.noGoz }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else-if="!cargando && consultado" class="vg-vacio">No hay personal activo para esa empresa.</p>
      <p v-if="msg" class="vg-msg">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="pdfUrl" class="vg-pdf-ov" @click.self="cerrarPdf">
        <div class="vg-pdf-md">
          <div class="vg-pdf-head"><span>{{ pdfNombre }}</span>
            <div class="vg-pdf-acc">
              <button class="vg-pdf-b ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="vg-pdf-b ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="vg-pdf-b cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="vg-pdf-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import jsPDF from 'jspdf'
import VacacionesPlanillaAyuda from '@/components/VacacionesPlanillaAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
interface Fila { cod: number; nombre: string; legajo: number; ingreso: string; puesto: string; corresponden: number; liquidadas: number; gozadas: number; noLiq: number; noGoz: number; ok: boolean }
const empresas = ref<{ cod: number; nombre: string }[]>([])
const emp = ref(1); const anio = ref(new Date().getFullYear())
const filas = ref<Fila[]>([]); const cargando = ref(false); const consultado = ref(false); const msg = ref('')
const empNombre = computed(() => empresas.value.find(e => e.cod === emp.value)?.nombre ?? '')
const elegidos = computed(() => filas.value.filter(f => f.ok).length)

// Rojo: nada por liquidar ni gozar. Amarillo: algo pendiente.
const color = (f: Fila) => (f.noLiq === 0 && f.noGoz === 0) ? 'rojo' : 'amarillo'

onMounted(async () => {
  try {
    const data = (await api.get('/empresas')).data
    const arr = Array.isArray(data) ? data : (data.data ?? [])
    empresas.value = arr.map((e: any) => ({ cod: Number(e.EMP_COD ?? e.cod), nombre: (e.EMP_NOM ?? e.nombre ?? '').toString().trim() }))
    // Preseleccionar AUTOELEVADORES si está
    const ae = empresas.value.find(e => /AUTOELEVADOR/i.test(e.nombre)); if (ae) emp.value = ae.cod
  } catch { /* dropdown queda vacío */ }
  if (emp.value) cargar()
})

const cargar = async () => {
  if (!emp.value) return
  cargando.value = true; msg.value = ''
  try {
    const { data } = await api.get('/vacaciones/planilla', { params: { emp: emp.value, anio: anio.value } })
    filas.value = (data.personal ?? []).map((f: any) => ({ ...f, ok: true })); consultado.value = true
  } catch (e: any) { msg.value = e?.response?.data?.message ?? 'No se pudo cargar la planilla.' }
  finally { cargando.value = false }
}
const marcarTodos = (v: boolean) => filas.value.forEach(f => f.ok = v)

const imprimir = () => {
  const sel = filas.value.filter(f => f.ok); if (!sel.length) return
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFont('helvetica', 'bold'); doc.setFontSize(13)
  doc.text(`${empNombre.value} — Corresponde al año ${anio.value}`, 14, 14)
  const cols = ['Cód.', 'Personal', 'Legajo', 'Ingreso', 'Puesto', 'Total', 'Liq.', 'Goz.', 'S/Liq.', 'S/Goz.']
  const xs = [14, 30, 92, 108, 130, 188, 206, 222, 238, 258]; let y = 24
  doc.setFontSize(9); doc.setFont('helvetica', 'bold'); cols.forEach((c, i) => doc.text(c, xs[i], y)); doc.setFont('helvetica', 'normal'); y += 2
  doc.setLineWidth(0.2); doc.line(14, y, 283, y); y += 5
  for (const f of sel) {
    if (y > 195) { doc.addPage(); y = 20 }
    const row = [String(f.cod), f.nombre.slice(0, 32), String(f.legajo), f.ingreso, (f.puesto || '').slice(0, 28), String(f.corresponden), String(f.liquidadas), String(f.gozadas), String(f.noLiq), String(f.noGoz)]
    row.forEach((c, i) => doc.text(c, xs[i], y)); y += 5
  }
  cerrarPdf(); pdfNombre.value = `VACACIONES_${anio.value}_${slug(empNombre.value)}.pdf`; pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const exportarExcel = async () => {
  const sel = filas.value.filter(f => f.ok); if (!sel.length) return
  const head = ['Código', 'Personal', 'Legajo', 'F. Ingreso', 'Puesto', 'Total Días', 'Liquidadas', 'Gozadas', 'Sin Liquidar', 'Sin Gozar']
  const rows = sel.map(f => [f.cod, f.nombre, f.legajo, f.ingreso, f.puesto, f.corresponden, f.liquidadas, f.gozadas, f.noLiq, f.noGoz])
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
  const html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
    + head.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>'
    + rows.map(r => '<tr>' + r.map(c => `<td>${esc(c)}</td>`).join('') + '</tr>').join('') + '</table></body></html>'
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), `VACACIONES_${anio.value}_${slug(empNombre.value)}.xls`)
}

const slug = (s: string) => s.toUpperCase().replace(/[^A-Z0-9]/g, '_')
</script>

<style scoped>
.vg-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.vg-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.vg-cab-ico { font-size:28px; } .vg-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .vg-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.vg-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vg-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.vg-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.vg-toolbar { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.vg-f { display:flex; flex-direction:column; gap:4px; } .vg-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.vg-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .vg-inp.ancho { min-width:300px; } .vg-inp.chico { width:100px; }
.vg-btn { border:none; padding:9px 18px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; } .vg-btn.ok { background:#16a34a; } .vg-btn.excel { background:#107c41; } .vg-btn:disabled { background:#cbd5e1; cursor:default; }
.vg-acc { margin-left:auto; display:flex; align-items:flex-end; gap:8px; }
.vg-btn-sm { background:#fff; border:1px solid #c3e6cb; color:#1b4332; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.vg-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:66vh; }
.vg-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.vg-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.vg-tabla td { padding:5px 8px; border-bottom:1px solid #e5e7eb; white-space:nowrap; color:#1e293b; } .vg-tabla td.c { text-align:center; }
.vg-tabla tbody tr.amarillo { background:#fff7cc; } .vg-tabla tbody tr.rojo { background:#ffd6d6; }
.vg-vacio { color:#94a3b8; padding:16px; } .vg-msg { padding:9px 14px; font-size:13px; border-radius:6px; background:#fee2e2; color:#b91c1c; max-width:700px; }
.vg-pdf-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.vg-pdf-md { width:min(960px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.5); }
.vg-pdf-head { display:flex; align-items:center; gap:14px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; flex-wrap:wrap; }
.vg-pdf-acc { margin-left:auto; display:flex; gap:8px; }
.vg-pdf-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .vg-pdf-b.ok { background:#22c55e; color:#fff; } .vg-pdf-b.cancel { background:#ef4444; color:#fff; }
.vg-pdf-frame { flex:1; border:none; width:100%; }
</style>
