<!-- CelularesDevolverView.vue — Telefonía Celular: Devolución de Teléfonos (celulares_devolver.scx). -->
<template>
  <div class="ca-view">
    <div class="ca-cab">
      <div class="ca-ico">↩️</div>
      <div class="ca-tx"><h1>Devolución de Teléfonos</h1><p>Registrar la devolución de un equipo celular</p></div>
      <button class="ca-ia" @click="modalIA = true">🤖 IA</button>
      <button class="ca-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="ca-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/celulares-devolver" titulo="Asistente IA — Devolución de Teléfonos"
            subtitulo="Preguntá sobre la devolución de celulares"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo registro una devolución?','¿Qué equipos aparecen?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['ca-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ca-body">
      <div class="ca-emp">
        <div class="ca-emp-datos">
          <label>Empleado</label>
          <div class="ca-emp-row">
            <EmpleadoInput :codigo="emp || 0" :nombre="empNombre" @select="onLupaEmp" />
          </div>
        </div>
        <div class="ca-foto"><img v-if="empFoto" :src="empFoto" /><div v-else class="ca-foto-ph">👤</div></div>
      </div>

      <template v-if="empNombre">
        <div class="ca-grilla">
          <table class="ca-tabla">
            <thead><tr>
              <th style="width:36px">OK</th><th style="width:60px">Cod.</th><th>IMEI</th><th>Marca</th><th>Modelo</th>
              <th>Color</th><th style="width:56px">Pulg.</th><th>Sistema Operativo</th><th>N° Línea</th><th>Entrega</th>
            </tr></thead>
            <tbody>
              <tr v-for="a in asignados" :key="a.id" class="activo">
                <td class="c"><input v-model="seleccion" type="checkbox" :value="a.id" /></td>
                <td class="c">{{ a.cod }}</td><td>{{ a.imei }}</td><td>{{ a.marca }}</td><td>{{ a.modelo }}</td>
                <td>{{ a.color }}</td><td class="c">{{ a.pantalla || '' }}</td><td>{{ a.sistema }}</td>
                <td>{{ a.nro_celular }}</td><td class="c">{{ fmt(a.entrega) }}</td>
              </tr>
              <tr v-if="!asignados.length"><td colspan="10" class="vacio">El empleado no tiene equipos activos para devolver.</td></tr>
            </tbody>
          </table>
        </div>

        <label class="ca-lbl">Observaciones del estado de devolución del teléfono recibido</label>
        <input v-model="observacion" v-enter-next maxlength="100" class="ca-obs" />
        <div class="ca-entrega">
          <div><label>Fecha de Devolución</label><input v-model="fecha" v-enter-next type="date" @keyup.enter="devolver" /></div>
        </div>

        <button class="ca-agregar dev" :disabled="guardando || !asignados.length" @click="devolver">
          {{ guardando ? '⟳…' : 'DEVOLVER TELÉFONO' }}
        </button>
      </template>
      <div v-else-if="!cargando" class="ca-elija">Ingrese el código de un empleado para ver sus equipos.</div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="ca-ov" @click.self="ayuda = false">
        <div class="ca-help-md">
          <h3>❓ Ayuda — Devolución de Teléfonos</h3>
          <ul>
            <li>Ingresá el <b>código del empleado</b> para ver los equipos que tiene actualmente asignados (sin devolver).</li>
            <li>Marcá en <b>OK</b> el/los equipos que devuelve.</li>
            <li>Completá la <b>fecha de devolución</b> y una <b>observación</b> del estado en que se recibe.</li>
            <li>Presioná <b>DEVOLVER TELÉFONO</b> para registrar la devolución.</li>
          </ul>
          <div class="ca-acc-bar"><span style="flex:1"></span><button class="ca-agregar chico" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const emp = ref<number | null>(null); const empNombre = ref(''); const empFoto = ref('')
const onLupaEmp = (r: any) => { emp.value = r.cod; cargarEmpleado() }
const asignados = ref<any[]>([]); const seleccion = ref<number[]>([])
const observacion = ref(''); const fecha = ref('')
const cargando = ref(false); const guardando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }

