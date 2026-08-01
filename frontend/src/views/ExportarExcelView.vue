<!--
  ExportarExcelView.vue — Exportar datos de empleados a Excel.
  Réplica del formulario FoxPro: grilla de columnas seleccionables (Ok / Índice /
  Orden), Todo/Nada, filtro de estado laboral y empresa, y exportación a .xls.
-->
<template>
  <div class="ex-view">
    <!-- Cabecera -->
    <div class="ex-cab">
      <div class="ex-cab-ico">📤</div>
      <div class="ex-cab-tx">
        <h1>Exportar a Excel</h1>
        <p>Datos de empleados — seleccioná las columnas a exportar</p>
      </div>
      <button class="ex-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ex-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ExportarExcelAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/exportar"
            titulo="Asistente IA — Exportar a Excel"
            subtitulo="Preguntá cómo armar la planilla"
            :sugerencias="[
              '¿Cómo elijo qué columnas exportar?',
              '¿Para qué sirve el Índice?',
              '¿Cómo ordeno la planilla?',
              '¿Puedo exportar solo los activos?']"
            @close="modalIA = false" />

    <div class="ex-card">
      <!-- Toolbar -->
      <div class="ex-toolbar">
        <div class="ex-tb-grupo">
          <button class="ex-mini" @click="marcarTodos(true)">✓ Todo</button>
          <button class="ex-mini ex-mini-off" @click="marcarTodos(false)">✕ Nada</button>
          <span class="ex-cuenta">{{ seleccionadas }} de {{ columnas.length }} columnas</span>
        </div>
        <div class="ex-tb-grupo">
          <label>Estado:</label>
          <label class="ex-radio"><input type="radio" :value="1" v-model.number="estado" /> Todos</label>
          <label class="ex-radio"><input type="radio" :value="2" v-model.number="estado" /> Activos</label>
          <label class="ex-radio"><input type="radio" :value="3" v-model.number="estado" /> Pasivos</label>
        </div>
        <div class="ex-tb-grupo">
          <label>Empresa:</label>
          <select v-model="empresa">
            <option value="todos">Todas</option>
            <option v-for="o in empresas" :key="o.EMP_COD" :value="o.EMP_COD">{{ o.EMP_NOM }}</option>
          </select>
        </div>
      </div>

      <!-- Grilla de columnas -->
      <div class="ex-grid-wrap">
        <table class="ex-tabla">
          <thead>
            <tr><th style="width:48px">Ok</th><th>Detalle</th><th style="width:70px">Índice</th><th style="width:70px">Orden</th></tr>
          </thead>
          <tbody>
            <tr v-for="c in columnas" :key="c.campo" :class="{ marcada: c.ok }">
              <td style="text-align:center"><input type="checkbox" v-model="c.ok" /></td>
              <td>{{ c.detalle }}</td>
              <td style="text-align:center"><input type="checkbox" v-model="c.indice" /></td>
              <td><input type="number" v-model.number="c.orden" min="1" max="99" class="ex-orden" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="ex-acciones">
        <button class="ex-btn-exportar" :disabled="exportando" @click="exportar">
          📤 {{ exportando ? 'Generando…' : 'Exportar a Excel' }}
        </button>
        <p v-if="msg" :class="['ex-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import ExportarExcelAyuda from '@/components/ExportarExcelAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false)
const modalIA = ref(false)

