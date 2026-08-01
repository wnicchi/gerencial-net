<!-- CalificacionView.vue — Calificación del empleado (registro + 4 solapas del puesto) -->
<template>
  <div class="ca-view">
    <div class="ca-cab">
      <div class="ca-ico">📝</div>
      <div class="ca-tx"><h1>Calificación del empleado</h1><p>Evaluación de desempeño en el puesto</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ca-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ca-body">
      <!-- Fila: fecha + empleado + foto -->
      <div class="ca-top">
        <div class="campo cmp"><label>Fecha</label><input v-model="f.fecha" type="date" /></div>
        <div class="campo grow">
          <label>Empleado</label>
          <EmpleadoInput :codigo="codigo" :nombre="nombre" :disabled="empBloqueado" @select="onSelEmp" />
        </div>
        <div class="ca-foto"><img v-if="fotoUrl" :src="fotoUrl" @error="fotoUrl = ''" /><div v-else class="ca-foto-ph">Sin foto</div></div>
      </div>

      <template v-if="codigo">
        <div class="ca-cols">
          <!-- Descripción del puesto -->
          <section class="ca-card">
            <h2>Descripción</h2>
            <div class="campo"><label>Puesto</label>
              <select v-model="puestoSel" @change="cargarPuesto">
                <option value="">— Seleccioná —</option>
                <option v-for="p in puestos" :key="p.cod" :value="p.cod">{{ p.des }}</option>
              </select>
              <span v-if="!puestos.length" class="ca-warn">El empleado no posee puesto asignado.</span>
            </div>
            <div class="campo"><label>Reporta a</label><input :value="det.reporta" disabled /></div>
            <div class="campo"><label>Departamento</label><input :value="det.departamento" disabled /></div>
            <div class="campo"><label>Objetivos</label><textarea :value="det.objetivos" rows="3" disabled></textarea></div>
          </section>

          <!-- 4 solapas -->
          <section class="ca-card">
            <div class="ca-tabs">
              <button v-for="t in tabs" :key="t.id" class="ca-tab" :class="{ on: tab === t.id }" @click="tab = t.id">{{ t.label }}</button>
            </div>
            <div class="ca-tabbody">
              <table v-if="tab === 'tareas'" class="ca-grid">
                <thead><tr><th>Tarea</th><th>Detalle de las Tareas a Realizar</th><th>Frec.</th><th>% util.</th></tr></thead>
                <tbody>
                  <tr v-for="t in det.tareas" :key="t.cod"><td>{{ t.cod }}</td><td>{{ t.des }}</td><td>{{ t.fre }}</td><td>{{ t.min }}</td></tr>
                  <tr v-if="!det.tareas.length"><td colspan="4" class="ca-vacio">Sin tareas.</td></tr>
                </tbody>
              </table>
              <ul v-else class="ca-lista">
                <li v-for="(x, i) in listaActual" :key="i">{{ x }}</li>
                <li v-if="!listaActual.length" class="ca-vacio2">Sin ítems.</li>
              </ul>
            </div>
          </section>
        </div>

        <!-- Observaciones -->
        <section class="ca-card ca-obs">
          <h2>Observaciones</h2>
          <div class="campo"><label>Resultado de la Evaluación</label><input v-model="f.resultado" maxlength="200" /></div>
          <div class="campo"><label>Observaciones Positivas</label><input v-model="f.positivas" maxlength="200" /></div>
          <div class="campo"><label>Aspectos a Mejorar</label><input v-model="f.negativas" maxlength="200" /></div>
          <div class="ca-resp">
            <div class="campo cmp"><label>Responsable (código)</label><input v-model.number="f.responsable" type="number" @blur="cargarResponsable" /></div>
            <div class="campo grow"><label>Nombre del responsable</label><input :value="responsableNom" disabled /></div>
          </div>
          <div class="ca-nec">
            <button class="btn sec" @click="verPendientes">📋 Ver Capacitación Pendiente</button>
            <select v-model.number="temaSel" class="ca-selt"><option :value="0">Área temática…</option><option v-for="t in temas" :key="t.cod" :value="t.cod">{{ t.des }}</option></select>
            <button class="btn sec" :disabled="!temaSel || proc" @click="asignarNecesidad">＋ Asignar Necesidad de Capacitación</button>
          </div>
        </section>

        <div class="ca-acc">
          <button class="btn reset" @click="reset">↺ Reset</button>
          <button class="btn ok" :disabled="proc || !puestoSel" @click="guardar">{{ proc ? '⟳ Guardando…' : '✔ GUARDAR CALIFICACIÓN' }}</button>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div v-if="modalPend" class="ca-ov" @click.self="modalPend = false">
        <div class="ca-modal">
          <h3>📋 Necesidades de Capacitación pendientes</h3>
          <ul class="ca-pend">
            <li v-for="(p, i) in pendientes" :key="i">({{ p.tema }}) <b>{{ p.des }}</b> — generada el {{ p.fecha }}</li>
            <li v-if="!pendientes.length" class="ca-vacio2">No hay necesidades pendientes.</li>
          </ul>
          <div class="ca-modal-acc"><button class="btn ok" @click="modalPend = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const hoy = new Date().toISOString().slice(0, 10)
