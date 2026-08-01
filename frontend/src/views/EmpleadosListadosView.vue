<!--
  EmpleadosListadosView.vue — Listado de Empleados (réplica del listado FoxPro).
  Filtros (estado, orden, empresa, contratista, lugar, convenio, categoría,
  sector, sub-sector, sexo, período de prueba, prestaciones) y tres salidas:
  PDF simple, PDF completo (con fotos) y Excel.
-->
<template>
  <div class="li-view">
    <!-- Cabecera -->
    <div class="li-cab">
      <div class="li-cab-ico">📊</div>
      <div class="li-cab-tx">
        <h1>Listado de Empleados</h1>
        <p>Configurá los filtros y elegí la salida</p>
      </div>
      <button class="li-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="li-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="li-btn-cerrar" title="Cerrar" @click="$emit('close')">✕</button>
    </div>

    <EmpleadosListadosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/listados"
            titulo="Asistente IA — Listados"
            subtitulo="Preguntá cómo armar el listado que necesitás"
            :sugerencias="[
              '¿Cómo saco un listado de cumpleaños del mes?',
              '¿Cómo listo solo los empleados dados de baja?',
              '¿Qué diferencia hay entre el PDF simple y el completo?',
              '¿Cómo filtro por un sector específico?']"
            @close="modalIA = false" />

    <!-- Panel de filtros -->
    <div class="li-card">
      <div class="li-grid">
        <!-- Estado -->
        <div class="li-campo">
          <label>Estado</label>
          <select v-model.number="f.estado">
            <option :value="1">En actividad</option>
            <option :value="2">Dados de baja</option>
          </select>
        </div>
        <!-- Orden -->
        <div class="li-campo">
          <label>Ordenar por</label>
          <select v-model.number="f.orden">
            <option :value="1">Alfabético</option>
            <option :value="2">Código</option>
            <option :value="3">Fecha de ingreso</option>
            <option :value="4">Día y mes de nacimiento</option>
            <option :value="5">Fecha de baja</option>
          </select>
        </div>
        <!-- Sexo -->
        <div class="li-campo">
          <label>Sexo</label>
          <select v-model="f.sexo">
            <option value="todos">Todos</option>
            <option value="F">Femenino</option>
            <option value="M">Masculino</option>
            <option value="N">No binario</option>
          </select>
        </div>

        <!-- Empresa -->
        <div class="li-campo">
          <label>Empresa</label>
          <select v-model="f.empresa">
            <option value="todos">Todas</option>
            <option v-for="o in opciones.empresas" :key="o.EMP_COD" :value="o.EMP_COD">{{ o.EMP_NOM }}</option>
          </select>
        </div>
        <!-- Contratista -->
        <div class="li-campo">
          <label>Contratista</label>
          <select v-model="f.contratista">
            <option value="todos">Todos</option>
            <option v-for="o in opciones.contratistas" :key="o.CONT_COD" :value="o.CONT_COD">{{ o.CONT_DET }}</option>
          </select>
        </div>
        <!-- Lugar -->
        <div class="li-campo">
          <label>Lugar</label>
          <select v-model="f.lugar">
            <option value="todos">Todos</option>
            <option v-for="o in opciones.lugares" :key="o.LUG_COD" :value="o.LUG_COD">{{ o.LUG_NOM }}</option>
          </select>
        </div>
        <!-- Convenio -->
        <div class="li-campo">
          <label>Convenio</label>
          <select v-model="f.convenio">
            <option value="todos">Todos</option>
            <option v-for="o in opciones.convenios" :key="o.CON_COD" :value="o.CON_COD">{{ o.CON_DES }}</option>
          </select>
        </div>
        <!-- Categoría -->
        <div class="li-campo">
          <label>Categoría</label>
          <select v-model="f.categoria">
            <option value="todos">Todas</option>
            <option v-for="o in opciones.categorias" :key="o.CAT_COD" :value="o.CAT_COD">{{ o.CAT_DES }}</option>
          </select>
        </div>
        <!-- Sector -->
        <div class="li-campo">
          <label>Sector</label>
          <select v-model="f.sector">
            <option value="todos">Todos</option>
            <option v-for="o in opciones.sectores" :key="o.SEC_COD" :value="o.SEC_COD">{{ o.SEC_DES }}</option>
          </select>
        </div>
        <!-- Sub-sector -->
        <div class="li-campo">
          <label>Sub-sector</label>
          <select v-model="f.subsector">
            <option value="todos">Todos</option>
            <option v-for="o in opciones.subsectores" :key="o.sub_cod" :value="o.sub_cod">{{ o.sub_des }}</option>
          </select>
        </div>
      </div>

      <!-- Filtros extra (checkboxes) -->
      <div class="li-checks">
        <label class="li-check"><input type="checkbox" v-model="f.prueba" /> Solo en período de prueba (primeros 90 días)</label>
        <label class="li-check"><input type="checkbox" v-model="f.prestaciones" /> Solo prestaciones (contratista, +90 días)</label>
        <label class="li-check"><input type="checkbox" v-model="f.sinPuesto" /> Empleados sin puestos asignados</label>
      </div>

      <!-- Botones de salida -->
      <div class="li-acciones">
        <button class="li-btn-pdf" :disabled="!!generando" @click="generar('simple')">
          📄 {{ generando === 'simple' ? 'Generando…' : 'PDF simple' }}
        </button>
        <button class="li-btn-pdf li-btn-foto" :disabled="!!generando" @click="generar('completo')">
          🖼️ {{ generando === 'completo' ? progresoFoto : 'PDF con fotos' }}
        </button>
        <button class="li-btn-excel" :disabled="!!generando" @click="generar('excel')">
          📤 {{ generando === 'excel' ? 'Generando…' : 'Exportar a Excel' }}
        </button>
      </div>
      <p v-if="msg" :class="['li-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal de previsualización PDF -->
    <Teleport to="body">
      <div v-if="pdfUrl" class="pdf-modal-overlay" @click.self="cerrarPdf">
        <div class="pdf-modal">
          <div class="pdf-modal-head">
            <span>{{ pdfNombre }}</span>
            <div class="pdf-modal-acc">
              <button class="btn-sm btn-ok" @click="guardarDesdeUrl(pdfUrl, pdfNombre)">⬇ Descargar</button>
              <button class="btn-sm btn-ok" @click="($refs.pdfFrame as HTMLIFrameElement)?.contentWindow?.print()">🖨 Imprimir</button>
              <button class="btn-sm btn-cancel" @click="cerrarPdf">✕ Cerrar</button>
            </div>
          </div>
          <iframe ref="pdfFrame" :src="pdfUrl" class="pdf-modal-frame"></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import jsPDF from 'jspdf'
