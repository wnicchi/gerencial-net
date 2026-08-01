<!-- RelojAjustesAbmView.vue — Reloj / Ajuste de Horarios: unificado (editor por día + grilla de ajustes cargados con borrado).
     Reemplaza "Ajuste Horarios Trabajados" + "Ajuste Horarios — Borrar". "Ajuste por Períodos" queda aparte. -->
<template>
  <div class="ra-view">
    <div class="ra-cab">
      <div class="ra-cab-ico">🕐</div>
      <div class="ra-cab-tx"><h1>Ajuste de Horarios Trabajados</h1><p>Corregir el horario de un empleado y administrar los ajustes cargados</p></div>
      <button class="ra-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="ra-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojAjustesAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-ajustes" titulo="Asistente IA — Ajuste de Horarios"
            subtitulo="Preguntá sobre la corrección de marcaciones"
            :sugerencias="['¿Cómo cargo un ajuste?','¿Qué significa ignorar una captura?','¿Cómo borro ajustes cargados?']"
            @close="modalIA = false" />

    <div class="ra-card" v-enter-next>
      <div class="ra-top">
        <div class="ra-col">
          <div class="ra-f bus">
            <span class="ra-lbl">Personal</span>
            <input v-model="busqueda" type="text" class="ra-inp" placeholder="Código o nombre…" @input="buscar" @focus="buscar" />
            <ul v-if="resultados.length" class="ra-result">
              <li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li>
            </ul>
          </div>
          <div class="ra-f"><span class="ra-lbl">Nombre</span><input :value="emp.nombre" class="ra-inp ro ancho" readonly /></div>
          <div class="ra-f"><span class="ra-lbl">Fecha</span><input v-model="fecha" type="date" class="ra-inp" @change="cargarDia" /></div>

          <div class="ra-turnos" v-if="emp.cod">
            <div class="ra-turno-head"><span></span><span>H. Entrada</span><span></span><span>H. Salida</span></div>
            <div class="ra-turno">
              <label class="ra-tlbl"><input type="checkbox" v-model="form.ve1" /> Turno 1</label>
              <div class="ra-hm"><input type="number" min="0" max="23" v-model.number="t1.eh" class="ra-sm" :disabled="!form.ve1" /> : <input type="number" min="0" max="59" v-model.number="t1.em" class="ra-sm" :disabled="!form.ve1" /></div>
              <label class="ra-chkmini"><input type="checkbox" v-model="form.vs1" /></label>
              <div class="ra-hm"><input type="number" min="0" max="23" v-model.number="t1.sh" class="ra-sm" :disabled="!form.vs1" /> : <input type="number" min="0" max="59" v-model.number="t1.sm" class="ra-sm" :disabled="!form.vs1" /></div>
            </div>
            <div class="ra-turno">
              <label class="ra-tlbl"><input type="checkbox" v-model="form.ve2" /> Turno 2</label>
              <div class="ra-hm"><input type="number" min="0" max="23" v-model.number="t2.eh" class="ra-sm" :disabled="!form.ve2" /> : <input type="number" min="0" max="59" v-model.number="t2.em" class="ra-sm" :disabled="!form.ve2" /></div>
              <label class="ra-chkmini"><input type="checkbox" v-model="form.vs2" /></label>
              <div class="ra-hm"><input type="number" min="0" max="23" v-model.number="t2.sh" class="ra-sm" :disabled="!form.vs2" /> : <input type="number" min="0" max="59" v-model.number="t2.sm" class="ra-sm" :disabled="!form.vs2" /></div>
            </div>
            <div class="ra-pie">
              <button class="ra-btn-ok" :disabled="procesando" @click="confirmar">{{ procesando ? '⟳…' : 'CONFIRMAR' }}</button>
              <button class="ra-btn-del" :disabled="procesando" @click="borrar">🗑 Borrar ajustes del día</button>
              <button class="ra-btn-sec" @click="reset">↺ Reset</button>
            </div>
          </div>
        </div>

        <div class="ra-col2" v-if="emp.cod">
          <div class="ra-panel">
            <div class="ra-panel-tit azul">Ajustes realizados en la fecha</div>
            <div class="ra-panel-bo">
              <div v-if="ajusteActual" class="ra-aj">
                <div v-if="ajusteActual.ve1 || ajusteActual.vs1">Turno 1: {{ ajusteActual.ve1 ? hm(ajusteActual.hen1) : '—' }} a {{ ajusteActual.vs1 ? hm(ajusteActual.hsa1) : '—' }}</div>
                <div v-if="ajusteActual.ve2 || ajusteActual.vs2">Turno 2: {{ ajusteActual.ve2 ? hm(ajusteActual.hen2) : '—' }} a {{ ajusteActual.vs2 ? hm(ajusteActual.hsa2) : '—' }}</div>
              </div>
              <p v-else class="ra-vacio">Sin ajustes cargados para esta fecha.</p>
            </div>
          </div>
          <div class="ra-panel">
            <div class="ra-panel-tit verde">Capturas reales en el reloj en la fecha</div>
            <table class="ra-tabla">
              <thead><tr><th>Ignorar</th><th>Fecha y hora del registro real</th></tr></thead>
              <tbody>
                <tr v-for="c in capturas" :key="c.unico" :class="{ ign: c.ignorar }">
                  <td class="c"><input type="checkbox" v-model="c.ignorar" /></td>
                  <td class="reg">{{ fmtFecha(c.fecha) }}</td>
                </tr>
                <tr v-if="!capturas.length"><td colspan="2" class="ra-vacio">No hay marcaciones reales ese día.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <p v-if="msg" :class="['ra-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>

      <!-- ── Grilla de ajustes cargados (reemplaza "Ajuste Horarios — Borrar") ── -->
      <div class="ra-lista">
        <div class="ra-lista-tools">
          <b>Ajustes cargados</b>
          <label class="ra-rango">Desde <input v-model="lDesde" type="date" @change="cargarLista" /></label>
          <label class="ra-rango">Hasta <input v-model="lHasta" type="date" @change="cargarLista" /></label>
          <input v-model="lNombre" class="ra-lbus" placeholder="🔍 Nombre…" @keyup.enter="cargarLista" />
          <button class="ra-btn-sec" @click="cargarLista">Buscar</button>
          <span class="ra-count">{{ lista.length }} ajuste(s)</span>
          <span style="flex:1"></span>
          <button class="ra-btn-del" :disabled="!seleccionados.length" @click="borrarLote">🗑 Borrar seleccionados ({{ seleccionados.length }})</button>
        </div>
        <div class="ra-lista-wrap">
          <table class="ra-tabla2">
            <thead><tr><th style="width:36px" class="c"><input type="checkbox" :checked="todosSel" @change="toggleTodos" /></th><th style="width:70px">Cód.</th><th>Empleado</th><th style="width:110px">Fecha</th><th style="width:80px" class="c">Cargar</th></tr></thead>
            <tbody>
              <tr v-if="!lista.length"><td colspan="5" class="ra-vacio">Sin ajustes en el rango.</td></tr>
              <tr v-for="a in lista" :key="a.cod + '_' + a.fecha" :class="{ sel: a.sel }">
                <td class="c"><input type="checkbox" v-model="a.sel" /></td>
                <td>{{ a.cod }}</td><td>{{ a.nombre }}</td><td>{{ fmt(a.fecha) }}</td>
                <td class="c"><button class="ra-mini" title="Cargar en el editor" @click="cargarEnEditor(a)">✏️</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import api from '@/services/auth'
