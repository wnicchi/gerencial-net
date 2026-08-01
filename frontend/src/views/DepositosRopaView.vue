<!-- DepositosRopaView.vue — ABM de Depósitos de Ropa/EPP (tabla ropadepo). -->
<template>
  <div class="cs-view">
    <div class="cs-cab">
      <div class="cs-ico">🏭</div>
      <div class="cs-tx"><h1>Depósitos de Ropa/EPP</h1><p>{{ lista.length }} depósito{{ lista.length === 1 ? '' : 's' }}</p></div>
      <button class="cs-ia" @click="modalIA = true">🤖 IA</button>
      <button class="cs-ayuda" @click="ayuda = true">❓ Ayuda</button>
      <button class="cs-nuevo" @click="abrirNuevo">＋ Nuevo</button>
    </div>

    <ChatIA v-if="modalIA" endpoint="/ia/depositos-ropa" titulo="Asistente IA — Depósitos de Ropa/EPP"
            subtitulo="Preguntá sobre el ABM de depósitos"
            :sugerencias="['¿Para qué sirve este módulo?','¿Cómo agrego un depósito?','¿Dónde se usan?','¿El código se asigna solo?']"
            @close="modalIA = false" />

    <div class="cs-card">
      <input v-model="filtro" class="cs-search" placeholder="🔍 Buscar por nombre…" />
      <div v-if="cargando" class="cs-vacio">⟳ Cargando…</div>
      <div v-else-if="!listaFiltrada.length" class="cs-vacio">Sin depósitos cargados</div>
      <table v-else class="cs-tabla">
        <thead><tr><th style="width:90px" class="ord" @click="ordenar('cod')">Código{{ flecha('cod') }}</th><th class="ord" @click="ordenar('nombre')">Descripción{{ flecha('nombre') }}</th><th style="width:110px;text-align:center">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="e in listaFiltrada" :key="e.cod">
            <td class="cs-cod">{{ e.cod }}</td><td>{{ e.nombre }}</td>
            <td style="text-align:center"><button class="cs-i" @click="abrirEditar(e)">✏️</button><button class="cs-i del" @click="eliminar(e)">🗑️</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="msg" :class="['cs-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>

    <Teleport to="body">
      <div v-if="modal" class="cs-ov" @click.self="modal = false">
        <div class="cs-md" v-enter-next>
          <h3>{{ editando ? 'Editar depósito' : 'Nuevo depósito' }}</h3>
          <label>Descripción</label>
          <input ref="inputNom" v-model="form.nombre" maxlength="50" @keyup.enter="guardar" />
          <p v-if="modalError" class="cs-md-err">{{ modalError }}</p>
          <div class="cs-md-acc">
            <button class="cs-cancel" @click="modal = false">Cancelar</button>
            <button class="cs-ok" :disabled="guardando" @click="guardar">{{ guardando ? '⟳' : 'Guardar' }}</button>
          </div>
        </div>
      </div>
      <div v-if="ayuda" class="cs-ov" @click.self="ayuda = false">
        <div class="cs-md">
          <h3>❓ Ayuda — Depósitos de Ropa/EPP</h3>
          <ul class="cs-help">
            <li>Administra los <b>depósitos</b> donde se guarda el stock de ropa y EPP (ej. Rosario, Pergamino).</li>
            <li>El <b>código</b> lo asigna el sistema; el <b>nombre</b> es obligatorio.</li>
          </ul>
          <div class="cs-md-acc"><button class="cs-ok" @click="ayuda = false">Cerrar</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/auth'
import ChatIA from '@/components/ChatIA.vue'

interface Item { cod: number; nombre: string }
const lista = ref<Item[]>([]); const cargando = ref(false); const filtro = ref('')
const msg = ref(''); const msgError = ref(false)
const modal = ref(false); const ayuda = ref(false); const modalIA = ref(false)
const editando = ref(false); const guardando = ref(false); const modalError = ref('')
const inputNom = ref<HTMLInputElement | null>(null)
const form = ref<{ cod: number | null; nombre: string }>({ cod: null, nombre: '' })

