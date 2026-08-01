<!-- AgendaGruposView.vue — Agenda: ABM de Grupos de usuarios (destinatarios masivos).
     Tablas GRUPOS + GRUP_USU (RRHH/RRHHlog). Los usuarios salen de la tabla `usuarios` de RRHH. -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">👥</div>
      <div class="ab-tx"><h1>Agenda — Grupos de Usuarios</h1><p>Grupos para enviar mensajes a varios usuarios a la vez</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-model="filtro" class="ab-search" placeholder="🔍 Buscar grupo…" />
      <span class="ab-count">{{ filtradas.length }} grupo(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo grupo</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead><tr><th style="width:70px">Cód.</th><th>Nombre del grupo</th><th style="width:110px" class="c">Usuarios</th><th style="width:110px" class="c">Acciones</th></tr></thead>
        <tbody>
          <tr v-if="cargando"><td colspan="4" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="4" class="ab-vacio">No hay grupos.</td></tr>
          <tr v-for="g in filtradas" :key="g.cod" class="ab-click" @click="abrirEditar(g)">
            <td class="ab-nro">{{ g.cod }}</td>
            <td>{{ g.descripcion }}</td>
            <td class="c"><span class="ab-chip">{{ g.cant }}</span></td>
            <td class="c ab-acc">
              <button class="ab-b edi" title="Ver / Editar" @click.stop="abrirEditar(g)">✏️</button>
              <button class="ab-b del" title="Eliminar" @click.stop="eliminar(g)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal alta / edición -->
    <Teleport to="body">
      <div v-if="modal" class="ab-ov" @click.self="modal = false">
        <div class="ab-md">
          <div class="ab-md-head"><span>{{ edit ? `✏️ Editar grupo N° ${edit.cod}` : '＋ Nuevo grupo' }}</span><button class="ab-x" @click="modal = false">✕</button></div>
          <div class="ab-md-body">
            <label class="ab-lbl">Nombre del grupo</label>
            <input v-model="descripcion" class="ab-inp" maxlength="50" placeholder="Ej: ADMINISTRACIÓN" />

            <div class="ab-usu-head">
              <label class="ab-lbl" style="margin:0">Usuarios del grupo</label>
              <input v-model="filtroUsu" class="ab-usu-bus" placeholder="🔍 Filtrar usuarios…" />
              <span class="ab-usu-sel">{{ seleccionados.size }} seleccionado(s)</span>
            </div>
            <div class="ab-usu-wrap">
              <label v-for="u in usuariosFiltrados" :key="u.codigo" class="ab-usu-row" :class="{ on: seleccionados.has(u.codigo) }">
                <input type="checkbox" :checked="seleccionados.has(u.codigo)" @change="toggle(u.codigo)" />
                <span class="ab-usu-cod">{{ u.codigo }}</span><span>{{ u.nombre }}</span>
              </label>
              <div v-if="!usuariosFiltrados.length" class="ab-vacio2">Sin usuarios.</div>
            </div>
            <p v-if="formError" class="ab-md-err">⚠️ {{ formError }}</p>
          </div>
          <div class="ab-md-foot"><span style="flex:1"></span>
            <button class="ab-cancel" @click="modal = false">Cancelar</button>
            <button class="ab-confirm" :disabled="proc" @click="guardar">{{ proc ? '⟳ Guardando…' : '✔ Guardar' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'

interface Usu { codigo: number; nombre: string }
const rows = ref<any[]>([]); const cargando = ref(false); const filtro = ref('')
const usuarios = ref<Usu[]>([])
const modal = ref(false); const edit = ref<any>(null); const proc = ref(false); const formError = ref('')
const descripcion = ref(''); const seleccionados = ref<Set<number>>(new Set()); const filtroUsu = ref('')
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3000) }

const filtradas = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? rows.value.filter(g => (g.descripcion || '').toLowerCase().includes(q)) : rows.value
})
const usuariosFiltrados = computed(() => {
  const q = filtroUsu.value.trim().toLowerCase()
  return q ? usuarios.value.filter(u => u.nombre.toLowerCase().includes(q) || String(u.codigo).includes(q)) : usuarios.value
})

async function cargar () {
  cargando.value = true
  try { rows.value = (await api.get('/agenda/grupos')).data ?? [] }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar.', true) }
  finally { cargando.value = false }
}
async function cargarUsuarios () {
  if (usuarios.value.length) return
  try { usuarios.value = (await api.get('/agenda/usuarios')).data ?? [] } catch { usuarios.value = [] }
}
function toggle (cod: number) {
  const s = new Set(seleccionados.value)
  s.has(cod) ? s.delete(cod) : s.add(cod)
  seleccionados.value = s
}

