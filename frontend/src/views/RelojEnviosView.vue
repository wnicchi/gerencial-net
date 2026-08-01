<!-- RelojEnviosView.vue — Reloj / Envíos Automáticos de Partes Diarios (reloj_envios). -->
<template>
  <div class="en-view">
    <div class="en-cab">
      <div class="en-cab-ico">📧</div>
      <div class="en-cab-tx"><h1>Envíos Automáticos de Partes Diarios</h1><p>Programas de envío automático del parte diario por email</p></div>
      <button class="en-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="en-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojEnviosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-envios" titulo="Asistente IA — Envíos de Partes"
            subtitulo="Preguntá sobre los envíos automáticos" :sugerencias="['¿Cómo creo un programa?','¿Cómo asocio empleados?','¿Qué son los 3 horarios?']"
            @close="modalIA = false" />

    <div class="en-card">
      <div class="en-tabs">
        <button :class="{ act: tab === 'prog' }" @click="tab = 'prog'">Programación de Envíos</button>
        <button :class="{ act: tab === 'emp' }" :disabled="!actual" @click="irAsociados">Empleados Asociados al Informe</button>
      </div>

      <!-- Solapa 1: Programación -->
      <div v-show="tab === 'prog'" class="en-prog">
        <div class="en-nav">
          <button class="en-navb" :disabled="!envios.length || idx === 0" @click="ir(0)">⏮</button>
          <button class="en-navb" :disabled="!envios.length || idx === 0" @click="ir(idx - 1)">◀</button>
          <span class="en-pos">{{ envios.length ? (idx + 1) : 0 }} / {{ envios.length }}</span>
          <button class="en-navb" :disabled="!envios.length || idx >= envios.length - 1" @click="ir(idx + 1)">▶</button>
          <button class="en-navb" :disabled="!envios.length || idx >= envios.length - 1" @click="ir(envios.length - 1)">⏭</button>
          <span class="en-sep"></span>
          <button class="en-btn nuevo" @click="nuevo">➕ Nuevo</button>
          <button class="en-btn ok" :disabled="procesando" @click="guardar">💾 {{ form.cod ? 'Guardar' : 'Crear' }}</button>
          <button class="en-btn del" :disabled="!form.cod || procesando" @click="eliminar">🗑 Eliminar</button>
        </div>

        <div class="en-form" v-enter-next>
          <div class="en-r">
            <div class="en-f chico"><span class="en-lbl">Código</span><input :value="form.cod || '(nuevo)'" class="en-inp ro" readonly /></div>
            <label class="en-activo" :class="{ on: form.act }"><input type="checkbox" v-model="form.act" /> Programa Activo</label>
          </div>
          <div class="en-f"><span class="en-lbl">Descripción</span><input v-model="form.des" class="en-inp ancho" placeholder="Descripción del programa…" /></div>

          <table class="en-dias">
            <thead><tr><th>Día</th><th></th><th>1ª Hora</th><th>2ª Hora</th><th>3ª Hora</th><th>Último envío</th></tr></thead>
            <tbody>
              <tr v-for="(d, i) in form.dias" :key="i" :class="{ on: d.activo }">
                <td class="nom">{{ d.nombre }}</td>
                <td class="c"><input type="checkbox" v-model="d.activo" /></td>
                <td><input class="en-h" :value="hm(d.h1)" :disabled="!d.activo" @change="d.h1 = parse(($event.target as HTMLInputElement).value)" /></td>
                <td><input class="en-h" :value="hm(d.h2)" :disabled="!d.activo" @change="d.h2 = parse(($event.target as HTMLInputElement).value)" /></td>
                <td><input class="en-h" :value="hm(d.h3)" :disabled="!d.activo" @change="d.h3 = parse(($event.target as HTMLInputElement).value)" /></td>
                <td class="ult">{{ d.ultimo }}</td>
              </tr>
            </tbody>
          </table>

          <div class="en-emails">
            <span class="en-lbl">Emails destinatarios</span>
            <input v-for="(e, i) in form.emails" :key="i" v-model="form.emails[i]" class="en-inp ancho" :placeholder="`Email ${i + 1}…`" />
          </div>
        </div>
      </div>

      <!-- Solapa 2: Empleados asociados -->
      <div v-show="tab === 'emp'" class="en-emp">
        <div class="en-emp-bar">
          <span class="en-emp-tit">{{ actual?.des }}</span>
          <input v-model="buscarEmp" type="text" class="en-inp" placeholder="Buscar empleado…" />
          <span class="en-cont">{{ asocSel }} asociados</span>
          <button class="en-btn ok" :disabled="procesando" @click="grabarAsociados">💾 Grabar Asociados</button>
        </div>
        <div class="en-grid-wrap">
          <table class="en-tabla">
            <thead><tr><th>Elegir</th><th>Código</th><th>Empleado</th><th>Legajo</th><th>Domicilio</th></tr></thead>
            <tbody>
              <tr v-for="a in asociadosFiltrados" :key="a.cod" :class="{ on: a.elegir }">
                <td class="c"><input type="checkbox" v-model="a.elegir" /></td>
                <td>{{ a.cod }}</td><td>{{ a.nombre }}</td><td>{{ a.legajo }}</td><td>{{ a.domicilio }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="msg" :class="['en-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import RelojEnviosAyuda from '@/components/RelojEnviosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const tab = ref<'prog' | 'emp'>('prog')
const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']
interface Dia { nombre: string; activo: boolean; h1: number; h2: number; h3: number; ultimo: string }
interface Env { cod: number; des: string; act: boolean; emails: string[]; dias: Dia[] }
const envios = ref<Env[]>([]); const idx = ref(0)
const form = reactive<Env>({ cod: 0, des: '', act: false, emails: ['', '', '', '', ''], dias: [] })
const asociados = ref<any[]>([]); const buscarEmp = ref('')
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const actual = computed(() => form.cod ? form : null)
const asocSel = computed(() => asociados.value.filter(a => a.elegir).length)
const asociadosFiltrados = computed(() => { const q = buscarEmp.value.trim().toLowerCase(); return q ? asociados.value.filter(a => a.nombre.toLowerCase().includes(q) || String(a.legajo).includes(q) || String(a.cod).includes(q)) : asociados.value })
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }
const hm = (v: number) => v ? `${String(Math.floor(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}` : ''
const parse = (s: string): number => { const m = (s || '').match(/(\d{1,2}):?(\d{2})?/); if (!m) return 0; return Math.min(23, +(m[1] || 0)) * 100 + Math.min(59, +(m[2] || 0)) }
const diasVacios = (): Dia[] => DIAS.map(n => ({ nombre: n, activo: false, h1: 0, h2: 0, h3: 0, ultimo: '' }))

