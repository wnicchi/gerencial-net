<!-- RelojGruposView.vue — Reloj / Grupos de Turnos Laborales (reloj_grupos). -->
<template>
  <div class="gr-view">
    <div class="gr-cab">
      <div class="gr-cab-ico">🕗</div>
      <div class="gr-cab-tx"><h1>Grupos de Turnos Laborales</h1><p>Definición de los turnos de horario que se asignan al personal</p></div>
      <button class="gr-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="gr-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojGruposAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-grupos" titulo="Asistente IA — Grupos de Turnos"
            subtitulo="Preguntá sobre los turnos" :sugerencias="['¿Cómo cargo un turno?','¿Qué es rotar cada N días?','¿Para qué sirven estos horarios?']"
            @close="modalIA = false" />

    <div class="gr-card">
      <div class="gr-split">
        <div class="gr-list">
          <div class="gr-list-head"><span>Grupos ({{ grupos.length }})</span><button class="gr-mini" @click="nuevo">➕ Nuevo</button></div>
          <ul>
            <li v-for="g in grupos" :key="g.cod" :class="{ on: g.cod === form.cod }" @click="sel(g.cod)"><b>{{ g.cod }}</b> {{ g.des }} <span class="gr-tn">({{ g.turnos }}T)</span></li>
            <li v-if="!grupos.length" class="vacio">Sin grupos cargados.</li>
          </ul>
        </div>

        <div class="gr-form" v-if="cargado">
          <div class="gr-r">
            <div class="gr-f chico"><span class="gr-lbl">Código</span><input :value="form.cod || '(nuevo)'" class="gr-inp ro" readonly /></div>
            <div class="gr-f"><span class="gr-lbl">Descripción</span>
              <input v-model="form.des" class="gr-inp ancho" :class="{ exc: excede }" :disabled="!editable" placeholder="Descripción del grupo…" @input="form.des = form.des.toUpperCase()" />
              <span v-if="editable" class="gr-cont" :class="{ over: excede }">{{ form.des.length }} / {{ MAX }}</span>
            </div>
          </div>

          <div class="gr-turnos">
            <div class="gr-turno" v-for="(t, ti) in form.turnos" :key="ti">
              <div class="gr-turno-tit">TURNO {{ ti + 1 }}</div>
              <table class="gr-tabla">
                <thead><tr><th></th><th>Día</th><th>Desde</th><th>Hasta</th></tr></thead>
                <tbody>
                  <tr v-for="(d, di) in t.dias" :key="di" :class="{ on: activo(d) }">
                    <td class="c"><input type="checkbox" :checked="activo(d)" :disabled="!editable" @change="toggle(d, ($event.target as HTMLInputElement).checked)" /></td>
                    <td class="nom">{{ d.nombre }}</td>
                    <td><input class="gr-h" :value="hm(d.entrada)" :disabled="!editable" @change="d.entrada = parse(($event.target as HTMLInputElement).value)" /></td>
                    <td><input class="gr-h" :value="hm(d.salida)" :disabled="!editable" @change="d.salida = parse(($event.target as HTMLInputElement).value)" /></td>
                  </tr>
                </tbody>
              </table>
              <div class="gr-rotar">Rotar cada <input class="gr-n" type="number" min="0" :disabled="!editable" v-model.number="t.cambio" /> días al turno <input class="gr-n" type="number" min="0" :disabled="!editable" v-model.number="t.pase" /></div>
            </div>
          </div>
          <p class="gr-nota">El sistema computa el primer día hábil del mes con respecto al Turno 1.</p>

          <div class="gr-acc">
            <button v-if="!form.cod" class="gr-btn ok" :disabled="procesando || excede" @click="guardar">💾 Crear</button>
            <button v-else-if="!editando" class="gr-btn ok" @click="editando = true">✏️ Editar</button>
            <button v-else class="gr-btn ok" :disabled="procesando || excede" @click="guardar">💾 Guardar</button>
            <button v-if="form.cod && !editando" class="gr-btn del" :disabled="procesando" @click="eliminar">🗑 Eliminar</button>
            <span v-if="editable && excede" class="gr-exc-msg">⚠ La descripción supera el máximo permitido.</span>
          </div>
          <p v-if="msg" :class="['gr-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/services/auth'
import RelojGruposAyuda from '@/components/RelojGruposAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const MAX = 100   // rgr_des nchar(100)
const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
interface Dia { nombre: string; entrada: number; salida: number }
interface Turno { cambio: number; pase: number; dias: Dia[] }
interface Grupo { cod: number; des: string; turnos: Turno[] }
const grupos = ref<{ cod: number; des: string; turnos: number }[]>([])
const form = reactive<Grupo>({ cod: 0, des: '', turnos: [] })
const cargado = ref(false); const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const editando = ref(false)
const editable = computed(() => !form.cod || editando.value)
const excede = computed(() => form.des.length > MAX)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }
const hm = (v: number) => v ? `${String(Math.floor(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}` : '00:00'
const parse = (s: string): number => { const m = (s || '').match(/(\d{1,2}):?(\d{2})?/); if (!m) return 0; return Math.min(23, +(m[1] || 0)) * 100 + Math.min(59, +(m[2] || 0)) }
const activo = (d: Dia) => d.entrada > 0 || d.salida > 0
const toggle = (d: Dia, on: boolean) => { if (!on) { d.entrada = 0; d.salida = 0 } else if (!d.entrada && !d.salida) { d.entrada = 800; d.salida = 1700 } }
const turnosVacios = (): Turno[] => [0, 1, 2].map(() => ({ cambio: 0, pase: 0, dias: DIAS.map(n => ({ nombre: n, entrada: 0, salida: 0 })) }))

