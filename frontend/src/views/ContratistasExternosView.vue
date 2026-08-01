<!-- ContratistasExternosView.vue — ABM de Contratistas Externos (3 solapas). -->
<template>
  <div class="ce-view">
    <div class="ce-cab">
      <div class="ce-cab-ico">🤝</div>
      <div class="ce-cab-tx"><h1>Contratistas Externos</h1><p>{{ lista.length }} contratista{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="ce-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ce-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
      <button class="ce-btn-nuevo" @click="nuevo">＋ Nuevo</button>
    </div>

    <ContratistasExternosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/contratistas-externos" titulo="Asistente IA — Contratistas Externos"
            subtitulo="Preguntá sobre el ABM de contratistas externos"
            :sugerencias="['¿Qué hay en cada solapa?','¿Cómo asigno exigencias?','¿Cómo cargo los empleados?','¿Por qué no me deja eliminar uno?']"
            @close="modalIA = false" />

    <div class="ce-body">
      <!-- Lista -->
      <aside class="ce-aside">
        <input v-model="filtro" class="ce-busc" type="text" placeholder="Buscar…" />
        <ul class="ce-lista">
          <li v-for="c in listaFiltrada" :key="c.cod" :class="{ on: sel?.cod === c.cod }" @click="abrir(c.cod)">
            <b>{{ c.nombre }}</b>
            <span><i :class="['ce-est', c.estado === 'A' ? 'a' : 'p']">{{ c.estado === 'A' ? 'ACTIVO' : 'PASIVO' }}</i> {{ c.tipo_nombre }}</span>
          </li>
          <li v-if="!listaFiltrada.length" class="ce-vacio">Sin contratistas</li>
        </ul>
      </aside>

      <!-- Detalle -->
      <section class="ce-detalle" v-if="form">
        <div class="ce-tabs">
          <button :class="{ on: tab === 1 }" @click="tab = 1">Datos Generales</button>
          <button :class="{ on: tab === 2 }" :disabled="!form.cod" @click="tab = 2; cargarExigencias()">Exigencias en Obras</button>
          <button :class="{ on: tab === 3 }" :disabled="!form.cod" @click="tab = 3; cargarEmpleados()">Empleados Externos Asociados</button>
        </div>

        <!-- Tab 1 -->
        <div v-show="tab === 1" class="ce-tab" v-enter-next>
          <div class="ce-fila">
            <div class="ce-c ce-chico"><label>Código</label><input :value="form.cod || '(automático)'" disabled /></div>
            <div class="ce-c"><label>Estado</label><div class="ce-radios"><label><input v-model="form.estado" type="radio" value="A" /> ACTIVO</label><label><input v-model="form.estado" type="radio" value="P" /> PASIVO</label></div></div>
            <div class="ce-c"><label>Tipo</label><select v-model.number="form.tipo"><option :value="0">—</option><option v-for="t in tipos" :key="t.cod" :value="Number(t.cod)">{{ t.nombre }}</option></select></div>
          </div>
          <div class="ce-c"><label>Nombre <span class="req">*</span></label><input v-model="form.nombre" type="text" maxlength="100" /></div>
          <div class="ce-c"><label>Domicilio</label><input v-model="form.domicilio" type="text" maxlength="100" /></div>
          <div class="ce-fila">
            <div class="ce-c"><label>Teléfonos</label><input v-model="form.telefono" type="text" maxlength="50" /></div>
            <div class="ce-c"><label>Email</label><input v-model="form.email" type="text" maxlength="100" /></div>
          </div>
          <div class="ce-c"><label>ART</label><input v-model="form.art" type="text" maxlength="100" /></div>
          <p v-if="err" class="ce-err">⚠️ {{ err }}</p>
          <button class="ce-btn-guardar" :disabled="guardando" @click="guardarDatos">💾 {{ guardando ? 'Guardando…' : 'Guardar datos' }}</button>
        </div>

        <!-- Tab 2 -->
        <div v-show="tab === 2" class="ce-tab">
          <p class="ce-hint">Tildá las exigencias legales que se le piden a este contratista.</p>
          <ul class="ce-exi">
            <li v-for="e in catalogo" :key="e.cod"><label><input v-model="exiSel[e.cod]" type="checkbox" /> {{ e.detalle }}</label></li>
            <li v-if="!catalogo.length" class="ce-vacio">No hay exigencias cargadas. Cargalas en el módulo "Exigencias Legales a Contratados".</li>
          </ul>
          <button class="ce-btn-guardar" :disabled="guardando" @click="guardarExigencias">💾 Guardar exigencias</button>
        </div>

        <!-- Tab 3 -->
        <div v-show="tab === 3" class="ce-tab">
          <div class="ce-emp-acc">
            <button class="ce-mini" @click="agregarEmp">＋ Agregar</button>
            <button class="ce-mini del" :disabled="!empSelCount" @click="eliminarEmp">🗑️ Eliminar seleccionados</button>
            <span class="ce-leyenda"><i class="ce-art"></i> ART vencida</span>
          </div>
          <div class="ce-emp-wrap">
            <table class="ce-emp">
              <thead><tr><th style="width:34px"></th><th>Nombre</th><th>DNI</th><th>CUIL/CUIT</th><th>Teléfono</th><th>Celular</th><th>Venc. ART</th></tr></thead>
              <tbody>
                <tr v-for="(e, i) in empleados" :key="e.unico || ('n' + i)" :class="{ venc: e.art_vencida }">
                  <td style="text-align:center"><input v-model="e._sel" type="checkbox" /></td>
                  <td><input v-model="e.nombre" type="text" class="ce-in" /></td>
                  <td><input v-model="e.dni" type="text" class="ce-in chico" /></td>
                  <td><input v-model="e.cuil" type="text" class="ce-in chico" /></td>
                  <td><input v-model="e.telefono" type="text" class="ce-in" /></td>
                  <td><input v-model="e.celular" type="text" class="ce-in" /></td>
                  <td><input v-model="e.venc_art" type="date" class="ce-in" @change="e.art_vencida = !!e.venc_art && e.venc_art < hoy" /></td>
                </tr>
                <tr v-if="!empleados.length"><td colspan="7" class="ce-vacio">Sin empleados asociados</td></tr>
              </tbody>
            </table>
          </div>
          <button class="ce-btn-guardar" :disabled="guardando" @click="guardarEmpleados">💾 Confirmar empleados</button>
        </div>
      </section>
      <section class="ce-detalle ce-placeholder" v-else>Elegí un contratista de la lista o creá uno nuevo.</section>
    </div>
    <p v-if="msg" :class="['ce-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import ContratistasExternosAyuda from '@/components/ContratistasExternosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const hoy = new Date().toISOString().slice(0, 10)

interface ItemLista { cod: number; nombre: string; estado: string; tipo: number; tipo_nombre: string }
const lista = ref<ItemLista[]>([]); const filtro = ref(''); const tipos = ref<any[]>([])
const sel = ref<ItemLista | null>(null); const tab = ref(1)
const form = ref<any>(null); const guardando = ref(false); const err = ref('')
const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3500) }

