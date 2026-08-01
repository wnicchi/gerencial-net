<!--
  ImportarEmpleadosContratistaView.vue — Importar Empleados desde Excel (contra_emplea_importar).
  Se elige un contratista, se abre un Excel, se mapean las columnas (Nombre, DNI, CUIL,
  Teléfono, Celular) y al proceder se dan de alta los empleados nuevos (los que ya
  existen por CUIL se omiten).
-->
<template>
  <div class="ie-view">
    <div class="ie-cab">
      <div class="ie-cab-ico">📥</div>
      <div class="ie-cab-tx"><h1>Importar Empleados desde Excel</h1><p>Alta masiva de empleados de un contratista</p></div>
      <button class="ie-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ie-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ImportarEmpleadosContratistaAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/importar-empleados-contratista" titulo="Asistente IA — Importar Empleados"
            subtitulo="Preguntá cómo importar desde Excel"
            :sugerencias="['¿Qué debe tener el Excel?','¿Cómo indico cada columna?','¿Qué pasa con un empleado ya cargado?']"
            @close="modalIA = false" />

    <div class="ie-card">
      <div class="ie-toolbar">
        <span class="ie-lbl">Contratista</span>
        <select v-model.number="contratista" class="ie-sel">
          <option :value="0">— Elegir contratista —</option>
          <option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option>
        </select>
        <label class="ie-btn-archivo" :class="{ off: !contratista }">
          📂 Buscar archivo
          <input type="file" accept=".xlsx,.xls,.csv" :disabled="!contratista" @change="abrirArchivo" hidden />
        </label>
        <span v-if="nombreArchivo" class="ie-archivo">{{ nombreArchivo }}</span>
        <button v-if="filas.length" class="ie-btn-reset" @click="reset">↺ Reset</button>
      </div>

      <div v-if="filas.length" class="ie-mapeo">
        <span class="ie-mtit">Identificá el contenido de las columnas:</span>
        <label v-for="m in mapeos" :key="m.k">{{ m.lbl }}
          <select v-model.number="(map as any)[m.k]">
            <option :value="-1">—</option>
            <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option>
          </select>
        </label>
      </div>

      <div v-if="filas.length" class="ie-grid-wrap">
        <table class="ie-tabla">
          <thead><tr>
            <th class="ie-fija">FILA</th><th class="ie-fija">OK</th>
            <th v-for="c in columnas" :key="c.idx" :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ c.letra }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ off: !f.ok }">
              <td class="ie-fija">{{ i + 1 }}</td>
              <td class="ie-fija"><input v-model="f.ok" type="checkbox" /></td>
              <td v-for="c in columnas" :key="c.idx" :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ f.celdas[c.idx] }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="ie-pie">
        <span class="ie-total">Total a importar: <b>{{ totalOk }}</b></span>
        <button class="ie-btn-ok" :disabled="procesando || !puedeProceder" @click="proceder">
          {{ procesando ? '⟳ Importando…' : 'PROCEDER CON LA IMPORTACIÓN' }}
        </button>
      </div>

      <p v-if="msg" :class="['ie-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import api from '@/services/auth'
import ImportarEmpleadosContratistaAyuda from '@/components/ImportarEmpleadosContratistaAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const contratistas = ref<{ cod: number; nombre: string }[]>([])
const contratista = ref(0)
const nombreArchivo = ref('')
const columnas = ref<{ idx: number; letra: string }[]>([])
const filas = reactive<{ ok: boolean; celdas: any[] }[]>([])
const map = reactive<{ nombre: number; dni: number; cuil: number; telefono: number; celular: number }>({ nombre: -1, dni: -1, cuil: -1, telefono: -1, celular: -1 })
const mapeos = [
  { k: 'nombre', lbl: 'Apellido y Nombre' }, { k: 'dni', lbl: 'DNI' }, { k: 'cuil', lbl: 'CUIL/CUIT' },
  { k: 'telefono', lbl: 'Teléfono' }, { k: 'celular', lbl: 'Celular' },
]
const procesando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 5000) }

const abrirArchivo = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  nombreArchivo.value = file.name; flash('')
  try {
    const buf = await file.arrayBuffer()
    const wb = XLSX.read(buf, { type: 'array' })
    const hoja = wb.SheetNames[0]
    const ws = hoja ? wb.Sheets[hoja] : undefined
    if (!ws) { flash('No se pudo leer la hoja del archivo.', true); return }
    const datos: any[][] = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' })
    const maxCols = datos.reduce((m, r) => Math.max(m, r.length), 0)
    columnas.value = Array.from({ length: maxCols }, (_, i) => ({ idx: i, letra: XLSX.utils.encode_col(i) }))
    filas.splice(0, filas.length, ...datos.map(r => ({ ok: true, celdas: Array.from({ length: maxCols }, (_, i) => r[i] ?? '') })))
    map.nombre = -1; map.dni = -1; map.cuil = -1; map.telefono = -1; map.celular = -1
  } catch (err) { console.error(err); flash('No se pudo leer el archivo. Verificá que sea un Excel válido.', true) }
  finally { (e.target as HTMLInputElement).value = '' }
}

