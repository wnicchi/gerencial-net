<!-- AbmSimple.vue — ABM genérico de código + descripción (lista + formulario). -->
<template>
  <div class="as-card">
    <div class="as-split">
      <div class="as-list">
        <div class="as-list-head"><span>{{ titulo }} ({{ items.length }})</span><button class="as-mini" @click="nuevo">➕ {{ nuevoLabel }}</button></div>
        <ul>
          <li v-for="it in items" :key="it.cod" :class="{ on: it.cod === form.cod }" @click="sel(it)"><b>{{ it.cod }}</b> {{ it.descripcion }}</li>
          <li v-if="!items.length" class="vacio">Sin registros.</li>
        </ul>
      </div>
      <div class="as-form" v-enter-next>
        <div class="as-f chico"><span class="as-lbl">Código</span><input :value="form.cod || '(nuevo)'" class="as-inp ro" readonly /></div>
        <div class="as-f">
          <span class="as-lbl">Nombre</span>
          <input v-model="form.descripcion" class="as-inp ancho" :class="{ exc: excede }" :disabled="!editable" placeholder="Descripción…" @input="form.descripcion = form.descripcion.toUpperCase()" />
          <span v-if="editable" class="as-cont" :class="{ over: excede }">{{ form.descripcion.length }} / {{ maxDescripcion }}</span>
        </div>
        <div class="as-acc">
          <button v-if="!form.cod" class="as-btn ok" :disabled="procesando || excede" @click="guardar">💾 Crear</button>
          <button v-else-if="!editando" class="as-btn ok" @click="editando = true">✏️ Editar</button>
          <button v-else class="as-btn ok" :disabled="procesando || excede" @click="guardar">💾 Guardar</button>
          <button v-if="form.cod && !editando" class="as-btn del" :disabled="procesando" @click="eliminar">🗑 Eliminar</button>
          <span v-if="editable && excede" class="as-exc-msg">⚠ Supera el máximo permitido.</span>
        </div>
        <p v-if="msg" :class="['as-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'

// maxDescripcion: largo real de la columna (para el contador/aviso). Default 100.
const props = withDefaults(defineProps<{ endpoint: string; coleccion: string; titulo: string; nuevoLabel: string; maxDescripcion?: number }>(), { maxDescripcion: 100 })
interface Item { cod: number; descripcion: string }
const items = ref<Item[]>([])
const form = reactive<Item>({ cod: 0, descripcion: '' })
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const editando = ref(false)   // registro existente: solo lectura hasta apretar Editar
const editable = computed(() => !form.cod || editando.value)
const excede = computed(() => form.descripcion.length > props.maxDescripcion)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

onMounted(cargar)
async function cargar () { try { items.value = (await api.get(props.endpoint)).data[props.coleccion] ?? [] } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar.', true) } }
const sel = (it: Item) => { Object.assign(form, it); editando.value = false }
const nuevo = () => { Object.assign(form, { cod: 0, descripcion: '' }); editando.value = false }

const guardar = async () => {
  if (!form.descripcion.trim()) return flash('Indicá la descripción.', true)
  if (excede.value) return flash('La descripción supera el máximo permitido. Acortala antes de guardar.', true)
  procesando.value = true
  try {
    if (form.cod) await api.put(`${props.endpoint}/${form.cod}`, { descripcion: form.descripcion })
    else { const { data } = await api.post(props.endpoint, { descripcion: form.descripcion }); form.cod = data.cod }
    editando.value = false; flash('Guardado correctamente.'); await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}
const eliminar = async () => {
  if (!form.cod || !confirm(`¿Elimina "${form.descripcion}"?`)) return
  procesando.value = true
  try { await api.delete(`${props.endpoint}/${form.cod}`); flash('Eliminado correctamente.'); nuevo(); await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.as-card { margin:16px 18px; }
.as-split { display:flex; gap:18px; flex-wrap:wrap; }
.as-list { width:380px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:64vh; }
.as-list-head { display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:#1b4332; color:#fff; font-size:13px; }
.as-mini { background:#fff; border:none; color:#1b4332; padding:4px 10px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:700; }
.as-list ul { margin:0; padding:0; list-style:none; overflow:auto; }
.as-list li { padding:8px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#1e293b; cursor:pointer; } .as-list li:hover { background:#f0faf4; } .as-list li.on { background:#fff7cc; } .as-list li b { color:#64748b; margin-right:6px; } .as-list li.vacio { color:#94a3b8; cursor:default; }
.as-form { flex:1; min-width:340px; display:flex; flex-direction:column; gap:14px; }
.as-f { display:flex; flex-direction:column; gap:4px; } .as-f.chico { width:120px; } .as-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.as-inp { border:1px solid #d1d5db; border-radius:6px; padding:9px 11px; font-size:14px; color:#1e293b; } .as-inp.ro { background:#f1f5f9; max-width:120px; } .as-inp.ancho { max-width:560px; } .as-inp:disabled { background:#f1f5f9; } .as-inp.exc { border-color:#dc2626; background:#fef2f2; }
.as-cont { font-size:11px; color:#94a3b8; margin-top:2px; max-width:560px; text-align:right; } .as-cont.over { color:#dc2626; font-weight:700; }
.as-exc-msg { color:#b91c1c; font-size:12.5px; font-weight:600; align-self:center; }
.as-acc { display:flex; gap:12px; align-items:center; }
.as-btn { border:none; padding:10px 22px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .as-btn.del { background:#ef4444; } .as-btn:disabled { background:#cbd5e1; cursor:default; }
.as-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:560px; } .as-msg.ok { background:#dcfce7; color:#166534; } .as-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
