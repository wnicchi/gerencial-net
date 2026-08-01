<!--
  ImportarObraSocialView.vue — Importar Costos Obras Sociales (3 solapas).
  1) Importar Costo O.S. (Excel + mapeo CUIL/DNI + Importe, por CUIL o por DNI)
  2) Historial (todo lo importado + totales + filtro)
  3) Informes (visualizar PDF / crear Excel del período)
-->
<template>
  <div class="os-view">
    <div class="os-cab">
      <div class="os-cab-ico">🏥</div>
      <div class="os-cab-tx">
        <h1>Importar Costos Obras Sociales</h1>
        <p>Importar planillas, consultar historial y generar informes</p>
      </div>
      <button class="os-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="os-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ImportarObraSocialAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/obra-social"
            titulo="Asistente IA — Costos Obras Sociales"
            subtitulo="Preguntá cómo importar, consultar o informar"
            :sugerencias="[
              '¿Cómo importo una planilla?',
              '¿Qué diferencia hay entre PLAN y APORTES?',
              '¿Para qué sirve el historial?',
              '¿Cómo genero el informe del mes?']"
            @close="modalIA = false" />

    <div class="os-tabs">
      <button :class="['os-tab', { activa: tab === 'importar' }]" @click="tab = 'importar'">Importar Costo O.S.</button>
      <button :class="['os-tab', { activa: tab === 'historial' }]" @click="irHistorial">Historial</button>
      <button :class="['os-tab', { activa: tab === 'informes' }]" @click="tab = 'informes'">Informes</button>
    </div>

    <!-- ░░ SOLAPA 1: IMPORTAR ░░ -->
    <div v-show="tab === 'importar'" class="os-card">
      <div class="os-fila-filtros">
        <div class="os-campo"><label>Mes</label><input v-model.number="imp.mes" type="number" min="1" max="12" /></div>
        <div class="os-campo"><label>Año</label><input v-model.number="imp.anio" type="number" min="2000" max="2099" /></div>
        <div class="os-campo os-campo-ancho"><label>Obra Social <span class="req">*</span></label>
          <select v-model="imp.obra"><option value="">— Elegir —</option>
            <option v-for="o in opc.obrassociales" :key="o.OBR_COD" :value="o.OBR_NOM">{{ o.OBR_NOM }}</option></select>
        </div>
        <div class="os-campo"><label>Tipo planilla</label>
          <div class="os-radios">
            <label><input type="radio" :value="1" v-model.number="imp.planilla" /> Plan</label>
            <label><input type="radio" :value="2" v-model.number="imp.planilla" /> Aportes</label>
          </div>
        </div>
        <label class="os-btn-archivo">📂 Buscar archivo
          <input type="file" accept=".xlsx,.xls,.csv" @change="abrirArchivo" hidden /></label>
        <button v-if="filas.length" class="os-btn-reset" @click="resetImport">↺ Reset</button>
      </div>

      <div v-if="filas.length" class="os-grid-wrap">
        <table class="os-tabla os-grid">
          <thead><tr><th class="fija">FILA</th><th class="fija">OK</th>
            <th v-for="c in columnas" :key="c.idx" :class="{ map: colsMap.has(c.idx) }">{{ c.letra }}</th></tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ inactiva: !f.ok }">
              <td class="fila-n">{{ i + 1 }}</td>
              <td style="text-align:center"><input type="checkbox" v-model="f.ok" /></td>
              <td v-for="c in columnas" :key="c.idx" :class="{ map: colsMap.has(c.idx) }">{{ f.celdas[c.idx] ?? '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="os-total">Total de registros a importar: <b>{{ totalImportar }}</b></div>

      <div v-if="filas.length" class="os-mapeo">
        <div class="os-mapeo-t">Identifique el contenido de las columnas</div>
        <div class="os-mapeo-grid">
          <div class="os-campo"><label>CUIL / DNI <span class="req">*</span></label>
            <select v-model.number="map.clave"><option :value="-1">—</option>
              <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option></select>
          </div>
          <div class="os-campo"><label>Importe <span class="req">*</span></label>
            <select v-model.number="map.importe"><option :value="-1">—</option>
              <option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option></select>
          </div>
        </div>
      </div>

      <div v-if="filas.length" class="os-acciones">
        <button class="os-btn-ok" :disabled="!puedeImportar || importando" @click="proceder('cuil')">⬆️ Importar por CUIL</button>
        <button class="os-btn-ok2" :disabled="!puedeImportar || importando" @click="proceder('dni')">⬆️ Importar por DNI</button>
        <p v-if="msg" :class="['os-msg', msgErr ? 'err' : 'ok']">{{ msg }}</p>
      </div>
      <div v-if="!filas.length" class="os-vacio">Elegí período, obra social, tipo de planilla y buscá el archivo Excel.</div>
    </div>

    <!-- ░░ SOLAPA 2: HISTORIAL ░░ -->
    <div v-show="tab === 'historial'" class="os-card">
      <h3 class="os-sub">Historial importación planilla costo obra social</h3>
      <div class="os-filtro">
        <input v-model="filtroHist" type="text" placeholder="Filtrar por nombre, obra o CUIL…" />
        <button class="os-btn-reset" @click="cargarHistorial">↻ Actualizar</button>
      </div>
      <div class="os-grid-wrap">
        <table class="os-tabla">
          <thead><tr><th>Empleado</th><th>Nombre</th><th>CUIL</th><th>Obra Social</th><th>Mes</th><th>Año</th>
            <th class="num">Neto</th><th class="num">Aporte</th><th class="num">Diferencia</th></tr></thead>
          <tbody>
            <tr v-for="(h, i) in histFiltrado" :key="i">
              <td>{{ h.cod }}</td><td>{{ h.nombre }}</td><td>{{ h.cuil }}</td><td>{{ h.obra }}</td>
              <td>{{ h.mes }}</td><td>{{ h.anio }}</td>
              <td class="num">{{ money(h.neto) }}</td><td class="num">{{ money(h.aporte) }}</td>
              <td class="num" :class="{ neg: Number(h.diferencia) < 0 }">{{ money(h.diferencia) }}</td>
            </tr>
            <tr v-if="!histFiltrado.length"><td colspan="9" class="os-vacio2">Sin registros</td></tr>
          </tbody>
        </table>
      </div>
      <div class="os-totales">
        <div class="os-tot-tit">TOTALES</div>
        <div class="os-tot-cards">
          <div class="os-tot-card">
            <span class="os-tot-lbl">Neto</span>
            <span class="os-tot-val">{{ money(totHist.neto) }}</span>
          </div>
          <div class="os-tot-card">
            <span class="os-tot-lbl">Aporte</span>
            <span class="os-tot-val">{{ money(totHist.aporte) }}</span>
          </div>
          <div class="os-tot-card" :class="{ neg: totHist.diferencia < 0 }">
            <span class="os-tot-lbl">Diferencia</span>
            <span class="os-tot-val">{{ money(totHist.diferencia) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ░░ SOLAPA 3: INFORMES ░░ -->
    <div v-show="tab === 'informes'" class="os-card">
      <h3 class="os-sub">Generación de informes</h3>
      <div class="os-inf-filtros">
        <div class="os-campo"><label>Mes</label><input v-model.number="inf.mes" type="number" min="1" max="12" /></div>
        <div class="os-campo"><label>Año</label><input v-model.number="inf.anio" type="number" min="2000" max="2099" /></div>
        <div class="os-campo os-campo-ancho"><label>Obra Social</label>
          <select v-model="inf.obra"><option value="">Todas</option>
            <option v-for="o in opc.obrassociales" :key="o.OBR_COD" :value="o.OBR_NOM">{{ o.OBR_NOM }}</option></select></div>
        <div class="os-campo os-campo-ancho"><label>Convenio</label>
          <select v-model="inf.convenio"><option value="">Todos</option>
            <option v-for="o in opc.convenios" :key="o.CON_COD" :value="o.CON_DES">{{ o.CON_DES }}</option></select></div>
      </div>
      <div class="os-acciones">
        <button class="os-btn-ok" :disabled="!!generando" @click="visualizarInforme">📄 {{ generando === 'pdf' ? 'Generando…' : 'Visualizar Informe' }}</button>
        <button class="os-btn-excel" :disabled="!!generando" @click="crearExcel">📤 {{ generando === 'excel' ? 'Generando…' : 'Crear Excel' }}</button>
        <p v-if="msgInf" :class="['os-msg', msgInfErr ? 'err' : 'ok']">{{ msgInf }}</p>
      </div>
    </div>

    <!-- Modal PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pdf-modal-overlay" @click.self="cerrarPdf">
        <div class="pdf-modal">
          <div class="pdf-modal-head"><span>{{ pdfNombre }}</span>
            <div class="pdf-modal-acc">
              <button class="btn-sm btn-ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="btn-sm btn-ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="btn-sm btn-cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div></div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="pdf-modal-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import jsPDF from 'jspdf'
import api from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import ImportarObraSocialAyuda from '@/components/ImportarObraSocialAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'

const authStore = useAuthStore()
const modalAyuda = ref(false)
const modalIA = ref(false)
const tab = ref<'importar' | 'historial' | 'informes'>('importar')

const hoy = new Date()
const opc = reactive<any>({ obrassociales: [], convenios: [] })
onMounted(async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    opc.obrassociales = data.obrassociales ?? []
    opc.convenios = data.convenios ?? []
  } catch (e) { console.error(e) }
})

// ── Helpers ───────────────────────────────────────────────
const money = (v: any) => Number(v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ══ SOLAPA 1: IMPORTAR ══
interface Fila { ok: boolean; celdas: any[] }
const imp = reactive({ mes: hoy.getMonth() + 1, anio: hoy.getFullYear(), obra: '', planilla: 1 })
const filas = reactive<Fila[]>([])
const columnas = ref<{ idx: number; letra: string }[]>([])
const map = reactive({ clave: -1, importe: -1 })
const importando = ref(false)
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e }

const abrirArchivo = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  flash('')
  try {
    const buf = await file.arrayBuffer()
    const wb = XLSX.read(buf, { type: 'array' })
    const hoja = wb.SheetNames[0]; if (!hoja) return
    const ws = wb.Sheets[hoja]; if (!ws) return
    const datos: any[][] = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' })
    const maxCols = datos.reduce((m, r) => Math.max(m, r.length), 0)
    columnas.value = Array.from({ length: maxCols }, (_, i) => ({ idx: i, letra: XLSX.utils.encode_col(i) }))
    filas.splice(0, filas.length, ...datos.map(r => ({ ok: true, celdas: Array.from({ length: maxCols }, (_, i) => r[i] ?? '') })))
    map.clave = -1; map.importe = -1
  } catch (err) { console.error(err); flash('No se pudo leer el archivo.', true) }
  finally { (e.target as HTMLInputElement).value = '' }
}

const muestra = (idx: number): string => {
  const v = filas.find(f => String(f.celdas[idx] ?? '').trim() !== '')?.celdas[idx]
  return v != null && String(v).trim() !== '' ? `  ·  ej: ${String(v).slice(0, 16)}` : ''
}
const colsMap = computed(() => { const s = new Set<number>(); if (map.clave >= 0) s.add(map.clave); if (map.importe >= 0) s.add(map.importe); return s })
const claveValida = (f: Fila): boolean => {
  if (map.clave < 0) return false
  const n = Number(String(f.celdas[map.clave] ?? '').replace(/\D/g, ''))
  return Number.isFinite(n) && n > 0
}
const totalImportar = computed(() => filas.filter(f => f.ok && claveValida(f)).length)
const puedeImportar = computed(() => map.clave >= 0 && map.importe >= 0 && !!imp.obra && totalImportar.value > 0)

const proceder = async (por: 'cuil' | 'dni') => {
  if (!puedeImportar.value || importando.value) return
  if (!imp.obra) { flash('Seleccioná a qué obra social corresponde.', true); return }
  if (!confirm(`¿Procede con la importación de datos (por ${por.toUpperCase()})?`)) return
  importando.value = true; flash('')
  try {
    const items = filas.filter(f => f.ok && claveValida(f)).map(f => ({
      clave: String(f.celdas[map.clave] ?? '').trim(),
      importe: Number(String(f.celdas[map.importe] ?? '0').replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '')) || 0,
    }))
    const { data } = await api.post('/empleados/obrasocial/importar', {
      mes: imp.mes, anio: imp.anio, obra: imp.obra, planilla: imp.planilla, por, items,
    })
    flash(`✅ Fin de la importación. Total: ${data.total}, coincidencias: ${data.encontrados}.`)
  } catch (e: any) {
    flash(e?.response?.data?.error ?? 'No se pudo importar.', true)
  } finally { importando.value = false }
}

