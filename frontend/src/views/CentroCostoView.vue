<!--
  CentroCostoView.vue — Carga de Centro de Costo por empleado y período.
  Réplica del formulario FoxPro "empleado_centro_costo": se elige un empleado,
  se ven/crean períodos (mes/año) y, para cada uno, el desagregado por centros
  de costo con su porcentaje. "Confirmar" reemplaza la distribución del período.
-->
<template>
  <div class="cc-view">
    <!-- Cabecera -->
    <div class="cc-cab">
      <div class="cc-cab-ico">💼</div>
      <div class="cc-cab-tx">
        <h1>Carga de Centro de Costo</h1>
        <p>Distribución del costo del empleado por período</p>
      </div>
      <button class="cc-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="cc-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <CentroCostoAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/centro-costo"
            titulo="Asistente IA — Centro de Costo"
            subtitulo="Preguntá cómo distribuir el costo del empleado"
            :sugerencias="[
              '¿Cómo cargo la distribución de un mes?',
              '¿Cómo agrego un centro de costo?',
              '¿Qué hace Confirmar?',
              '¿Los porcentajes deben sumar 100?']"
            @close="modalIA = false" />

    <div class="cc-card">
      <!-- Empleado -->
      <div class="cc-campo">
        <label>Empleado</label>
        <EmpleadoInput :codigo="emp?.PER_COD ?? 0" :nombre="(emp?.PER_NOM || '').trim()" @select="onSelEmp" />
      </div>

      <div v-if="emp" class="cc-emp-sel">
        <span>Empleado:</span> <b>{{ emp.PER_COD }} — {{ (emp.PER_NOM || '').trim() }}</b>
      </div>

      <!-- Dos columnas: períodos + desagregado -->
      <div v-if="emp" class="cc-grids">
        <!-- Períodos -->
        <div class="cc-col cc-col-periodos">
          <div class="cc-col-cab">
            <span>Períodos de distribución</span>
            <button class="cc-mini" @click="nuevoPeriodo">＋ Nuevo</button>
          </div>
          <table class="cc-tabla">
            <thead><tr><th>Mes</th><th>Año</th></tr></thead>
            <tbody>
              <tr v-if="modoNuevo" class="cc-fila-nueva">
                <td><input v-model.number="nuevoMes" type="number" min="1" max="12" class="cc-in-num" /></td>
                <td><input v-model.number="nuevoAno" type="number" min="2000" max="2099" class="cc-in-num" /></td>
              </tr>
              <tr v-for="p in periodos" :key="p.mya" :class="{ sel: !modoNuevo && periodoSel?.mya === p.mya }"
                  @click="elegirPeriodo(p)">
                <td>{{ p.mes }}</td>
                <td>{{ p.ano }}</td>
              </tr>
              <tr v-if="!periodos.length && !modoNuevo"><td colspan="2" class="cc-vacio">Sin períodos cargados</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Desagregado -->
        <div class="cc-col cc-col-detalle">
          <div class="cc-col-cab">
            <span>Desagregado por centros de costo</span>
            <span class="cc-total" :class="{ ok: totalPorc === 100, over: totalPorc > 100 }">Total: {{ totalPorc.toFixed(2) }}%</span>
          </div>

          <table class="cc-tabla">
            <thead>
              <tr><th style="width:70px">Código</th><th>Nombre Centro de Costo</th><th style="width:90px">Porcentaje</th><th style="width:50px">Borra</th></tr>
            </thead>
            <tbody>
              <tr v-for="(it, i) in items" :key="i">
                <td class="cc-cod">{{ it.cod }}</td>
                <td>{{ it.descripcion }}</td>
                <td><input v-model.number="it.porcentaje" type="number" min="0" step="0.01" class="cc-in-por" /></td>
                <td style="text-align:center"><button class="cc-del" title="Quitar" @click="quitar(i)">🗑️</button></td>
              </tr>
              <tr v-if="!items.length"><td colspan="4" class="cc-vacio">Sin centros en este período</td></tr>
            </tbody>
          </table>

          <!-- Agregar centro -->
          <div v-if="puedeEditar" class="cc-agregar">
            <select v-model="ccoSel">
              <option value="">— Elegir centro de costo —</option>
              <option v-for="c in catalogoDisponible" :key="c.cod" :value="c.cod">{{ c.cod }} — {{ c.descripcion }}</option>
            </select>
            <input v-model.number="ccoPorc" type="number" min="0" step="0.01" placeholder="%" class="cc-in-por" />
            <button class="cc-mini cc-add" :disabled="!ccoSel" @click="agregarCentro">＋ Agregar</button>
          </div>
        </div>
      </div>

      <!-- Acciones -->
      <div v-if="emp" class="cc-acciones">
        <button class="cc-btn-confirmar" :disabled="!puedeConfirmar || guardando" @click="confirmar">
          ✅ {{ guardando ? 'Confirmando…' : 'Confirmar C.Costo' }}
        </button>
        <button class="cc-btn-reset" @click="reset">↺ Reset</button>
        <p v-if="msg" :class="['cc-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'
import CentroCostoAyuda from '@/components/CentroCostoAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

