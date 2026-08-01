<!-- ExportarNovedadesView.vue — Exportar Novedades confirmadas a Excel (selección de columnas). -->
<template>
  <div class="ex-view">
    <div class="ex-cab">
      <div class="ex-cab-ico">📤</div>
      <div class="ex-cab-tx"><h1>Exportar Novedades</h1><p>Novedades confirmadas y grabadas — elegí las columnas a exportar</p></div>
      <button class="ex-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ex-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <ExportarNovedadesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/exportar-novedades" titulo="Asistente IA — Exportar Novedades"
            subtitulo="Preguntá sobre la exportación de novedades a Excel"
            :sugerencias="['¿Qué exporta este módulo?','¿Cómo elijo las columnas?','¿Para qué sirve el índice y el orden?','¿Qué datos toma?']"
            @close="modalIA = false" />

    <div class="ex-cuerpo">
      <div class="ex-titulo">SELECCIONAR COLUMNAS A EXPORTAR</div>

      <div class="ex-grid-wrap">
        <table class="ex-grid">
          <thead><tr><th style="width:50px">Ok</th><th>Detalle</th><th style="width:70px">Índice</th><th style="width:80px">Orden</th></tr></thead>
          <tbody>
            <tr v-for="col in columnas" :key="col.campo" :class="{ on: col.ok }">
              <td style="text-align:center"><input v-model="col.ok" type="checkbox" /></td>
              <td>{{ col.detalle }}</td>
              <td style="text-align:center"><input v-model="col.indice" type="checkbox" /></td>
              <td style="text-align:center"><input v-if="col.indice" v-model.number="col.orden" type="number" min="1" class="ex-orden" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="ex-acciones">
        <button class="ex-btn-chip" @click="marcar(true)">TODO</button>
        <button class="ex-btn-chip" @click="marcar(false)">NADA</button>
      </div>

      <div class="ex-filtros">
        <label>Empresa
          <select v-model.number="empresa">
            <option v-for="e in empresas" :key="e.cod" :value="Number(e.cod)">{{ e.nombre }}</option>
          </select>
        </label>
        <label>Mes <input v-model.number="mes" type="number" min="1" max="12" /></label>
        <label>Año <input v-model.number="anio" type="number" min="2000" max="2999" /></label>
      </div>

      <button class="ex-btn-excel" :disabled="exportando" @click="exportar">
        {{ exportando ? '⟳ Exportando…' : '📊 EXPORTAR A EXCEL' }}
      </button>
      <p v-if="msg" :class="['ex-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import ExportarNovedadesAyuda from '@/components/ExportarNovedadesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'
import { guardarComo } from '@/utils/descargas'

const modalAyuda = ref(false); const modalIA = ref(false)

interface Col { ok: boolean; detalle: string; campo: string; num: boolean; indice: boolean; orden: number }
// Mismo armado de columnas que el FoxPro (sistema SILCAR: se muestran todas)
const columnas = ref<Col[]>([
  { detalle: 'CODIGO',           campo: 'codigo',      num: true },
  { detalle: 'PERSONAL',         campo: 'nombre',      num: false },
  { detalle: 'LEGAJO',           campo: 'legajo',      num: true },
  { detalle: 'CUIL',             campo: 'cuil',        num: false },
  { detalle: 'CONVENIO',         campo: 'convenio',    num: false },
  { detalle: 'BASICO',           campo: 'basico',      num: true },
  { detalle: 'NO REMUNERATIVO',  campo: 'noremu',      num: true },
  { detalle: 'DIAS DE_MES',      campo: 'm_dmes',      num: true },
  { detalle: 'DIAS TRABAJOS',    campo: 'm_dtr',       num: true },
  { detalle: 'COMISION VENTAS',  campo: 'm_cve',       num: true },
  { detalle: 'COMISION COBROS',  campo: 'm_cco',       num: true },
  { detalle: 'ADELANTOS',        campo: 'm_ade',       num: true },
  { detalle: 'ANTICIPOS',        campo: 'm_ant',       num: true },
  { detalle: 'ALMUERZOS',        campo: 'm_alm',       num: true },
  { detalle: 'PORC PRESENTISMO', campo: 'm_od1',       num: true },
  { detalle: 'NETO JUAN',        campo: 'm_nj1',       num: true },
  { detalle: 'ADICIONAL NETO',   campo: 'm_an1',       num: true },
  { detalle: 'DIAS VACACIONES',  campo: 'dias_vaca',   num: true },
  { detalle: 'DIAS ENFERMEDAD',  campo: 'dias_enfer',  num: true },
  { detalle: 'DIAS LICENCIA',    campo: 'dias_licen',  num: true },
  { detalle: 'HORAS 100',        campo: 'm_h100',      num: true },
  { detalle: 'HORAS 50',         campo: 'm_h050',      num: true },
  { detalle: 'HORAS NOCTURNAS',  campo: 'm_hnoc',      num: true },
  { detalle: 'ADEL VIAJE NETO',  campo: 'm_av_neto',   num: true },
  { detalle: 'NETO EXTRA',       campo: 'm_av_extra',  num: true },
  { detalle: 'TOTAL NETO',       campo: 'm_totalneto', num: true },
  { detalle: 'OBSERVACIONES',    campo: 'm_ob1',       num: false },
  { detalle: 'CONTRATISTA',      campo: 'contrata',    num: false },
  { detalle: 'BANCO A DEPOSITAR', campo: 'banco',      num: false },
].map((c, i) => ({ ...c, ok: true, indice: false, orden: i + 1 })))

