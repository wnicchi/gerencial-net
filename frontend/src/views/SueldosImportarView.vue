<!--
  SueldosImportarView.vue — Importar del Estudio (sueldos_importar).
  Se elige Tipo/Mes/Año/Fecha de Pago, se abre un Excel y se mapean las columnas
  (CUIL, Código, Detalle, Cantidad, Haberes, Deducciones). Al proceder se cargan las
  liquidaciones buscando cada empleado por CUIL (reemplaza la liquidación previa del período).
-->
<template>
  <div class="ia-view">
    <div class="ia-cab">
      <div class="ia-cab-ico">📥</div>
      <div class="ia-cab-tx"><h1>Importar del Estudio</h1><p>Importar liquidaciones desde el Excel del estudio contable</p></div>
      <button class="ia-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ia-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <SueldosImportarAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/sueldos-importar" titulo="Asistente IA — Importar del Estudio"
            subtitulo="Preguntá cómo importar desde el Excel"
            :sugerencias="['¿Qué columnas necesito?','¿Qué pasa si un CUIL no existe?','¿Reemplaza la liquidación anterior?']"
            @close="modalIA = false" />

    <div class="ia-card">
      <div class="ia-toolbar" v-enter-next>
        <span class="ia-lbl">Tipo</span>
        <select v-model.number="tipo" class="ia-sel"><option :value="0">— Seleccionar —</option><option v-for="t in tipos" :key="t.cod" :value="t.cod">{{ t.desc }}</option></select>
        <span class="ia-lbl">Mes</span>
        <select v-model.number="mes" class="ia-sel chico"><option v-for="m in 12" :key="m" :value="m">{{ meses[m - 1] }}</option></select>
        <span class="ia-lbl">Año</span>
        <input v-model.number="anio" type="number" class="ia-anio" />
        <span class="ia-lbl">Fecha de Pago</span>
        <input v-model="fechaPago" type="date" class="ia-date" />
        <label class="ia-btn-archivo">
          📂 Importar archivo Excel
          <input type="file" accept=".xlsx,.xls,.csv" @change="abrirArchivo" hidden />
        </label>
        <span v-if="nombreArchivo" class="ia-archivo">{{ nombreArchivo }}</span>
        <button v-if="filas.length" class="ia-btn-reset" @click="reset">↺ Reset</button>
      </div>

      <div v-if="filas.length" class="ia-mapeo">
        <span class="ia-mtit">Identificá el contenido de las columnas:</span>
        <label v-for="m in mapeos" :key="m.k">{{ m.lbl }}
          <select v-model.number="(map as any)[m.k]">
            <option :value="-1">—</option>
            <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option>
          </select>
        </label>
      </div>

      <div v-if="filas.length" class="ia-grid-wrap">
        <table class="ia-tabla">
          <thead><tr><th class="ia-fija">FILA</th><th class="ia-fija">OK</th>
            <th v-for="c in columnas" :key="c.idx" :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ c.letra }}</th></tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ off: !f.ok }">
              <td class="ia-fija">{{ i + 1 }}</td>
              <td class="ia-fija"><input v-model="f.ok" type="checkbox" /></td>
              <td v-for="c in columnas" :key="c.idx" :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ f.celdas[c.idx] }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="ia-pie">
        <span class="ia-total">Total de registros a importar: <b>{{ totalOk }}</b></span>
        <button class="ia-btn-ok" :disabled="procesando || !puede" @click="proceder">{{ procesando ? '⟳ Importando…' : 'PROCEDER CON LA IMPORTACIÓN' }}</button>
      </div>

      <div v-if="noHallados.length" class="ia-faltantes">
        <b>No se importó nada.</b> Estos CUIL del Excel no existen en el padrón de empleados:
        <ul><li v-for="(c, i) in noHallados" :key="i">{{ c }}</li></ul>
      </div>
      <p v-if="msg" :class="['ia-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import * as XLSX from 'xlsx'
import api from '@/services/auth'
import SueldosImportarAyuda from '@/components/SueldosImportarAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const hoy = new Date()
const tipo = ref(0); const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear()); const fechaPago = ref('')
const tipos = ref<{ cod: number; desc: string }[]>([])
const nombreArchivo = ref('')
const columnas = ref<{ idx: number; letra: string }[]>([])
const filas = reactive<{ ok: boolean; celdas: any[] }[]>([])
const map = reactive<{ cuil: number; codigo: number; detalle: number; cantidad: number; haber: number; deduce: number }>({ cuil: -1, codigo: -1, detalle: -1, cantidad: -1, haber: -1, deduce: -1 })
const mapeos = [
  { k: 'cuil', lbl: 'CUIL' }, { k: 'codigo', lbl: 'Código' }, { k: 'detalle', lbl: 'Detalle' },
  { k: 'cantidad', lbl: 'Cantidad' }, { k: 'haber', lbl: 'Haberes' }, { k: 'deduce', lbl: 'Deducciones' },
]
const claves = ['cuil', 'codigo', 'detalle', 'cantidad', 'haber', 'deduce'] as const
const procesando = ref(false)
const msg = ref(''); const msgError = ref(false); const noHallados = ref<string[]>([])
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 8000) }

const abrirArchivo = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  nombreArchivo.value = file.name; flash(''); noHallados.value = []
  try {
    const buf = await file.arrayBuffer()
    const wb = XLSX.read(buf, { type: 'array' })
    const hoja = wb.SheetNames[0]; const ws = hoja ? wb.Sheets[hoja] : undefined
    if (!ws) { flash('No se pudo leer la hoja del archivo.', true); return }
    const datos: any[][] = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' })
    const maxCols = datos.reduce((m, r) => Math.max(m, r.length), 0)
    columnas.value = Array.from({ length: maxCols }, (_, i) => ({ idx: i, letra: XLSX.utils.encode_col(i) }))
    filas.splice(0, filas.length, ...datos.map(r => ({ ok: true, celdas: Array.from({ length: maxCols }, (_, i) => r[i] ?? '') })))
    for (const k of claves) (map as any)[k] = -1
  } catch (err) { console.error(err); flash('No se pudo leer el archivo. Verificá que sea un Excel válido.', true) }
  finally { (e.target as HTMLInputElement).value = '' }
}