import { guardarComo, guardarDesdeUrl } from '@/utils/descargas'
import api from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import EmpleadosListadosAyuda from '@/components/EmpleadosListadosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

defineEmits<{ close: [] }>()

const authStore = useAuthStore()
const modalAyuda = ref(false)
const modalIA = ref(false)

// ── Filtros ───────────────────────────────────────────────
const f = reactive({
  estado: 1, orden: 1, sexo: 'todos',
  empresa: 'todos', contratista: 'todos', lugar: 'todos',
  convenio: 'todos', categoria: 'todos', sector: 'todos', subsector: 'todos',
  prueba: false, prestaciones: false, sinPuesto: false,
})

// ── Lookups para los selects ──────────────────────────────
const opciones = reactive<any>({
  empresas: [], contratistas: [], lugares: [], convenios: [],
  categorias: [], sectores: [], subsectores: [],
})

onMounted(async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    Object.assign(opciones, {
      empresas: data.empresas ?? [], contratistas: data.contratistas ?? [],
      lugares: data.lugares ?? [], convenios: data.convenios ?? [],
      categorias: data.categorias ?? [], sectores: data.sectores ?? [],
      subsectores: data.subsectores ?? [],
    })
  } catch (e) { console.error('Error cargando opciones', e) }
})

// ── Estado de la UI ───────────────────────────────────────
const generando = ref<'' | 'simple' | 'completo' | 'excel'>('')
const progresoFoto = ref('Generando…')
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

// ── Modal PDF ─────────────────────────────────────────────
const pdfUrl = ref('')
const pdfNombre = ref('')
const cerrarPdf = () => { if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = '' }

// ── Helpers ───────────────────────────────────────────────
const fFecha = (v: any): string => {
  if (!v) return ''
  const s = String(v).slice(0, 10)
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s
}
const sexoTxt = (s: string) => ({ F: 'F', M: 'M', N: 'N' } as any)[(s ?? '').trim()] ?? ''

