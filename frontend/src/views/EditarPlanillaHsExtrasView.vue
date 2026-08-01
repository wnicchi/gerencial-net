<!-- EditarPlanillaHsExtrasView.vue — Editar Planillas de Horas Extras. -->
<template>
  <div class="ed-view">
    <div class="ed-cab">
      <div class="ed-cab-ico">✏️</div>
      <div class="ed-cab-tx"><h1>Editar Planilla de Horas Extras</h1><p>Buscá una planilla y ajustá horas y valores</p></div>
      <button class="ed-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ed-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <CrearPlanillaHsExtrasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/planillas-hs-extras" titulo="Asistente IA — Planillas de Horas Extras"
            subtitulo="Preguntá sobre la edición de planillas"
            :sugerencias="['¿Cómo busco una planilla?','¿Qué pasa si cambio las horas?','¿Cómo se recalcula el total?','¿Cómo guardo los cambios?']"
            @close="modalIA = false" />

    <!-- Buscador -->
    <div class="ed-barra">
      <label>Número Planilla
        <span class="ed-plan">
          <input v-model.number="nro" type="number" min="1" @keyup.enter="aceptar" />
          <button class="ed-btn-buscar" title="Buscar planilla" @click="abrirBuscador">🔍</button>
        </span>
      </label>
      <button class="ed-btn-aceptar" :disabled="cargando || !nro" @click="aceptar">{{ cargando ? '⟳…' : 'ACEPTAR' }}</button>
      <div v-if="nombre" class="ed-info"><b>{{ nombre }}</b> · {{ creada }} · del {{ fecha1 }} al {{ fecha2 }}</div>
    </div>

    <!-- Grilla editable -->
    <div v-if="rows.length" class="ed-grid-wrap">
      <table class="ed-grid">
        <thead>
          <tr><th v-for="c in cols" :key="c.k" :class="{ ed: c.edit }">{{ c.t }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="r.unico">
            <td v-for="c in cols" :key="c.k" :class="celdaClase(c, r, i)">
              <input v-if="c.edit" class="ed-in" type="number" step="0.01" :value="r[c.k]" @change="setNum(r, c.k, $event)" />
              <span v-else>{{ c.txt ? r[c.k] : fmt(r[c.k]) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="rows.length" class="ed-acc">
      <span class="ed-leyenda"><i></i> valor modificado</span>
      <button class="ed-btn-excel" @click="exportar">📊 GENERAR EXCEL</button>
      <button class="ed-btn-guardar" :disabled="guardando" @click="guardar">{{ guardando ? '⟳ Guardando…' : '💾 GUARDAR CAMBIOS' }}</button>
    </div>

    <!-- Buscador de planillas -->
    <Teleport to="body">
      <div v-if="buscador" class="ed-ov" @click.self="buscador = false">
        <div class="ed-busc">
          <div class="ed-busc-cab"><h3>🔍 Buscar Planillas de Horas Extras</h3><button class="ed-busc-x" @click="buscador = false">✕</button></div>
          <div class="ed-busc-body">
            <table class="ed-busc-tabla">
              <thead><tr><th style="width:70px">Nro.</th><th>Detalle</th><th style="width:110px">Fecha</th></tr></thead>
              <tbody>
                <tr v-for="p in planillas" :key="p.numero" @click="nro = p.numero" :class="{ sel: p.numero === nro }" @dblclick="elegir(p.numero)">
                  <td class="ed-num">{{ p.numero }}</td><td>{{ p.detalle }}</td><td>{{ p.fecha }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="ed-busc-pie"><button class="ed-btn-aceptar" :disabled="!nro" @click="elegir(nro!)">ACEPTAR</button><button class="ed-busc-cerrar" @click="buscador = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import CrearPlanillaHsExtrasAyuda from '@/components/CrearPlanillaHsExtrasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)

type Row = Record<string, any>
interface Col { k: string; t: string; edit?: boolean; calc?: boolean; txt?: boolean }
const cols: Col[] = [
  { k: 'legajo', t: 'Legajo', txt: true }, { k: 'sector', t: 'Sector', txt: true }, { k: 'nombre', t: 'Empleado', txt: true },
  { k: 'hs_50', t: '50%', edit: true }, { k: 'hs_100', t: '100%', edit: true }, { k: 'hs_noc', t: 'Noct.', edit: true },
  { k: 'hs_noc50', t: 'Noct.50%', edit: true }, { k: 'hs_dia30', t: 'Día 30%', edit: true },
  { k: 'valor_nor', t: 'Valor Hr.', edit: true }, { k: 'valor_extra50', t: 'Valor 50%', edit: true }, { k: 'valor_extra100', t: 'Valor 100%', edit: true },
  { k: 'valor_dia30', t: 'V.día 30%', edit: true }, { k: 'valor_noc', t: 'V. Noct.', edit: true }, { k: 'valor_noc50', t: 'V.Noct.50%', edit: true },
  { k: 'bruto_extra50', t: 'Total 50%', calc: true }, { k: 'bruto_extra100', t: 'Total 100%', calc: true }, { k: 'bruto_noc', t: 'Total Noct.', calc: true },
  { k: 'bruto_noc50', t: 'Total Noct.50%', calc: true }, { k: 'bruto_dia30', t: 'Total Día 30%', calc: true }, { k: 'total', t: 'Total', calc: true },
]

const nro = ref<number | null>(null)
const rows = ref<Row[]>([]); const orig = ref<Row[]>([])
const nombre = ref(''); const creada = ref(''); const fecha1 = ref(''); const fecha2 = ref('')
const cargando = ref(false); const guardando = ref(false)

const nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmt = (v: any) => nf.format(Number(v ?? 0))
const r2 = (n: number) => Math.round(n * 100) / 100

const recalcular = (r: Row) => {
  r.bruto_extra50 = r2(Number(r.hs_50) * Number(r.valor_extra50))
  r.bruto_extra100 = r2(Number(r.hs_100) * Number(r.valor_extra100))
  r.bruto_noc = r2(Number(r.hs_noc) * Number(r.valor_noc))
  r.bruto_noc50 = r2(Number(r.hs_noc50) * Number(r.valor_noc50))
  r.bruto_dia30 = r2(Number(r.hs_dia30) * Number(r.valor_dia30))
  r.total = r2(r.bruto_extra50 + r.bruto_extra100 + r.bruto_noc + r.bruto_noc50 + r.bruto_dia30)
}
const setNum = (r: Row, k: string, ev: Event) => {
  r[k] = Number((ev.target as HTMLInputElement).value) || 0
  recalcular(r)
}
const celdaClase = (c: Col, r: Row, i: number) => {
  const cls: string[] = []
  if (c.edit) cls.push('ed-cell')
  if ((c.edit || c.calc) && orig.value[i] && Number(r[c.k]) !== Number(orig.value[i][c.k])) cls.push('ed-chg')
  return cls
}

const aceptar = async () => {
  if (!nro.value) return
  cargando.value = true
  try {
    const { data } = await api.get(`/planillas-hs-extras/${nro.value}`)
    nombre.value = data.nombre; creada.value = data.creada; fecha1.value = data.fecha1; fecha2.value = data.fecha2
    rows.value = data.rows
    orig.value = data.rows.map((r: Row) => ({ ...r }))
  } catch (e: any) {
    rows.value = []; nombre.value = ''
    alert('⚠️ ' + (e?.response?.data?.message ?? 'No se pudo cargar la planilla.'))
  } finally { cargando.value = false }
}

const guardar = async () => {
  if (!confirm('¿Guarda los cambios realizados de horas extras?')) return
  guardando.value = true
  try {
    const { data } = await api.post('/planillas-hs-extras/guardar', { rows: rows.value })
    orig.value = rows.value.map(r => ({ ...r }))
    alert(`✔ Planilla actualizada (${data.actualizados} registros).`)
  } catch (e: any) { alert('⚠️ ' + (e?.response?.data?.message ?? 'No se pudo guardar.')) }
  finally { guardando.value = false }
}

const exportar = async () => {
  const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const th = cols.map(c => `<th>${esc(c.t)}</th>`).join('')
  const trs = rows.value.map(r => '<tr>' + cols.map(c => `<td>${c.txt ? esc(r[c.k]) : Number(r[c.k] ?? 0)}</td>`).join('') + '</tr>').join('')
  const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>${th}</tr></thead><tbody>${trs}</tbody></table></body></html>`
  await guardarComo(new Blob([html], { type: 'application/vnd.ms-excel' }), `PLANILLA_HS_EXTRAS_${nro.value}.xls`)
}

// Buscador de planillas
const buscador = ref(false); const planillas = ref<any[]>([])
const abrirBuscador = async () => {
  buscador.value = true
  if (planillas.value.length) return
  try { planillas.value = (await api.get('/novedades/planillas-hs-extras')).data } catch (e) { console.error(e) }
}
const elegir = (n: number) => { nro.value = n; buscador.value = false; aceptar() }
</script>

<style scoped>
.ed-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ed-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ed-cab-ico { font-size:28px; } .ed-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ed-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ed-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ed-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ed-barra { display:flex; flex-wrap:wrap; align-items:end; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.ed-barra label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.ed-plan { display:flex; align-items:center; gap:6px; }
.ed-plan input { width:90px; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.ed-btn-buscar { background:#fff; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; cursor:pointer; font-size:14px; }
.ed-btn-buscar:hover { background:#f0faf4; border-color:#40916c; }
.ed-btn-aceptar { background:#16a34a; color:#fff; border:none; padding:9px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ed-btn-aceptar:hover:not(:disabled){ background:#15803d; } .ed-btn-aceptar:disabled { background:#cbd5e1; }
.ed-info { font-size:13px; color:#1b4332; background:#fff7ae; padding:7px 12px; border-radius:6px; align-self:center; }
.ed-grid-wrap { margin:14px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; max-height:62vh; }
.ed-grid { border-collapse:collapse; font-size:12px; white-space:nowrap; }
.ed-grid th { position:sticky; top:0; background:#0f172a; color:#fff; font-weight:600; padding:6px 8px; font-size:11px; }
.ed-grid th.ed { background:#1b4332; }
.ed-grid td { padding:2px 8px; border-bottom:1px solid #e5e7eb; border-right:1px solid #f1f5f9; color:#1e293b; background:#eef0f2; text-align:right; }
.ed-cell { background:#fff !important; }
.ed-chg { background:#fff200 !important; }
.ed-in { width:72px; border:1px solid #d1d5db; border-radius:4px; padding:3px 5px; font-size:12px; text-align:right; outline:none; }
.ed-in:focus { border-color:#40916c; box-shadow:0 0 0 2px rgba(64,145,108,.2); }
.ed-grid td:nth-child(-n+3) { text-align:left; }
.ed-acc { display:flex; align-items:center; gap:14px; padding:0 18px 18px; }
.ed-leyenda { font-size:12px; color:#6b7280; display:flex; align-items:center; } .ed-leyenda i { width:13px; height:13px; background:#fff200; border:1px solid #cbd5e1; border-radius:3px; margin-right:5px; }
.ed-btn-excel { margin-left:auto; background:#1d6f42; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ed-btn-guardar { background:#2563eb; color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.ed-btn-guardar:hover:not(:disabled){ background:#1d4ed8; } .ed-btn-guardar:disabled { background:#cbd5e1; }
.ed-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ed-busc { width:min(560px,96vw); max-height:88vh; display:flex; flex-direction:column; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); }
.ed-busc-cab { display:flex; align-items:center; padding:12px 16px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; } .ed-busc-cab h3 { margin:0; font-size:15px; }
.ed-busc-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:26px; height:26px; border-radius:7px; cursor:pointer; }
.ed-busc-body { overflow:auto; } .ed-busc-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ed-busc-tabla th { position:sticky; top:0; background:#c0dcc0; color:#14532d; font-weight:700; text-align:left; padding:7px 12px; }
.ed-busc-tabla td { padding:6px 12px; border-bottom:1px solid #eef2f7; cursor:pointer; color:#1e293b; }
.ed-busc-tabla tbody tr td { background:#ffffff; }
.ed-busc-tabla tbody tr:nth-child(even) td { background:#eef6ee; }
.ed-busc-tabla tbody tr:hover td { background:#dcefdc; color:#0f172a; } .ed-busc-tabla tr.sel td { background:#1b4332 !important; color:#fff !important; }
.ed-num { text-align:center; font-family:monospace; font-weight:700; color:#14532d; }
.ed-busc-tabla tr.sel .ed-num { color:#fff; }
.ed-busc-pie { display:flex; justify-content:center; gap:10px; padding:12px; border-top:1px solid #e5e7eb; }
.ed-busc-cerrar { background:#ef4444; color:#fff; border:none; padding:9px 18px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
</style>