onMounted(cargar)
async function cargar () {
  try { envios.value = (await api.get('/reloj/envios')).data.envios ?? []; if (envios.value.length) ir(0); else nuevo() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar los programas.', true) }
}

const ir = (i: number) => {
  if (i < 0 || i >= envios.value.length) return
  idx.value = i; const e = envios.value[i]
  Object.assign(form, { cod: e.cod, des: e.des, act: e.act, emails: [...e.emails], dias: e.dias.map(d => ({ ...d })) })
}
const nuevo = () => { Object.assign(form, { cod: 0, des: '', act: true, emails: ['', '', '', '', ''], dias: diasVacios() }) }

const guardar = async () => {
  if (!form.des.trim()) return flash('Indicá la descripción del programa.', true)
  procesando.value = true
  try {
    const payload = { des: form.des, act: form.act, emails: form.emails, dias: form.dias.map(d => ({ activo: d.activo, h1: d.h1, h2: d.h2, h3: d.h3 })) }
    if (form.cod) await api.put(`/reloj/envios/${form.cod}`, payload)
    else { const { data } = await api.post('/reloj/envios', payload); form.cod = data.cod }
    flash('Programa guardado correctamente.')
    await cargar(); const i = envios.value.findIndex(e => e.cod === form.cod); if (i >= 0) ir(i)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}

const eliminar = async () => {
  if (!form.cod) return
  if (!confirm(`¿Elimina el programa "${form.des}"?`)) return
  procesando.value = true
  try { await api.delete(`/reloj/envios/${form.cod}`); flash('Programa eliminado.'); await cargar() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}

const irAsociados = async () => {
  if (!form.cod) return flash('Guardá el programa antes de asociar empleados.', true)
  tab.value = 'emp'
  try { asociados.value = (await api.get(`/reloj/envios/${form.cod}/asociados`)).data.asociados ?? [] }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar los empleados.', true) }
}

const grabarAsociados = async () => {
  procesando.value = true
  try { const elegidos = asociados.value.filter(a => a.elegir).map(a => a.cod); const { data } = await api.post(`/reloj/envios/${form.cod}/asociados`, { elegidos }); flash(data.message) }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron grabar los asociados.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.en-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.en-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.en-cab-ico { font-size:28px; } .en-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .en-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.en-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.en-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.en-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.en-tabs { display:flex; gap:4px; border-bottom:2px solid #1b4332; }
.en-tabs button { border:none; background:#e2e8f0; color:#475569; padding:9px 20px; border-radius:8px 8px 0 0; cursor:pointer; font-size:13px; font-weight:700; } .en-tabs button.act { background:#1b4332; color:#fff; } .en-tabs button:disabled { opacity:.5; cursor:default; }
.en-nav { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.en-navb { background:#fff; border:1px solid #cbd5e1; border-radius:6px; width:34px; height:32px; cursor:pointer; font-size:13px; } .en-navb:disabled { opacity:.4; cursor:default; }
.en-pos { font-size:12px; color:#64748b; margin:0 6px; } .en-sep { flex:1; }
.en-btn { border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .en-btn.nuevo { background:#0e7490; } .en-btn.del { background:#ef4444; } .en-btn:disabled { background:#cbd5e1; cursor:default; }
.en-form { display:flex; flex-direction:column; gap:12px; }
.en-r { display:flex; align-items:flex-end; gap:18px; }
.en-f { display:flex; flex-direction:column; gap:4px; } .en-f.chico { width:110px; } .en-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.en-inp { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:13px; color:#1e293b; } .en-inp.ro { background:#f1f5f9; } .en-inp.ancho { min-width:480px; max-width:620px; }
.en-activo { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#64748b; padding:7px 12px; border-radius:6px; border:1px solid #e2e8f0; } .en-activo.on { color:#166534; background:#dcfce7; border-color:#bbf7d0; }
.en-dias { border-collapse:collapse; font-size:13px; max-width:640px; }
.en-dias th { background:#1b4332; color:#fff; padding:6px 10px; text-align:left; font-size:12px; }
.en-dias td { padding:4px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .en-dias td.c { text-align:center; } .en-dias td.nom { font-weight:600; } .en-dias td.ult { color:#64748b; font-size:11px; }
.en-dias tbody tr.on td { background:#f0faf4; }
.en-h { width:64px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:12.5px; text-align:center; } .en-h:disabled { background:#f1f5f9; color:#cbd5e1; }
.en-emails { display:flex; flex-direction:column; gap:6px; }
.en-emp-bar { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.en-emp-tit { font-weight:800; color:#1b4332; font-size:14px; } .en-cont { font-size:12px; color:#64748b; margin-left:auto; }
.en-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:58vh; }
.en-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.en-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:7px 8px; text-align:left; white-space:nowrap; }
.en-tabla td { padding:5px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .en-tabla td.c { text-align:center; }
.en-tabla tbody tr.on td { background:#fff7a8; }
.en-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:760px; } .en-msg.ok { background:#dcfce7; color:#166534; } .en-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
