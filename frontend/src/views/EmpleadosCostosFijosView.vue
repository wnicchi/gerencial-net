<!-- EmpleadosCostosFijosView.vue — Costos Laborales: Editar Costos Fijos (empleados_costos_fijos.scx). -->
<template>
  <div class="cf-view">
    <div class="cf-cab">
      <div class="cf-ico">💹</div>
      <div class="cf-tx"><h1>Costos Fijos para el cálculo de Costos Laborales</h1><p>Editar importes por período</p></div>
      <button class="cf-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cf-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="cf-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/costos-fijos" titulo="Asistente IA — Editar Costos Fijos"
            subtitulo="Preguntá sobre los costos fijos laborales"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo cargo un costo?','¿Cómo cambio un importe?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['cf-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="cf-body">
      <!-- Período -->
      <div class="cf-periodo">
        <span class="cf-lbl">Corresponden al período</span>
        <label>Mes</label>
        <input v-model.number="mes" type="number" min="1" max="12" :disabled="bloqueado" class="cf-num" />
        <label>Año</label>
        <input v-model.number="anio" type="number" min="2021" max="2200" :disabled="bloqueado" class="cf-num cf-anio" />
        <button v-if="!bloqueado" class="cf-aceptar" :disabled="cargando" @click="aceptar">ACEPTAR</button>
        <button v-else class="cf-cambiar" @click="reset">Cambiar período</button>
      </div>

      <template v-if="bloqueado">
        <!-- Grilla -->
        <table class="cf-tabla">
          <thead><tr><th style="width:40px">Ok</th><th>Conceptos</th><th style="width:160px">Importe</th></tr></thead>
          <tbody>
            <tr v-for="c in costos" :key="c.cod">
              <td class="c"><input v-model="seleccion" type="checkbox" :value="c.cod" /></td>
              <td>{{ c.detalle }}</td>
              <td class="imp">
                <input v-model.number="c.importe" type="number" step="0.01" min="0"
                       @focus="antes = c.importe" @blur="guardarImporte(c)" @keyup.enter="($event.target as HTMLInputElement).blur()" />
              </td>
            </tr>
            <tr v-if="!costos.length"><td colspan="3" class="vacio">No hay costos cargados para este período.</td></tr>
          </tbody>
        </table>

        <div class="cf-pie">
          <button class="cf-eliminar" :disabled="!seleccion.length" @click="confBorrar = true">🗑️ Eliminar Costo Seleccionado</button>
          <span style="flex:1"></span>
          <span class="cf-sub-lbl">SUBTOTAL</span>
          <span class="cf-sub">{{ money(subtotal) }}</span>
        </div>

        <!-- Agregar -->
        <div class="cf-agregar">
          <label>Descripción</label>
          <input v-model="nuevo" v-enter-next maxlength="100" placeholder="Descripción del nuevo costo…" @keyup.enter="agregar" />
          <button class="cf-btn-add" :disabled="guardando" @click="agregar">AGREGAR</button>
        </div>
      </template>
      <div v-else class="cf-elija">Elegí el mes y el año y presioná ACEPTAR para editar los costos del período.</div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="cf-ov" @click.self="ayuda = false">
        <div class="cf-help-md">
          <h3>❓ Ayuda — Editar Costos Fijos</h3>
          <ul>
            <li>Elegí <b>mes</b> y <b>año</b> y presioná <b>ACEPTAR</b> para cargar los costos de ese período.</li>
            <li>Editá el <b>importe</b> de cada concepto directamente en la grilla (se guarda al salir del campo).</li>
            <li>Cargá un nuevo concepto escribiendo la <b>descripción</b> y presionando <b>AGREGAR</b> (no se permiten descripciones repetidas en el mismo período).</li>
            <li>Marcá los concptos y usá <b>Eliminar Costo Seleccionado</b> para borrarlos.</li>
            <li>El <b>SUBTOTAL</b> suma todos los importes del período.</li>
          </ul>
          <div class="cf-pie"><span style="flex:1"></span><button class="cf-aceptar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="confBorrar" class="cf-ov" @click.self="confBorrar = false">
        <div class="cf-help-md">
          <h3>🗑️ Eliminar costos</h3>
          <p style="color:#334155;font-size:14px">¿Eliminar los <b>{{ seleccion.length }}</b> costo(s) seleccionado(s)?</p>
          <div class="cf-pie"><span style="flex:1"></span>
            <button class="cf-cambiar" @click="confBorrar = false">Cancelar</button>
            <button class="cf-eliminar" @click="eliminar">Eliminar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

const hoy = new Date()
const mes = ref(hoy.getMonth() + 1); const anio = ref(hoy.getFullYear())
const bloqueado = ref(false)
const costos = ref<any[]>([]); const seleccion = ref<number[]>([])
const nuevo = ref(''); const antes = ref(0)
const cargando = ref(false); const guardando = ref(false); const confBorrar = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)

