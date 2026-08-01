<!-- EmpleadosContratistaView.vue — Empleados de Contratistas (contra_emplea). -->
<template>
  <div class="ec-view">
    <div class="ec-cab">
      <div class="ec-cab-ico">👥</div>
      <div class="ec-cab-tx"><h1>Empleados de Contratistas</h1><p>Altas, bajas y modificaciones de empleados por contratista</p></div>
      <button class="ec-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ec-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <EmpleadosContratistaAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/empleados-contratista" titulo="Asistente IA — Empleados de Contratistas"
            subtitulo="Preguntá sobre la carga de empleados"
            :sugerencias="['¿Cómo agrego un empleado?','¿Qué significa la fila amarilla?','¿Por qué una fila está en rojo?','¿Cómo confirmo los cambios?']"
            @close="modalIA = false" />

    <div class="ec-filtros">
      <span class="ec-lbl">Contratista</span>
      <select v-model.number="contratista" class="ec-sel" @change="cargar">
        <option :value="0">— Elegir contratista —</option>
        <option v-for="c in contratistas" :key="c.cod" :value="c.cod">{{ c.nombre }}</option>
      </select>
      <span v-if="contratista" class="ec-leyendas">
        <i class="ec-lg rojo"></i> ART vencida
        <i class="ec-lg amar"></i> Ocupado en obra
      </span>
    </div>

    <div v-if="contratista" class="ec-emp-acc">
      <button class="ec-mini" @click="agregar">＋ Agregar empleado</button>
      <button class="ec-mini del" :disabled="!selCount" @click="eliminar">🗑️ Eliminar marcados ({{ selCount }})</button>
      <button class="ec-mini ok" :disabled="guardando" @click="confirmar">💾 {{ guardando ? 'Guardando…' : 'CONFIRMAR EMPLEADOS' }}</button>
      <span class="ec-tot">{{ empleados.length }} empleado{{ empleados.length === 1 ? '' : 's' }}</span>
    </div>

    <div v-if="contratista" class="ec-wrap">
      <table class="ec-grid">
        <thead><tr>
          <th style="width:34px"></th><th>Apellido y Nombre</th><th>DNI</th><th>CUIL/CUIT</th>
          <th>Teléfono</th><th>Celular</th><th>Venc. ART</th>
        </tr></thead>
        <tbody>
          <tr v-for="(e, i) in empleados" :key="e.unico || ('n' + i)" :class="filaClase(e)">
            <td style="text-align:center"><input v-model="e._sel" type="checkbox" /></td>
            <td><input v-model="e.nombre" type="text" class="ec-in" /></td>
            <td><input v-model="e.dni" type="text" class="ec-in chico" /></td>
            <td><input v-model="e.cuil" type="text" class="ec-in chico" /></td>
            <td><input v-model="e.telefono" type="text" class="ec-in" /></td>
            <td><input v-model="e.celular" type="text" class="ec-in" /></td>
            <td><input v-model="e.venc_art" type="date" class="ec-in" @change="recalcVenc(e)" /></td>
          </tr>
          <tr v-if="!empleados.length"><td colspan="7" class="ec-vacio">Este contratista no tiene empleados cargados.</td></tr>
        </tbody>
      </table>
    </div>
    <div v-else class="ec-vacio big">Elegí un contratista para ver y editar sus empleados.</div>

    <p v-if="msg" :class="['ec-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/auth'
import EmpleadosContratistaAyuda from '@/components/EmpleadosContratistaAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date().toISOString().slice(0, 10)
interface Emp { unico: number; nombre: string; dni: string; cuil: string; telefono: string; celular: string; venc_art: string; art_vencida: boolean; ocupado: boolean; _sel: boolean }

const contratistas = ref<{ cod: number; nombre: string }[]>([])
const contratista = ref(0)
const empleados = ref<Emp[]>([])
const guardando = ref(false)
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3500) }

const selCount = computed(() => empleados.value.filter(e => e._sel).length)
const filaClase = (e: Emp) => e.art_vencida ? 'venc' : (e.ocupado ? 'ocu' : '')
const recalcVenc = (e: Emp) => { e.art_vencida = !!e.venc_art && e.venc_art < hoy }

const cargar = async () => {
  empleados.value = []
  if (!contratista.value) return
  try { empleados.value = (await api.get(`/contratistas-externos/${contratista.value}/empleados`)).data.map((e: any) => ({ ...e, _sel: false })) }
  catch (e) { console.error(e); flash('No se pudieron cargar los empleados.', true) }
}
const agregar = () => empleados.value.unshift({ unico: 0, nombre: '', dni: '', cuil: '', telefono: '', celular: '', venc_art: '', art_vencida: true, ocupado: false, _sel: false })
const eliminar = () => { empleados.value = empleados.value.filter(e => !e._sel) }
const confirmar = async () => {
  if (!confirm('¿Confirma los empleados del contratista?')) return
  guardando.value = true
  try { const { data } = await api.post(`/contratistas-externos/${contratista.value}/empleados`, { rows: empleados.value }); flash(`Empleados confirmados (${data.total})`); await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo confirmar.', true) }
  finally { guardando.value = false }
}

onMounted(async () => {
  try { contratistas.value = (await api.get('/contratistas-externos')).data.map((c: any) => ({ cod: c.cod, nombre: c.nombre })) }
  catch (e) { console.error(e) }
})
</script>

<style scoped>
.ec-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ec-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ec-cab-ico { font-size:28px; } .ec-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ec-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ec-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ec-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ec-filtros { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; flex-wrap:wrap; }
.ec-lbl { font-size:13px; font-weight:700; color:#1b4332; }
.ec-sel { border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:13px; min-width:280px; }
.ec-leyendas { margin-left:auto; display:flex; align-items:center; gap:12px; font-size:12px; color:#6b7280; }
.ec-lg { width:13px; height:13px; border-radius:3px; display:inline-block; margin-right:4px; vertical-align:-2px; }
.ec-lg.rojo { background:#fecaca; border:1px solid #f87171; } .ec-lg.amar { background:#fef9c3; border:1px solid #facc15; }
.ec-emp-acc { display:flex; align-items:center; gap:10px; padding:12px 18px 0; }
.ec-mini { background:#fff; border:1px solid #cbd5e1; padding:7px 13px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; }
.ec-mini:hover { background:#f0faf4; } .ec-mini.del:hover { background:#fee2e2; } .ec-mini:disabled { opacity:.5; cursor:not-allowed; }
.ec-mini.ok { background:#16a34a; color:#fff; border-color:#16a34a; } .ec-mini.ok:hover:not(:disabled) { background:#15803d; }
.ec-tot { margin-left:auto; font-size:12px; color:#6b7280; }
.ec-wrap { margin:12px 18px; border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:68vh; }
.ec-grid { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.ec-grid th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:8px 10px; font-size:12px; text-align:left; }
.ec-grid td { padding:3px 8px; border-bottom:1px solid #f1f5f9; }
.ec-grid tr.venc td { background:#fee2e2; } .ec-grid tr.ocu td { background:#fef9c3; }
.ec-in { width:100%; min-width:90px; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:13px; outline:none; }
.ec-in.chico { min-width:70px; } .ec-in:focus { border-color:#40916c; box-shadow:0 0 0 2px rgba(64,145,108,.15); }
.ec-vacio { padding:30px; text-align:center; color:#9ca3af; font-size:14px; } .ec-vacio.big { padding:60px; }
.ec-msg { padding:8px 14px; margin:12px 18px; font-size:13px; border-radius:6px; } .ec-msg.ok { background:#dcfce7; color:#166534; } .ec-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