interface Periodo { mes: number; ano: number; mya: string }
interface Item { cod: any; descripcion: string; porcentaje: number }

// ── Empleado ──────────────────────────────────────────────
const emp = ref<any | null>(null)
const onSelEmp = (r: any) => seleccionar(r)

const seleccionar = async (r: any) => {
  emp.value = r
  await cargarTodo()
}

// ── Períodos / detalle / catálogo ─────────────────────────
const periodos = ref<Periodo[]>([])
const periodoSel = ref<Periodo | null>(null)
const items = reactive<Item[]>([])
const catalogo = ref<{ cod: any; descripcion: string }[]>([])

const modoNuevo = ref(false)
const nuevoMes = ref<number | null>(new Date().getMonth() + 1)
const nuevoAno = ref<number | null>(new Date().getFullYear())

const ccoSel = ref<any>('')
const ccoPorc = ref<number | null>(null)

const guardando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, err = false) => { msg.value = t; msgError.value = err; setTimeout(() => msg.value = '', 4000) }

// Token para descartar cargas obsoletas (evita duplicados por condición de carrera)
let cargaToken = 0

const cargarTodo = async (selMya?: string) => {
  periodos.value = []; items.length = 0; periodoSel.value = null; modoNuevo.value = false
  if (!emp.value) return
  try {
    const [pe, cat] = await Promise.all([
      api.get(`/empleados/${emp.value.PER_COD}/centros-costo/periodos`),
      catalogo.value.length ? Promise.resolve({ data: catalogo.value }) : api.get('/empleados/centros-costo-catalogo'),
    ])
    periodos.value = pe.data ?? []
    if (!catalogo.value.length) catalogo.value = cat.data ?? []
    const destino = (selMya && periodos.value.find(x => x.mya === selMya)) || periodos.value[0]
    if (destino) await elegirPeriodo(destino)
  } catch (e) { console.error('Error cargando centro de costo', e) }
}

const elegirPeriodo = async (p: Periodo) => {
  modoNuevo.value = false
  periodoSel.value = p
  const token = ++cargaToken
  items.length = 0
  try {
    const { data } = await api.get(`/empleados/${emp.value.PER_COD}/centros-costo/detalle/${p.mya}`)
    if (token !== cargaToken) return   // otra carga más nueva ya tomó el control
    const nuevos = (data ?? []).map((d: any) => ({ cod: d.cod, descripcion: d.descripcion, porcentaje: Number(d.porcentaje) }))
    items.splice(0, items.length, ...nuevos)   // reemplazo atómico
  } catch (e) { console.error(e) }
}

const nuevoPeriodo = () => {
  modoNuevo.value = true
  periodoSel.value = null
  items.length = 0
  nuevoMes.value = new Date().getMonth() + 1
  nuevoAno.value = new Date().getFullYear()
}

// ── Edición de ítems ──────────────────────────────────────
const puedeEditar = computed(() => modoNuevo.value || !!periodoSel.value)
const catalogoDisponible = computed(() => {
  const usados = new Set(items.map(i => String(i.cod)))
  return catalogo.value.filter(c => !usados.has(String(c.cod)))
})
const totalPorc = computed(() => items.reduce((s, i) => s + (Number(i.porcentaje) || 0), 0))

const agregarCentro = () => {
  if (!ccoSel.value) return
  const c = catalogo.value.find(x => String(x.cod) === String(ccoSel.value))
  if (!c) return
  // No permitir el mismo centro de costo dos veces en el período
  if (items.some(i => String(i.cod) === String(c.cod))) {
    flash(`El centro "${c.cod} — ${c.descripcion}" ya está agregado en este período.`, true)
    return
  }
  items.push({ cod: c.cod, descripcion: c.descripcion, porcentaje: Number(ccoPorc.value) || 0 })
  ccoSel.value = ''; ccoPorc.value = null
}
const quitar = (i: number) => { items.splice(i, 1) }

// ── Confirmar ─────────────────────────────────────────────
const periodoActivo = computed<{ mes: number; ano: number } | null>(() => {
  if (modoNuevo.value) {
    if (!nuevoMes.value || !nuevoAno.value) return null
    return { mes: nuevoMes.value, ano: nuevoAno.value }
  }
  if (periodoSel.value) return { mes: Number(periodoSel.value.mes), ano: Number(periodoSel.value.ano) }
  return null
})
// Requiere empleado, período (elegido o nuevo válido) y al menos un centro de costo
const puedeConfirmar = computed(() => !!emp.value && !!periodoActivo.value && items.length > 0)