import RelojAjustesAyuda from '@/components/RelojAjustesAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const fecha = ref(_iso(new Date()))
const emp = reactive({ cod: 0, nombre: '' })
const form = reactive({ ve1: false, vs1: false, ve2: false, vs2: false })
const t1 = reactive({ eh: 0, em: 0, sh: 0, sm: 0 })
const t2 = reactive({ eh: 0, em: 0, sh: 0, sm: 0 })
const capturas = ref<{ unico: number; fecha: string; ignorar: boolean }[]>([])
const ajusteActual = ref<any>(null)
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }
const hm = (v: number) => `${String(Math.floor(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}`
const fmt = (d: string) => d ? d.split('-').reverse().join('/') : ''
const fmtFecha = (iso: string) => { const [f, h] = iso.split(' '); const [a, m, d] = f.split('-'); const [hh, mm] = (h || '00:00').split(':'); let H = parseInt(hh, 10); const ap = H >= 12 ? 'PM' : 'AM'; H = H % 12 || 12; return `${d}/${m}/${a} ${String(H).padStart(2, '0')}:${mm} ${ap}` }

const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8, activo: 1 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionar = async (r: any) => { busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = []; emp.cod = r.PER_COD; emp.nombre = (r.PER_NOM || '').trim(); await cargarDia() }