interface Opcion { cod: string | number; nombre: string }
const empresas = ref<Opcion[]>([])
const hoy = new Date()
const empresa = ref(1); const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const exportando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

const marcar = (v: boolean) => columnas.value.forEach(c => c.ok = v)

const exportar = async () => {
  const sel = columnas.value.filter(c => c.ok)
  if (sel.length === 0) { flash('No ha seleccionado ningún campo.', true); return }
  exportando.value = true
  try {
    const { data } = await api.get('/novedades/exportar-datos', { params: { empresa: empresa.value, mes: mes.value, anio: anio.value } })
    let rows: Record<string, any>[] = data
    if (rows.length === 0) { flash('No hay novedades confirmadas para el período.', true); return }

    // Ordenar por las columnas marcadas como índice (según su orden)
    const indices = columnas.value.filter(c => c.indice).sort((a, b) => a.orden - b.orden)
    if (indices.length) {
      rows = [...rows].sort((ra, rb) => {
        for (const ix of indices) {
          const va = ra[ix.campo], vb = rb[ix.campo]
          let cmp = ix.num ? Number(va) - Number(vb) : String(va ?? '').localeCompare(String(vb ?? ''))
          if (cmp !== 0) return cmp
        }
        return 0
      })
    }

    // Armar Excel (header = detalle con espacios → guión bajo, igual que el FoxPro)
    const esc = (s: any) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const th = sel.map(c => `<th>${esc(c.detalle.replace(/ /g, '_'))}</th>`).join('')
    const trs = rows.map(r => '<tr>' + sel.map(c => `<td>${c.num ? Number(r[c.campo] ?? 0) : esc(r[c.campo])}</td>`).join('') + '</tr>').join('')
    const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head>`
      + `<body><table border="1"><thead><tr>${th}</tr></thead><tbody>${trs}</tbody></table></body></html>`
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' })
    const res = await guardarComo(blob, `NOVEDADES_${anio.value}_${String(mes.value).padStart(2, '0')}.xls`)
    if (res === 'ok') flash(`Exportadas ${rows.length} novedades.`)
  } catch (e) { console.error(e); flash('No se pudo exportar.', true) }
  finally { exportando.value = false }
}

onMounted(async () => {
  try { empresas.value = (await api.get('/empresas')).data } catch (e) { console.error(e) }
})
</script>

<style scoped>
.ex-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ex-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ex-cab-ico { font-size:28px; } .ex-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ex-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ex-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-btn-ia:hover { filter:brightness(1.1); }
.ex-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ex-btn-ayuda:hover { background:#f0faf4; }
.ex-cuerpo { padding:18px; max-width:560px; margin:0 auto; width:100%; }
.ex-titulo { text-align:center; font-size:13px; font-weight:700; color:#1b4332; margin-bottom:10px; }
.ex-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:46vh; }
.ex-grid { width:100%; border-collapse:collapse; font-size:13px; }
.ex-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; }
.ex-grid td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.ex-grid tr.on td { background:#fff7ae; }
.ex-orden { width:54px; border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:12px; text-align:center; }
.ex-acciones { display:flex; gap:8px; margin:10px 0; }
.ex-btn-chip { background:#fff; border:1px solid #cbd5e1; padding:6px 18px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:700; color:#374151; }
.ex-btn-chip:hover { background:#f0faf4; border-color:#40916c; }
.ex-filtros { display:flex; flex-wrap:wrap; align-items:end; gap:14px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:12px; }
.ex-filtros label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.ex-filtros input, .ex-filtros select { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.ex-filtros input[type=number] { width:90px; }
.ex-filtros input:focus, .ex-filtros select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.ex-btn-excel { display:block; width:100%; background:#1d6f42; color:#fff; border:none; padding:12px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:700; }
.ex-btn-excel:hover:not(:disabled){ background:#15803d; } .ex-btn-excel:disabled { background:#cbd5e1; }
.ex-msg { padding:8px 12px; margin:10px 0 0; font-size:13px; border-radius:6px; text-align:center; }
.ex-msg.ok { background:#dcfce7; color:#166534; } .ex-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