onMounted(cargarLista)
async function cargarLista () { try { grupos.value = (await api.get('/reloj/grupos')).data.grupos ?? []; if (grupos.value.length) sel(grupos.value[0].cod); else nuevo() } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron cargar.', true) } }

const sel = async (cod: number) => {
  try { const { data } = await api.get(`/reloj/grupos/${cod}`); Object.assign(form, { cod: data.cod, des: data.des, turnos: data.turnos }); cargado.value = true; editando.value = false }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar el grupo.', true) }
}
const nuevo = () => { Object.assign(form, { cod: 0, des: '', turnos: turnosVacios() }); cargado.value = true; editando.value = false }

const guardar = async () => {
  if (!form.des.trim()) return flash('Indicá la descripción del grupo.', true)
  if (excede.value) return flash('La descripción supera el máximo permitido. Acortala antes de guardar.', true)
  procesando.value = true
  try {
    const payload = { des: form.des, turnos: form.turnos.map(t => ({ cambio: t.cambio || 0, pase: t.pase || 0, dias: t.dias.map(d => ({ entrada: d.entrada, salida: d.salida })) })) }
    if (form.cod) await api.put(`/reloj/grupos/${form.cod}`, payload)
    else { const { data } = await api.post('/reloj/grupos', payload); form.cod = data.cod }
    flash('Grupo guardado.'); await cargarLista(); if (form.cod) await sel(form.cod)
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo guardar.', true) }
  finally { procesando.value = false }
}
const eliminar = async () => {
  if (!form.cod || !confirm(`¿Elimina el grupo "${form.des}"?`)) return
  procesando.value = true
  try { await api.delete(`/reloj/grupos/${form.cod}`); flash('Grupo eliminado.'); await cargarLista() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo eliminar.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.gr-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.gr-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.gr-cab-ico { font-size:28px; } .gr-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .gr-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.gr-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.gr-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.gr-card { margin:16px 18px; }
.gr-split { display:flex; gap:18px; flex-wrap:wrap; align-items:flex-start; }
.gr-list { width:300px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; max-height:72vh; }
.gr-list-head { display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:#1b4332; color:#fff; font-size:13px; }
.gr-mini { background:#fff; border:none; color:#1b4332; padding:4px 10px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:700; }
.gr-list ul { margin:0; padding:0; list-style:none; overflow:auto; }
.gr-list li { padding:8px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#1e293b; cursor:pointer; } .gr-list li:hover { background:#f0faf4; } .gr-list li.on { background:#fff7cc; } .gr-list li b { color:#64748b; margin-right:6px; } .gr-list li.vacio { color:#94a3b8; cursor:default; } .gr-tn { color:#94a3b8; font-size:11px; }
.gr-form { flex:1; min-width:680px; display:flex; flex-direction:column; gap:14px; }
.gr-r { display:flex; align-items:flex-end; gap:18px; flex-wrap:wrap; }
.gr-f { display:flex; flex-direction:column; gap:4px; } .gr-f.chico { width:110px; } .gr-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.gr-inp { border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:14px; color:#1e293b; } .gr-inp.ro { background:#f1f5f9; max-width:100px; } .gr-inp.ancho { min-width:380px; } .gr-inp:disabled { background:#f1f5f9; } .gr-inp.exc { border-color:#dc2626; background:#fef2f2; }
.gr-cont { font-size:11px; color:#94a3b8; margin-top:2px; text-align:right; } .gr-cont.over { color:#dc2626; font-weight:700; }
.gr-exc-msg { color:#b91c1c; font-size:12.5px; font-weight:600; align-self:center; }
.gr-h:disabled, .gr-n:disabled { background:#f1f5f9; }
.gr-turnos { display:flex; gap:14px; flex-wrap:wrap; }
.gr-turno { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.gr-turno-tit { background:#1b4332; color:#fff; padding:6px 12px; font-weight:800; font-size:13px; }
.gr-tabla { border-collapse:collapse; font-size:12.5px; }
.gr-tabla th { background:#f1f5f9; color:#475569; padding:5px 8px; font-size:11px; text-align:left; }
.gr-tabla td { padding:3px 8px; border-bottom:1px solid #f6f8fa; color:#1e293b; } .gr-tabla td.c { text-align:center; } .gr-tabla td.nom { font-weight:600; min-width:84px; }
.gr-tabla tbody tr.on td { background:#fff7cc; }
.gr-h { width:58px; border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:12px; text-align:center; }
.gr-rotar { padding:6px 10px; font-size:12px; color:#475569; background:#f8fafc; border-top:1px solid #f1f5f9; } .gr-n { width:46px; border:1px solid #d1d5db; border-radius:5px; padding:2px 4px; font-size:12px; text-align:center; margin:0 3px; }
.gr-nota { font-size:12px; color:#b45309; margin:0; }
.gr-acc { display:flex; gap:12px; align-items:center; }
.gr-btn { border:none; padding:10px 22px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .gr-btn.del { background:#ef4444; } .gr-btn:disabled { background:#cbd5e1; cursor:default; }
.gr-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:680px; } .gr-msg.ok { background:#dcfce7; color:#166534; } .gr-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
