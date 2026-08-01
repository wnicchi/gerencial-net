<!--
  ImportarConvenioView.vue — Importar datos Convenio desde Excel.
  Réplica del formulario FoxPro: se abre un Excel, se muestra en una grilla con
  columnas A, B, C…, el usuario indica en qué columna está cada dato (Código,
  Convenio, Categoría, Afiliado) y al proceder se actualizan los empleados.
-->
<template>
  <div class="im-view">
    <!-- Cabecera -->
    <div class="im-cab">
      <div class="im-cab-ico">📥</div>
      <div class="im-cab-tx">
        <h1>Importar datos Convenio</h1>
        <p>Actualización masiva de empleados desde un Excel</p>
      </div>
      <button class="im-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="im-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ImportarConvenioAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/importar"
            titulo="Asistente IA — Importar datos Convenio"
            subtitulo="Preguntá cómo importar desde Excel"
            :sugerencias="[
              '¿Qué debe tener el Excel?',
              '¿Cómo indico en qué columna está cada dato?',
              '¿Qué pasa si un código no existe?',
              '¿Qué datos se actualizan?']"
            @close="modalIA = false" />

    <!-- Solapas -->
    <div class="im-tabs">
      <button :class="['im-tab', { activa: tab === 'importar' }]" @click="tab = 'importar'">Importar datos</button>
      <button :class="['im-tab', { activa: tab === 'exportar' }]" @click="tab = 'exportar'">Exportar datos específicos</button>
    </div>

    <!-- ░░ SOLAPA IMPORTAR ░░ -->
    <div v-show="tab === 'importar'" class="im-card">
      <!-- Buscar archivo -->
      <div class="im-toolbar">
        <label class="im-btn-archivo">
          📂 Buscar archivo
          <input type="file" accept=".xlsx,.xls,.csv" @change="abrirArchivo" hidden />
        </label>
        <span v-if="nombreArchivo" class="im-archivo">{{ nombreArchivo }}</span>
        <button v-if="filas.length" class="im-btn-reset" @click="reset">↺ Reset</button>
      </div>

      <!-- Grilla -->
      <div v-if="filas.length" class="im-grid-wrap">
        <table class="im-tabla">
          <thead>
            <tr>
              <th class="im-fija">FILA</th>
              <th class="im-fija">OK</th>
              <th v-for="c in columnas" :key="c.idx"
                  :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ c.letra }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ inactiva: !f.ok }">
              <td class="im-fila-n">{{ i + 1 }}</td>
              <td style="text-align:center"><input type="checkbox" v-model="f.ok" /></td>
              <td v-for="c in columnas" :key="c.idx"
                  :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ f.celdas[c.idx] ?? '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="im-total">
        Total de registros a importar: <b>{{ totalImportar }}</b>
      </div>

      <!-- Mapeo de columnas -->
      <div v-if="filas.length" class="im-mapeo">
        <div class="im-mapeo-t">Identifique el contenido de las columnas</div>
        <div class="im-mapeo-grid">
          <div class="im-map-campo">
            <label>Código Empleado <span class="req">*</span></label>
            <select v-model.number="map.codigo"><option :value="-1">—</option>
              <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option>
            </select>
          </div>
          <div class="im-map-campo">
            <label>Convenio</label>
            <select v-model.number="map.convenio"><option :value="-1">—</option>
              <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option>
            </select>
          </div>
          <div class="im-map-campo">
            <label>Categoría</label>
            <select v-model.number="map.categoria"><option :value="-1">—</option>
              <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option>
            </select>
          </div>
          <div class="im-map-campo">
            <label>Afiliado</label>
            <select v-model.number="map.afiliado"><option :value="-1">—</option>
              <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="filas.length" class="im-acciones">
        <button class="im-btn-proceder" :disabled="!puedeImportar || importando" @click="proceder">
          ⬆️ {{ importando ? 'Importando…' : 'Proceder con la importación' }}
        </button>
        <p v-if="msg" :class="['im-msg', msgError ? 'err' : 'ok']" v-html="msg"></p>
      </div>

      <div v-if="!filas.length" class="im-vacio">
        Buscá un archivo Excel (.xlsx, .xls o .csv) para comenzar.
      </div>
    </div>

    <!-- ░░ SOLAPA EXPORTAR DATOS ESPECÍFICOS ░░ -->
    <div v-show="tab === 'exportar'" class="im-card">
      <p class="im-exp-intro">Genera un Excel de empleados <b>activos</b> con su código de convenio, categoría y afiliación
        (la planilla que luego se edita y se vuelve a importar). Filtrá si querés acotar.</p>
      <div class="im-exp-filtros">
        <div class="im-map-campo">
          <label>Empresa</label>
          <select v-model="fexp.empresa"><option value="todos">Todas</option>
            <option v-for="o in opc.empresas" :key="o.EMP_COD" :value="o.EMP_COD">{{ o.EMP_NOM }}</option></select>
        </div>
        <div class="im-map-campo">
          <label>Contratista</label>
          <select v-model="fexp.contratista"><option value="todos">Todos</option>
            <option v-for="o in opc.contratistas" :key="o.CONT_COD" :value="o.CONT_COD">{{ o.CONT_DET }}</option></select>
        </div>
        <div class="im-map-campo">
          <label>Lugar</label>
          <select v-model="fexp.lugar"><option value="todos">Todos</option>
            <option v-for="o in opc.lugares" :key="o.LUG_COD" :value="o.LUG_COD">{{ o.LUG_NOM }}</option></select>
        </div>
        <div class="im-map-campo">
          <label>Sector Laboral</label>
          <select v-model="fexp.sector"><option value="todos">Todos</option>
            <option v-for="o in opc.sectores" :key="o.SEC_COD" :value="o.SEC_COD">{{ o.SEC_DES }}</option></select>
        </div>
        <div class="im-map-campo">
          <label>Sub-Sector</label>
          <select v-model="fexp.subsector"><option value="todos">Todos</option>
            <option v-for="o in opc.subsectores" :key="o.sub_cod" :value="o.sub_cod">{{ o.sub_des }}</option></select>
        </div>
        <div class="im-map-campo">
          <label>Convenio</label>
          <select v-model="fexp.convenio"><option value="todos">Todos</option>
            <option v-for="o in opc.convenios" :key="o.CON_COD" :value="o.CON_COD">{{ o.CON_DES }}</option></select>
        </div>
        <div class="im-map-campo">
          <label>Categoría</label>
          <select v-model="fexp.categoria"><option value="todos">Todas</option>
            <option v-for="o in opc.categorias" :key="o.CAT_COD" :value="o.CAT_COD">{{ o.CAT_DES }}</option></select>
        </div>
      </div>
      <div class="im-acciones">
        <button class="im-btn-proceder" :disabled="exportando" @click="exportarDatos">
          📤 {{ exportando ? 'Generando…' : 'Exportar Datos' }}
        </button>
        <p v-if="msgExp" :class="['im-msg', msgExpError ? 'err' : 'ok']">{{ msgExp }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import api from '@/services/auth'
import ImportarConvenioAyuda from '@/components/ImportarConvenioAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false)
const modalIA = ref(false)
const tab = ref<'importar' | 'exportar'>('importar')

interface Fila { ok: boolean; celdas: any[] }
const nombreArchivo = ref('')
const filas = reactive<Fila[]>([])
const columnas = ref<{ idx: number; letra: string }[]>([])
const map = reactive({ codigo: -1, convenio: -1, categoria: -1, afiliado: -1 })

const importando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err }

