<!-- ViajesView.vue — Viajes del personal: ABM unificado (grilla + alta + editar + eliminar).
     Reemplaza Viajes Agregar / Editar.
     Preparado para abrirse desde la ficha del empleado (prop :empleado) — Propuesta "módulo madre". -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">🧳</div>
      <div class="ab-tx"><h1>Viajes del Personal</h1><p>Alta, edición y baja de viajes imputados a empleados</p></div>
      <button class="ab-ayuda" @click="ayuda = true">❓ Ayuda</button>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-if="!empBloqueado" v-model="filtroTxt" class="ab-search" placeholder="🔍 Buscar por empleado, destino o dominio…" />
      <label class="ab-rango">Desde <input v-model="desde" type="date" @change="cargarGrilla" /></label>
      <label class="ab-rango">Hasta <input v-model="hasta" type="date" @change="cargarGrilla" /></label>
      <span class="ab-count">{{ filtradas.length }} viaje(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo viaje</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead>
          <tr>
            <th>Empleado</th>
            <th style="width:100px">Imputación</th>
            <th style="width:135px">Salida</th>
            <th style="width:135px">Llegada</th>
            <th style="width:55px" class="c">Días</th>
            <th>Destino</th>
            <th style="width:70px" class="r">Km</th>
            <th style="width:80px">Dominio</th>
            <th style="width:90px" class="c">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando"><td colspan="9" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="9" class="ab-vacio">No hay viajes para mostrar.</td></tr>
          <tr v-for="v in filtradas" :key="v.UNICO" @dblclick="abrirEditar(v)">
            <td>{{ v.PVI_PER }} — {{ (v.PVI_NOM || '').trim() }}</td>
            <td>{{ fdate(v.PVI_FEC) }}</td>
            <td>{{ fdt(v.PVI_FSA) }}</td>
            <td>{{ fdt(v.PVI_FEN) }}</td>
            <td class="c">{{ v.PVI_DIA }}</td>
            <td>{{ (v.PVI_DES || '').trim() }}</td>
            <td class="r">{{ v.PVI_KMS }}</td>
            <td>{{ (v.PVI_VEH || '').trim() }}</td>
            <td class="c ab-acc">
              <button class="ab-b edi" title="Editar"   @click="abrirEditar(v)">✏️</button>
              <button class="ab-b del" title="Eliminar" @click="eliminar(v)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ─────────── Modal alta / edición ─────────── -->
    <Teleport to="body">
      <div v-if="modo" class="ab-ov" @click.self="cerrar">
        <div class="ab-md">
          <div class="ab-md-head"><span>{{ modo === 'nuevo' ? '＋ Nuevo viaje' : '✏️ Editar viaje' }}</span><button class="ab-x" @click="cerrar">✕</button></div>
          <div class="ab-md-body">
            <div class="ab-fila">
              <label>Personal</label>
              <div class="ab-emp">
                <input :value="form.PVI_PER || ''" type="text" class="ab-cod" readonly />
                <button v-if="modo === 'nuevo' && !empBloqueado" type="button" class="ab-lupa" @click="lupaOpen = true">🔍 Buscar</button>
                <input :value="form.PVI_NOM" type="text" class="ab-nom" readonly placeholder="Nombre del empleado" />
                <img v-if="foto" :src="foto" alt="Foto" class="ab-foto" />
              </div>
            </div>
            <div class="ab-fila-2">
              <div><label>Fecha imputación</label><input v-model="form.PVI_FEC" type="date" /></div>
              <div><label>Días imputados</label><input v-model.number="form.PVI_DIA" type="number" min="0" /></div>
            </div>
            <div class="ab-fila-2">
              <div><label>Salida</label><input v-model="form.PVI_FSA" type="datetime-local" /></div>
              <div><label>Llegada</label><input v-model="form.PVI_FEN" type="datetime-local" /></div>
            </div>
            <div class="ab-fila"><label>Destino</label><input v-model="form.PVI_DES" type="text" maxlength="100" /></div>
            <div class="ab-fila-2">
              <div><label>Kilómetros</label><input v-model.number="form.PVI_KMS" type="number" min="0" /></div>
              <div><label>Dominio vehículo</label><input v-model="form.PVI_VEH" type="text" maxlength="7" style="text-transform:uppercase" /></div>
            </div>
            <div class="ab-fila"><label>Observación</label><input v-model="form.PVI_OBS" type="text" maxlength="100" /></div>
            <p v-if="formError" class="ab-md-err">⚠️ {{ formError }}</p>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span>
            <button class="ab-cancel" @click="cerrar">Cancelar</button>
            <button class="ab-confirm" :disabled="proc" @click="guardar">{{ proc ? '⟳ Guardando…' : '✔ Confirmar' }}</button>
          </div>
        </div>
      </div>

      <div v-if="ayuda" class="ab-ov ab-ov2" @click.self="ayuda = false">
        <div class="ab-md-small">
          <h3>❓ Ayuda — Viajes</h3>
          <ul class="ab-help">
            <li>La <b>grilla</b> lista los viajes; filtrá por rango de fechas o buscá por empleado/destino/dominio.</li>
            <li><b>＋ Nuevo viaje</b>: elegí el empleado y cargá los datos. La <b>salida no puede ser posterior a la llegada</b>.</li>
            <li>En cada fila: <b>✏️ Editar</b> (no cambia el empleado) y <b>🗑️ Eliminar</b>.</li>
          </ul>
          <div class="ab-md-foot"><span style="flex:1"></span><button class="ab-confirm" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>

    <EmpleadoBuscar v-if="lupaOpen" @select="onEmpleado" @close="lupaOpen = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import api from '@/services/auth'
import EmpleadoBuscar from '@/components/EmpleadoBuscar.vue'

const props = withDefaults(defineProps<{ empleado?: number; empleadoNombre?: string }>(), { empleado: 0, empleadoNombre: '' })
const empBloqueado = computed(() => !!props.empleado)

const hoyIso = () => new Date().toISOString().slice(0, 10)
const ahoraLocal = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16) }
function nuevoForm () { return { UNICO: 0, PVI_PER: null as number | null, PVI_NOM: '', PVI_FEC: hoyIso(), PVI_DIA: 1, PVI_FSA: ahoraLocal(), PVI_FEN: ahoraLocal(), PVI_DES: '', PVI_KMS: 0, PVI_VEH: '', PVI_OBS: '' } }