const money = (v: number) => (v ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const subtotal = computed(() => costos.value.reduce((s, c) => s + (Number(c.importe) || 0), 0))

async function cargar () {
  cargando.value = true; seleccion.value = []
  try {
    const { data } = await api.get('/costos-laborales', { params: { mes: mes.value, anio: anio.value } })
    costos.value = data.costos ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron leer los costos.', true) }
  finally { cargando.value = false }
}

async function aceptar () {
  if (!mes.value || mes.value < 1 || mes.value > 12) { flash('Mes inválido.', true); return }
  if (!anio.value) { flash('Año inválido.', true); return }
  await cargar(); bloqueado.value = true
}

async function guardarImporte (c: any) {
  const val = Number(c.importe) || 0
  if (val === Number(antes.value)) return
  try { await api.put(`/costos-laborales/${c.cod}`, { importe: val }) }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo actualizar el importe.', true); c.importe = antes.value }
}

async function agregar () {
  const det = nuevo.value.trim()
  if (!det) { flash('Debe ingresar la descripción del nuevo costo.', true); return }
  guardando.value = true
  try {
    await api.post('/costos-laborales', { mes: mes.value, anio: anio.value, descripcion: det })
    nuevo.value = ''; await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo agregar el costo.', true) }
  finally { guardando.value = false }
}

async function eliminar () {
  const ids = [...seleccion.value]; confBorrar.value = false
  try {
    for (const cod of ids) await api.delete(`/costos-laborales/${cod}`)
    flash('Costo(s) eliminado(s).'); await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

function reset () {
  bloqueado.value = false; costos.value = []; seleccion.value = []; nuevo.value = ''
  mes.value = hoy.getMonth() + 1; anio.value = hoy.getFullYear()
}
</script>

<style scoped>
.cf-view { display:flex; flex-direction:column; min-height:100%; }
.cf-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cf-ico { font-size:28px; } .cf-tx h1 { margin:0; font-size:18px; color:#1e293b; } .cf-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cf-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cf-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cf-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cf-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .cf-msg.ok { background:#d1fae5; color:#065f46; } .cf-msg.err { background:#fee2e2; color:#991b1b; }
.cf-body { padding:16px 18px; max-width:680px; }
.cf-periodo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fafdff; }
.cf-lbl { font-size:13px; font-weight:700; color:#374151; }
.cf-periodo label { font-size:13px; color:#475569; }
.cf-num { width:64px; border:1px solid #c8d8ea; border-radius:6px; padding:7px 8px; font-size:14px; font-weight:700; text-align:center; } .cf-num:disabled { background:#eef2f7; color:#64748b; } .cf-anio { width:80px; }
.cf-aceptar { background:#1b4332; color:#fff; border:none; border-radius:7px; padding:8px 18px; cursor:pointer; font-weight:800; font-size:13px; } .cf-aceptar:disabled { opacity:.5; }
.cf-cambiar { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:8px 14px; cursor:pointer; font-weight:700; font-size:13px; }
.cf-tabla { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; }
.cf-tabla th { background:#1b4332; color:#fff; text-align:left; padding:7px 10px; font-size:12px; } .cf-tabla th:last-child { text-align:right; }
.cf-tabla td { border-bottom:1px solid #eef2f7; padding:5px 10px; color:#1e293b; } .cf-tabla td.c { text-align:center; }
.cf-tabla td.imp { text-align:right; } .cf-tabla td.imp input { width:150px; text-align:right; border:1px solid #c8d8ea; border-radius:6px; padding:6px 8px; font-size:14px; font-weight:700; color:#1e293b; }
.cf-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; }
.cf-pie { display:flex; align-items:center; gap:10px; margin-top:12px; }
.cf-eliminar { background:#dc2626; color:#fff; border:none; border-radius:7px; padding:8px 14px; cursor:pointer; font-weight:700; font-size:13px; } .cf-eliminar:disabled { opacity:.4; }
.cf-sub-lbl { font-size:14px; font-weight:800; color:#14532d; text-decoration:underline; }
.cf-sub { font-size:18px; font-weight:800; color:#1e293b; min-width:110px; text-align:right; }
.cf-agregar { display:flex; align-items:center; gap:10px; margin-top:18px; border-top:1px dashed #e2e8f0; padding-top:14px; }
.cf-agregar label { font-size:13px; font-weight:700; color:#374151; }
.cf-agregar input { flex:1; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; color:#1e293b; }
.cf-btn-add { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:9px 20px; cursor:pointer; font-weight:800; font-size:13px; } .cf-btn-add:disabled { opacity:.5; }
.cf-elija { text-align:center; color:#94a3b8; padding:28px; }
.cf-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.cf-help-md { background:#fff; border-radius:14px; padding:22px; width:min(520px,94vw); } .cf-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .cf-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