// ── Abrir y parsear el Excel ──────────────────────────────
const abrirArchivo = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  nombreArchivo.value = file.name
  flash('')
  try {
    const buf = await file.arrayBuffer()
    const wb = XLSX.read(buf, { type: 'array' })
    const hoja = wb.SheetNames[0]
    if (!hoja) { flash('El archivo no tiene hojas.', true); return }
    const ws = wb.Sheets[hoja]
    if (!ws) { flash('No se pudo leer la hoja del archivo.', true); return }
    const datos: any[][] = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' })

    // Cantidad máxima de columnas
    const maxCols = datos.reduce((m, r) => Math.max(m, r.length), 0)
    columnas.value = Array.from({ length: maxCols }, (_, i) => ({ idx: i, letra: XLSX.utils.encode_col(i) }))

    filas.splice(0, filas.length, ...datos.map(r => ({
      ok: true,
      celdas: Array.from({ length: maxCols }, (_, i) => r[i] ?? ''),
    })))
    map.codigo = -1; map.convenio = -1; map.categoria = -1; map.afiliado = -1
  } catch (err) {
    console.error('Error leyendo Excel', err)
    flash('No se pudo leer el archivo. Verificá que sea un Excel válido.', true)
  } finally {
    ;(e.target as HTMLInputElement).value = ''
  }
}

