<!--
  EmpresasView.vue — ABM de Empresas (tabla empresas).
  Alta, edición y baja. Código incremental (lo asigna el backend).
-->
<template>
  <div class="em-view">
    <div class="em-cab">
      <div class="em-cab-ico">🏢</div>
      <div class="em-cab-tx">
        <h1>Empresas</h1>
        <p>{{ lista.length }} empresa{{ lista.length === 1 ? '' : 's' }}</p>
      </div>
      <button class="em-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="em-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="em-btn-nuevo" @click="abrirNuevo">＋ Nueva</button>
    </div>

    <EmpresasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/empresas"
            titulo="Asistente IA — Empresas"
            subtitulo="Preguntá sobre el ABM de empresas"
            :sugerencias="[
              '¿Para qué sirve este módulo?',
              '¿Qué es la base relacionada?',
              '¿Por qué no me deja eliminar una empresa?',
              '¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="em-card">
      <div v-if="cargando" class="em-vacio">⟳ Cargando…</div>
      <div v-else-if="lista.length === 0" class="em-vacio">Sin empresas cargadas</div>
      <table v-else class="em-tabla">
        <thead>
          <tr>
            <th style="width:80px">Código</th>
            <th>Nombre</th>
            <th style="width:150px">CUIT</th>
            <th style="width:130px">Provincia</th>
            <th style="width:120px">Base</th>
            <th style="width:120px;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in lista" :key="e.cod">
            <td class="em-cod">{{ e.cod }}</td>
            <td><b>{{ e.nombre }}</b></td>
            <td>{{ e.cuit }}</td>
            <td>{{ e.provincia }}</td>
            <td><span class="em-chip">{{ baseLabel(e.base) }}</span></td>
            <td style="text-align:center">
              <button class="em-icono" title="Editar" @click="abrirEditar(e)">✏️</button>
              <button class="em-icono em-del" title="Eliminar" @click="eliminar(e)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['em-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <!-- Modal alta/edición -->
    <Teleport to="body">
      <div v-if="modal" class="em-overlay" @click.self="modal = false">
        <div class="em-modal" v-enter-next>
          <h3>{{ editando ? 'Editar empresa' : 'Nueva empresa' }}</h3>

          <div class="em-seccion">Datos Generales</div>
          <div class="em-campo">
            <label>Código</label>
            <input :value="editando ? form.cod : '(automático)'" disabled />
          </div>
          <div class="em-campo">
            <label>Nombre <span class="req">*</span></label>
            <input ref="inputNom" v-model="form.nombre" type="text" maxlength="100" />
          </div>
          <div class="em-campo">
            <label>Domicilio</label>
            <input v-model="form.domicilio" type="text" maxlength="100" />
          </div>
          <div class="em-fila3">
            <div class="em-campo"><label>Provincia</label><input v-model="form.provincia" type="text" maxlength="30" /></div>
            <div class="em-campo"><label>Cód. Postal (CPA)</label><input v-model="form.cpa" type="text" maxlength="10" /></div>
            <div class="em-campo"><label>C.U.I.T.</label><input v-model="form.cuit" type="text" maxlength="14" /></div>
          </div>
          <div class="em-fila2">
            <div class="em-campo"><label>Caja</label><input v-model="form.caja" type="text" maxlength="20" /></div>
            <div class="em-campo"><label>Nro. Inscripción</label><input v-model="form.inscripcion" type="text" maxlength="10" /></div>
          </div>

          <div class="em-seccion">Indicadores</div>
          <div class="em-fila3">
            <div class="em-campo"><label>Gran Contribuyente</label>
              <div class="em-radios">
                <label><input type="radio" value="S" v-model="form.gran_contrib" /> SI</label>
                <label><input type="radio" value="N" v-model="form.gran_contrib" /> NO</label>
              </div>
            </div>
            <div class="em-campo"><label>Servicios Eventuales</label>
              <div class="em-radios">
                <label><input type="radio" value="S" v-model="form.servicios_ev" /> SI</label>
                <label><input type="radio" value="N" v-model="form.servicios_ev" /> NO</label>
              </div>
            </div>
            <div class="em-campo"><label>Redondeo</label>
              <div class="em-radios">
                <label><input type="radio" value="S" v-model="form.redondeo" /> SI</label>
                <label><input type="radio" value="N" v-model="form.redondeo" /> NO</label>
              </div>
            </div>
          </div>
          <div class="em-fila2">
            <div class="em-campo"><label>Base de Datos Relacionada</label>
              <div class="em-radios">
                <label><input type="radio" :value="1" v-model.number="form.base" /> SILCAR</label>
                <label><input type="radio" :value="2" v-model.number="form.base" /> LOGÍSTICA</label>
              </div>
            </div>
            <div class="em-campo"><label>Cód. cuenta HABERES</label><input v-model="form.cuenta_haberes" type="text" maxlength="20" /></div>
          </div>

          <div class="em-seccion">Banco de Santa Fe</div>
          <div class="em-fila2">
            <div class="em-campo"><label>Código Empresa</label><input v-model="form.bsf_empresa" type="text" maxlength="20" /></div>
            <div class="em-campo"><label>Código Convenio</label><input v-model="form.bsf_convenio" type="text" maxlength="20" /></div>
          </div>

          <p v-if="modalError" class="em-modal-err">⚠️ {{ modalError }}</p>
          <div class="em-modal-btns">
            <button class="em-btn-guardar" :disabled="guardando" @click="guardar">💾 {{ guardando ? 'Guardando…' : 'Guardar' }}</button>
            <button class="em-btn-cancel" @click="modal = false">Cancelar</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import EmpresasAyuda from '@/components/EmpresasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false)
const modalIA = ref(false)