// ── Catálogo de columnas (réplica FoxPro QUECOLUMNA) ──────
interface Columna { detalle: string; campo: string; orden: number; ok: boolean; indice: boolean }
const base: [string, string, number][] = [
  ['CODIGO', 'CODIGO', 1], ['LEGAJO', 'LEGAJO', 2], ['NOMBRE', 'NOMBRE', 3],
  ['NOMBRE DEL CONYUGE', 'CONYUGE', 4], ['NOMBRE DEL PADRE', 'PADRE', 5], ['NOMBRE DE LA MADRE', 'MADRE', 6],
  ['NOMBRE DE LOS HIJOS', 'HIJOS', 7], ['JUBILADO', 'JUBILADO', 8], ['SEXO', 'SEXO', 9],
  ['DOMICILIO', 'DOMICILIO', 10], ['LOCALIDAD', 'LOCALIDAD', 11], ['CODIGO POSTAL', 'POSTAL', 12],
  ['FECHA DE INGRESO', 'F_INGRESO', 13], ['NACIMIENTO', 'F_NACIMIENTO', 14], ['BAJA', 'F_BAJA', 15],
  ['TIPO DOCUMENTO', 'TIP_DOC', 16], ['NRO_DOCUMENTO', 'NRO_DOCUMENTO', 17], ['CUIL', 'CUIL', 18],
  ['ALMUERZA', 'ALMUERZA', 19], ['COMEDOR', 'CCOMEDOR', 20], ['ALMUERZA CON CARGO', 'CON_CARGO', 21],
  ['ESTADO ACTUAL', 'ESTADO', 22], ['TELEFONOS', 'TELEFONOS', 23], ['CELULAR', 'CELULAR', 24],
  ['TEL_CONTACTO', 'CONTACTO', 25], ['ESTADO CIVIL', 'EST_CIVIL', 26], ['EMPRESA', 'EMPRESA', 27],
  ['CONTRATISTA', 'CONTRATA', 28], ['LUGAR', 'LUGAR', 29], ['SECTOR', 'SECTOR', 30],
  ['SUBSECTOR', 'SUBSECTOR', 31], ['CBU', 'CBU', 32], ['CONVENIO', 'CONVENIO', 33],
  ['CATEGORIA', 'CATEGORIA', 34], ['BASICO', 'BASICO', 35], ['HORAS TRABAJADAS', 'HORAS', 36],
  ['VALOR HORA', 'VALOR_HORA', 37], ['NO REMUNERATIVO', 'NOREMU', 38], ['ANTICIPOS', 'ANTICIPOS', 39],
  ['FUTUROS AUMENTOS', 'FUTURO_AUMENTO', 40], ['SUELDO NETO', 'SUELDO_NETO', 41], ['SUELDO BRUTO', 'SUELDO_BRUTO', 42],
  ['COBRA HORAS EXTRAS', 'HORAS_EXTRAS', 43], ['VALOR HORA EXTRA', 'VALOR_H_EXTRA', 44], ['BANCO A DEPOSITAR', 'BANCO', 45],
  ['GRUPO LABORAL', 'GRUPO_LABORAL', 46], ['HORA ENTRADA', 'H_ENTRADA', 47], ['HORA SALIDA', 'H_SALIDA', 48],
  ['OBSERVACIONES', 'OBSERVACIONES', 49], ['PUESTOS LABORALES', 'PUESTOS', 50], ['FORMACION ACADEMICA', 'FACADEMICA', 51],
  ['FECHA EFECTIVO', 'F_EFECTIVO', 52],
]
// Ordenado por Detalle (como FoxPro: ORDER BY DETALLE), Ok por defecto en true
const columnas = reactive<Columna[]>(
  base.map(([detalle, campo, orden]) => ({ detalle, campo, orden, ok: true, indice: false }))
     .sort((a, b) => a.detalle.localeCompare(b.detalle, 'es'))
)

const seleccionadas = computed(() => columnas.filter(c => c.ok).length)
const marcarTodos = (v: boolean) => columnas.forEach(c => c.ok = v)

// ── Filtros ───────────────────────────────────────────────
const estado = ref(2)            // 1 todos · 2 activos · 3 pasivos
const empresa = ref<any>('todos')
const empresas = ref<any[]>([])

onMounted(async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    empresas.value = data.empresas ?? []
  } catch (e) { console.error('Error cargando empresas', e) }
})

// ── Exportar ──────────────────────────────────────────────
const exportando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

