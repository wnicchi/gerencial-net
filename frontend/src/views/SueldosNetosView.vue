<!-- SueldosNetosView.vue — Exportar / Importar Sueldo Neto (sueldos_netos). -->
<template>
  <div class="sn-view">
    <div class="sn-cab">
      <div class="sn-cab-ico">💱</div>
      <div class="sn-cab-tx"><h1>Exportar / Importar Sueldo Neto</h1><p>Actualizá o descargá el neto de los empleados</p></div>
      <button class="sn-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="sn-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <SueldosNetosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/sueldos-netos" titulo="Asistente IA — Sueldo Neto"
            subtitulo="Preguntá cómo importar o exportar el neto"
            :sugerencias="['¿Qué columnas necesita el Excel?','¿Qué pasa si un código no existe?','¿Cómo exporto?']"
            @close="modalIA = false" />

    <div class="sn-tabs">
      <button :class="['sn-tab', { on: tab === 'imp' }]" @click="tab = 'imp'">Importar Sueldo Neto</button>
      <button :class="['sn-tab', { on: tab === 'exp' }]" @click="tab = 'exp'">Exportar Sueldo Neto</button>
    </div>

    <!-- IMPORTAR -->
    <div v-show="tab === 'imp'" class="sn-card">
      <div class="sn-toolbar">
        <label class="sn-btn-archivo">📂 Buscar archivo<input type="file" accept=".xlsx,.xls,.csv" @change="abrirArchivo" hidden /></label>
        <span v-if="nombreArchivo" class="sn-archivo">{{ nombreArchivo }}</span>
        <button v-if="filas.length" class="sn-btn-reset" @click="reset">↺ Reset</button>
      </div>
      <div v-if="filas.length" class="sn-mapeo">
        <span class="sn-mtit">Identificá el contenido de las columnas:</span>
        <label>Código empleado<select v-model.number="map.codigo"><option :value="-1">—</option><option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option></select></label>
        <label>Sueldo neto<select v-model.number="map.sueldo"><option :value="-1">—</option><option v-for="c in columnas" :key="c.idx" :value="c.idx">{{ c.letra }}{{ muestra(c.idx) }}</option></select></label>
      </div>
      <div v-if="filas.length" class="sn-grid-wrap">
        <table class="sn-tabla">
          <thead><tr><th class="sn-fija">FILA</th><th class="sn-fija">OK</th><th v-for="c in columnas" :key="c.idx" :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ c.letra }}</th></tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ off: !f.ok }">
              <td class="sn-fija">{{ i + 1 }}</td><td class="sn-fija"><input v-model="f.ok" type="checkbox" /></td>
              <td v-for="c in columnas" :key="c.idx" :class="{ mapeada: columnasMapeadas.has(c.idx) }">{{ f.celdas[c.idx] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="filas.length" class="sn-pie">
        <span class="sn-total">Total a importar: <b>{{ totalOk }}</b></span>
        <button class="sn-btn-ok" :disabled="procesando || !puede" @click="proceder">{{ procesando ? '⟳ Importando…' : 'PROCEDER CON LA IMPORTACIÓN' }}</button>
      </div>
    </div>

    <!-- EXPORTAR -->
    <div v-show="tab === 'exp'" class="sn-card">
      <div class="sn-filtros">
        <div class="sn-f"><span class="sn-lbl">Empresa</span><select v-model.number="ef.empresa" class="sn-sel"><option :value="0">Todas</option><option v-for="o in cat.empresas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="sn-f"><span class="sn-lbl">Contratista</span><select v-model.number="ef.contratista" class="sn-sel"><option :value="0">Todas</option><option v-for="o in cat.contratistas" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="sn-f"><span class="sn-lbl">Lugar</span><select v-model.number="ef.lugar" class="sn-sel"><option :value="0">Todos</option><option v-for="o in cat.lugares" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="sn-f"><span class="sn-lbl">Convenio</span><select v-model.number="ef.convenio" class="sn-sel"><option :value="0">Todos</option><option v-for="o in cat.convenios" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="sn-f"><span class="sn-lbl">Categoría</span><select v-model.number="ef.categoria" class="sn-sel"><option :value="0">Todas</option><option v-for="o in cat.categorias" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="sn-f"><span class="sn-lbl">Sector</span><select v-model.number="ef.sector" class="sn-sel"><option :value="0">Todos</option><option v-for="o in cat.sectores" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
        <div class="sn-f"><span class="sn-lbl">Subsector</span><select v-model.number="ef.subsector" class="sn-sel"><option :value="0">Todos</option><option v-for="o in cat.subsectores" :key="o.cod" :value="o.cod">{{ o.nombre }}</option></select></div>
      </div>
      <button class="sn-btn-exp" :disabled="exportando" @click="exportar">📊 {{ exportando ? 'Generando…' : 'EXPORTAR SUELDO NETO A EXCEL' }}</button>
    </div>

    <p v-if="msg" :class="['sn-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import * as XLSX from 'xlsx'
import api from '@/services/auth'
import SueldosNetosAyuda from '@/components/SueldosNetosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)
const tab = ref<'imp' | 'exp'>('imp')
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t) setTimeout(() => msg.value = '', 6000) }