const resetImport = () => { filas.splice(0, filas.length); columnas.value = []; map.clave = -1; map.importe = -1; flash('') }

// ══ SOLAPA 2: HISTORIAL ══
const hist = ref<any[]>([])
const totHist = reactive({ neto: 0, aporte: 0, diferencia: 0 })
const filtroHist = ref('')

const irHistorial = () => { tab.value = 'historial'; if (!hist.value.length) cargarHistorial() }
const cargarHistorial = async () => {
  try {
    const { data } = await api.get('/empleados/obrasocial/historial')
    hist.value = data.rows ?? []
    Object.assign(totHist, data.totales ?? { neto: 0, aporte: 0, diferencia: 0 })
  } catch (e) { console.error(e) }
}
const histFiltrado = computed(() => {
  const q = filtroHist.value.trim().toLowerCase()
  if (!q) return hist.value
  return hist.value.filter(h =>
    String(h.nombre ?? '').toLowerCase().includes(q) ||
    String(h.obra ?? '').toLowerCase().includes(q) ||
    String(h.cuil ?? '').toLowerCase().includes(q))
})

// ══ SOLAPA 3: INFORMES ══
const inf = reactive({ mes: hoy.getMonth() + 1, anio: hoy.getFullYear(), obra: '', convenio: '' })
const generando = ref<'' | 'pdf' | 'excel'>('')
const msgInf = ref(''); const msgInfErr = ref(false)
const flashInf = (t: string, e = false) => { msgInf.value = t; msgInfErr.value = e; setTimeout(() => msgInf.value = '', 4000) }
const pdfUrl = ref(''); const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

