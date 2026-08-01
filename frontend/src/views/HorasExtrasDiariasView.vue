<!-- HorasExtrasDiariasView.vue — Planilla de Horas Extras Diarias (y nocturnas). -->
<template>
  <div class="hx-view">
    <div class="hx-cab">
      <div class="hx-cab-ico">⏰</div>
      <div class="hx-cab-tx">
        <h1>Planilla de Horas Extras Diarias</h1>
        <p>Cálculo de horas extras y nocturnas por día</p>
      </div>
      <button class="hx-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="hx-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <HorasExtrasDiariasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/horas-extras-diarias" titulo="Asistente IA — Horas Extras Diarias"
            subtitulo="Preguntá sobre las horas extras del día"
            :sugerencias="['¿Cómo se calcula el tiempo extra?','¿Qué son las horas nocturnas?','¿Cómo cargo el 50% y el 100%?','¿Por qué algunos no aparecen?']"
            @close="modalIA = false" />

    <!-- Filtros -->
    <div class="hx-filtros">
      <label>Empresa
        <select v-model.number="f.empresa">
          <option v-for="e in empresas" :key="e.cod" :value="Number(e.cod)">{{ e.nombre }}</option>
        </select>
      </label>
      <label>Contratista
        <select v-model.number="f.contratista">
          <option :value="0">— Elegir —</option>
          <option v-for="c in contratistas" :key="c.cod" :value="Number(c.cod)">{{ c.nombre }}</option>
        </select>
      </label>
      <label>Día a editar <input v-model="f.fecha" type="date" /></label>
      <button class="hx-btn-aceptar" :disabled="cargando || !f.contratista" @click="aceptar">{{ cargando ? '⟳ Analizando…' : 'ACEPTAR' }}</button>
    </div>

    <p v-if="esViernes" class="hx-viernes">⚠️ ATENCIÓN! ES DÍA VIERNES</p>

    <!-- Grilla editable -->
    <div v-if="rows.length" class="hx-grid-wrap">
      <table class="hx-grid">
        <thead>
          <tr>
            <th>Código</th><th>Nombre</th><th>Legajo</th><th>Entrada</th><th>Salida</th>
            <th>Tiempo Extra</th><th>Extra 50%</th><th>Extra 100%</th><th>Adic. Nocturna</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="r.codigo">
            <td class="hx-num">{{ r.codigo }}</td>
            <td :class="{ 'hx-conv': r.convenio === 3 || r.convenio === 7 }">{{ r.nombre }}</td>
            <td class="hx-num">{{ r.legajo }}</td>
            <td class="hx-num">{{ r.entra }}</td>
            <td class="hx-num">{{ r.sale }}</td>
            <td class="hx-num hx-ext">{{ r.horas_extra }}</td>
            <td :class="{ 'hx-chg': r.m_est50 !== orig[i].m_est50 }"><input class="hx-hm" :value="hhmm(r.m_est50)" @change="setHM(r, 'm_est50', $event)" /></td>
            <td :class="{ 'hx-chg': r.m_est100 !== orig[i].m_est100 }"><input class="hx-hm" :value="hhmm(r.m_est100)" @change="setHM(r, 'm_est100', $event)" /></td>
            <td :class="{ 'hx-chg': r.m_estnoc50 !== orig[i].m_estnoc50 }"><input class="hx-hm" :value="hhmm(r.m_estnoc50)" @change="setHM(r, 'm_estnoc50', $event)" /></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="!cargando && consultado" class="hx-vacio">No hay horas extras para el día seleccionado.</div>

    <div v-if="rows.length" class="hx-barra">
      <span class="hx-leyenda"><i class="hx-conv-i"></i> SMATA / F.Convenio &nbsp;·&nbsp; <i class="hx-chg-i"></i> valor modificado</span>
      <button class="hx-btn-confirmar" :disabled="confirmando" @click="confirmar">{{ confirmando ? '⟳ Grabando…' : '✔ CONFIRMAR' }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'
import HorasExtrasDiariasAyuda from '@/components/HorasExtrasDiariasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)

interface Opcion { cod: string | number; nombre: string }
interface Row {
  codigo: number; nombre: string; legajo: string; convenio: number
  entra: string; sale: string; horas_extra: string; m_calculada: number
  m_est50: number; m_est100: number; m_estnoc50: number
  ant_est50: number; ant_est100: number; ant_estnoc50: number
  reloj_entrada: string; reloj_salida: string
}
const empresas = ref<Opcion[]>([]); const contratistas = ref<Opcion[]>([])
const ayer = new Date(Date.now() - 86400000)
const f = ref({ empresa: 1, contratista: 0, fecha: ayer.toISOString().slice(0, 10) })

const rows = ref<Row[]>([]); const orig = ref<Row[]>([])
const esViernes = ref(false); const cargando = ref(false); const consultado = ref(false); const confirmando = ref(false)

// HH:MM ↔ entero HHMM
const hhmm = (n: number): string => {
  const v = Math.max(0, Math.trunc(n))
  return `${String(Math.trunc(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}`
}
const aHHMM = (txt: string): number => {
  const m = String(txt).trim().match(/^(\d{1,2})\s*[:.]?\s*(\d{0,2})$/)
  if (!m) return 0
  const h = parseInt(m[1] || '0', 10); let mi = parseInt(m[2] || '0', 10)
  if (mi > 59) mi = 59
  return h * 100 + mi
}
const setHM = (r: Row, campo: 'm_est50' | 'm_est100' | 'm_estnoc50', ev: Event) => {
  const val = aHHMM((ev.target as HTMLInputElement).value)
  r[campo] = val
  ;(ev.target as HTMLInputElement).value = hhmm(val)
}

const aceptar = async () => {
  cargando.value = true; consultado.value = true
  try {
    const { data } = await api.get('/horas-extras-diarias', { params: { empresa: f.value.empresa, contratista: f.value.contratista, fecha: f.value.fecha } })
    rows.value = data.rows
    // El "original" usa los valores guardados (ant_*): así lo calculado-pero-no-guardado
    // (ej: la nocturna) se resalta en amarillo, igual que en FoxPro.
    orig.value = data.rows.map((r: Row) => ({ ...r, m_est50: r.ant_est50, m_est100: r.ant_est100, m_estnoc50: r.ant_estnoc50 }))
    esViernes.value = data.es_viernes
  } catch (e) { console.error(e); rows.value = [] }
  finally { cargando.value = false }
}

const confirmar = async () => {
  if (!confirm('¿Confirma la planilla de horas extras del día?')) return
  confirmando.value = true
  try {
    const payload = {
      fecha: f.value.fecha,
      rows: rows.value.map(r => ({
        codigo: r.codigo, nombre: r.nombre, reloj_entrada: r.reloj_entrada, reloj_salida: r.reloj_salida,
        m_calculada: r.m_calculada, m_est50: r.m_est50, m_est100: r.m_est100, m_estnoc50: r.m_estnoc50,
      })),
    }
    const { data } = await api.post('/horas-extras-diarias/confirmar', payload)
    orig.value = rows.value.map(r => ({ ...r }))   // ya no hay cambios pendientes
    alert(`✔ Confirmado. Se grabaron ${data.grabados} registros.`)
  } catch (e: any) {
    // Si el servidor no devolvió JSON (error de IIS/red), mostrar el código HTTP para diagnóstico.
    const detalle = e?.response?.data?.message
      ?? (e?.response?.status ? `Error ${e.response.status} del servidor.` : 'Sin respuesta del servidor (red o timeout).')
    alert('⚠️ No se pudo confirmar. ' + detalle)
  } finally { confirmando.value = false }
}

onMounted(async () => {
  try {
    const [e, c] = await Promise.all([api.get('/empresas'), api.get('/contratistas')])
    empresas.value = e.data; contratistas.value = c.data
    // Default de empresa válido: si el valor actual no está en la lista, tomar la primera
    // (evita que el combo quede vacío cuando la empresa del server no es la 1, ej. Logística=2).
    const cods = empresas.value.map((x: any) => Number(x.cod))
    if (!cods.includes(f.value.empresa)) f.value.empresa = cods[0] ?? 0
  } catch (err) { console.error(err) }
})
</script>

<style scoped>
.hx-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.hx-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.hx-cab-ico { font-size:28px; } .hx-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .hx-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.hx-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.hx-btn-ia:hover { filter:brightness(1.1); }
.hx-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.hx-btn-ayuda:hover { background:#f0faf4; }
.hx-filtros { display:flex; flex-wrap:wrap; align-items:end; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.hx-filtros label { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:600; color:#374151; }
.hx-filtros input, .hx-filtros select { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; outline:none; }
.hx-filtros input:focus, .hx-filtros select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.hx-btn-aceptar { background:#16a34a; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.hx-btn-aceptar:hover:not(:disabled){ background:#15803d; } .hx-btn-aceptar:disabled { background:#cbd5e1; }
.hx-viernes { margin:10px 18px 0; padding:9px 14px; background:#dc2626; color:#fff; border-radius:6px; font-size:14px; font-weight:800; text-align:center; letter-spacing:.5px; }
.hx-grid-wrap { margin:14px 18px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.hx-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.hx-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 12px; font-size:12px; }
.hx-grid td { padding:5px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; background:#fff; }
.hx-num { text-align:center; font-variant-numeric:tabular-nums; }
.hx-ext { font-weight:700; color:#1b4332; }
.hx-conv { background:#fff200; font-weight:600; }
.hx-chg { background:#fff200 !important; }
.hx-hm { width:62px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:13px; text-align:center; outline:none; font-variant-numeric:tabular-nums; }
.hx-hm:focus { border-color:#40916c; box-shadow:0 0 0 2px rgba(64,145,108,.2); }
.hx-vacio { padding:40px; text-align:center; color:#9ca3af; font-size:14px; }
.hx-barra { display:flex; align-items:center; gap:18px; padding:0 18px 18px; }
.hx-leyenda { font-size:12px; color:#6b7280; display:flex; align-items:center; }
.hx-conv-i, .hx-chg-i { display:inline-block; width:13px; height:13px; border-radius:3px; margin-right:4px; background:#fff200; border:1px solid #cbd5e1; vertical-align:middle; }
.hx-btn-confirmar { margin-left:auto; background:#2563eb; color:#fff; border:none; padding:10px 24px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.hx-btn-confirmar:hover:not(:disabled){ background:#1d4ed8; } .hx-btn-confirmar:disabled { background:#cbd5e1; }
</style>