// Importar
const nombreArchivo = ref('')
const columnas = ref<{ idx: number; letra: string }[]>([])
const filas = reactive<{ ok: boolean; celdas: any[] }[]>([])
const map = reactive<{ codigo: number; sueldo: number }>({ codigo: -1, sueldo: -1 })
const procesando = ref(false)

const abrirArchivo = async (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  nombreArchivo.value = file.name; flash('')
  try {
    const buf = await file.arrayBuffer(); const wb = XLSX.read(buf, { type: 'array' })
    const ws = wb.SheetNames[0] ? wb.Sheets[wb.SheetNames[0]] : undefined
    if (!ws) { flash('No se pudo leer la hoja.', true); return }
    const datos: any[][] = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' })
    const maxCols = datos.reduce((m, r) => Math.max(m, r.length), 0)
    columnas.value = Array.from({ length: maxCols }, (_, i) => ({ idx: i, letra: XLSX.utils.encode_col(i) }))
    filas.splice(0, filas.length, ...datos.map(r => ({ ok: true, celdas: Array.from({ length: maxCols }, (_, i) => r[i] ?? '') })))
    map.codigo = -1; map.sueldo = -1
  } catch (err) { console.error(err); flash('No se pudo leer el archivo.', true) }
  finally { (e.target as HTMLInputElement).value = '' }
}
const muestra = (idx: number) => { const v = filas.find(f => String(f.celdas[idx] ?? '').trim() !== '')?.celdas[idx]; return v != null && String(v).trim() !== '' ? `  ·  ej: ${String(v).slice(0, 14)}` : '' }
const columnasMapeadas = computed(() => { const s = new Set<number>(); if (map.codigo >= 0) s.add(map.codigo); if (map.sueldo >= 0) s.add(map.sueldo); return s })
const totalOk = computed(() => filas.filter(f => f.ok).length)
const puede = computed(() => map.codigo >= 0 && map.sueldo >= 0 && totalOk.value > 0)
const cel = (f: { celdas: any[] }, idx: number) => idx >= 0 ? String(f.celdas[idx] ?? '').trim() : ''