async function cargarEmpleado () {
  if (!emp.value || emp.value <= 0) { flash('Debe ingresar el empleado.', true); return }
  cargando.value = true; empNombre.value = ''; empFoto.value = ''; asignados.value = []; seleccion.value = []
  try {
    const { data } = await api.get(`/celulares/empleado/${emp.value}`)
    empNombre.value = data.empleado.nombre
    asignados.value = (data.asignados ?? []).filter((a: any) => a.activo)
    try { const f = await api.get(`/empleados/${emp.value}/foto`); empFoto.value = f.data?.foto || '' } catch { /* */ }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
  finally { cargando.value = false }
}

async function devolver () {
  if (!fecha.value) { flash('Debe ingresar la fecha que fue devuelto.', true); return }
  if (!observacion.value.trim()) { flash('Ingresar una observación del estado en que es devuelto el teléfono.', true); return }
  if (!seleccion.value.length) { flash('Debe seleccionar qué teléfono devolver.', true); return }
  guardando.value = true
  try {
    await api.post('/celulares/devolver', { unicos: seleccion.value, fecha: fecha.value, observacion: observacion.value })
    flash('Devolución registrada.')
    observacion.value = ''; fecha.value = ''
    await cargarEmpleado()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo registrar la devolución.', true) }
  finally { guardando.value = false }
}

function reset () { emp.value = null; empNombre.value = ''; empFoto.value = ''; asignados.value = []; seleccion.value = []; observacion.value = ''; fecha.value = '' }
</script>

<style scoped>
.ca-view { display:flex; flex-direction:column; min-height:100%; }
.ca-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ca-ico { font-size:28px; } .ca-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ca-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ca-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ca-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ca-msg.ok { background:#d1fae5; color:#065f46; } .ca-msg.err { background:#fee2e2; color:#991b1b; }
.ca-body { padding:16px 18px; max-width:920px; }
.ca-emp { display:flex; gap:16px; align-items:flex-start; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fafdff; }
.ca-emp-datos { flex:1; } .ca-emp-datos label { font-size:12px; font-weight:700; color:#374151; }
.ca-emp-row { display:flex; gap:10px; margin-top:5px; }
.ca-emp-row input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:15px; color:#1e293b; }
.ca-emp-row input[type=number] { width:110px; font-weight:800; } .ca-nom { flex:1; background:#f1f5f9; }
.ca-lupa { background:#394959; color:#fff; border:none; padding:9px 13px; border-radius:7px; cursor:pointer; font-size:14px; }
.ca-foto { width:96px; height:78px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#eef2f7; display:flex; align-items:center; justify-content:center; }
.ca-foto img { width:100%; height:100%; object-fit:cover; } .ca-foto-ph { font-size:32px; color:#94a3b8; }
.ca-grilla { margin-top:16px; }
.ca-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.ca-tabla th { background:#6a957b; color:#fff; text-align:left; padding:7px 9px; font-size:11.5px; white-space:nowrap; }
.ca-tabla td { border-bottom:1px solid #eef2f7; padding:6px 9px; color:#1e293b; } .ca-tabla td.c { text-align:center; }
.ca-tabla tr.activo td { background:#fff9c4; }
.ca-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; background:#fff; }
.ca-lbl { display:block; font-size:12px; font-weight:700; color:#374151; margin:16px 0 4px; }
.ca-obs { width:100%; box-sizing:border-box; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; color:#1e293b; }
.ca-entrega { display:flex; gap:18px; margin-top:12px; }
.ca-entrega label { font-size:12px; font-weight:700; color:#374151; display:block; }
.ca-entrega input { border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; color:#1e293b; margin-top:4px; }
.ca-agregar { margin-top:16px; width:100%; border:none; border-radius:8px; padding:12px; cursor:pointer; font-weight:800; font-size:14px; } .ca-agregar:disabled { opacity:.5; } .ca-agregar.chico { width:auto; padding:9px 20px; background:#eef2f7; color:#475569; }
.ca-agregar.dev { background:#22c55e; color:#0f3d22; }
.ca-elija { text-align:center; color:#94a3b8; padding:30px; }
.ca-acc-bar { display:flex; align-items:center; gap:8px; margin-top:16px; }
.ca-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.ca-help-md { background:#fff; border-radius:14px; padding:22px; width:min(560px,94vw); } .ca-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .ca-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