const confirmar = async () => {
  const pa = periodoActivo.value
  if (!emp.value) { flash('Seleccioná un empleado.', true); return }
  if (!pa) { flash('Seleccioná o creá un período (mes y año).', true); return }
  if (items.length === 0) { flash('Agregá al menos un centro de costo.', true); return }
  if (totalPorc.value > 100.001) {
    flash(`La suma de porcentajes es ${totalPorc.value.toFixed(2)}% y no puede superar el 100%.`, true)
    return
  }

  // No permitir centros de costo duplicados en el período
  const vistos = new Set<string>()
  for (const it of items) {
    const k = String(it.cod)
    if (vistos.has(k)) {
      flash(`Hay un centro de costo duplicado (${it.cod} — ${it.descripcion}). Quitá el repetido antes de confirmar.`, true)
      return
    }
    vistos.add(k)
  }

  const per = `${String(pa.mes).padStart(2, ' ')} / ${pa.ano}`
  if (!confirm(`¿Confirma Período ${per}?`)) return
  guardando.value = true
  try {
    await api.put(`/empleados/${emp.value.PER_COD}/centros-costo`, {
      mes: pa.mes, ano: pa.ano,
      items: items.map(i => ({ cod: i.cod, descripcion: i.descripcion, porcentaje: Number(i.porcentaje) || 0 })),
    })
    flash(`Período ${pa.mes}/${pa.ano} confirmado.`)
    const myaGuardado = `${pa.ano}${String(pa.mes).padStart(2, '0')}`
    await cargarTodo(myaGuardado)
  } catch (e: any) {
    flash(e?.response?.data?.error ?? 'No se pudo confirmar el período.', true)
  } finally {
    guardando.value = false
  }
}

const reset = () => { if (emp.value) cargarTodo() }
</script>

<style scoped>
.cc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cc-cab-ico { font-size:28px; }
.cc-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.cc-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cc-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none;
  padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cc-btn-ia:hover { filter:brightness(1.1); }
.cc-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px;
  border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cc-btn-ayuda:hover { background:#f0faf4; }

.cc-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; max-width:900px; }
.cc-campo { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.cc-campo label { font-size:12px; font-weight:600; color:#374151; }
.cc-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.cc-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.cc-buscador { position:relative; }
.cc-buscador-lupa { display:flex; gap:8px; } .cc-buscador-lupa input { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
.cc-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.cc-result { list-style:none; margin:4px 0 0; padding:4px; position:absolute; z-index:20; left:0; right:0;
  background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 30px rgba(0,0,0,.18); max-height:260px; overflow:auto; }
.cc-result li { padding:8px 10px; border-radius:6px; cursor:pointer; display:flex; flex-direction:column; gap:2px; }
.cc-result li:hover { background:#f0faf4; }
.cc-result li b { font-size:13px; color:#1e293b; }
.cc-result li span { font-size:11px; color:#6b7280; }

.cc-emp-sel { background:#f0fdf4; border:1px solid #d1fae5; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:14px; color:#1e293b; }
.cc-emp-sel span { color:#6b7280; margin-right:6px; }

.cc-grids { display:grid; grid-template-columns:240px 1fr; gap:18px; }
.cc-col { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.cc-col-cab { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:9px 12px;
  background:#1b4332; color:#fff; font-size:12px; font-weight:600; }
.cc-mini { background:#22c55e; color:#fff; border:none; padding:4px 9px; border-radius:5px; cursor:pointer; font-size:11px; font-weight:600; }
.cc-mini:hover:not(:disabled) { background:#16a34a; }
.cc-mini:disabled { background:rgba(255,255,255,.3); cursor:default; }
.cc-total { background:rgba(255,255,255,.18); padding:3px 8px; border-radius:5px; font-weight:700; }
.cc-total.ok { background:#22c55e; }
.cc-total.over { background:#dc2626; }

.cc-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.cc-tabla th { background:#eef2f7; color:#334155; text-align:left; padding:7px 10px; font-size:11px;
  text-transform:uppercase; letter-spacing:.3px; border-bottom:1px solid #e2e8f0; }
.cc-tabla td { padding:7px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.cc-col-periodos tbody tr { cursor:pointer; }
.cc-col-periodos tbody tr:hover td { background:#f8fafc; }
.cc-tabla tr.sel td { background:#dcfce7; font-weight:600; }
.cc-fila-nueva td { background:#fefce8; }
.cc-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.cc-vacio { text-align:center; color:#9ca3af; font-style:italic; padding:14px; }
.cc-in-num { width:62px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:13px; }
.cc-in-por { width:80px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:13px; text-align:right; }
.cc-del { background:transparent; border:none; cursor:pointer; font-size:14px; }
.cc-del:hover { transform:scale(1.2); }

.cc-agregar { display:flex; gap:8px; padding:10px 12px; border-top:1px solid #f1f5f9; background:#fafafa; }
.cc-agregar select { flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:13px; outline:none; }
.cc-add { padding:6px 12px; }

.cc-acciones { display:flex; align-items:center; gap:10px; margin-top:18px; padding-top:16px; border-top:1px solid #f1f5f9; }
.cc-btn-confirmar { background:#16a34a; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:700; }
.cc-btn-confirmar:hover:not(:disabled) { background:#15803d; }
.cc-btn-confirmar:disabled { background:#cbd5e1; cursor:default; }
.cc-btn-reset { background:#fff; color:#475569; border:1px solid #d1d5db; padding:10px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.cc-btn-reset:hover { background:#f8fafc; }
.cc-msg { margin:0 0 0 6px; font-size:13px; padding:7px 12px; border-radius:6px; }
.cc-msg.ok { background:#dcfce7; color:#166534; }
.cc-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