const proceder = async () => {
  if (!confirm('¿Procede con la actualización de sueldos netos?')) return
  procesando.value = true
  try {
    const rows = filas.filter(f => f.ok).map(f => ({ codigo: cel(f, map.codigo), sueldo: cel(f, map.sueldo) }))
    const { data } = await api.post('/sueldos-netos/importar', { rows })
    flash(`Importación OK. Actualizados: ${data.actualizados}. Códigos no encontrados: ${data.no_encontrados}.`)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo importar.', true) }
  finally { procesando.value = false }
}
const reset = () => { filas.splice(0, filas.length); columnas.value = []; nombreArchivo.value = ''; map.codigo = -1; map.sueldo = -1; flash('') }

// Exportar
const ef = reactive({ empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, sector: 0, subsector: 0 })
const cat = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], sectores: [], subsectores: [] })
const exportando = ref(false)
const exportar = async () => {
  exportando.value = true
  try {
    const data = (await api.get('/sueldos-netos/exportar', { params: ef })).data
    if (!data.length) { flash('No hay empleados para los filtros.', true); return }
    const fil = data.map((r: any) => `<tr><td>${r.codigo}</td><td>${esc(r.nombre)}</td><td>${r.neto}</td></tr>`).join('')
    const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr style="background:#1b4332;color:#fff;font-weight:bold"><th>CODIGO</th><th>NOMBRE</th><th>NETO</th></tr>${fil}</table></body></html>`
    await guardarComo(new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' }), 'SUELDOS_NETOS.xls')
    flash(`Exportados ${data.length} empleados.`)
  } catch (e) { console.error(e); flash('No se pudo exportar.', true) }
  finally { exportando.value = false }
}
const esc = (s: string) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const mapCat = (arr: any[]) => arr.map((o: any) => ({ cod: o.cod ?? o.COD ?? o.id, nombre: (o.nombre ?? o.desc ?? o.det ?? o.descripcion ?? '').toString().trim() }))

onMounted(async () => {
  const f = async (u: string) => { try { return (await api.get(u)).data } catch { return [] } }
  cat.empresas = mapCat(await f('/empresas')); cat.contratistas = mapCat(await f('/contratistas')); cat.lugares = mapCat(await f('/lugares'))
  cat.convenios = mapCat(await f('/convenios')); cat.categorias = mapCat(await f('/categorias')); cat.sectores = mapCat(await f('/sectores')); cat.subsectores = mapCat(await f('/subsectores'))
})
</script>

<style scoped>
.sn-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.sn-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sn-cab-ico { font-size:28px; } .sn-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sn-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sn-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sn-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sn-tabs { display:flex; gap:4px; border-bottom:2px solid #e2e8f0; padding:0 18px; background:#f8fafc; }
.sn-tab { background:transparent; border:none; padding:11px 18px; font-size:13px; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; }
.sn-tab.on { color:#1b4332; border-bottom-color:#1b4332; }
.sn-card { padding:16px 18px; }
.sn-toolbar { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.sn-btn-archivo { background:#1b4332; color:#fff; padding:8px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sn-archivo { font-size:13px; color:#475569; } .sn-btn-reset { background:#fff; border:1px solid #cbd5e1; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; }
.sn-mapeo { display:flex; flex-wrap:wrap; gap:14px; align-items:center; padding:12px 14px; background:#f0faf4; border:1px solid #c3e6cb; border-radius:8px; margin-bottom:14px; }
.sn-mtit { font-size:13px; font-weight:700; color:#1b4332; width:100%; }
.sn-mapeo label { font-size:12px; font-weight:600; color:#374151; display:flex; flex-direction:column; gap:4px; }
.sn-mapeo select { border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:12px; min-width:160px; }
.sn-grid-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:48vh; }
.sn-tabla { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.sn-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 10px; font-weight:600; } .sn-tabla th.mapeada { background:#b45309; }
.sn-tabla td { padding:4px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .sn-tabla td.mapeada { background:#fef9c3; } .sn-tabla tr.off td { opacity:.4; }
.sn-fija { background:#f8fafc; position:sticky; left:0; }
.sn-pie { display:flex; align-items:center; gap:18px; margin-top:14px; }
.sn-total { font-size:14px; color:#374151; } .sn-total b { color:#1b4332; font-size:16px; }
.sn-btn-ok { margin-left:auto; background:#16a34a; color:#fff; border:none; padding:10px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .sn-btn-ok:disabled { background:#cbd5e1; }
.sn-filtros { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
.sn-f { display:flex; flex-direction:column; gap:4px; } .sn-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.sn-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; min-width:170px; }
.sn-btn-exp { background:#16a34a; color:#fff; border:none; padding:11px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; } .sn-btn-exp:disabled { background:#cbd5e1; }
.sn-msg { padding:9px 14px; margin:0 18px 14px; font-size:13px; border-radius:6px; } .sn-msg.ok { background:#dcfce7; color:#166534; } .sn-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
