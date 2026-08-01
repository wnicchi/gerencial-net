<!-- RelojPeriodosView.vue — Reloj / Ajuste Horarios por Períodos (reloj_capturas_edicion). -->
<template>
  <div class="rp-view">
    <div class="rp-cab">
      <div class="rp-cab-ico">🛠️</div>
      <div class="rp-cab-tx"><h1>Reloj de Personal — Ajuste de Capturas</h1><p>Editar las fichadas día por día de un empleado en un período</p></div>
      <button class="rp-btn-ia" title="Asistente IA" @click="modalIA = true">🤖 IA</button>
      <button class="rp-btn-ayuda" title="Ayuda" @click="modalAyuda = true">❓ Ayuda</button>
    </div>

    <RelojPeriodosAyuda v-if="modalAyuda" @close="modalAyuda = false" />
    <ChatIA v-if="modalIA" endpoint="/ia/reloj-periodos" titulo="Asistente IA — Ajuste por Períodos"
            subtitulo="Preguntá sobre la edición de fichadas" :sugerencias="['¿Cómo edito una hora?','¿Qué significa 00:00?','¿Qué pasa al confirmar?']"
            @close="modalIA = false" />

    <div class="rp-card">
      <div class="rp-top">
        <div class="rp-f bus">
          <span class="rp-lbl">Personal</span>
          <input v-model="busqueda" type="text" class="rp-inp" placeholder="Código o nombre…" @input="buscar" @focus="buscar" />
          <ul v-if="resultados.length" class="rp-result"><li v-for="r in resultados" :key="r.PER_COD" @click="seleccionar(r)">{{ r.PER_COD }} — {{ (r.PER_NOM || '').trim() }}</li></ul>
        </div>
        <div class="rp-f"><span class="rp-lbl">Nombre</span><input :value="emp.nombre" class="rp-inp ro ancho" readonly /></div>
        <div class="rp-f"><span class="rp-lbl">Fecha desde</span><input v-model="fecha1" type="date" class="rp-inp" /></div>
        <div class="rp-f"><span class="rp-lbl">Hasta</span><input v-model="fecha2" type="date" class="rp-inp" /></div>
        <button class="rp-btn ok" :disabled="!emp.cod || cargando" @click="recuperar">{{ cargando ? '⟳…' : 'Recuperar Datos' }}</button>
      </div>

      <div v-if="filas.length" class="rp-grid-wrap">
        <table class="rp-tabla">
          <thead><tr><th>Marcar</th><th>Fecha</th><th>1ª Entrada</th><th>1ª Salida</th><th>2ª Entrada</th><th>2ª Salida</th><th>Tiempo Trabajado</th></tr></thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="i" :class="{ mk: f.marcar }">
              <td class="c"><input type="checkbox" v-model="f.marcar" /></td>
              <td>{{ fmt(f.fecha) }}</td>
              <td :class="cellClass(f, 'e1', f.antEnt1)"><input class="rp-h" v-model="f.e1" @change="recalc" /></td>
              <td :class="cellClass(f, 's1', f.antSal1)"><input class="rp-h" v-model="f.s1" @change="recalc" /></td>
              <td :class="cellClass(f, 'e2', f.antEnt2)"><input class="rp-h" v-model="f.e2" @change="recalc" /></td>
              <td :class="cellClass(f, 's2', f.antSal2)"><input class="rp-h" v-model="f.s2" @change="recalc" /></td>
              <td class="c b">{{ hm(f.trabajado) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filas.length" class="rp-pie">
        <button class="rp-btn-sec" @click="limpiarMarcados">Limpiar Marcados</button>
        <span class="rp-aviso">⚠ La hora 00:00 se toma como sin marcación</span>
        <span class="rp-total">Total de horas trabajadas: <b>{{ hm(totalHoras) }}</b></span>
        <button class="rp-btn ok" :disabled="procesando" @click="confirmar">{{ procesando ? '⟳…' : 'Aceptar' }}</button>
      </div>
      <p v-if="msg" :class="['rp-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import api from '@/services/auth'
import RelojPeriodosAyuda from '@/components/RelojPeriodosAyuda.vue'
import ChatIA from '@/components/ChatIA.vue'

const modalAyuda = ref(false); const modalIA = ref(false)
const _iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoy = new Date(); const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
const fecha1 = ref(_iso(primero)); const fecha2 = ref(_iso(hoy))
const emp = reactive({ cod: 0, nombre: '' })
const filas = ref<any[]>([])
const cargando = ref(false); const procesando = ref(false); const msg = ref(''); const msgError = ref(false)
const flash = (t: string, e = false) => { msg.value = t; msgError.value = e; if (t && !e) setTimeout(() => msg.value = '', 6000) }
const fmt = (iso: string) => { const [a, m, d] = iso.split('-'); return `${d}/${m}/${a}` }
const hm = (v: number) => `${String(Math.floor(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}`
const toHm = (v: number) => `${String(Math.floor(v / 100)).padStart(2, '0')}:${String(v % 100).padStart(2, '0')}`
const parse = (s: string): number => { const m = (s || '').match(/(\d{1,2}):?(\d{2})?/); if (!m) return 0; const h = Math.min(23, parseInt(m[1] || '0', 10)); const mi = Math.min(59, parseInt(m[2] || '0', 10)); return h * 100 + mi }

const busqueda = ref(''); const resultados = ref<any[]>([]); let tB: any = null
const buscar = () => {
  clearTimeout(tB); const q = busqueda.value.trim()
  if (q.length < 2) { resultados.value = []; return }
  tB = setTimeout(async () => { try { resultados.value = (await api.get('/empleados', { params: { buscar: q, por_pagina: 8 } })).data.data ?? [] } catch { resultados.value = [] } }, 250)
}
const seleccionar = (r: any) => { busqueda.value = `${r.PER_COD} — ${(r.PER_NOM || '').trim()}`; resultados.value = []; emp.cod = r.PER_COD; emp.nombre = (r.PER_NOM || '').trim() }

const recuperar = async () => {
  if (!emp.cod) return
  cargando.value = true; msg.value = ''; filas.value = []
  try {
    const { data } = await api.get('/reloj/periodos/recuperar', { params: { cod: emp.cod, fecha1: fecha1.value, fecha2: fecha2.value } })
    emp.nombre = data.nombre
    filas.value = (data.filas ?? []).map((f: any) => ({ ...f, e1: toHm(f.entraHor1), s1: toHm(f.saleHor1), e2: toHm(f.entraHor2), s2: toHm(f.saleHor2) }))
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudo recuperar.', true) }
  finally { cargando.value = false }
}

// Recalcula el tiempo trabajado de cada fila a partir de los campos editados.
const recalc = () => {
  for (const f of filas.value) {
    const e1 = parse(f.e1), s1 = parse(f.s1), e2 = parse(f.e2), s2 = parse(f.s2)
    f.entraHor1 = e1; f.saleHor1 = s1; f.entraHor2 = e2; f.saleHor2 = s2
    const par = (en: number, sa: number) => { if (!en && !sa) return 0; const em = Math.floor(en / 100) * 60 + en % 100, sm = Math.floor(sa / 100) * 60 + sa % 100; if (sa > en) return sm - em; if (sa < en) return 24 * 60 + sm - em; return 0 }
    let min = 0
    if (s1) min += par(e1, s1)
    if (s2) min += par(e2, s2)
    f.trabajado = Math.floor(min / 60) * 100 + min % 60
  }
}
const totalHoras = computed(() => { let min = 0; for (const f of filas.value) min += Math.floor(f.trabajado / 100) * 60 + f.trabajado % 100; return Math.floor(min / 60) * 100 + min % 60 })
const cellClass = (f: any, key: string, ant: number) => parse(f[key]) !== ant ? 'chg' : ''

const limpiarMarcados = () => { for (const f of filas.value) if (f.marcar) { f.e1 = '00:00'; f.s1 = '00:00'; f.e2 = '00:00'; f.s2 = '00:00' } recalc() }

const confirmar = async () => {
  recalc()
  if (!confirm('¿Confirma los ajustes?')) return
  procesando.value = true
  try {
    const payload = filas.value.map(f => ({
      fecha: f.fecha,
      entraHor1: parse(f.e1), saleHor1: parse(f.s1), entraHor2: parse(f.e2), saleHor2: parse(f.s2),
      antEnt1: f.antEnt1, antSal1: f.antSal1, antEnt2: f.antEnt2, antSal2: f.antSal2,
      frealEh1: f.frealEh1, frealSh1: f.frealSh1, frealEh2: f.frealEh2, frealSh2: f.frealSh2,
    }))
    const { data } = await api.post('/reloj/periodos/confirmar', { cod: emp.cod, nombre: emp.nombre, filas: payload })
    flash(data.message)
    await recuperar()
  } catch (e: any) { flash(e?.response?.data?.message ?? 'No se pudieron confirmar los ajustes.', true) }
  finally { procesando.value = false }
}
</script>

<style scoped>
.rp-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.rp-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.rp-cab-ico { font-size:28px; } .rp-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .rp-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.rp-btn-ia { margin-left:auto; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; border:none; padding:9px 14px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rp-btn-ayuda { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:9px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.rp-card { margin:16px 18px; display:flex; flex-direction:column; gap:12px; }
.rp-top { display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap; }
.rp-f { display:flex; flex-direction:column; gap:4px; position:relative; } .rp-lbl { font-size:12px; font-weight:700; color:#1b4332; }
.rp-inp { border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:13px; color:#1e293b; } .rp-inp.ro { background:#f1f5f9; } .rp-inp.ancho { min-width:280px; }
.rp-bus .rp-inp { min-width:220px; }
.rp-result { position:absolute; top:100%; left:0; right:0; z-index:30; margin:2px 0 0; padding:0; list-style:none; background:#fff; border:1px solid #cbd5e1; border-radius:6px; max-height:220px; overflow:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); }
.rp-result li { padding:7px 10px; font-size:13px; cursor:pointer; color:#1e293b; border-bottom:1px solid #f1f5f9; } .rp-result li:hover { background:#f0faf4; }
.rp-btn { border:none; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#fff; background:#16a34a; } .rp-btn:disabled { background:#cbd5e1; cursor:default; }
.rp-grid-wrap { border:1px solid #e2e8f0; border-radius:8px; overflow:auto; max-height:54vh; }
.rp-tabla { width:100%; border-collapse:collapse; font-size:12.5px; }
.rp-tabla thead th { position:sticky; top:0; background:#1b4332; color:#fff; padding:6px 8px; text-align:left; white-space:nowrap; }
.rp-tabla td { padding:3px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap; color:#1e293b; } .rp-tabla td.c { text-align:center; } .rp-tabla td.b { font-weight:700; }
.rp-tabla td.chg { background:#bbf7d0; }
.rp-tabla tbody tr.mk td { background:#fff7cc; } .rp-tabla tbody tr.mk td.chg { background:#bbf7d0; }
.rp-h { width:64px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:12.5px; text-align:center; background:transparent; }
.rp-pie { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.rp-btn-sec { background:#fff; color:#1b4332; border:1px solid #c3e6cb; padding:8px 14px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; }
.rp-aviso { font-size:12px; color:#b45309; }
.rp-total { margin-left:auto; font-size:14px; color:#475569; } .rp-total b { color:#14532d; font-size:16px; }
.rp-msg { padding:9px 14px; font-size:13px; border-radius:6px; max-width:760px; } .rp-msg.ok { background:#dcfce7; color:#166534; } .rp-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