// Trae las filas del backend con los filtros actuales
const traerRows = async (): Promise<{ titulo: string; rows: any[] }> => {
  const params: any = { estado: f.estado, orden: f.orden }
  if (f.sexo !== 'todos') params.sexo = f.sexo
  for (const k of ['empresa', 'contratista', 'lugar', 'convenio', 'categoria', 'sector', 'subsector'] as const) {
    if ((f as any)[k] !== 'todos') params[k] = (f as any)[k]
  }
  if (f.prueba) params.prueba = 1
  if (f.prestaciones) params.prestaciones = 1
  if (f.sinPuesto) params.sin_puesto = 1
  const { data } = await api.get('/empleados/listado', { params })
  return { titulo: data.titulo, rows: data.rows ?? [] }
}

// ── Orquestador ───────────────────────────────────────────
const generar = async (tipo: 'simple' | 'completo' | 'excel') => {
  if (generando.value) return
  generando.value = tipo
  try {
    const { titulo, rows } = await traerRows()
    if (rows.length === 0) {
      flash('No se encuentran datos para los filtros seleccionados.', true)
      return
    }
    if (tipo === 'excel') await exportarExcel(rows)
    else if (tipo === 'simple') await pdfSimple(titulo, rows)
    else await pdfCompleto(titulo, rows)
  } catch (e) {
    console.error('Error generando listado', e)
    flash('No se pudo generar el listado.', true)
  } finally {
    generando.value = ''
    progresoFoto.value = 'Generando…'
  }
}

const pie = (doc: jsPDF, PW: number, PH: number) => {
  const total = doc.getNumberOfPages()
  const txt = `${new Date().toLocaleString('es-AR')} - ${(authStore.usuario?.NOMBRE ?? '').trim()}`
  for (let p = 1; p <= total; p++) {
    doc.setPage(p)
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(90, 90, 90)
    doc.text(txt, 12, PH - 6)
    doc.text(`Página ${p} de ${total}`, PW - 12, PH - 6, { align: 'right' })
  }
}