const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? lista.value.filter(c => c.nombre.toLowerCase().includes(q)) : lista.value
})

const cargarLista = async () => { try { lista.value = (await api.get('/contratistas-externos')).data } catch (e) { console.error(e) } }
const formVacio = () => ({ cod: 0, nombre: '', domicilio: '', telefono: '', email: '', estado: 'A', art: '', tipo: 0 })
const nuevo = () => { sel.value = null; form.value = formVacio(); tab.value = 1; err.value = '' }
const abrir = async (cod: number) => {
  sel.value = lista.value.find(c => c.cod === cod) ?? null
  tab.value = 1; err.value = ''
  try { form.value = (await api.get(`/contratistas-externos/${cod}`)).data } catch (e) { console.error(e) }
}
const guardarDatos = async () => {
  if (!form.value.nombre.trim()) { err.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; err.value = ''
  try {
    const payload = { nombre: form.value.nombre, domicilio: form.value.domicilio, telefono: form.value.telefono, email: form.value.email, estado: form.value.estado, art: form.value.art, tipo: form.value.tipo }
    if (form.value.cod) { await api.put(`/contratistas-externos/${form.value.cod}`, payload); flash('Datos guardados') }
    else { const { data } = await api.post('/contratistas-externos', payload); form.value.cod = data.cod; flash('Contratista creado'); }
    await cargarLista(); sel.value = lista.value.find(c => c.cod === form.value.cod) ?? sel.value
  } catch (e: any) { err.value = e?.response?.data?.message ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}

// Tab 2 — exigencias
const catalogo = ref<any[]>([]); const exiSel = reactive<Record<number, boolean>>({})
const cargarExigencias = async () => {
  if (!form.value?.cod) return
  try {
    const { data } = await api.get(`/contratistas-externos/${form.value.cod}/exigencias`)
    catalogo.value = data.catalogo
    Object.keys(exiSel).forEach(k => delete exiSel[+k])
    for (const a of data.asignadas) exiSel[a.cod] = true
  } catch (e) { console.error(e) }
}
const guardarExigencias = async () => {
  guardando.value = true
  try { await api.post(`/contratistas-externos/${form.value.cod}/exigencias`, { ids: Object.keys(exiSel).filter(k => exiSel[+k]).map(Number) }); flash('Exigencias guardadas') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

// Tab 3 — empleados
const empleados = ref<any[]>([])
const empSelCount = computed(() => empleados.value.filter(e => e._sel).length)
const cargarEmpleados = async () => {
  if (!form.value?.cod) return
  try { empleados.value = (await api.get(`/contratistas-externos/${form.value.cod}/empleados`)).data.map((e: any) => ({ ...e, _sel: false })) }
  catch (e) { console.error(e) }
}
const agregarEmp = () => empleados.value.push({ unico: 0, nombre: '', dni: '', cuil: '', telefono: '', celular: '', venc_art: '', art_vencida: true, _sel: false })
const eliminarEmp = () => { empleados.value = empleados.value.filter(e => !e._sel) }
const guardarEmpleados = async () => {
  guardando.value = true
  try { const { data } = await api.post(`/contratistas-externos/${form.value.cod}/empleados`, { rows: empleados.value }); flash(`Empleados guardados (${data.total})`); await cargarEmpleados() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { guardando.value = false }
}

onMounted(async () => { await cargarLista(); try { tipos.value = (await api.get('/contratista-tipos')).data } catch (e) { console.error(e) } })
</script>

<style scoped>
.ce-view { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.ce-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ce-cab-ico { font-size:28px; } .ce-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ce-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ce-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ce-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ce-btn-nuevo { background:#22c55e; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ce-body { display:flex; flex:1; overflow:hidden; }
.ce-aside { width:300px; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; background:#f8fafc; }
.ce-busc { margin:12px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; outline:none; }
.ce-lista { flex:1; overflow:auto; list-style:none; margin:0; padding:0; }
.ce-lista li { padding:9px 14px; border-bottom:1px solid #eef2f7; cursor:pointer; display:flex; flex-direction:column; gap:3px; }
.ce-lista li:hover { background:#eef6ee; } .ce-lista li.on { background:#1b4332; } .ce-lista li.on b, .ce-lista li.on span { color:#fff; }
.ce-lista li b { font-size:13px; color:#1e293b; } .ce-lista li span { font-size:11px; color:#6b7280; display:flex; align-items:center; gap:6px; }
.ce-est { font-weight:700; padding:1px 6px; border-radius:999px; font-size:10px; } .ce-est.a { background:#dcfce7; color:#166534; } .ce-est.p { background:#fee2e2; color:#b91c1c; }
.ce-vacio { color:#9ca3af; font-size:13px; text-align:center; padding:20px; cursor:default; }
.ce-detalle { flex:1; overflow:auto; padding:18px; }
.ce-placeholder { display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:14px; }
.ce-tabs { display:flex; gap:4px; border-bottom:2px solid #e2e8f0; margin-bottom:18px; }
.ce-tabs button { background:transparent; border:none; padding:10px 16px; font-size:13px; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; }
.ce-tabs button.on { color:#1b4332; border-bottom-color:#1b4332; } .ce-tabs button:disabled { color:#cbd5e1; cursor:not-allowed; }
.ce-tab { max-width:760px; }
.ce-fila { display:flex; gap:14px; flex-wrap:wrap; } .ce-fila .ce-c { flex:1; min-width:160px; }
.ce-c { margin-bottom:14px; display:flex; flex-direction:column; gap:5px; } .ce-c.ce-chico { max-width:140px; }
.ce-c label { font-size:12px; font-weight:600; color:#374151; } .ce-c .req { color:#dc2626; }
.ce-c input[type=text], .ce-c select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; outline:none; }
.ce-c input:focus, .ce-c select:focus { border-color:#40916c; box-shadow:0 0 0 3px rgba(64,145,108,.15); } .ce-c input:disabled { background:#f1f5f9; color:#94a3b8; }
.ce-radios { display:flex; gap:16px; padding-top:6px; } .ce-radios label { flex-direction:row; align-items:center; gap:5px; font-size:14px; font-weight:600; color:#1b4332; cursor:pointer; }
.ce-err { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; border-radius:6px; padding:7px 10px; font-size:13px; margin:4px 0; }
.ce-btn-guardar { background:#16a34a; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; margin-top:8px; } .ce-btn-guardar:disabled { background:#cbd5e1; }
.ce-hint { font-size:13px; color:#6b7280; margin:0 0 12px; }
.ce-exi { list-style:none; margin:0 0 14px; padding:0; max-height:48vh; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
.ce-exi li { padding:8px 12px; border-bottom:1px solid #f1f5f9; } .ce-exi li label { display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; cursor:pointer; }
.ce-emp-acc { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.ce-mini { background:#fff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .ce-mini:hover { background:#f0faf4; } .ce-mini.del:hover { background:#fee2e2; } .ce-mini:disabled { opacity:.5; cursor:not-allowed; }
.ce-leyenda { margin-left:auto; font-size:12px; color:#6b7280; display:flex; align-items:center; } .ce-leyenda i { width:13px; height:13px; background:#fecaca; border:1px solid #f87171; border-radius:3px; margin-right:5px; }
.ce-emp-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:48vh; margin-bottom:12px; }
.ce-emp { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
.ce-emp th { position:sticky; top:0; background:#1b4332; color:#fff; font-weight:600; padding:7px 10px; font-size:12px; }
.ce-emp td { padding:3px 8px; border-bottom:1px solid #f1f5f9; }
.ce-emp tr.venc td { background:#fee2e2; }
.ce-in { width:100%; min-width:90px; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:13px; outline:none; } .ce-in.chico { min-width:70px; }
.ce-msg { padding:8px 14px; margin:0 18px 12px; font-size:13px; border-radius:6px; } .ce-msg.ok { background:#dcfce7; color:#166534; } .ce-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