const muestra = (idx: number): string => {
  const v = filas.find(f => String(f.celdas[idx] ?? '').trim() !== '')?.celdas[idx]
  return v != null && String(v).trim() !== '' ? `  ·  ej: ${String(v).slice(0, 16)}` : ''
}
// Al elegir la columna de CUIL, dejar tildadas SOLO las filas que tienen un CUIL con
// dígitos: así se descartan solas la fila de encabezado del Excel y los renglones vacíos.
watch(() => map.cuil, (idx) => {
  if (idx < 0) return
  for (const f of filas) f.ok = /\d/.test(String(f.celdas[idx] ?? ''))
})

const columnasMapeadas = computed(() => {
  const s = new Set<number>()
  for (const k of claves) if ((map as any)[k] >= 0) s.add((map as any)[k])
  return s
})
const totalOk = computed(() => filas.filter(f => f.ok).length)
const puede = computed(() => tipo.value > 0 && claves.every(k => (map as any)[k] >= 0) && totalOk.value > 0 && anio.value > 0)

const cel = (f: { celdas: any[] }, idx: number) => idx >= 0 ? String(f.celdas[idx] ?? '').trim() : ''

const proceder = async () => {
  if (tipo.value <= 0) return flash('Seleccioná el TIPO de liquidación.', true)
  if (!claves.every(k => (map as any)[k] >= 0)) return flash('Indicá las columnas de CUIL, Código, Detalle, Cantidad, Haberes y Deducciones.', true)
  if (!confirm('¿Procede con la importación de la liquidación?')) return
  procesando.value = true; noHallados.value = []
  try {
    const rows = filas.filter(f => f.ok).map(f => ({
      cuil: cel(f, map.cuil), codigo: cel(f, map.codigo), detalle: cel(f, map.detalle),
      cantidad: cel(f, map.cantidad), haber: cel(f, map.haber), deduce: cel(f, map.deduce),
    }))
    const { data } = await api.post('/sueldos-importar', { tipo: tipo.value, mes: mes.value, anio: anio.value, fechaPago: fechaPago.value || null, rows })
    flash(`Importación procesada OK. Liquidaciones: ${data.liquidaciones}. Conceptos: ${data.items}. Total neto: ${nf.format(data.total)}.`)
  } catch (e: any) {
    if (e?.response?.status === 422 && e.response.data?.no_hallados) {
      noHallados.value = e.response.data.no_hallados
      flash(e.response.data.message ?? 'Hay CUIL no encontrados en el padrón.', true)
    } else flash(e?.response?.data?.message ?? 'No se pudo importar.', true)
  } finally { procesando.value = false }
}
const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const reset = () => { filas.splice(0, filas.length); columnas.value = []; nombreArchivo.value = ''; noHallados.value = []; for (const k of claves) (map as any)[k] = -1; flash('') }

onMounted(async () => {
  try { tipos.value = (await api.get('/liquidaciones/tipos')).data.map((t: any) => ({ cod: t.cod ?? t.COD, desc: (t.desc ?? t.descripcion ?? '').toString().trim() })) }
  catch { tipos.value = [] }
})
</script>

<style scoped>
.ia-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ia-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ia-cab-ico { font-size:28px; } .ia-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ia-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ia-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ia-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ia-card { margin:16px 18px; }
.ia-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.ia-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ia-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; color:#1e293b; } .ia-sel.chico { min-width:auto; }
.ia-anio { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; width:90px; }
.ia-date { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; color:#1e293b; }
.ia-btn-archivo { background:#1b4332; color:#fff; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ia-archivo { font-size:13px; color:#475569; }
.ia-btn-reset { background:#fff; border:1px solid #cbd5e1; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; }
.ia-mapeo { display:flex; flex-wrap:wrap; gap:14px; align-items:center; padding:12px 14px; background:#f0faf4; border:1px solid #c3e6cb; border-radius:8px; margin-bottom:14px; }
.ia-mtit { font-size:13px; font-weight:700; color:#1b4332; width:100%; }
.ia-mapeo label { font-size:12px; font-weight:600; color:#374151; display:flex; flex-direction:column; gap:4px; }
.ia-mapeo select { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:12px; min-width:150px; color:#1e293b; }
.ia-grid-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:46vh; }
.ia-tabla { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.ia-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 10px; font-weight:600; }
.ia-tabla th.mapeada { background:#b45309; }
.ia-tabla td { padding:4px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ia-tabla td.mapeada { background:#fef9c3; }
.ia-tabla tr.off td { opacity:.4; }
.ia-fija { background:#f8fafc; position:sticky; left:0; }
.ia-pie { display:flex; align-items:center; gap:18px; margin-top:14px; }
.ia-total { font-size:14px; color:#374151; } .ia-total b { color:#1b4332; font-size:16px; }
.ia-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:10px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .ia-btn-ok:disabled { background:#cbd5e1; cursor:not-allowed; }
.ia-faltantes { margin-top:14px; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; font-size:13px; }
.ia-faltantes ul { margin:6px 0 0; padding-left:20px; } .ia-faltantes li { font-family:monospace; }
.ia-msg { padding:9px 14px; margin-top:14px; font-size:13px; border-radius:6px; } .ia-msg.ok { background:#dcfce7; color:#166534; } .ia-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
