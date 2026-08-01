<!-- SiniestrosSeguimientoView.vue — ART Siniestros: Seguimiento (art_siniestros_seguimiento.scx). Agenda de movimientos. -->
<template>
  <div class="sc-view">
    <div class="sc-cab">
      <div class="sc-ico">🗓️</div>
      <div class="sc-tx"><h1>ART Siniestros — Seguimiento</h1><p>Agenda de movimientos de un siniestro</p></div>
      <button class="sc-ia" @click="modalIA = true">🤖 IA</button>
      <button class="sc-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="sc-reset" @click="reset">↺ Reset</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/siniestros-seguimiento" titulo="Asistente IA — Seguimiento de Siniestro"
            subtitulo="Preguntá sobre la agenda de seguimiento"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego un movimiento?','¿Puedo modificar o borrar un movimiento?']"
            @close="modalIA = false" />

    <transition name="fade"><div v-if="msg" :class="['sc-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="sc-body">
      <div class="sc-nro">
        <label>Nro. Siniestro</label>
        <input v-model.number="nro" type="number" min="1" @keyup.enter="consultar" placeholder="Número…" />
        <button class="sc-buscar" :disabled="cargando" @click="consultar">{{ cargando ? '⟳…' : 'Consultar' }}</button>
        <button class="sc-lupa" @click="lupa = true">🔍 Buscar</button>
      </div>
      <SiniestroBuscar v-if="lupa" @select="onLupa" @close="lupa = false" />

      <template v-if="s">
        <div class="sc-datos">
          <div><span>Fecha</span><b>{{ fmt(s.fecha) }}</b></div>
          <div><span>Empleado</span><b>{{ s.empleado }} — {{ s.empleado_nombre }}</b></div>
        </div>

        <div class="sc-agenda">
          <div class="sc-agenda-tit">Movimientos de agenda</div>
          <table class="sc-tabla">
            <thead><tr><th style="width:120px">Fecha</th><th>Detalle</th><th style="width:120px">Acciones</th></tr></thead>
            <tbody>
              <tr v-for="m in agenda" :key="m.imp" :class="{ sel: editImp === m.imp }">
                <td>{{ fmt(m.fecha) }}</td>
                <td class="det">{{ m.detalle }}</td>
                <td class="acc">
                  <button class="mini" @click="editar(m)">✏️</button>
                  <button class="mini del" @click="borrar(m)">🗑️</button>
                </td>
              </tr>
              <tr v-if="!agenda.length"><td colspan="3" class="vacio">Sin movimientos cargados.</td></tr>
            </tbody>
          </table>

          <div class="sc-form">
            <div class="sc-form-tit">{{ editImp ? 'Modificar movimiento' : 'Agregar movimiento' }}</div>
            <div class="sc-form-row">
              <div><label>Fecha</label><input v-model="fMov.fecha" v-enter-next type="date" /></div>
              <div class="flex1"><label>Detalle</label><input v-model="fMov.detalle" v-enter-next type="text" maxlength="250" @keyup.enter="guardar" placeholder="Detalle del movimiento…" /></div>
            </div>
            <div class="sc-form-acc">
              <button class="sc-guardar" :disabled="guardando" @click="guardar">{{ guardando ? '⟳…' : editImp ? 'MODIFICAR' : 'AGREGAR' }}</button>
              <button v-if="editImp" class="sc-cancel" @click="cancelar">Cancelar</button>
            </div>
          </div>
        </div>
      </template>
      <div v-else-if="!cargando" class="sc-elija">Ingrese un número de siniestro para ver su agenda.</div>
    </div>

    <Teleport to="body">
      <div v-if="ayuda" class="sc-ov" @click.self="ayuda = false">
        <div class="sc-help-md">
          <h3>❓ Ayuda — Seguimiento de Siniestro</h3>
          <ul>
            <li>Buscá el siniestro por número para ver su <b>agenda de movimientos</b>.</li>
            <li>Completá <b>fecha</b> y <b>detalle</b> y presioná <b>AGREGAR</b> para cargar un movimiento.</li>
            <li>Usá ✏️ para <b>modificar</b> un movimiento y 🗑️ para <b>borrarlo</b>.</li>
          </ul>
          <div class="sc-acc"><span style="flex:1"></span><button class="sc-buscar" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
      <div v-if="confBorrar" class="sc-ov" @click.self="confBorrar = null">
        <div class="sc-help-md">
          <h3>🗑️ Borrar movimiento</h3>
          <p style="color:#334155;font-size:14px">¿Borrar el movimiento del <b>{{ fmt(confBorrar.fecha) }}</b>?</p>
          <div class="sc-acc"><span style="flex:1"></span>
            <button class="sc-cancel" @click="confBorrar = null">Cancelar</button>
            <button class="sc-guardar del" @click="confirmarBorrar">Borrar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'
import SiniestroBuscar from '@/components/SiniestroBuscar.vue'

const nro = ref<number | null>(null); const lupa = ref(false)
const s = ref<any>(null); const agenda = ref<any[]>([])
const cargando = ref(false); const guardando = ref(false)
const msg = ref(''); const msgErr = ref(false); const modalIA = ref(false); const ayuda = ref(false)
const editImp = ref<number | null>(null); const confBorrar = ref<any>(null)
const fMov = reactive({ fecha: '', detalle: '' })