const esc = (v: any) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const exportar = async () => {
  if (exportando.value) return
  const cols = columnas.filter(c => c.ok)
  if (cols.length === 0) { flash('No ha seleccionado ningún campo.', true); return }

  exportando.value = true
  try {
    const params: any = { estado: estado.value }
    if (empresa.value !== 'todos') params.empresa = empresa.value
    const { data } = await api.get('/empleados/exportar', { params })
    let rows: any[] = data ?? []
    if (rows.length === 0) { flash('No se encuentran empleados para los filtros seleccionados.', true); return }

    // Ordenar por las columnas marcadas como Índice (según su Orden)
    const indices = columnas.filter(c => c.indice).sort((a, b) => a.orden - b.orden)
    if (indices.length) {
      rows = [...rows].sort((x, y) => {
        for (const ix of indices) {
          const a = x[ix.campo], b = y[ix.campo]
          if (a == null && b == null) continue
          if (typeof a === 'number' && typeof b === 'number') { if (a !== b) return a - b }
          else { const c = String(a ?? '').localeCompare(String(b ?? ''), 'es'); if (c) return c }
        }
        return 0
      })
    }

    // Encabezados: Detalle con espacios → guion bajo (CAMPOEXCEL del FoxPro)
    const thead = cols.map(c => `<th>${esc(c.detalle.replace(/ /g, '_'))}</th>`).join('')
    const tbody = rows.map(r =>
      '<tr>' + cols.map(c => `<td>${esc(r[c.campo])}</td>`).join('') + '</tr>'
    ).join('')
    const html =
      `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">` +
      `<head><meta charset="UTF-8"></head><body>` +
      `<table border="1"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody></table>` +
      `</body></html>`

    const blob = new Blob(['﻿', html], { type: 'application/vnd.ms-excel' })
    const ok = await guardarComo(blob, 'DATOS_EMPLEADOS.xls')
    if (ok === 'cancelado') { flash('Exportación cancelada.'); return }
    flash(`Excel generado (${rows.length} empleados, ${cols.length} columnas).`)
  } catch (e) {
    console.error('Error exportando', e)
    flash('No se pudo generar el Excel.', true)
  } finally {
    exportando.value = false
  }
}
</script>

<style scoped>
.ex-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.ex-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; flex-shrink:0; }
.ex-cab-ico { font-size:28px; }
.ex-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.ex-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ex-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none;
  padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-btn-ia:hover { filter:brightness(1.1); }
.ex-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-btn-ayuda:hover { background:#f0faf4; }

.ex-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 18px;
  max-width:760px; display:flex; flex-direction:column; min-height:0; flex:1; }
.ex-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:18px; padding-bottom:14px; border-bottom:1px solid #f1f5f9; }
.ex-tb-grupo { display:flex; align-items:center; gap:8px; }
.ex-tb-grupo > label { font-size:12px; font-weight:600; color:#374151; }
.ex-mini { background:#22c55e; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ex-mini:hover { background:#16a34a; }
.ex-mini-off { background:#94a3b8; }
.ex-mini-off:hover { background:#64748b; }
.ex-cuenta { font-size:12px; color:#6b7280; }
.ex-radio { display:flex; align-items:center; gap:4px; font-size:13px; color:#374151; cursor:pointer; }
.ex-tb-grupo select { border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:13px; outline:none; max-width:260px; }

.ex-grid-wrap { flex:1; overflow:auto; margin-top:12px; border:1px solid #e2e8f0; border-radius:8px; }
.ex-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ex-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; text-align:left; padding:8px 12px;
  font-size:11px; text-transform:uppercase; letter-spacing:.3px; z-index:1; }
.ex-tabla td { padding:6px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ex-tabla tr.marcada td { background:#feffd6; }
.ex-tabla tr:hover td { background:#f0faf4; }
.ex-orden { width:54px; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:12px; text-align:center; }

.ex-acciones { display:flex; align-items:center; gap:12px; margin-top:14px; padding-top:14px; border-top:1px solid #f1f5f9; }
.ex-btn-exportar { background:#1d6f42; color:#fff; border:none; padding:11px 22px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.ex-btn-exportar:hover:not(:disabled) { background:#155233; }
.ex-btn-exportar:disabled { background:#cbd5e1; cursor:default; }
.ex-msg { font-size:13px; padding:7px 12px; border-radius:6px; }
.ex-msg.ok { background:#dcfce7; color:#166534; }
.ex-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
