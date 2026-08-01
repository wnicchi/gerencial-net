<!-- CrearPlanillaHsExtrasView.vue — Crear Planillas de Horas Extras. -->
<template>
  <div class="pe-view">
    <div class="pe-cab">
      <div class="pe-cab-ico">📋</div>
      <div class="pe-cab-tx"><h1>Crear Planilla de Horas Extras</h1><p>Elegí empleados y período para totalizar y crear la planilla</p></div>
      <button class="pe-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="pe-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <CrearPlanillaHsExtrasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/planillas-hs-extras" titulo="Asistente IA — Planillas de Horas Extras"
            subtitulo="Preguntá sobre la creación de planillas"
            :sugerencias="['¿Cómo armo una planilla?','¿Qué empleados aparecen?','¿Cómo se calcula el total?','¿Qué es el día 30%?']"
            @close="modalIA = false" />

    <!-- Filtros -->
    <div class="pe-card">
      <div class="pe-grid">
        <div class="pe-campo"><label>Empresa</label><select v-model.number="f.empresa"><option :value="0">Todas</option><option v-for="o in op.empresas" :key="o.EMP_COD" :value="Number(o.EMP_COD)">{{ o.EMP_NOM }}</option></select></div>
        <div class="pe-campo"><label>Contratista</label><select v-model.number="f.contratista"><option :value="0">Todos</option><option v-for="o in op.contratistas" :key="o.CONT_COD" :value="Number(o.CONT_COD)">{{ o.CONT_DET }}</option></select></div>
        <div class="pe-campo"><label>Lugar</label><select v-model.number="f.lugar"><option :value="0">Todos</option><option v-for="o in op.lugares" :key="o.LUG_COD" :value="Number(o.LUG_COD)">{{ o.LUG_NOM }}</option></select></div>
        <div class="pe-campo"><label>Convenio</label><select v-model.number="f.convenio"><option :value="0">Todos</option><option v-for="o in op.convenios" :key="o.CON_COD" :value="Number(o.CON_COD)">{{ o.CON_DES }}</option></select></div>
        <div class="pe-campo"><label>Categoría</label><select v-model.number="f.categoria"><option :value="0">Todas</option><option v-for="o in op.categorias" :key="o.CAT_COD" :value="Number(o.CAT_COD)">{{ o.CAT_DES }}</option></select></div>
        <div class="pe-campo"><label>Sector</label><select v-model.number="f.sector"><option :value="0">Todos</option><option v-for="o in op.sectores" :key="o.SEC_COD" :value="Number(o.SEC_COD)">{{ o.SEC_DES }}</option></select></div>
        <div class="pe-campo"><label>Sub-Sector</label><select v-model.number="f.subsector"><option :value="0">Todos</option><option v-for="o in op.subsectores" :key="o.sub_cod" :value="Number(o.sub_cod)">{{ o.sub_des }}</option></select></div>
        <div class="pe-campo"><label>Clasificados por</label><select v-model.number="f.orden"><option :value="1">Alfabeto</option><option :value="2">Legajo</option><option :value="3">F. Ingreso</option><option :value="4">F. Nacimiento</option></select></div>
      </div>
      <button class="pe-btn-traer" :disabled="cargando" @click="traer">{{ cargando ? '⟳ Generando…' : 'TRAER EMPLEADOS SELECCIONADOS' }}</button>
    </div>

    <!-- Lista de empleados -->
    <div v-if="empleados.length" class="pe-lista-wrap">
      <div class="pe-lista-acc">
        <button class="pe-chip" @click="marcar(true)">todos</button>
        <button class="pe-chip" @click="marcar(false)">nada</button>
        <span class="pe-cont">{{ seleccionados.length }} de {{ empleados.length }} seleccionados</span>
      </div>
      <div class="pe-tabla-wrap">
        <table class="pe-tabla">
          <thead><tr><th style="width:40px">Sel</th><th>Legajo</th><th>Nombre</th><th>T.Doc</th><th>Nº Doc.</th><th>Cuil</th><th>Domicilio</th></tr></thead>
          <tbody>
            <tr v-for="e in empleados" :key="e.cod" :class="{ on: e.es_ok }">
              <td style="text-align:center"><input v-model="e.es_ok" type="checkbox" /></td>
              <td class="pe-num">{{ e.legajo }}</td><td>{{ e.nombre }}</td><td>{{ e.tipo_doc }}</td>
              <td class="pe-num">{{ e.nro_doc }}</td><td class="pe-num">{{ e.cuil }}</td><td>{{ e.domicilio }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pe-pie">
        <label>Desde <input v-model="f.fecha1" type="date" /></label>
        <label>al <input v-model="f.fecha2" type="date" /></label>
        <label class="pe-nom">Nombre de la Planilla <input v-model="f.nombre" type="text" maxlength="60" placeholder="Ej: EXTRAS MAYO 2026" /></label>
        <button class="pe-btn-generar" :disabled="generando || !seleccionados.length" @click="generarExcel">
          {{ generando ? '⟳ Procesando…' : '📊 GENERAR EXCEL CON HORAS TRABAJADAS' }}
        </button>
      </div>
      <p v-if="msg" :class="['pe-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
    <div v-else-if="!cargando && consultado" class="pe-vacio">No se encuentran empleados para el filtro seleccionado.</div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue'
import api from '@/services/auth'
import CrearPlanillaHsExtrasAyuda from '@/components/CrearPlanillaHsExtrasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)

interface Emp { es_ok: boolean; cod: number; legajo: string; nombre: string; tipo_doc: string; nro_doc: string; cuil: string; domicilio: string }
const op = reactive<any>({ empresas: [], contratistas: [], lugares: [], convenios: [], categorias: [], sectores: [], subsectores: [] })
const hoy = new Date().toISOString().slice(0, 10)
const f = ref({ empresa: 0, contratista: 0, lugar: 0, convenio: 0, categoria: 0, sector: 0, subsector: 0, orden: 1, fecha1: hoy, fecha2: hoy, nombre: '' })

const empleados = ref<Emp[]>([])
const cargando = ref(false); const consultado = ref(false); const generando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

const seleccionados = computed(() => empleados.value.filter(e => e.es_ok))
const marcar = (v: boolean) => empleados.value.forEach(e => e.es_ok = v)

const traer = async () => {
  cargando.value = true; consultado.value = true
  try {
    const params: any = { orden: f.value.orden }
    for (const k of ['empresa', 'contratista', 'lugar', 'convenio', 'categoria', 'sector', 'subsector'] as const) {
      if (f.value[k]) params[k] = f.value[k]
    }
    empleados.value = (await api.get('/planillas-hs-extras/empleados', { params })).data
  } catch (e) { console.error(e); empleados.value = [] }
  finally { cargando.value = false }
}

const generarExcel = async () => {
  if (!f.value.nombre.trim()) { flash('Debe ingresar un nombre de planilla.', true); return }
  generando.value = true
  try {
    const codigos = seleccionados.value.map(e => e.cod)
    const { data } = await api.post('/planillas-hs-extras/generar', { codigos, fecha1: f.value.fecha1, fecha2: f.value.fecha2 })
    const rows = data.rows as any[]
    if (!rows.length) { flash('No hay horas trabajadas para el período seleccionado.', true); return }
    await exportar(rows)
    if (confirm('¿Genera una nueva planilla de horas extras con estos datos?')) {
      const res = await api.post('/planillas-hs-extras/crear', { codigos, fecha1: f.value.fecha1, fecha2: f.value.fecha2, nombre: f.value.nombre })
      flash(`Planilla N° ${res.data.numero} creada (${res.data.creados} empleados).`)
    }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo generar.', true) }
  finally { generando.value = false }
}

const exportar = async (rows: any[]) => {
  const cols: { t: string; k: string }[] = [
    { t: 'SECTOR', k: 'sector' }, { t: 'LEGAJO', k: 'cod' }, { t: 'EMPLEADO', k: 'nombre' },
    { t: 'HS_50', k: 'hs_50' }, { t: 'HS_100', k: 'hs_100' }, { t: 'HS_NOC', k: 'hs_noc' }, { t: 'HS_NOC50', k: 'hs_noc50' }, { t: 'DIA_30', k: 'hs_dia30' },
    { t: 'VALOR_NOR', k: 'valor_nor' }, { t: 'VALOR_EXTRA50', k: 'valor_extra50' }, { t: 'VALOR_EXTRA100', k: 'valor_extra100' },
    { t: 'VALOR_DIA30', k: 'valor_dia30' }, { t: 'VALOR_NOC', k: 'valor_noc' }, { t: 'VALOR_NOC50', k: 'valor_noc50' },
    { t: 'BRUTO_EXTRA50', k: 'bruto_extra50' }, { t: 'BRUTO_EXTRA100', k: 'bruto_extra100' }, { t: 'BRUTO_NOC', k: 'bruto_noc' },
    { t: 'BRUTO_NOC50', k: 'bruto_noc50' }, { t: 'BRUTO_DIA30', k: 'bruto_dia30' }, { t: 'TOTAL', k: 'total' },
  ]
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const th = cols.map(c => `<th>${esc(c.t)}</th>`).join('')
  const trs = rows.map(r => '<tr>' + cols.map(c => {
    const txt = c.k === 'sector' || c.k === 'nombre'
    return `<td>${txt ? esc(r[c.k]) : Number(r[c.k] ?? 0)}</td>`
  }).join('') + '</tr>').join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>${th}</tr></thead><tbody>${trs}</tbody></table></body></html>`
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), 'PLANILLA_HS_EXTRAS.xls')
}

onMounted(async () => {
  try {
    const { data } = await api.get('/empleados/opciones')
    Object.assign(op, { empresas: data.empresas ?? [], contratistas: data.contratistas ?? [], lugares: data.lugares ?? [], convenios: data.convenios ?? [], categorias: data.categorias ?? [], sectores: data.sectores ?? [], subsectores: data.subsectores ?? [] })
  } catch (e) { console.error(e) }
})
</script>

<style scoped>
.pe-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.pe-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.pe-cab-ico { font-size:28px; } .pe-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .pe-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.pe-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.pe-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.pe-card { margin:14px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; }
.pe-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
.pe-campo { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.pe-campo select { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.pe-campo select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.pe-btn-traer { margin-top:14px; background:#16a34a; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.pe-btn-traer:hover:not(:disabled){ background:#15803d; } .pe-btn-traer:disabled { background:#cbd5e1; }
.pe-lista-wrap { margin:0 18px 18px; }
.pe-lista-acc { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.pe-chip { background:#fff; border:1px solid #cbd5e1; padding:5px 16px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:700; color:#374151; }
.pe-chip:hover { background:#f0faf4; border-color:#40916c; }
.pe-cont { font-size:12px; color:#6b7280; margin-left:6px; }
.pe-tabla-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:48vh; }
.pe-tabla { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.pe-tabla th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 12px; font-size:12px; }
.pe-tabla td { padding:5px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.pe-tabla tr.on td { background:#fff200; }
.pe-num { text-align:center; font-variant-numeric:tabular-nums; }
.pe-pie { display:flex; flex-wrap:wrap; align-items:end; gap:14px; margin-top:14px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; }
.pe-pie label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.pe-pie input { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.pe-nom { flex:1; min-width:220px; }
.pe-btn-generar { background:#1d6f42; color:#fff; border:none; padding:11px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.pe-btn-generar:hover:not(:disabled){ background:#15803d; } .pe-btn-generar:disabled { background:#cbd5e1; }
.pe-msg { padding:8px 12px; margin:10px 0 0; font-size:13px; border-radius:6px; }
.pe-msg.ok { background:#dcfce7; color:#166534; } .pe-msg.err { background:#fee2e2; color:#b91c1c; }
.pe-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
</style>