const reset = () => { form.ve1 = form.vs1 = form.ve2 = form.vs2 = false; Object.assign(t1, { eh: 0, em: 0, sh: 0, sm: 0 }); Object.assign(t2, { eh: 0, em: 0, sh: 0, sm: 0 }) }

const cargarDia = async () => {
  if (!emp.cod) return
  try {
    const { data } = await api.get('/reloj/ajustes/dia', { params: { cod: emp.cod, fecha: fecha.value } })
    capturas.value = data.capturas ?? []; ajusteActual.value = data.ajuste
    reset()
    if (data.ajuste) {
      const a = data.ajuste
      form.ve1 = a.ve1; form.vs1 = a.vs1; form.ve2 = a.ve2; form.vs2 = a.vs2
      t1.eh = Math.floor(a.hen1 / 100); t1.em = a.hen1 % 100; t1.sh = Math.floor(a.hsa1 / 100); t1.sm = a.hsa1 % 100
      t2.eh = Math.floor(a.hen2 / 100); t2.em = a.hen2 % 100; t2.sh = Math.floor(a.hsa2 / 100); t2.sm = a.hsa2 % 100
    }
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la fecha.', true) }
}

const confirmar = async () => {
  if (!emp.cod) return flash('Seleccioná un empleado.', true)
  if (!confirm('¿Confirma el ajuste de horarios?')) return
  procesando.value = true
  try {
    await api.post('/reloj/ajustes', {
      cod: emp.cod, fecha: fecha.value,
      hen1: t1.eh * 100 + t1.em, hsa1: t1.sh * 100 + t1.sm, hen2: t2.eh * 100 + t2.em, hsa2: t2.sh * 100 + t2.sm,
      ve1: form.ve1, vs1: form.vs1, ve2: form.ve2, vs2: form.vs2,
      capturas: capturas.value.map(c => ({ unico: c.unico, ignorar: c.ignorar })),
    })
    flash('Ajuste grabado correctamente.')
    await cargarDia(); await cargarLista()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo grabar el ajuste.', true) }
  finally { procesando.value = false }
}

const borrar = async () => {
  if (!emp.cod) return
  if (!confirm(`¿Seguro de borrar los ajustes de este día del empleado ${emp.nombre}?`)) return
  procesando.value = true
  try { const { data } = await api.post('/reloj/ajustes/eliminar', { cod: emp.cod, fecha: fecha.value }); flash(data.message); await cargarDia(); await cargarLista() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
  finally { procesando.value = false }
}

// ── Grilla de ajustes cargados ──
const lDesde = ref(_iso(new Date(Date.now() - 30 * 864e5))); const lHasta = ref(_iso(new Date())); const lNombre = ref('')
const lista = ref<any[]>([])
const seleccionados = computed(() => lista.value.filter(a => a.sel))
const todosSel = computed(() => lista.value.length > 0 && lista.value.every(a => a.sel))
const toggleTodos = (e: Event) => { const v = (e.target as HTMLInputElement).checked; lista.value.forEach(a => a.sel = v) }

const cargarLista = async () => {
  try { lista.value = (await api.get('/reloj/ajustes/lista', { params: { desde: lDesde.value, hasta: lHasta.value, nombre: lNombre.value } })).data.ajustes ?? [] }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo cargar la lista de ajustes.', true) }
}
const cargarEnEditor = async (a: any) => { emp.cod = a.cod; emp.nombre = a.nombre; busqueda.value = `${a.cod} — ${a.nombre}`; fecha.value = a.fecha; await cargarDia(); window.scrollTo({ top: 0, behavior: 'smooth' }) }

const borrarLote = async () => {
  const items = seleccionados.value.map(a => ({ cod: a.cod, fecha: a.fecha }))
  if (!items.length) return
  if (!confirm(`¿Borrar ${items.length} ajuste(s) seleccionado(s)?`)) return
  try { const { data } = await api.post('/reloj/ajustes/borrar-lote', { items }); flash(data.message); await cargarLista() }
  catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo borrar.', true) }
}

cargarLista()
</script>

<style scoped>
.ra-view { display:flex; flex-direction:column; min-height:100%; }
.ra-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.ra-cab-ico { font-size:28px; } .ra-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .ra-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.ra-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ra-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.ra-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.ra-top { display:flex; gap:24px; flex-wrap:wrap; }
.ra-col { flex:1; min-width:360px; display:flex; flex-direction:column; gap:12px; }
.ra-col2 { flex:1; min-width:360px; display:flex; flex-direction:column; gap:14px; }
.ra-f { display:flex; flex-direction:column; gap:4px; position:relative; } .ra-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.ra-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .ra-inp.ro { background:#f1f5f9; } .ra-inp.ancho { min-width:360px; }
.ra-bus .ra-inp { min-width:240px; }
.ra-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.ra-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .ra-result li:hover { background:#f0faf4; }
.ra-turnos { border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; display:flex; flex-direction:column; gap:10px; background:#f8fafc; }
.ra-turno-head { display:grid; grid-template-columns:90px 1fr 28px 1fr; gap:8px; font-size:11px; font-weight:700; color:#64748b; padding-left:2px; }
.ra-turno { display:grid; grid-template-columns:90px 1fr 28px 1fr; gap:8px; align-items:center; }
.ra-tlbl { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#1e293b; }
.ra-chkmini { display:flex; justify-content:center; }
.ra-hm { display:flex; align-items:center; gap:4px; font-size:13px; color:#475569; }
.ra-sm { width:52px; border:1px solid #d1d5db; border-radius:6px; padding:6px; font-size:13px; text-align:center; } .ra-sm:disabled { background:#f1f5f9; color:#cbd5e1; }
.ra-pie { display:flex; gap:10px; flex-wrap:wrap; margin-top:6px; }
.ra-btn-ok { background:#16a34a; color:#fff; border:none; padding:10px 28px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:800; } .ra-btn-ok:disabled { background:#cbd5e1; }
.ra-btn-del { background:#ef4444; color:#fff; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; } .ra-btn-del:disabled { background:#fca5a5; }
.ra-btn-sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 14px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; }
.ra-panel { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.ra-panel-tit { padding:7px 12px; font-weight:800; font-size:13px; color:#fff; } .ra-panel-tit.azul { background:#1d4ed8; } .ra-panel-tit.verde { background:#16a34a; }
.ra-panel-bo { padding:10px 12px; font-size:13px; color:#1e293b; }
.ra-aj div { padding:2px 0; }
.ra-tabla { width:100%; border-collapse:collapse; font-size:13px; }
.ra-tabla th { background:#f1f5f9; color:#475569; padding:6px 10px; text-align:left; }
.ra-tabla td { padding:5px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .ra-tabla td.c { text-align:center; } .ra-tabla td.reg { font-weight:600; color:#1d4ed8; }
.ra-tabla tbody tr.ign td { background:#fee2e2; text-decoration:line-through; color:#b91c1c; }
.ra-vacio { color:#94a3b8; padding:10px; text-align:center; }
.ra-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:760px; } .ra-msg.ok { background:#dcfce7; color:#166534; } .ra-msg.err { background:#fee2e2; color:#b91c1c; }
.ra-lista { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; margin-top:6px; }
.ra-lista-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:13px; color:#1e293b; }
.ra-rango { font-size:12px; color:#475569; display:flex; align-items:center; gap:5px; } .ra-rango input { border:1px solid #c8d8ea; border-radius:6px; padding:5px 7px; font-size:13px; }
.ra-lbus { border:1px solid #c8d8ea; border-radius:6px; padding:6px 10px; font-size:13px; min-width:160px; }
.ra-count { font-size:12px; color:#64748b; font-weight:600; }
.ra-lista-wrap { overflow-x:auto; }
.ra-tabla2 { width:100%; border-collapse:collapse; font-size:13px; }
.ra-tabla2 th { position:sticky; top:0; background:#1e293b; color:#fff; padding:8px 10px; text-align:left; font-size:12px; } .ra-tabla2 th.c, .ra-tabla2 td.c { text-align:center; }
.ra-tabla2 td { padding:6px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; } .ra-tabla2 tr.sel td { background:#fef9c3; }
.ra-mini { background:#eef2f7; border:none; border-radius:6px; padding:4px 8px; cursor:pointer; font-size:13px; }
</style>