// ── Helpers de mapeo ──────────────────────────────────────
const muestra = (idx: number): string => {
  const v = filas.find(f => String(f.celdas[idx] ?? '').trim() !== '')?.celdas[idx]
  return v != null && String(v).trim() !== '' ? `  ·  ej: ${String(v).slice(0, 18)}` : ''
}
const columnasMapeadas = computed(() => {
  const s = new Set<number>()
  for (const k of ['codigo', 'convenio', 'categoria', 'afiliado'] as const) {
    if ((map as any)[k] >= 0) s.add((map as any)[k])
  }
  return s
})
const codigoValido = (f: Fila): boolean => {
  if (map.codigo < 0) return false
  const n = Number(String(f.celdas[map.codigo] ?? '').replace(/\D/g, ''))
  return Number.isFinite(n) && n > 0
}
const totalImportar = computed(() => filas.filter(f => f.ok && codigoValido(f)).length)
const puedeImportar = computed(() =>
  map.codigo >= 0 &&
  (map.convenio >= 0 || map.categoria >= 0 || map.afiliado >= 0) &&
  totalImportar.value > 0
)

// ── Proceder con la importación ───────────────────────────
const proceder = async () => {
  if (!puedeImportar.value || importando.value) return
  if (!confirm(`¿Importar ${totalImportar.value} registro(s)?`)) return
  importando.value = true
  flash('')
  try {
    const items = filas.filter(f => f.ok && codigoValido(f)).map(f => {
      const it: any = { codigo: Number(String(f.celdas[map.codigo]).replace(/\D/g, '')) }
      if (map.convenio >= 0)  it.convenio  = String(f.celdas[map.convenio] ?? '').trim()
      if (map.categoria >= 0) it.categoria = String(f.celdas[map.categoria] ?? '').trim()
      if (map.afiliado >= 0)  it.afiliado  = String(f.celdas[map.afiliado] ?? '').trim()
      return it
    })
    const { data } = await api.post('/empleados/importar-convenio', { items })
    let t = `✅ Importación completa. <b>${data.actualizados}</b> empleado(s) actualizado(s).`
    if (data.no_encontrados > 0) {
      t += ` <b>${data.no_encontrados}</b> código(s) no encontrado(s)` +
           (data.codigos_no_encontrados?.length ? `: ${data.codigos_no_encontrados.join(', ')}` : '') + '.'
    }
    flash(t)
  } catch (e: any) {
    flash(e?.response?.data?.error ?? 'No se pudo completar la importación.', true)
  } finally {
    importando.value = false
  }
}

const reset = () => {
  filas.splice(0, filas.length)
  columnas.value = []
  nombreArchivo.value = ''
  map.codigo = -1; map.convenio = -1; map.categoria = -1; map.afiliado = -1
  flash('')
}

// ── Solapa Exportar datos específicos ─────────────────────
const opc = reactive<any>({ empresas: [], contratistas: [], lugares: [], sectores: [], subsectores: [], convenios: [], categorias: [] })
const fexp = reactive({ empresa: 'todos', contratista: 'todos', lugar: 'todos', sector: 'todos', subsector: 'todos', convenio: 'todos', categoria: 'todos' })
const exportando = ref(false)
const msgExp = ref(''); const msgExpError = ref(false)
const flashExp = (t: string, err = false) => { msgExp.value = t; msgExpError.value = err; setTimeout(() => msgExp.value = '', 4000) }

onMounted(async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    Object.assign(opc, {
      empresas: data.empresas ?? [], contratistas: data.contratistas ?? [], lugares: data.lugares ?? [],
      sectores: data.sectores ?? [], subsectores: data.subsectores ?? [], convenios: data.convenios ?? [], categorias: data.categorias ?? [],
    })
  } catch (e) { console.error('Error cargando opciones', e) }
})