async function abrirNuevo () {
  edit.value = null; descripcion.value = ''; seleccionados.value = new Set(); filtroUsu.value = ''; formError.value = ''
  await cargarUsuarios(); modal.value = true
}
async function abrirEditar (g: any) {
  formError.value = ''; filtroUsu.value = ''
  await cargarUsuarios()
  try {
    const { data } = await api.get(`/agenda/grupos/${g.cod}`)
    edit.value = { cod: data.cod }; descripcion.value = data.descripcion
    seleccionados.value = new Set((data.usuarios ?? []).map((u: any) => u.codigo))
    modal.value = true
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo abrir el grupo.', true) }
}

async function guardar () {
  formError.value = ''
  if (!descripcion.value.trim()) { formError.value = 'Ingresá el nombre del grupo.'; return }
  proc.value = true
  const payload = { descripcion: descripcion.value, usuarios: [...seleccionados.value] }
  try {
    if (edit.value) await api.put(`/agenda/grupos/${edit.value.cod}`, payload)
    else await api.post('/agenda/grupos', payload)
    modal.value = false; await cargar(); flash('Grupo guardado.')
  } catch (e: any) { formError.value = e?.response?.data?.message ?? 'No se pudo guardar.' }
  finally { proc.value = false }
}
async function eliminar (g: any) {
  if (!confirm(`¿Eliminar el grupo "${g.descripcion}" y sus ${g.cant} usuario(s)?`)) return
  try { await api.delete(`/agenda/grupos/${g.cod}`); await cargar(); flash('Grupo eliminado.') }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}

cargar()
</script>

<style scoped>
.ab-view { display:flex; flex-direction:column; min-height:100%; }
.ab-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ab-ico { font-size:28px; } .ab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ab-msg { margin:10px 18px 0; padding:9px 14px; border-radius:8px; font-size:13px; } .ab-msg.ok { background:#d1fae5; color:#065f46; } .ab-msg.err { background:#fee2e2; color:#991b1b; }
.ab-tools { display:flex; align-items:center; gap:10px; padding:14px 18px 8px; flex-wrap:wrap; }
.ab-search { flex:1; min-width:220px; border:1px solid #c8d8ea; border-radius:8px; padding:9px 12px; font-size:14px; color:#1e293b; }
.ab-count { font-size:12.5px; color:#64748b; font-weight:600; }
.ab-nuevo { background:#1b4332; color:#fff; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; }
.ab-tabla-wrap { padding:6px 18px 24px; max-width:720px; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-tabla tbody tr.ab-click { cursor:pointer; }
.ab-nro { font-weight:800; color:#1b4332; }
.ab-chip { display:inline-block; background:#e0e7ff; color:#3730a3; font-weight:700; font-size:12px; padding:1px 10px; border-radius:999px; }
.ab-vacio { text-align:center; color:#94a3b8; padding:22px; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; } .ab-b.del:hover { background:#fee2e2; } .ab-b.edi:hover { background:#e0eefc; }
/* Modal */
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:flex-start; justify-content:center; padding:34px 16px; overflow:auto; }
.ab-md { background:#fff; border-radius:14px; width:min(560px,97vw); display:flex; flex-direction:column; max-height:90vh; }
.ab-md-head { display:flex; align-items:center; padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800; color:#1e293b; font-size:15px; }
.ab-x { margin-left:auto; background:#eef2f7; border:none; border-radius:6px; width:30px; height:30px; cursor:pointer; font-size:14px; color:#475569; }
.ab-md-body { padding:16px 18px; overflow:auto; }
.ab-lbl { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:4px; }
.ab-inp { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; text-transform:uppercase; }
.ab-usu-head { display:flex; align-items:center; gap:10px; margin:16px 0 6px; flex-wrap:wrap; }
.ab-usu-bus { flex:1; min-width:140px; border:1px solid #c8d8ea; border-radius:6px; padding:6px 10px; font-size:13px; }
.ab-usu-sel { font-size:12px; color:#64748b; font-weight:600; }
.ab-usu-wrap { border:1px solid #e2e8f0; border-radius:8px; max-height:44vh; overflow:auto; }
.ab-usu-row { display:flex; align-items:center; gap:9px; padding:7px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:13px; color:#1e293b; }
.ab-usu-row:hover { background:#f8fafc; } .ab-usu-row.on { background:#eff6ff; }
.ab-usu-cod { color:#2563eb; font-weight:700; min-width:34px; }
.ab-vacio2 { padding:16px; text-align:center; color:#94a3b8; }
.ab-md-err { color:#b91c1c; font-size:13px; margin:8px 0 0; }
.ab-md-foot { display:flex; align-items:center; gap:8px; padding:12px 18px; border-top:1px solid #e2e8f0; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#1b4332; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