const traerInforme = async (): Promise<any[]> => {
  const params: any = { mes: inf.mes, anio: inf.anio }
  if (inf.obra) params.obra = inf.obra
  if (inf.convenio) params.convenio = inf.convenio
  const { data } = await api.get('/empleados/obrasocial/informe', { params })
  return data ?? []
}

const visualizarInforme = async () => {
  if (generando.value) return
  generando.value = 'pdf'; msgInf.value = ''
  try {
    const rows = await traerInforme()
    if (!rows.length) { flashInf('No hay datos para el período/filtros seleccionados.', true); return }

    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
    const PW = 297, PH = 210, ML = 8, MR = 8
    const cols = [
      { t: 'Nombre', k: 'nombre', w: 46, a: 'l' as const },
      { t: 'CUIL', k: 'cuil', w: 24, a: 'l' as const },
      { t: 'Convenio', k: 'convenio', w: 26, a: 'l' as const },
      { t: 'Obra Social', k: 'obra', w: 34, a: 'l' as const },
      { t: 'Sueldo', k: 'sueldo_neto', w: 24, a: 'r' as const, m: true },
      { t: 'Prom.Extra', k: 'prom_extra', w: 24, a: 'r' as const, m: true },
      { t: 'Plan', k: 'plan', w: 24, a: 'r' as const, m: true },
      { t: 'Aporte', k: 'aporte', w: 24, a: 'r' as const, m: true },
      { t: 'Diferencia', k: 'diferencia', w: 24, a: 'r' as const, m: true },
      { t: 'Costo Real', k: 'costo_real', w: 24, a: 'r' as const, m: true },
    ]
    let y = 0
    const cab = () => {
      y = 12
      doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(27, 67, 50)
      doc.text(`INFORME DE OBRAS SOCIALES — ${String(inf.mes).padStart(2, '0')}/${inf.anio}`, PW / 2, y, { align: 'center' }); y += 7
      doc.setFontSize(7); doc.setFillColor(229, 231, 235); doc.rect(ML, y - 4, PW - ML - MR, 6, 'F')
      doc.setTextColor(30, 41, 59); let x = ML
      for (const c of cols) { doc.text(c.t, c.a === 'r' ? x + c.w - 1 : x + 1, y, { align: c.a === 'r' ? 'right' : 'left' }); x += c.w }
      y += 5
    }
    cab(); doc.setFont('helvetica', 'normal')
    const tot: any = { sueldo_neto: 0, prom_extra: 0, plan: 0, aporte: 0, diferencia: 0, costo_real: 0 }
    let n = 0
    for (const r of rows) {
      if (y > PH - 16) { doc.addPage(); cab(); doc.setFont('helvetica', 'normal') }
      if (n % 2 === 1) { doc.setFillColor(247, 250, 252); doc.rect(ML, y - 3.5, PW - ML - MR, 5, 'F') }
      let x = ML; doc.setFontSize(6.8); doc.setTextColor(30, 41, 59)
      for (const c of cols) {
        let v = c.m ? money(r[c.k]) : String(r[c.k] ?? '')
        if (!c.m) while (v && doc.getTextWidth(v) > c.w - 2) v = v.slice(0, -1)
        doc.text(v, c.a === 'r' ? x + c.w - 1 : x + 1, y, { align: c.a === 'r' ? 'right' : 'left' })
        if (c.m) tot[c.k] += Number(r[c.k] ?? 0)
        x += c.w
      }
      y += 5; n++
    }
    // Totales
    if (y > PH - 16) { doc.addPage(); cab() }
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7); doc.setDrawColor(150); doc.line(ML, y - 2, PW - MR, y - 2)
    let x = ML
    for (const c of cols) {
      if (c.k === 'nombre') doc.text('TOTALES', x + 1, y + 1)
      else if (c.m) doc.text(money(tot[c.k]), x + c.w - 1, y + 1, { align: 'right' })
      x += c.w
    }
    // Pie
    const total = doc.getNumberOfPages()
    const pie = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
    for (let p = 1; p <= total; p++) { doc.setPage(p); doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90)
      doc.text(pie, ML, PH - 5); doc.text(`Página ${p} de ${total}`, PW - MR, PH - 5, { align: 'right' }) }

    cerrarPdf()
    pdfNombre.value = `Informe_Obras_Sociales_${inf.mes}_${inf.anio}.pdf`
    pdfUrl.value = URL.createObjectURL(doc.output('blob'))
  } catch (e) { console.error(e); flashInf('No se pudo generar el informe.', true) }
  finally { generando.value = '' }
}