// ── PDF SIMPLE (tabla apaisada) ───────────────────────────
const pdfSimple = async (titulo: string, rows: any[]) => {
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const PW = 297, PH = 210, ML = 10, MR = 10

  // Columnas: ancho en mm y alineación
  const cols = [
    { k: 'cod',       t: 'Cód.',      w: 14 },
    { k: 'legajo',    t: 'Legajo',    w: 18 },
    { k: 'nombre',    t: 'Apellido y Nombre', w: 62 },
    { k: 'ingreso',   t: 'Ingreso',   w: 20, f: fFecha },
    { k: 'sector',    t: 'Sector',    w: 38 },
    { k: 'categoria', t: 'Categoría', w: 38 },
    { k: 'convenio',  t: 'Convenio',  w: 32 },
    { k: 'dni',       t: 'DNI',       w: 22 },
    { k: 'celular',   t: 'Celular',   w: 28 },
  ]

  let y = 0
  const dibujarCab = () => {
    y = 12
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(27, 67, 50)
    doc.text(titulo, PW / 2, y, { align: 'center' }); y += 7
    doc.setFontSize(8); doc.setTextColor(0, 0, 0)
    let x = ML
    doc.setFillColor(229, 231, 235); doc.rect(ML, y - 4, PW - ML - MR, 6, 'F')
    for (const c of cols) { doc.text(c.t, x + 1, y); x += c.w }
    y += 4
    doc.setDrawColor(180); doc.setLineWidth(0.2); doc.line(ML, y - 2.5, PW - MR, y - 2.5)
  }
  dibujarCab()
  doc.setFont('helvetica', 'normal')

  let n = 0
  for (const r of rows) {
    if (y > PH - 14) { doc.addPage(); dibujarCab(); doc.setFont('helvetica', 'normal') }
    if (n % 2 === 1) { doc.setFillColor(247, 250, 252); doc.rect(ML, y - 3.5, PW - ML - MR, 5, 'F') }
    let x = ML
    doc.setTextColor(30, 41, 59); doc.setFontSize(7.5)
    for (const c of cols) {
      const raw = (r as any)[c.k]
      let val = c.f ? c.f(raw) : (raw ?? '')
      val = String(val)
      // recorte para que no se solape con la columna siguiente
      while (val && doc.getTextWidth(val) > c.w - 2) val = val.slice(0, -1)
      doc.text(val, x + 1, y)
      x += c.w
    }
    y += 5; n++
  }

  // Total
  if (y > PH - 14) { doc.addPage(); y = 14 }
  doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(27, 67, 50)
  doc.text(`Total: ${rows.length} empleado${rows.length === 1 ? '' : 's'}`, ML, y + 3)

  pie(doc, PW, PH)
  cerrarPdf()
  pdfNombre.value = 'Listado_Empleados.pdf'
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

// ── PDF COMPLETO (con foto, una ficha por empleado) ───────
const pdfCompleto = async (titulo: string, rows: any[]) => {
  // Traer las fotos (best-effort, con concurrencia limitada)
  const fotos = new Map<number, { data: string; ext: string }>()
  let hechos = 0
  const pool = 6
  const cola = [...rows]
  const worker = async () => {
    while (cola.length) {
      const r = cola.shift()!
      try {
        const { data } = await api.get(`/empleados/${r.cod}/foto`)
        const dataUrl: string = data?.foto ?? ''
        if (dataUrl.startsWith('data:image')) {
          fotos.set(r.cod, { data: dataUrl, ext: dataUrl.startsWith('data:image/png') ? 'PNG' : 'JPEG' })
        }
      } catch { /* sin foto para este empleado */ }
      hechos++; progresoFoto.value = `Fotos ${hechos}/${rows.length}…`
    }
  }
  await Promise.all(Array.from({ length: pool }, worker))

  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
  const PW = 210, PH = 297, ML = 12, MR = 12
  let y = 0
  const cabecera = () => {
    y = 14
    doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(27, 67, 50)
    doc.text(titulo, PW / 2, y, { align: 'center' }); y += 8
    doc.setTextColor(0, 0, 0)
  }
  cabecera()

  const FW = 22, FH = 28, GAP = 4, FICHA_H = FH + 6
  for (const r of rows) {
    if (y + FICHA_H > PH - 12) { doc.addPage(); cabecera() }
    const fx = ML, fy = y
    // Foto / placeholder
    doc.setDrawColor(150); doc.setLineWidth(0.3); doc.rect(fx, fy, FW, FH)
    const ft = fotos.get(r.cod)
    if (ft) { try { doc.addImage(ft.data, ft.ext, fx + 0.4, fy + 0.4, FW - 0.8, FH - 0.8) } catch {} }
    else { doc.setFontSize(6); doc.setTextColor(160, 160, 160); doc.text('s/foto', fx + FW / 2, fy + FH / 2, { align: 'center' }) }

    // Datos a la derecha
    const dx = fx + FW + GAP
    let dy = fy + 4
    const campo = (lbl: string, val: any, x: number, yy: number, lblW = 22) => {
      doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(27, 67, 50); doc.text(lbl, x, yy)
      doc.setFont('helvetica', 'normal'); doc.setTextColor(30, 41, 59)
      doc.text(String(val ?? '').trim(), x + lblW, yy)
    }
    const colW = (PW - MR - dx) / 2
    campo('Código', r.cod, dx, dy, 16); campo('Legajo', r.legajo, dx + colW, dy, 16); dy += 4.5
    doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(15, 23, 42)
    doc.text(String(r.nombre ?? '').trim(), dx, dy); dy += 5
    campo('Ingreso', fFecha(r.ingreso), dx, dy, 16); campo('Nac.', fFecha(r.nacimiento), dx + colW, dy, 12); dy += 4.5
    campo('DNI', r.dni, dx, dy, 16); campo('Sexo', sexoTxt(r.sexo), dx + colW, dy, 12); dy += 4.5
    campo('Sector', r.sector, dx, dy, 16); dy += 4.5
    campo('Categoría', r.categoria, dx, dy, 16); dy += 4.5
    campo('Convenio', r.convenio, dx, dy, 16)

    y += FICHA_H
    doc.setDrawColor(225); doc.setLineWidth(0.2); doc.line(ML, y - 2, PW - MR, y - 2)
  }

  pie(doc, PW, PH)
  cerrarPdf()
  pdfNombre.value = 'Listado_Empleados_con_Fotos.pdf'
  pdfUrl.value = URL.createObjectURL(doc.output('blob'))
}

// ── EXCEL (.xls vía tabla HTML — columnas del listado FoxPro) ──
const exportarExcel = async (rows: any[]) => {
  const cols: { t: string; k: string; f?: (v: any) => string }[] = [
    { t: 'CODIGO', k: 'cod' }, { t: 'NOMBRE', k: 'nombre' }, { t: 'LEGAJO', k: 'legajo' },
    { t: 'NACIMIENTO', k: 'nacimiento', f: fFecha }, { t: 'F_INGRESO', k: 'ingreso', f: fFecha },
    { t: 'F_EFECTIVO', k: 'efectivo', f: fFecha }, { t: 'DNI', k: 'dni' }, { t: 'CUIT', k: 'cuit' },
    { t: 'DOMICILIO', k: 'domicilio' }, { t: 'TELEFONO', k: 'telefono' }, { t: 'CELULAR', k: 'celular' },
    { t: 'CONVENIO', k: 'convenio' }, { t: 'CATEGORIA', k: 'categoria' }, { t: 'SECTOR', k: 'sector' },
    { t: 'SUBSECTOR', k: 'subsector' }, { t: 'MSI', k: 'msi' },
  ]
  const esc = (v: any) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const thead = cols.map(c => `<th>${c.t}</th>`).join('')
  const tbody = rows.map(r =>
    '<tr>' + cols.map(c => `<td>${esc(c.f ? c.f(r[c.k]) : r[c.k])}</td>`).join('') + '</tr>'
  ).join('')
  const html =
    `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">` +
    `<head><meta charset="UTF-8"></head><body>` +
    `<table border="1"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table>` +
    `</body></html>`

  const blob = new Blob(['﻿', html], { type: 'application/vnd.ms-excel' })
  const r = await guardarComo(blob, 'LISTADO_DE_EMPLEADOS.xls')
  flash(r === 'cancelado' ? 'Exportación cancelada.' : `Excel generado (${rows.length} empleados).`)
}
</script>

<style scoped>
.li-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.li-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.li-cab-ico { font-size:28px; }
.li-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.li-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.li-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none;
  padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.li-btn-ia:hover { filter:brightness(1.1); }
.li-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.li-btn-ayuda:hover { background:#f0faf4; }
.li-btn-cerrar { background:#fff; color:#64748b; border:1px solid #e2e8f0; width:34px; height:34px;
  border-radius:6px; cursor:pointer; font-size:15px; font-weight:700; }
.li-btn-cerrar:hover { background:#fee2e2; color:#b91c1c; border-color:#fca5a5; }

.li-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:18px 20px; max-width:980px; }
.li-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; }
.li-campo { display:flex; flex-direction:column; gap:5px; }
.li-campo label { font-size:12px; font-weight:600; color:#374151; }
.li-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; background:#fff; outline:none; }
.li-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }

.li-checks { display:flex; flex-wrap:wrap; gap:18px; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; }
.li-check { display:flex; align-items:center; gap:7px; font-size:13px; color:#374151; cursor:pointer; }

.li-acciones { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; padding-top:16px; border-top:1px solid #f1f5f9; }
.li-btn-pdf { background:#16a34a; color:#fff; border:none; padding:10px 18px; border-radius:6px;
  cursor:pointer; font-size:13px; font-weight:600; }
.li-btn-pdf:hover:not(:disabled){ background:#15803d; }
.li-btn-foto { background:#0d9488; }
.li-btn-foto:hover:not(:disabled){ background:#0f766e; }
.li-btn-excel { background:#1d6f42; color:#fff; border:none; padding:10px 18px; border-radius:6px;
  cursor:pointer; font-size:13px; font-weight:600; }
.li-btn-excel:hover:not(:disabled){ background:#155233; }
.li-acciones button:disabled { background:#cbd5e1; cursor:default; }
.li-msg { padding:9px 12px; margin:12px 0 0; font-size:13px; border-radius:6px; }
.li-msg.ok { background:#dcfce7; color:#166534; }
.li-msg.err { background:#fee2e2; color:#b91c1c; }

/* Modal PDF */
.pdf-modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:9000;
  display:flex; align-items:center; justify-content:center; padding:20px; }
.pdf-modal { background:#fff; border-radius:10px; width:min(1000px,96vw); height:92vh; display:flex; flex-direction:column;
  overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.pdf-modal-head { display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:10px 16px; background:#1b4332; color:#fff; font-size:14px; }
.pdf-modal-acc { display:flex; gap:8px; }
.pdf-modal-frame { flex:1; border:none; width:100%; }
.btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-block; }
.btn-ok { background:#22c55e; color:#fff; }
.btn-ok:hover { background:#16a34a; }
.btn-cancel { background:rgba(255,255,255,.2); color:#fff; }
.btn-cancel:hover { background:rgba(255,255,255,.35); }
</style>