const rows = ref<any[]>([]); const cargando = ref(false)
const filtroTxt = ref(''); const desde = ref(''); const hasta = ref('')
const modo = ref<'' | 'nuevo' | 'editar'>('')
const form = reactive(nuevoForm())
const foto = ref(''); const lupaOpen = ref(false); const ayuda = ref(false)
const proc = ref(false); const formError = ref('')
const msg = ref(''); const msgErr = ref(false)

const fdate = (d: string) => d ? String(d).slice(0, 10).split('-').reverse().join('/') : ''
const fdt = (d: string) => { if (!d) return ''; const s = String(d).replace('T', ' '); const [fecha, hora = ''] = s.split(' '); return `${fecha.split('-').reverse().join('/')} ${hora.slice(0, 5)}` }
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3500) }
const toLocal = (d: string) => { if (!d) return ''; const s = String(d).replace(' ', 'T'); return s.slice(0, 16) }
const esVacia = (d: string) => !d || String(d).slice(0, 4) === '1900'

const filtradas = computed(() => {
  let base = rows.value
  if (props.empleado) base = base.filter(v => Number(v.PVI_PER) === props.empleado)
  const q = filtroTxt.value.trim().toLowerCase()
  if (!q) return base
  return base.filter(v => String(v.PVI_PER).includes(q) || (v.PVI_NOM || '').toLowerCase().includes(q)
    || (v.PVI_DES || '').toLowerCase().includes(q) || (v.PVI_VEH || '').toLowerCase().includes(q))
})