const exportarDatos = async () => {
  if (exportando.value) return
  exportando.value = true
  flashExp('')
  try {
    const params: any = {}
    for (const k of ['empresa', 'contratista', 'lugar', 'sector', 'subsector', 'convenio', 'categoria'] as const) {
      if ((fexp as any)[k] !== 'todos') params[k] = (fexp as any)[k]
    }
    const { data } = await api.get('/empleados/exportar-convenio', { params })
    const rows: any[] = data ?? []
    if (rows.length === 0) { flashExp('No se encuentran empleados para los filtros seleccionados.', true); return }

    const cols = [
      { t: 'CODIGO', k: 'codigo' }, { t: 'NOMBRE', k: 'nombre' },
      { t: 'CODIGO_CONVENIO', k: 'codigo_convenio' }, { t: 'CODIGO_CATEGORIA', k: 'codigo_categoria' },
      { t: 'AFILIADO', k: 'afiliado' },
    ]
    const esc = (v: any) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const thead = cols.map(c => `<th>${c.t}</th>`).join('')
    const tbody = rows.map(r => '<tr>' + cols.map(c => `<td>${esc(r[c.k])}</td>`).join('') + '</tr>').join('')
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">` +
      `<head><meta charset="UTF-8"></head><body><table border="1"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table></body></html>`

    const blob = new Blob(['﻿', html], { type: 'application/vnd.ms-excel' })
    const r = await guardarComo(blob, 'EMPLEADOS_DATOS_CONVENIO.xls')
    flashExp(r === 'cancelado' ? 'Exportación cancelada.' : `Excel generado (${rows.length} empleados).`)
  } catch (e) {
    console.error('Error exportando', e)
    flashExp('No se pudo generar el Excel.', true)
  } finally {
    exportando.value = false
  }
}
</script>

<style scoped>
.im-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.im-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.im-cab-ico { font-size:28px; }
.im-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.im-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.im-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none;
  padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.im-btn-ia:hover { filter:brightness(1.1); }
.im-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.im-btn-ayuda:hover { background:#f0faf4; }

.im-tabs { display:flex; gap:4px; margin:14px 18px 0; }
.im-tab { background:#eef2f7; color:#475569; border:none; border-radius:8px 8px 0 0; padding:10px 18px;
  cursor:pointer; font-size:13px; font-weight:600; }
.im-tab.activa { background:#fff; color:#1b4332; box-shadow:0 -2px 0 #16a34a inset; }
.im-card { margin:0 18px 18px; background:#fff; border:1px solid #e2e8f0; border-radius:0 10px 10px 10px; padding:16px 18px;
  display:flex; flex-direction:column; min-height:0; flex:1; }
.im-exp-intro { font-size:13px; color:#475569; line-height:1.5; margin:0 0 16px; }
.im-exp-filtros { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
.im-toolbar { display:flex; align-items:center; gap:14px; padding-bottom:14px; border-bottom:1px solid #f1f5f9; }
.im-btn-archivo { background:#2563eb; color:#fff; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.im-btn-archivo:hover { background:#1d4ed8; }
.im-archivo { font-size:13px; color:#475569; }
.im-btn-reset { margin-left:auto; background:#fff; color:#475569; border:1px solid #d1d5db; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; }
.im-btn-reset:hover { background:#f8fafc; }

.im-grid-wrap { flex:1; overflow:auto; margin-top:12px; border:1px solid #e2e8f0; border-radius:8px; min-height:140px; }
.im-tabla { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.im-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; padding:5px 10px; font-weight:700; z-index:1; border-right:1px solid #2d5a45; }
.im-tabla th.mapeada { background:#ca8a04; }
.im-tabla td { padding:4px 10px; border-bottom:1px solid #f1f5f9; border-right:1px solid #f1f5f9; color:#1e293b; }
.im-tabla td.mapeada { background:#fefce8; }
.im-tabla tr.inactiva td { opacity:.4; }
.im-fija { background:#0f3024 !important; }
.im-fila-n { color:#94a3b8; font-family:monospace; text-align:center; }

.im-total { margin-top:12px; font-size:14px; color:#1e293b; }
.im-total b { color:#1b4332; font-size:16px; }

.im-mapeo { margin-top:14px; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; background:#f8fafc; }
.im-mapeo-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.4px; margin-bottom:12px; }
.im-mapeo-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
.im-map-campo { display:flex; flex-direction:column; gap:5px; }
.im-map-campo label { font-size:12px; font-weight:600; color:#374151; }
.im-map-campo .req { color:#dc2626; }
.im-map-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; background:#fff; outline:none; }
.im-map-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }

.im-acciones { display:flex; flex-direction:column; gap:10px; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; }
.im-btn-proceder { align-self:flex-start; background:#16a34a; color:#fff; border:none; padding:11px 22px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.im-btn-proceder:hover:not(:disabled) { background:#15803d; }
.im-btn-proceder:disabled { background:#cbd5e1; cursor:default; }
.im-msg { font-size:13px; padding:9px 12px; border-radius:6px; margin:0; }
.im-msg.ok { background:#dcfce7; color:#166534; }
.im-msg.err { background:#fee2e2; color:#b91c1c; }
.im-vacio { flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:14px; }
</style>