const f = reactive({ fecha: hoy, resultado: '', positivas: '', negativas: '', responsable: 0 })
const codigo = ref(0); const nombre = ref(''); const cuit = ref(''); const fotoUrl = ref('')
const puestos = ref<{ cod: string; des: string }[]>([]); const puestoSel = ref('')
const det = reactive<any>({ reporta: '', departamento: '', objetivos: '', tareas: [], educacion: [], cualidades: [], competencias: [] })
const responsableNom = ref('')
const temas = ref<{ cod: number; des: string }[]>([]); const temaSel = ref(0)
const pendientes = ref<any[]>([]); const modalPend = ref(false)
const proc = ref(false); const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const tabs = [
  { id: 'tareas', label: 'Tareas' }, { id: 'educacion', label: 'Req. Educacionales' },
  { id: 'cualidades', label: 'Cualidades Nec.' }, { id: 'competencias', label: 'Competencias' },
] as const
const tab = ref<'tareas' | 'educacion' | 'cualidades' | 'competencias'>('tareas')
const listaActual = computed(() => tab.value === 'educacion' ? det.educacion : tab.value === 'cualidades' ? det.cualidades : tab.value === 'competencias' ? det.competencias : [])

const onSelEmp = (r: any) => seleccionar(r)

// Precarga desde la ficha del empleado (Propuesta A)
onMounted(() => { if (props.empleado) seleccionar({ PER_COD: props.empleado }) })
async function seleccionar (r: any) {
  if (!temas.value.length) { try { temas.value = (await api.get('/calificaciones/temas')).data ?? [] } catch { /* */ } }
  try {
    const { data } = await api.get(`/calificaciones/empleado/${r.PER_COD}`)
    codigo.value = data.codigo; nombre.value = data.nombre; cuit.value = data.cuit
    puestos.value = data.puestos; puestoSel.value = data.puestos[0]?.cod ?? ''
    if (puestoSel.value) await cargarPuesto()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
  fotoUrl.value = ''
  try { const { data } = await api.get(`/empleados/${r.PER_COD}/foto`); if (data?.foto) fotoUrl.value = data.foto } catch { /* sin foto */ }
}

async function cargarPuesto () {
  if (!puestoSel.value) return
  try { Object.assign(det, (await api.get(`/calificaciones/puesto/${encodeURIComponent(puestoSel.value)}`)).data) } catch { /* */ }
}

async function cargarResponsable () {
  responsableNom.value = ''
  if (!f.responsable) return
  try { responsableNom.value = (await api.get(`/calificaciones/persona/${f.responsable}`)).data.nombre } catch { responsableNom.value = '⚠️ código inexistente' }
}

async function verPendientes () {
  try { pendientes.value = (await api.get(`/calificaciones/pendientes/${codigo.value}`)).data ?? []; modalPend.value = true } catch { /* */ }
}

async function asignarNecesidad () {
  if (!temaSel.value) return
  proc.value = true
  try { const { data } = await api.post('/calificaciones/necesidad', { codigo: codigo.value, tema: temaSel.value, fecha: f.fecha }); flash(data.mensaje ?? 'Necesidad generada.'); temaSel.value = 0 }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo asignar.', true) }
  finally { proc.value = false }
}

async function guardar () {
  proc.value = true
  try {
    await api.post('/calificaciones', { codigo: codigo.value, responsable: f.responsable, puesto: puestoSel.value, fecha: f.fecha, resultado: f.resultado, positivas: f.positivas, negativas: f.negativas })
    flash('Calificación guardada correctamente.'); reset()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}

function reset () {
  codigo.value = 0; nombre.value = ''; cuit.value = ''; puestoSel.value = ''; puestos.value = []
  Object.assign(f, { fecha: hoy, resultado: '', positivas: '', negativas: '', responsable: 0 })
  Object.assign(det, { reporta: '', departamento: '', objetivos: '', tareas: [], educacion: [], cualidades: [], competencias: [] })
  responsableNom.value = ''; temaSel.value = 0; fotoUrl.value = ''
}
</script>

<style scoped>
.ca-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ca-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ca-ico { font-size:28px; } .ca-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ca-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ca-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ca-msg.ok { background:#d1fae5; color:#065f46; } .ca-msg.err { background:#fee2e2; color:#991b1b; }
.ca-body { padding:16px 18px; max-width:1000px; }
.ca-top { display:flex; gap:14px; align-items:flex-end; }
.campo { display:flex; flex-direction:column; gap:5px; } .campo.cmp { width:150px; } .campo.grow { flex:1; }
.campo label { font-size:12px; font-weight:600; color:#374151; }
.campo input, .campo select, .campo textarea { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; color:#1e293b; }
.campo input:disabled, .campo textarea:disabled { background:#f1f5f9; }
.ca-busc { position:relative; } .ca-busc input { width:100%; }
.ca-busc-lupa { display:flex; gap:8px; } .ca-busc-lupa input { flex:1; }
.ca-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ca-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.ca-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.ca-result li:hover { background:#f0faf4; } .ca-result li b { font-size:13px; color:#1e293b; } .ca-result li span { font-size:11px; color:#6b7280; }
.ca-foto { width:120px; height:90px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ca-foto img { width:100%; height:100%; object-fit:cover; } .ca-foto-ph { font-size:11px; color:#9ca3af; }
.ca-cols { display:grid; grid-template-columns:340px 1fr; gap:14px; margin-top:16px; }
.ca-card { background:#fff; border:1px solid #dde4ee; border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:10px; }
.ca-card h2 { margin:0 0 4px; font-size:14px; color:#1a3a5c; }
.ca-warn { font-size:12px; color:#b45309; }
.ca-tabs { display:flex; gap:3px; flex-wrap:wrap; }
.ca-tab { border:none; background:#e8eef5; color:#475569; padding:6px 11px; border-radius:7px 7px 0 0; cursor:pointer; font-size:12px; font-weight:600; }
.ca-tab.on { background:#2d6a9f; color:#fff; }
.ca-tabbody { border:1px solid #e8eef5; border-radius:0 6px 6px 6px; min-height:160px; overflow:auto; max-height:240px; }
.ca-grid { width:100%; border-collapse:collapse; font-size:12.5px; }
.ca-grid th { background:#cfe6cf; color:#14532d; padding:5px 8px; text-align:left; font-size:11.5px; position:sticky; top:0; }
.ca-grid td { padding:4px 8px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ca-lista { list-style:none; margin:0; padding:8px 12px; } .ca-lista li { padding:4px 0; border-bottom:1px solid #f0f4f9; font-size:13px; color:#1e293b; }
.ca-vacio { text-align:center; color:#94a3b8; padding:12px; } .ca-vacio2 { color:#94a3b8; }
.ca-obs { margin-top:14px; }
.ca-resp { display:flex; gap:12px; } .ca-resp .grow { flex:1; }
.ca-nec { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:6px; }
.ca-selt { border:1px solid #c8d8ea; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; min-width:200px; }
.ca-acc { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; }
.btn { border:none; padding:10px 20px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.sec { background:#e0eefc; color:#2d6a9f; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.ca-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.ca-modal { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); }
.ca-modal h3 { margin:0 0 12px; color:#1a3a5c; } .ca-pend { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.8; }
.ca-modal-acc { display:flex; justify-content:flex-end; margin-top:14px; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
@media (max-width:880px) { .ca-cols { grid-template-columns:1fr; } }
</style>