async function cargarGrilla () {
  cargando.value = true
  try {
    const params: any = {}
    if (desde.value && hasta.value) { params.desde = desde.value; params.hasta = hasta.value }
    rows.value = (await api.get('/viajes', { params })).data ?? []
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista.', true) }
  finally { cargando.value = false }
}

async function cargarFoto (cod: number) { foto.value = ''; try { foto.value = (await api.get(`/empleados/${cod}/foto`)).data?.foto ?? '' } catch { foto.value = '' } }
function onEmpleado (e: any) { form.PVI_PER = Number(e.cod ?? e.PER_COD); form.PVI_NOM = (e.nombre ?? e.PER_NOM ?? '').trim(); lupaOpen.value = false; if (form.PVI_PER) cargarFoto(form.PVI_PER) }

function abrirNuevo () {
  Object.assign(form, nuevoForm()); foto.value = ''; formError.value = ''
  if (props.empleado) { form.PVI_PER = props.empleado; form.PVI_NOM = props.empleadoNombre; cargarFoto(props.empleado) }
  modo.value = 'nuevo'
}
function abrirEditar (v: any) {
  Object.assign(form, {
    UNICO: v.UNICO, PVI_PER: v.PVI_PER, PVI_NOM: (v.PVI_NOM || '').trim(),
    PVI_FEC: esVacia(v.PVI_FEC) ? '' : String(v.PVI_FEC).slice(0, 10),
    PVI_DIA: v.PVI_DIA ?? 0, PVI_FSA: esVacia(v.PVI_FSA) ? '' : toLocal(v.PVI_FSA), PVI_FEN: esVacia(v.PVI_FEN) ? '' : toLocal(v.PVI_FEN),
    PVI_DES: (v.PVI_DES || '').trim(), PVI_KMS: v.PVI_KMS ?? 0, PVI_VEH: (v.PVI_VEH || '').trim(), PVI_OBS: (v.PVI_OBS || '').trim(),
  })
  foto.value = ''; formError.value = ''; if (form.PVI_PER) cargarFoto(form.PVI_PER)
  modo.value = 'editar'
}
function cerrar () { modo.value = '' }

async function guardar () {
  formError.value = ''
  if (modo.value === 'nuevo' && !form.PVI_PER) { formError.value = 'Elegí un empleado.'; return }
  if (form.PVI_FSA && form.PVI_FEN && form.PVI_FSA > form.PVI_FEN) { formError.value = 'La fecha/hora de SALIDA no puede ser mayor a la de LLEGADA.'; return }
  proc.value = true
  const payload: any = {
    PVI_FEC: form.PVI_FEC || null,
    PVI_FSA: form.PVI_FSA ? form.PVI_FSA.replace('T', ' ') + ':00' : null,
    PVI_FEN: form.PVI_FEN ? form.PVI_FEN.replace('T', ' ') + ':00' : null,
    PVI_DIA: form.PVI_DIA, PVI_DES: form.PVI_DES, PVI_KMS: form.PVI_KMS, PVI_VEH: (form.PVI_VEH || '').toUpperCase(), PVI_OBS: form.PVI_OBS,
  }
  try {
    if (modo.value === 'nuevo') {
      await api.post('/viajes', { ...payload, PVI_PER: form.PVI_PER, PVI_NOM: form.PVI_NOM })
      flash('Viaje guardado correctamente.')
    } else {
      await api.put(`/viajes/${form.UNICO}`, payload)
      flash('Viaje modificado correctamente.')
    }
    await cargarGrilla()
    cerrar()
  } catch (e: any) { formError.value = e?.response?.data?.message ?? 'No se pudo guardar el viaje.' }
  finally { proc.value = false }
}

async function eliminar (v: any) {
  if (!confirm(`¿Eliminar el viaje de ${(v.PVI_NOM || '').trim()} (${fdt(v.PVI_FSA)})?`)) return
  try { await api.post('/viajes/eliminar', { unicos: [v.UNICO] }); flash('Viaje eliminado.'); await cargarGrilla() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

cargarGrilla()
</script>

<style scoped>
.ab-view { display:flex; flex-direction:column; min-height:100%; }
.ab-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ab-ico { font-size:28px; } .ab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ab-ayuda { margin-left:auto; background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ab-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ab-msg.ok { background:#d1fae5; color:#065f46; } .ab-msg.err { background:#fee2e2; color:#991b1b; }
.ab-tools { display:flex; align-items:center; gap:10px; padding:14px 18px 8px; flex-wrap:wrap; }
.ab-search { flex:1; min-width:220px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-rango { font-size:12px; color:#475569; display:flex; align-items:center; gap:5px; } .ab-rango input { border:1px solid #c8d8ea; border-radius:6px; padding:6px 8px; font-size:13px; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#16a34a; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; overflow-x:auto; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; white-space:nowrap; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; }
.ab-tabla th.r, .ab-tabla td.r { text-align:right; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; }
.ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-vacio { text-align:center; color:#94a3b8; padding:24px; }
.ab-acc { white-space:nowrap; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; }
.ab-b.del:hover { background:#fee2e2; } .ab-b.edi:hover { background:#e0eefc; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-ov2 { align-items:center; z-index:9200; }
.ab-md { background:#fff; border-radius:14px; width:min(700px,97vw); display:flex; flex-direction:column; max-height:92vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-fila { margin-bottom:12px; } .ab-fila-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px; }
.ab-md-body label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px; }
.ab-md-body input { width:100%; height:34px; border:1px solid #cbd5e1; border-radius:7px; padding:0 9px; font-size:14px; color:#1e293b; box-sizing:border-box; }
.ab-md-body input[readonly] { background:#f1f5f9; color:#334155; }
.ab-emp { display:flex; align-items:center; gap:8px; } .ab-cod { max-width:100px; } .ab-nom { flex:1; }
.ab-lupa { height:34px; border:none; background:#394959; color:#fff; border-radius:7px; padding:0 12px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ab-foto { width:38px; height:38px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; }
.ab-md-err { color:#b91c1c; font-size:13px; margin:4px 0 0; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(520px,94vw); } .ab-md-small h3 { margin:0 0 10px; color:#1a3a5c; }
.ab-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