const crearExcel = async () => {
  if (generando.value) return
  generando.value = 'excel'; msgInf.value = ''
  try {
    const rows = await traerInforme()
    if (!rows.length) { flashInf('No hay datos para el período/filtros seleccionados.', true); return }
    const cols = [
      { t: 'NOMBRE', k: 'nombre' }, { t: 'CUIL', k: 'cuil' }, { t: 'CONVENIO', k: 'convenio' }, { t: 'OBRA', k: 'obra' },
      { t: 'SUELDO_NETO', k: 'sueldo_neto' }, { t: 'PROM_EXTRA', k: 'prom_extra' }, { t: 'PLAN', k: 'plan' },
      { t: 'APORTE', k: 'aporte' }, { t: 'DIFERENCIA', k: 'diferencia' }, { t: 'COSTO_REAL', k: 'costo_real' },
    ]
    const esc = (v: any) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const thead = cols.map(c => `<th>${c.t}</th>`).join('')
    const tbody = rows.map(r => '<tr>' + cols.map(c => `<td>${esc(r[c.k])}</td>`).join('') + '</tr>').join('')
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body><table border="1"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table></body></html>`
    const blob = new Blob(['﻿', html], { type: 'application/vnd.ms-excel' })
    const nombre = `COSTOS_OBRAS_SOCIALES${inf.obra ? '_' + inf.obra : ''}${inf.convenio ? '_' + inf.convenio : ''}.xls`
    const r = await guardarComo(blob, nombre)
    flashInf(r === 'cancelado' ? 'Exportación cancelada.' : `Excel generado (${rows.length} empleados).`)
  } catch (e) { console.error(e); flashInf('No se pudo generar el Excel.', true) }
  finally { generando.value = '' }
}
</script>

<style scoped>
.os-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.os-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.os-cab-ico { font-size:28px; }
.os-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.os-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.os-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.os-btn-ia:hover { filter:brightness(1.1); }
.os-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }

.os-tabs { display:flex; gap:4px; margin:14px 18px 0; }
.os-tab { background:#eef2f7; color:#475569; border:none; border-radius:8px 8px 0 0; padding:10px 18px; cursor:pointer; font-size:13px; font-weight:600; }
.os-tab.activa { background:#fff; color:#1b4332; box-shadow:0 -2px 0 #16a34a inset; }
.os-card { margin:0 18px 18px; background:#fff; border:1px solid #e2e8f0; border-radius:0 10px 10px 10px; padding:16px 18px; display:flex; flex-direction:column; min-height:0; flex:1; overflow:auto; }
.os-sub { margin:0 0 14px; color:#1b4332; font-size:15px; text-align:center; }

.os-fila-filtros, .os-inf-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px; padding-bottom:14px; border-bottom:1px solid #f1f5f9; }
.os-campo { display:flex; flex-direction:column; gap:5px; }
.os-campo label { font-size:12px; font-weight:600; color:#374151; }
.os-campo .req { color:#dc2626; }
.os-campo input, .os-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; background:#fff; outline:none; }
.os-campo input[type=number] { width:80px; }
.os-campo-ancho select { min-width:240px; }
.os-radios { display:flex; gap:12px; font-size:13px; padding-top:4px; }
.os-btn-archivo { background:#2563eb; color:#fff; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.os-btn-archivo:hover { background:#1d4ed8; }
.os-btn-reset { background:#fff; color:#475569; border:1px solid #d1d5db; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; }

.os-grid-wrap { flex:1; overflow:auto; margin-top:12px; border:1px solid #e2e8f0; border-radius:8px; min-height:140px; }
.os-tabla { width:100%; border-collapse:collapse; font-size:12px; }
.os-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 10px; text-align:left; font-size:11px; z-index:1; }
.os-tabla td { padding:6px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.os-tabla tr:hover td { background:#f0faf4; }
.os-tabla .num { text-align:right; font-variant-numeric:tabular-nums; }
.os-tabla .neg { color:#dc2626; }
.os-grid { white-space:nowrap; }
.os-grid th.map, .os-grid td.map { background:#fefce8; }
.os-grid th.map { background:#ca8a04; }
.os-grid tr.inactiva td { opacity:.4; }
.fija { background:#0f3024 !important; }
.fila-n { color:#94a3b8; font-family:monospace; text-align:center; }

.os-total { margin-top:12px; font-size:14px; } .os-total b { color:#1b4332; font-size:16px; }
.os-mapeo { margin-top:14px; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; background:#f8fafc; }
.os-mapeo-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.4px; margin-bottom:12px; }
.os-mapeo-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }

.os-acciones { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; }
.os-btn-ok { background:#16a34a; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.os-btn-ok:hover:not(:disabled) { background:#15803d; }
.os-btn-ok2 { background:#0d9488; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.os-btn-ok2:hover:not(:disabled) { background:#0f766e; }
.os-btn-excel { background:#1d6f42; color:#fff; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.os-acciones button:disabled { background:#cbd5e1; cursor:default; }
.os-msg { font-size:13px; padding:7px 12px; border-radius:6px; margin:0; }
.os-msg.ok { background:#dcfce7; color:#166534; } .os-msg.err { background:#fee2e2; color:#b91c1c; }
.os-vacio { flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:14px; }
.os-vacio2 { text-align:center; color:#9ca3af; padding:16px; }
.os-filtro { display:flex; gap:10px; margin-bottom:12px; }
.os-filtro input { flex:1; max-width:360px; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; outline:none; }
.os-totales { margin-top:14px; padding-top:14px; border-top:2px solid #e2e8f0; }
.os-tot-tit { font-size:12px; font-weight:700; color:#1b4332; letter-spacing:1px; text-transform:uppercase; margin-bottom:10px; }
.os-tot-cards { display:flex; flex-wrap:wrap; gap:28px; }
.os-tot-card { display:flex; flex-direction:column; gap:4px; min-width:170px; background:#f0fdf4; border:1px solid #d1fae5;
  border-left:4px solid #16a34a; border-radius:10px; padding:12px 18px; }
.os-tot-lbl { font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.5px; }
.os-tot-val { font-size:22px; font-weight:800; color:#1b4332; font-variant-numeric:tabular-nums; }
.os-tot-card.neg { background:#fef2f2; border-color:#fecaca; border-left-color:#dc2626; }
.os-tot-card.neg .os-tot-val { color:#dc2626; }

.pdf-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:9000; display:flex; align-items:center; justify-content:center; padding:20px; }
.pdf-modal { background:#fff; border-radius:10px; width:min(1100px,97vw); height:90vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.pdf-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 16px; background:#1b4332; color:#fff; font-size:14px; }
.pdf-modal-acc { display:flex; gap:8px; }
.pdf-modal-frame { flex:1; border:none; width:100%; }
.btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:none; }
.btn-ok { background:#22c55e; color:#fff; } .btn-ok:hover { background:#16a34a; }
.btn-cancel { background:rgba(255,255,255,.2); color:#fff; } .btn-cancel:hover { background:rgba(255,255,255,.35); }
</style>
