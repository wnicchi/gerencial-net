<!-- RelojUbicacionView.vue — Reloj / Reloj Ubicación (reloj_ubicaciones). -->
<template>
  <div class="ub-view">
    <div class="ub-cab">
      <div class="ub-cab-ico">📍</div>
      <div class="ub-cab-tx"><h1>Reloj Ubicación</h1><p>Ubicaciones físicas de los relojes de personal</p></div>
      <button class="ub-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ub-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojUbicacionAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-ubicaciones" titulo="Asistente IA — Reloj Ubicación"
            subtitulo="Preguntá sobre las ubicaciones" :sugerencias="['¿Qué es una ubicación de reloj?','¿Cómo agrego una?','¿Puedo eliminarla?']"
            @close="modalIA = false" />

    <div class="ub-card">
      <div class="ub-split">
        <div class="ub-list">
          <div class="ub-list-head"><span>Ubicaciones ({{ ubicaciones.length }})</span><button class="ub-mini" @click="nuevo">➕ Nueva</button></div>
          <ul>
            <li v-for="u in ubicaciones" :key="u.cod" :class="{ on: u.cod === form.cod }" @click="sel(u)"><b>{{ u.cod }}</b> {{ u.descripcion }}</li>
            <li v-if="!ubicaciones.length" class="vacio">Sin ubicaciones cargadas.</li>
          </ul>
        </div>
        <div class="ub-form" v-enter-next>
          <div class="ub-f"><span class="ub-lbl">Código</span><input :value="form.cod || '(nueva)'" class="ub-inp ro" readonly /></div>
          <div class="ub-f">
            <span class="ub-lbl">Descripción</span>
            <input v-model="form.descripcion" class="ub-inp ancho" :class="{ exc: excede }" :disabled="!editable" placeholder="Ubicación del reloj…" @input="form.descripcion = form.descripcion.toUpperCase()" />
            <span v-if="editable" class="ub-cont" :class="{ over: excede }">{{ form.descripcion.length }} / {{ MAX }}</span>
          </div>
          <div class="ub-acc">
            <button v-if="!form.cod" class="ub-btn ok" :disabled="procesando || excede" @click="guardar">💾 Crear</button>
            <button v-else-if="!editando" class="ub-btn ok" @click="editando = true">✏️ Editar</button>
            <button v-else class="ub-btn ok" :disabled="procesando || excede" @click="guardar">💾 Guardar</button>
            <button v-if="form.cod && !editando" class="ub-btn del" :disabled="procesando" @click="eliminar">🗑 Eliminar</button>
            <span v-if="editable && excede" class="ub-exc-msg">⚠ Supera el máximo permitido.</span>
          </div>
          <p v-if="msg" :class="['ub-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import RelojUbicacionAyuda from '@/components/RelojUbicacionAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const MAX = 50   // ure_des nchar(50)
interface Ub { cod: number; descripcion: string }
const ubicaciones = ref<Ub[]>([])
const form = reactive<Ub>({ cod: 0, descripcion: '' })
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const editando = ref(false)
const editable = computed(() => !form.cod || editando.value)
const excede = computed(() => form.descripcion.length > MAX)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

onMounted(cargar)
async function cargar () { try { ubicaciones.value = (await api.get('/reloj/ubicaciones')).data.ubicaciones ?? [] } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar.', true) } }
const sel = (u: Ub) => { Object.assign(form, u); editando.value = false }
const nuevo = () => { Object.assign(form, { cod: 0, descripcion: '' }); editando.value = false }

const guardar = async () => {
  if (!form.descripcion.trim()) return flash('Indicá la descripción.', true)
  if (excede.value) return flash('La descripción supera el máximo permitido. Acortala antes de guardar.', true)
  procesando.value = true
  try {
    if (form.cod) await api.put(`/reloj/ubicaciones/${form.cod}`, { descripcion: form.descripcion })
    else { const { data } = await api.post('/reloj/ubicaciones', { descripcion: form.descripcion }); form.cod = data.cod }
    editando.value = false; flash('Ubicación guardada.'); await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}
const eliminar = async () => {
  if (!form.cod || !confirm(`¿Elimina la ubicación "${form.descripcion}"?`)) return
  procesando.value = true
  try { await api.delete(`/reloj/ubicaciones/${form.cod}`); flash('Ubicación eliminada.'); nuevo(); await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.ub-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.ub-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ub-cab-ico { font-size:28px; } .ub-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ub-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ub-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ub-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ub-card { margin:16px 18px; }
.ub-split { display:flex; gap:18px; flex-wrap:wrap; }
.ub-list { width:340px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:60vh; }
.ub-list-head { display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:#1b4332; color:#fff; font-size:13px; }
.ub-mini { background:#fff; border:none; color:#1b4332; padding:4px 10px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:700; }
.ub-list ul { margin:0; padding:0; list-style:none; overflow:auto; }
.ub-list li { padding:8px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#1e293b; cursor:pointer; } .ub-list li:hover { background:#f0faf4; } .ub-list li.on { background:#fff7cc; } .ub-list li b { color:#64748b; margin-right:6px; } .ub-list li.vacio { color:#94a3b8; cursor:default; }
.ub-form { flex:1; min-width:320px; display:flex; flex-direction:column; gap:14px; }
.ub-f { display:flex; flex-direction:column; gap:4px; } .ub-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.ub-inp { border:1px solid #d1d5db; border-radius:6px; padding:9px 11px; font-size:14px; color:#1e293b; } .ub-inp.ro { background:#f1f5f9; max-width:130px; } .ub-inp.ancho { max-width:480px; } .ub-inp:disabled { background:#f1f5f9; } .ub-inp.exc { border-color:#dc2626; background:#fef2f2; }
.ub-cont { font-size:11px; color:#94a3b8; margin-top:2px; max-width:480px; text-align:right; } .ub-cont.over { color:#dc2626; font-weight:700; }
.ub-exc-msg { color:#b91c1c; font-size:12.5px; font-weight:600; align-self:center; }
.ub-acc { display:flex; gap:12px; align-items:center; }
.ub-btn { border:none; padding:10px 22px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .ub-btn.del { background:#ef4444; } .ub-btn:disabled { background:#cbd5e1; cursor:default; }
.ub-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:480px; } .ub-msg.ok { background:#dcfce7; color:#166534; } .ub-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