const sortKey = ref<'cod' | 'nombre'>('nombre'); const sortDir = ref<1 | -1>(1)
const ordenar = (k: 'cod' | 'nombre') => { if (sortKey.value === k) sortDir.value = (sortDir.value === 1 ? -1 : 1) as 1 | -1; else { sortKey.value = k; sortDir.value = 1 } }
const flecha = (k: 'cod' | 'nombre') => sortKey.value === k ? (sortDir.value === 1 ? ' ▲' : ' ▼') : ''
const listaFiltrada = computed(() => {
  const q = filtro.value.trim().toLowerCase()
  const base = q ? lista.value.filter(e => e.nombre.toLowerCase().includes(q)) : lista.value.slice()
  const k = sortKey.value, dir = sortDir.value
  return base.slice().sort((a, b) => k === 'cod' ? (a.cod - b.cod) * dir : String(a.nombre).localeCompare(String(b.nombre), 'es') * dir)
})
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; setTimeout(() => msg.value = '', 3000) }
const cargar = async () => { cargando.value = true; try { lista.value = (await api.get('/depositos-ropa')).data } catch (e) { console.error(e) } finally { cargando.value = false } }

const abrirNuevo = async () => { editando.value = false; form.value = { cod: null, nombre: '' }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }
const abrirEditar = async (e: Item) => { editando.value = true; form.value = { ...e }; modalError.value = ''; modal.value = true; await nextTick(); inputNom.value?.focus() }

const guardar = async () => {
  if (!form.value.nombre.trim()) { modalError.value = 'El nombre es obligatorio.'; return }
  guardando.value = true; modalError.value = ''
  try {
    if (editando.value) { await api.put(`/depositos-ropa/${form.value.cod}`, { nombre: form.value.nombre }); flash('Depósito actualizado') }
    else { await api.post('/depositos-ropa', { nombre: form.value.nombre }); flash('Depósito creado') }
    modal.value = false; await cargar()
  } catch (e: any) { modalError.value = e?.response?.data?.message ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo guardar.' }
  finally { guardando.value = false }
}
const eliminar = async (e: Item) => {
  if (!confirm(`¿Eliminar el depósito "${e.nombre}"?`)) return
  try { await api.delete(`/depositos-ropa/${e.cod}`); flash('Depósito eliminado'); await cargar() }
  catch (err: any) { flash(err?.response?.data?.message ?? 'No se pudo eliminar.', true) }
}
onMounted(cargar)
</script>

<style scoped>
.cs-view { display:flex; flex-direction:column; min-height:100%; }
.cs-cab { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cs-ico { font-size:28px; } .cs-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cs-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cs-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.cs-nuevo { background:#2d6a9f; color:#fff; border:none; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:700; }
.cs-card { margin:18px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; max-width:680px; }
.cs-search { width:100%; border:1px solid #d1d5db; border-radius:7px; padding:9px 12px; font-size:14px; margin-bottom:12px; outline:none; }
.cs-vacio { text-align:center; color:#94a3b8; padding:24px; }
.cs-tabla { width:100%; border-collapse:collapse; font-size:14px; }
.cs-tabla th { background:#f4f7fc; color:#2a4a6a; padding:8px 10px; text-align:left; font-size:12px; border-bottom:1px solid #e2e8f0; }
.cs-tabla td { padding:8px 10px; border-bottom:1px solid #f0f4f9; color:#1e293b; } .cs-cod { color:#2d6a9f; font-weight:700; }
.cs-i { background:none; border:none; cursor:pointer; font-size:15px; padding:2px 5px; } .cs-i.del:hover { filter:saturate(2); }
.cs-tabla th.ord { cursor:pointer; user-select:none; white-space:nowrap; } .cs-tabla th.ord:hover { background:#e6eefc; color:#1a3a5c; }
.cs-msg { margin:12px 0 0; padding:8px 12px; border-radius:6px; font-size:13px; } .cs-msg.ok { background:#d1fae5; color:#065f46; } .cs-msg.err { background:#fee2e2; color:#991b1b; }
.cs-ov { position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:18px; }
.cs-md { background:#fff; border-radius:14px; padding:22px; width:min(420px,94vw); }
.cs-md h3 { margin:0 0 14px; color:#1a3a5c; } .cs-md label { font-size:12px; font-weight:600; color:#374151; }
.cs-md input { width:100%; border:1px solid #c8d8ea; border-radius:7px; padding:9px 11px; font-size:14px; margin-top:5px; outline:none; box-sizing:border-box; }
.cs-md-err { color:#991b1b; font-size:13px; margin:8px 0 0; }
.cs-help { margin:0; padding-left:18px; color:#334155; font-size:13.5px; line-height:1.7; }
.cs-md-acc { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
.cs-cancel { background:#eef2f7; color:#475569; border:none; border-radius:7px; padding:9px 16px; cursor:pointer; font-weight:600; }
.cs-ok { background:#2d6a9f; color:#fff; border:none; border-radius:7px; padding:9px 18px; cursor:pointer; font-weight:700; } .cs-ok:disabled { opacity:.5; }
</style>