const fmt = (v: string) => v ? v.split('-').reverse().join('/') : ''
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const onLupa = (n: number) => { lupa.value = false; nro.value = n; consultar() }

async function consultar () {
  if (!nro.value || nro.value <= 0) { flash('Ingrese el número de siniestro.', true); return }
  cargando.value = true; s.value = null; agenda.value = []; cancelar()
  try {
    const { data } = await api.get(`/siniestros/${nro.value}`)
    s.value = data.siniestro
    await cargarAgenda()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo consultar el siniestro.', true) }
  finally { cargando.value = false }
}

async function cargarAgenda () {
  const { data } = await api.get(`/siniestros/${nro.value}/agenda`)
  agenda.value = data ?? []
}

function editar (m: any) { editImp.value = m.imp; fMov.fecha = m.fecha; fMov.detalle = m.detalle }
function cancelar () { editImp.value = null; fMov.fecha = ''; fMov.detalle = '' }

async function guardar () {
  if (!fMov.fecha) { flash('Debe ingresar la fecha.', true); return }
  if (!fMov.detalle.trim()) { flash('Debe ingresar el detalle.', true); return }
  guardando.value = true
  try {
    if (editImp.value) {
      await api.put(`/siniestros/agenda/${editImp.value}`, { fecha: fMov.fecha, detalle: fMov.detalle })
      flash('Movimiento modificado.')
    } else {
      await api.post(`/siniestros/${nro.value}/agenda`, { fecha: fMov.fecha, detalle: fMov.detalle })
      flash('Movimiento cargado.')
    }
    cancelar(); await cargarAgenda()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar el movimiento.', true) }
  finally { guardando.value = false }
}

function borrar (m: any) { confBorrar.value = m }
async function confirmarBorrar () {
  const m = confBorrar.value; confBorrar.value = null
  try {
    await api.delete(`/siniestros/agenda/${m.imp}`)
    flash('Movimiento borrado.')
    if (editImp.value === m.imp) cancelar()
    await cargarAgenda()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar el movimiento.', true) }
}

function reset () { nro.value = null; s.value = null; agenda.value = []; cancelar() }
</script>

<style scoped>
.sc-view { display:flex; flex-direction:column; min-height:100%; }
.sc-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.sc-ico { font-size:28px; } .sc-tx h1 { margin:0; font-size:19px; color:#1e293b; } .sc-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.sc-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-reset { background:#eef2f7; color:#475569; border:none; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.sc-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .sc-msg.ok { background:#d1fae5; color:#065f46; } .sc-msg.err { background:#fee2e2; color:#991b1b; }
.sc-body { padding:16px 18px; max-width:900px; }
.sc-nro { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.sc-nro label { font-size:13px; font-weight:700; color:#374151; }
.sc-nro input { width:140px; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:15px; font-weight:700; color:#1e293b; }
.sc-buscar { background:#7f1d1d; color:#fff; border:none; padding:9px 18px; border-radius:7px; cursor:pointer; font-weight:800; font-size:13px; } .sc-buscar:disabled { opacity:.5; }
.sc-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; }
.sc-datos { display:flex; gap:24px; padding:12px 14px; background:#fafdff; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:16px; flex-wrap:wrap; }
.sc-datos span { font-size:11px; color:#6b7280; display:block; } .sc-datos b { font-size:14px; color:#1e293b; }
.sc-agenda-tit { font-size:13px; font-weight:800; color:#14532d; margin-bottom:8px; }
.sc-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.sc-tabla th { background:#1b4332; color:#fff; text-align:left; padding:8px 10px; font-size:12px; }
.sc-tabla td { border-bottom:1px solid #eef2f7; padding:7px 10px; color:#1e293b; }
.sc-tabla tr.sel td { background:#fef9c3; }
.sc-tabla td.det { white-space:pre-wrap; } .sc-tabla td.acc { text-align:center; white-space:nowrap; } .sc-tabla td.vacio { text-align:center; color:#94a3b8; padding:16px; }
.mini { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:13px; margin:0 2px; } .mini.del { background:#fee2e2; }
.sc-form { margin-top:16px; border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#fff; }
.sc-form-tit { font-size:12px; font-weight:800; color:#1e293b; margin-bottom:10px; }
.sc-form-row { display:flex; gap:12px; } .sc-form-row .flex1 { flex:1; }
.sc-form-row label { font-size:12px; font-weight:600; color:#374151; display:block; }
.sc-form-row input { border:1px solid #c8d8ea; border-radius:7px; padding:8px 10px; font-size:14px; color:#1e293b; margin-top:4px; width:100%; box-sizing:border-box; }
.sc-form-acc { display:flex; gap:8px; margin-top:12px; }
.sc-guardar { background:#16a34a; color:#fff; border:none; border-radius:7px; padding:9px 20px; cursor:pointer; font-weight:800; font-size:13px; } .sc-guardar:disabled { opacity:.5; } .sc-guardar.del { background:#dc2626; }
.sc-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:700; font-size:13px; }
.sc-elija { text-align:center; color:#94a3b8; padding:30px; }
.sc-acc { display:flex; align-items:center; gap:8px; margin-top:14px; }
.sc-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:50px 18px; }
.sc-help-md { background:#fff; border-radius:14px; padding:22px; width:min(520px,94vw); } .sc-help-md h3 { margin:0 0 12px; color:#1a3a5c; } .sc-help-md ul { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