const baseLabel = (v: any) => Number(v) === 2 ? 'Logística' : 'SILCAR'

interface Empresa {
  cod: number; nombre: string; domicilio: string; provincia: string; cpa: string; cuit: string;
  caja: string; inscripcion: string; gran_contrib: string; servicios_ev: string; redondeo: string;
  base: number; cuenta_haberes: string; bsf_empresa: string; bsf_convenio: string
}

const lista = ref<Empresa[]>([])
const cargando = ref(false)
const msg = ref(''); const msgError = ref(false)

const modal = ref(false)
const editando = ref(false)
const guardando = ref(false)
const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<any>(vacio())

function vacio () {
  return {
    cod: null, nombre: '', domicilio: '', provincia: '', cpa: '', cuit: '', caja: '', inscripcion: '',
    gran_contrib: 'N', servicios_ev: 'N', redondeo: 'N', base: 1, cuenta_haberes: '', bsf_empresa: '', bsf_convenio: '',
  }
}

const cargar = async () => {
  cargando.value = true
  try { lista.value = (await api.get('/empresas')).data }
  catch (e) { console.error(e) } finally { cargando.value = false }
}
const flash = (texto: string, error = false) => { msg.value = texto; msgError.value = error; setTimeout(() => msg.value = '', 3000) }

const abrirNuevo = async () => {
  editando.value = false; form.value = vacio(); modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}
const abrirEditar = async (e: Empresa) => {
  editando.value = true; form.value = { ...e, base: Number(e.base) || 1 }; modalError.value = ''; modal.value = true
  await nextTick(); inputNom.value?.focus()
}

const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  const f = form.value
  const payload = {
    nombre: f.nombre, domicilio: f.domicilio, provincia: f.provincia, cpa: f.cpa, cuit: f.cuit,
    caja: f.caja, inscripcion: f.inscripcion, gran_contrib: f.gran_contrib, servicios_ev: f.servicios_ev,
    redondeo: f.redondeo, base: f.base, cuenta_haberes: f.cuenta_haberes, bsf_empresa: f.bsf_empresa, bsf_convenio: f.bsf_convenio,
  }
  try {
    if (editando.value) { await api.put(`/empresas/${f.cod}`, payload); flash('Empresa actualizada') }
    else { await api.post('/empresas', payload); flash('Empresa creada') }
    modal.value = false
    await cargar()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message
      ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
      ?? 'No se pudo guardar.'
  } finally { guardando.value = false }
}

const eliminar = async (e: Empresa) => {
  if (!confirm(`¿Eliminar la empresa "${e.nombre}"?`)) return
  try { await api.delete(`/empresas/${e.cod}`); flash('Empresa eliminada'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

onMounted(cargar)
</script>

<style scoped>
.em-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.em-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.em-cab-ico { font-size:28px; }
.em-cab-tx h1 { margin:0; font-size:20px; color:#1e293b; }
.em-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.em-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.em-btn-ia:hover { filter:brightness(1.1); }
.em-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.em-btn-ayuda:hover { background:#f0faf4; }
.em-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.em-btn-nuevo:hover { background:#16a34a; }

.em-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; max-width:920px; }
.em-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; }
.em-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.em-tabla th { background:#e5e7eb; color:#1f2937; font-weight:700; text-align:left; padding:8px 12px; border-bottom:1px solid #cbd5e1; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
.em-tabla td { padding:9px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; }
.em-tabla tr:hover td { background:#f8fafc; }
.em-cod { font-family:monospace; font-weight:700; color:#1b4332; }
.em-chip { background:#e0f2fe; color:#075985; border-radius:20px; padding:2px 10px; font-size:12px; font-weight:600; }
.em-icono { background:transparent; border:none; cursor:pointer; font-size:15px; padding:2px 6px; border-radius:4px; }
.em-icono:hover { background:#f0faf4; transform:scale(1.15); }
.em-del:hover { background:#fee2e2; }
.em-msg { padding:8px 12px; margin:6px; font-size:13px; border-radius:6px; }
.em-msg.ok { background:#dcfce7; color:#166534; } .em-msg.err { background:#fee2e2; color:#b91c1c; }

.em-overlay { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.em-modal { background:#fff; border-radius:12px; padding:22px 26px; width:min(640px,96vw); box-shadow:0 20px 50px rgba(0,0,0,.4); max-height:92vh; overflow:auto; }
.em-modal h3 { margin:0 0 14px; color:#1b4332; font-size:17px; }
.em-seccion { font-size:11px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.6px; margin:14px 0 10px; padding-bottom:5px; border-bottom:1px solid #eef2f7; }
.em-seccion:first-of-type { margin-top:4px; }
.em-campo { margin-bottom:12px; display:flex; flex-direction:column; gap:5px; }
.em-campo label { font-size:12px; font-weight:600; color:#374151; }
.em-campo .req { color:#dc2626; }
.em-campo input { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.em-campo input:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); }
.em-campo input:disabled { background:#f1f5f9; color:#94a3b8; }
.em-fila2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.em-fila3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.em-radios { display:flex; gap:14px; font-size:13px; padding-top:6px; color:#374151; }
.em-radios label { display:flex; align-items:center; gap:5px; cursor:pointer; }
.em-modal-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:8px 0 0; }
.em-modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
.em-btn-guardar { background:#16a34a; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.em-btn-guardar:hover:not(:disabled){ background:#15803d; } .em-btn-guardar:disabled { background:#cbd5e1; }
.em-btn-cancel { background:#fff; color:#475569; border:1px solid #d1d5db; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
.em-btn-cancel:hover { background:#f8fafc; }
@media (max-width:560px){ .em-fila2,.em-fila3{ grid-template-columns:1fr; } }
</style>