const muestra = (idx: number): string => {
  const v = filas.find(f => String(f.celdas[idx] ?? '').trim() !== '')?.celdas[idx]
  return v != null && String(v).trim() !== '' ? `  ·  ej: ${String(v).slice(0, 16)}` : ''
}
const columnasMapeadas = computed(() => {
  const s = new Set<number>()
  for (const k of ['nombre', 'dni', 'cuil', 'telefono', 'celular'] as const) if ((map as any)[k] >= 0) s.add((map as any)[k])
  return s
})
const totalOk = computed(() => filas.filter(f => f.ok).length)
const puedeProceder = computed(() => contratista.value > 0 && map.nombre >= 0 && map.dni >= 0 && map.cuil >= 0 && totalOk.value > 0)

const proceder = async () => {
  if (map.nombre < 0) return flash('Indicá la columna del NOMBRE.', true)
  if (map.dni < 0) return flash('Indicá la columna del DNI.', true)
  if (map.cuil < 0) return flash('Indicá la columna del CUIL.', true)
  if (!confirm('¿Procede con la importación?')) return
  procesando.value = true
  try {
    const rows = filas.filter(f => f.ok).map(f => ({
      nombre: cel(f, map.nombre), dni: cel(f, map.dni), cuil: cel(f, map.cuil),
      telefono: cel(f, map.telefono), celular: cel(f, map.celular),
    }))
    const { data } = await api.post(`/contratistas-externos/${contratista.value}/empleados/importar`, { rows })
    flash(`Importación OK. Altas: ${data.insertados}. Omitidos (ya existían): ${data.omitidos}.`)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo importar.', true) }
  finally { procesando.value = false }
}
const cel = (f: { celdas: any[] }, idx: number) => idx >= 0 ? String(f.celdas[idx] ?? '').trim() : ''

const reset = () => { filas.splice(0, filas.length); columnas.value = []; nombreArchivo.value = ''; map.nombre = -1; map.dni = -1; map.cuil = -1; map.telefono = -1; map.celular = -1; flash('') }

onMounted(async () => {
  try { contratistas.value = (await api.get('/contratistas-externos')).data.map((c: any) => ({ cod: c.cod, nombre: c.nombre })) }
  catch (e) { console.error(e) }
})
</script>

<style scoped>
.ie-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ie-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ie-cab-ico { font-size:28px; } .ie-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ie-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ie-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ie-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ie-card { margin:16px 18px; }
.ie-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.ie-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ie-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; min-width:260px; }
.ie-btn-archivo { background:#1b4332; color:#fff; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ie-btn-archivo.off { background:#cbd5e1; cursor:not-allowed; }
.ie-archivo { font-size:13px; color:#475569; }
.ie-btn-reset { background:#fff; border:1px solid #cbd5e1; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; }
.ie-mapeo { display:flex; flex-wrap:wrap; gap:14px; align-items:center; padding:12px 14px; background:#f0faf4; border:1px solid #c3e6cb; border-radius:8px; margin-bottom:14px; }
.ie-mtit { font-size:13px; font-weight:700; color:#1b4332; width:100%; }
.ie-mapeo label { font-size:12px; font-weight:600; color:#374151; display:flex; flex-direction:column; gap:4px; }
.ie-mapeo select { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:12px; min-width:150px; }
.ie-grid-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:48vh; }
.ie-tabla { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.ie-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 10px; font-weight:600; }
.ie-tabla th.mapeada { background:#b45309; }
.ie-tabla td { padding:4px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ie-tabla td.mapeada { background:#fef9c3; }
.ie-tabla tr.off td { opacity:.4; }
.ie-fija { background:#f8fafc; position:sticky; left:0; }
.ie-pie { display:flex; align-items:center; gap:18px; margin-top:14px; }
.ie-total { font-size:14px; color:#374151; } .ie-total b { color:#1b4332; font-size:16px; }
.ie-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:10px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ie-btn-ok:disabled { background:#cbd5e1; cursor:not-allowed; }
.ie-msg { padding:9px 14px; margin-top:14px; font-size:13px; border-radius:6px; } .ie-msg.ok { background:#dcfce7; color:#166534; } .ie-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
