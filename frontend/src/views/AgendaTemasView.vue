<!-- AgendaTemasView.vue — Agenda: ABM de Temas (asuntos de los mensajes). Tabla TEMAS (RRHH/RRHHlog). -->
<template>
  <div class="ab-view">
    <div class="ab-cab">
      <div class="ab-ico">🏷️</div>
      <div class="ab-tx"><h1>Agenda — Temas</h1><p>Asuntos disponibles para los mensajes de la agenda</p></div>
    </div>

    <transition name="fade"><div v-if="msg" :class="['ab-msg', msgErr ? 'err' : 'ok']">{{ msg }}</div></transition>

    <div class="ab-tools">
      <input v-model="filtro" class="ab-search" placeholder="🔍 Buscar tema…" />
      <span class="ab-count">{{ filtradas.length }} tema(s)</span>
      <span style="flex:1"></span>
      <button class="ab-nuevo" @click="abrirNuevo">＋ Nuevo tema</button>
    </div>

    <div class="ab-tabla-wrap">
      <table class="ab-tabla">
        <thead><tr><th style="width:70px">Cód.</th><th>Descripción</th><th style="width:110px" class="c">Acciones</th></tr></thead>
        <tbody>
          <tr v-if="cargando"><td colspan="3" class="ab-vacio">⟳ Cargando…</td></tr>
          <tr v-else-if="!filtradas.length"><td colspan="3" class="ab-vacio">No hay temas.</td></tr>
          <tr v-for="t in filtradas" :key="t.cod" class="ab-click" @click="abrirEditar(t)">
            <td class="ab-nro">{{ t.cod }}</td>
            <td>{{ t.descripcion }}</td>
            <td class="c ab-acc">
              <button class="ab-b edi" title="Editar" @click.stop="abrirEditar(t)">✏️</button>
              <button class="ab-b del" title="Eliminar" @click.stop="eliminar(t)">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="ab-ov ab-ov2" @click.self="modal = false">
        <div class="ab-md-small">
          <h3>{{ edit ? '✏️ Editar tema' : '＋ Nuevo tema' }}</h3>
          <label>Descripción</label>
          <input v-model="descripcion" maxlength="50" placeholder="Ej: TRABAJO" @keyup.enter="guardar" />
          <div class="ab-md-foot"><span style="flex:1"></span>
            <button class="ab-cancel" @click="modal = false">Cancelar</button>
            <button class="ab-confirm" :disabled="proc" @click="guardar">{{ proc ? '⟳' : '✔ Guardar' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'

const rows = ref<any[]>([]); const cargando = ref(false); const filtro = ref('')
const modal = ref(false); const edit = ref<any>(null); const descripcion = ref(''); const proc = ref(false)
const msg = ref(''); const msgErr = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgErr.value = e; if (t && !e) setTimeout(() => msg.value = '', 3000) }

const filtradas = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  return q ? rows.value.filter(t => (t.descripcion || '').toLowerCase().includes(q)) : rows.value
})

async function cargar () {
  cargando.value = true
  try { rows.value = (await api.get('/agenda/temas')).data ?? [] }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar.', true) }
  finally { cargando.value = false }
}
function abrirNuevo () { edit.value = null; descripcion.value = ''; modal.value = true }
function abrirEditar (t: any) { edit.value = t; descripcion.value = t.descripcion; modal.value = true }

async function guardar () {
  if (!descripcion.value.trim()) { flash('Ingresá la descripción.', true); return }
  proc.value = true
  try {
    if (edit.value) await api.put(`/agenda/temas/${edit.value.cod}`, { descripcion: descripcion.value })
    else await api.post('/agenda/temas', { descripcion: descripcion.value })
    modal.value = false; await cargar(); flash('Tema guardado.')
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { proc.value = false }
}
async function eliminar (t: any) {
  if (!confirm(`¿Eliminar el tema "${t.descripcion}"?`)) return
  try { await api.delete(`/agenda/temas/${t.cod}`); await cargar(); flash('Tema eliminado.') }
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
.ab-tabla-wrap { padding:6px 18px 24px; max-width:640px; }
.ab-tabla { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.ab-tabla th { background:#1e293b; color:#fff; padding:9px 10px; text-align:left; font-size:12px; font-weight:700; } .ab-tabla th.c, .ab-tabla td.c { text-align:center; }
.ab-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .ab-tabla tbody tr:hover { background:#f8fafc; }
.ab-tabla tbody tr.ab-click { cursor:pointer; }
.ab-nro { font-weight:800; color:#1b4332; }
.ab-vacio { text-align:center; color:#94a3b8; padding:22px; }
.ab-b { background:#eef2f7; border:none; border-radius:6px; padding:5px 8px; cursor:pointer; font-size:14px; margin:0 2px; } .ab-b.del:hover { background:#fee2e2; } .ab-b.edi:hover { background:#e0eefc; }
.ab-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:24px; }
.ab-md-small { background:#fff; border-radius:14px; padding:20px; width:min(420px,94vw); } .ab-md-small h3 { margin:0 0 12px; color:#1a3a5c; }
.ab-md-small label { font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.ab-md-small input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; box-sizing:border-box; color:#1e293b; text-transform:uppercase; }
.ab-md-foot { display:flex; align-items:center; gap:8px; margin-top:16px; }
.ab-cancel { background:#eef2f7; color:#475569; border:none; border-radius:8px; padding:10px 18px; cursor:pointer; font-weight:600; }
.ab-confirm { background:#1b4332; color:#fff; border:none; border-radius:8px; padding:10px 20px; cursor:pointer; font-weight:800; font-size:13px; } .ab-confirm:disabled { opacity:.5; }
.fade-enter-active,.fade-leave-active { transition:opacity .2s; } .fade-enter-from,.fade-leave-to { opacity:0; }
</style>
