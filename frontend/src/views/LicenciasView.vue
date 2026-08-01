<!-- LicenciasView.vue — Reloj / Licencias Laborales (licencias-laborales). -->
<template>
  <div class="li-view">
    <div class="li-cab">
      <div class="li-cab-ico">📄</div>
      <div class="li-cab-tx"><h1>Licencias Laborales</h1><p>Tipos de licencia que se usan al cargar las faltas</p></div>
      <button class="li-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="li-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <LicenciasAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/licencias" titulo="Asistente IA — Licencias"
            subtitulo="Preguntá sobre los tipos de licencia" :sugerencias="['¿Qué es con/sin goce?','¿Qué significa no aplicar en planilla?','¿Puedo borrar una licencia usada?']"
            @close="modalIA = false" />

    <div class="li-card">
      <div class="li-split">
        <div class="li-list">
          <div class="li-list-head"><span>Licencias ({{ licencias.length }})</span><button class="li-mini" @click="nuevo">➕ Nueva</button></div>
          <ul>
            <li v-for="l in licencias" :key="l.cod" :class="{ on: l.cod === form.cod }" @click="sel(l)"><b>{{ l.cod }}</b> {{ l.detalle }}</li>
            <li v-if="!licencias.length" class="vacio">Sin licencias cargadas.</li>
          </ul>
        </div>
        <div class="li-form" v-enter-next>
          <div class="li-r">
            <div class="li-f chico"><span class="li-lbl">Código</span><input :value="form.cod || '(nueva)'" class="li-inp ro" readonly /></div>
            <label class="li-chk"><input type="checkbox" v-model="form.noPlanilla" :disabled="!editable" /> No Aplicar en Planilla de Novedades</label>
          </div>
          <div class="li-f"><span class="li-lbl">Descripción</span>
            <input v-model="form.detalle" class="li-inp ancho" :class="{ exc: excede }" :disabled="!editable" placeholder="Descripción de la licencia…" @input="form.detalle = form.detalle.toUpperCase()" />
            <span v-if="editable" class="li-cont" :class="{ over: excede }">{{ form.detalle.length }} / {{ MAX }}</span>
          </div>
          <div class="li-chks">
            <label class="li-chk"><input type="checkbox" v-model="form.conGoce" :disabled="!editable" /> Licencia CON GOCE</label>
            <label class="li-chk"><input type="checkbox" v-model="form.sinGoce" :disabled="!editable" /> Licencia SIN GOCE</label>
          </div>
          <div class="li-acc">
            <button v-if="!form.cod" class="li-btn ok" :disabled="procesando || excede" @click="guardar">💾 Crear</button>
            <button v-else-if="!editando" class="li-btn ok" @click="editando = true">✏️ Editar</button>
            <button v-else class="li-btn ok" :disabled="procesando || excede" @click="guardar">💾 Guardar</button>
            <button v-if="form.cod && !editando" class="li-btn del" :disabled="procesando" @click="eliminar">🗑 Eliminar</button>
            <span v-if="editable && excede" class="li-exc-msg">⚠ Supera el máximo permitido.</span>
          </div>
          <p v-if="msg" :class="['li-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import LicenciasAyuda from '@/components/LicenciasAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const MAX = 100   // lic_det char(100)
interface Lic { cod: number; detalle: string; conGoce: boolean; sinGoce: boolean; noPlanilla: boolean }
const licencias = ref<Lic[]>([])
const form = reactive<Lic>({ cod: 0, detalle: '', conGoce: false, sinGoce: false, noPlanilla: false })
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const editando = ref(false)
const editable = computed(() => !form.cod || editando.value)
const excede = computed(() => form.detalle.length > MAX)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }

onMounted(cargar)
async function cargar () { try { licencias.value = (await api.get('/licencias')).data.licencias ?? [] } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar.', true) } }
const sel = (l: Lic) => { Object.assign(form, l); editando.value = false }
const nuevo = () => { Object.assign(form, { cod: 0, detalle: '', conGoce: false, sinGoce: false, noPlanilla: false }); editando.value = false }

const guardar = async () => {
  if (!form.detalle.trim()) return flash('Indicá la descripción.', true)
  if (excede.value) return flash('La descripción supera el máximo permitido. Acortala antes de guardar.', true)
  procesando.value = true
  try {
    const payload = { detalle: form.detalle, conGoce: form.conGoce, sinGoce: form.sinGoce, noPlanilla: form.noPlanilla }
    if (form.cod) await api.put(`/licencias/${form.cod}`, payload)
    else { const { data } = await api.post('/licencias', payload); form.cod = data.cod }
    editando.value = false; flash('Licencia guardada.'); await cargar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}
const eliminar = async () => {
  if (!form.cod || !confirm(`¿Elimina la licencia "${form.detalle}"?`)) return
  procesando.value = true
  try { await api.delete(`/licencias/${form.cod}`); flash('Licencia eliminada.'); nuevo(); await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.li-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.li-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.li-cab-ico { font-size:28px; } .li-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .li-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.li-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.li-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.li-card { margin:16px 18px; }
.li-split { display:flex; gap:18px; flex-wrap:wrap; }
.li-list { width:360px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:64vh; }
.li-list-head { display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:#1b4332; color:#fff; font-size:13px; }
.li-mini { background:#fff; border:none; color:#1b4332; padding:4px 10px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:700; }
.li-list ul { margin:0; padding:0; list-style:none; overflow:auto; }
.li-list li { padding:8px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#1e293b; cursor:pointer; } .li-list li:hover { background:#f0faf4; } .li-list li.on { background:#fff7cc; } .li-list li b { color:#64748b; margin-right:6px; } .li-list li.vacio { color:#94a3b8; cursor:default; }
.li-form { flex:1; min-width:340px; display:flex; flex-direction:column; gap:14px; }
.li-r { display:flex; align-items:flex-end; gap:20px; flex-wrap:wrap; }
.li-f { display:flex; flex-direction:column; gap:4px; } .li-f.chico { width:120px; } .li-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.li-inp { border:1px solid #d1d5db; border-radius:6px; padding:9px 11px; font-size:14px; color:#1e293b; } .li-inp.ro { background:#f1f5f9; max-width:120px; } .li-inp.ancho { max-width:520px; } .li-inp:disabled { background:#f1f5f9; } .li-inp.exc { border-color:#dc2626; background:#fef2f2; }
.li-cont { font-size:11px; color:#94a3b8; margin-top:2px; max-width:520px; text-align:right; } .li-cont.over { color:#dc2626; font-weight:700; }
.li-exc-msg { color:#b91c1c; font-size:12.5px; font-weight:600; align-self:center; }
.li-chks { display:flex; gap:24px; flex-wrap:wrap; }
.li-chk { display:flex; align-items:center; gap:7px; font-size:13px; color:#1e293b; font-weight:600; padding-bottom:7px; }
.li-acc { display:flex; gap:12px; align-items:center; }
.li-btn { border:none; padding:10px 22px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .li-btn.del { background:#ef4444; } .li-btn:disabled { background:#cbd5e1; cursor:default; }
.li-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:520px; } .li-msg.ok { background:#dcfce7; color:#166534; } .li-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
