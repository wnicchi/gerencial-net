<!-- AsignarPuestoView.vue — Asignar Puesto de Trabajo a un empleado -->
<template>
  <div class="ap-view">
    <div class="ap-cab">
      <div class="ap-ico">👤</div>
      <div class="ap-tx"><h1>Asignar Puesto de Trabajo</h1><p>Vincula un empleado con su puesto (por CUIL)</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ap-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ap-body">
      <div class="ap-form">
        <div class="ap-campo">
          <label>Nro. Personal</label>
          <EmpleadoInput :codigo="codigo" :nombre="nombre" @select="onSelEmp" />
        </div>
        <div class="ap-campo"><label>Nombre</label><input :value="nombre" disabled /></div>
        <div class="ap-campo"><label>CUIL</label><input :value="cuil" disabled /></div>

        <template v-if="codigo">
          <div class="ap-campo">
            <label>Puesto</label>
            <select v-model="puestoSel">
              <option value="">— Seleccioná un puesto —</option>
              <option v-for="p in catPuestos" :key="p.codigo" :value="p.codigo">{{ p.descripcion }}</option>
            </select>
          </div>
          <div class="ap-acc">
            <button class="btn ok" :disabled="!puestoSel || proc" @click="asignar">✔ ASIGNAR</button>
            <button class="btn baja" :disabled="!puestoSel || proc" @click="darBaja">⊘ DAR BAJA</button>
            <button class="btn reset" @click="reset">↺ Reset</button>
          </div>

          <div class="ap-asig">
            <div class="ap-asig-tit">Puestos asignados</div>
            <div v-for="a in asignados" :key="a.puesto" class="ap-asig-row">
              <span class="ap-asig-pue">{{ a.puesto_des || a.puesto }}</span>
              <span class="ap-asig-fe">desde {{ a.desde || '—' }}</span>
            </div>
            <p v-if="!asignados.length" class="ap-asig-vacio">Este empleado no tiene puestos asignados.</p>
          </div>
        </template>
      </div>

      <div class="ap-foto">
        <img v-if="fotoUrl" :src="fotoUrl" alt="foto" />
        <div v-else class="ap-foto-ph">Sin foto</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import api from '@/services/auth'
import EmpleadoInput from '@/components/EmpleadoInput.vue'

const codigo = ref(0); const nombre = ref(''); const cuil = ref(''); const fotoUrl = ref('')
const catPuestos = ref<{ codigo: string; descripcion: string }[]>([]); const puestoSel = ref('')
const asignados = ref<{ puesto: string; puesto_des: string; desde: string }[]>([])
const proc = ref(false); const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 4000) }

const onSelEmp = (r: any) => seleccionar(r)

async function cargarPuestos () { if (catPuestos.value.length) return; try { catPuestos.value = (await api.get('/tareas/puestos')).data ?? [] } catch { /* */ } }

async function seleccionar (r: any) {
  await cargarPuestos()
  await cargar(Number(r.PER_COD))
  if (fotoUrl.value) URL.revokeObjectURL(fotoUrl.value); fotoUrl.value = ''
  try {
    const { data } = await api.get(`/empleados/${r.PER_COD}/foto`)
    if (data?.foto) fotoUrl.value = data.foto
  } catch { /* sin foto */ }
}

async function cargar (cod: number) {
  try {
    const { data } = await api.get(`/asignar-puesto/empleado/${cod}`)
    codigo.value = data.codigo; nombre.value = data.nombre; cuil.value = data.cuil
    asignados.value = data.asignados
    puestoSel.value = data.asignados[0]?.puesto ?? ''
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el empleado.', true) }
}

async function asignar () {
  if (!puestoSel.value) return
  if (!confirm('¿Confirma el puesto asignado a la fecha?')) return
  proc.value = true
  try { await api.post('/asignar-puesto', { codigo: codigo.value, puesto: puestoSel.value }); await cargar(codigo.value); flash('Puesto asignado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo asignar.', true) }
  finally { proc.value = false }
}

async function darBaja () {
  if (!puestoSel.value) return
  if (!confirm('¿Da de baja este puesto del empleado?')) return
  proc.value = true
  try { await api.post('/asignar-puesto/baja', { codigo: codigo.value, puesto: puestoSel.value }); await cargar(codigo.value); flash('Puesto dado de baja.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo dar de baja.', true) }
  finally { proc.value = false }
}

function reset () {
  codigo.value = 0; nombre.value = ''; cuil.value = ''; puestoSel.value = ''
  asignados.value = []
  if (fotoUrl.value) URL.revokeObjectURL(fotoUrl.value); fotoUrl.value = ''
}
</script>

<style scoped>
.ap-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ap-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ap-ico { font-size:28px; } .ap-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ap-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ap-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ap-msg.ok { background:#d1fae5; color:#065f46; } .ap-msg.err { background:#fee2e2; color:#991b1b; }
.ap-body { display:flex; gap:18px; margin:18px; max-width:820px; }
.ap-form { flex:1; display:flex; flex-direction:column; gap:14px; }
.ap-campo { display:flex; flex-direction:column; gap:5px; }
.ap-campo label { font-size:12px; font-weight:600; color:#374151; }
.ap-campo input, .ap-campo select { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; outline:none; color:#1e293b; }
.ap-campo input:disabled { background:#f1f5f9; color:#1e293b; font-weight:600; }
.ap-busc { position:relative; } .ap-busc input { width:100%; }
.ap-busc-lupa { display:flex; gap:8px; } .ap-busc-lupa input { flex:1; }
.ap-lupa { background:#394959; color:#fff; border:none; padding:9px 14px; border-radius:7px; cursor:pointer; font-weight:700; font-size:13px; white-space:nowrap; }
.ap-result { position:absolute; z-index:50; top:100%; left:0; right:0; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 12px 28px rgba(0,0,0,.18); max-height:240px; overflow:auto; }
.ap-result li { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; flex-direction:column; gap:2px; }
.ap-result li:hover { background:#f0faf4; } .ap-result li b { font-size:13px; color:#1e293b; } .ap-result li span { font-size:11px; color:#6b7280; }
.ap-acc { display:flex; gap:8px; flex-wrap:wrap; }
.btn { border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:700; }
.btn.ok { background:#2d6a9f; color:#fff; } .btn.baja { background:#fee2e2; color:#991b1b; } .btn.reset { background:#eef2f7; color:#475569; } .btn:disabled { opacity:.5; cursor:default; }
.ap-asig { margin-top:6px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ap-asig-tit { background:#f4f7fc; padding:8px 12px; font-size:13px; font-weight:700; color:#2a4a6a; border-bottom:1px solid #e2e8f0; }
.ap-asig-row { display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #f0f4f9; font-size:13px; }
.ap-asig-pue { color:#1a3a5c; font-weight:600; } .ap-asig-fe { color:#64748b; font-size:12px; }
.ap-asig-vacio { color:#94a3b8; padding:12px; font-size:13px; margin:0; }
.ap-foto { width:200px; height:220px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ap-foto img { width:100%; height:100%; object-fit:cover; } .ap-foto-ph { font-size:12px; color:#9ca3af; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
